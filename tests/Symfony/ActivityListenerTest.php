<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Symfony;

use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Symfony\ActivityListener;
use Kaveraa\DataLifecycle\Symfony\CurrentUser;
use Kaveraa\DataLifecycle\Tests\Doctrine\DoctrineTestCase;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Member;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The activity signal: a write from time to time, never on every request.
 */
final class ActivityListenerTest extends DoctrineTestCase
{
    public function test_the_last_sign_of_life_is_written(): void
    {
        $member = $this->member('2022-01-01 09:00:00');

        $this->listen($member)->onRequest($this->request());

        self::assertSame('2023-01-01 12:00', $this->reload(Member::class, (int) $member->id)->lastActiveAt?->format('Y-m-d H:i'));
    }

    public function test_a_first_visit_without_a_date_is_written(): void
    {
        $member = $this->member(null);

        $this->listen($member)->onRequest($this->request());

        self::assertSame('2023-01-01 12:00', $this->reload(Member::class, (int) $member->id)->lastActiveAt?->format('Y-m-d H:i'));
    }

    public function test_nothing_is_written_again_before_the_window_is_over(): void
    {
        $member = $this->member('2023-01-01 11:50:00');

        $this->listen($member)->onRequest($this->request());

        self::assertSame('2023-01-01 11:50', $this->reload(Member::class, (int) $member->id)->lastActiveAt?->format('Y-m-d H:i'));
    }

    public function test_the_date_is_written_again_once_the_window_is_over(): void
    {
        $member = $this->member('2023-01-01 11:40:00');

        $this->listen($member)->onRequest($this->request());

        self::assertSame('2023-01-01 12:00', $this->reload(Member::class, (int) $member->id)->lastActiveAt?->format('Y-m-d H:i'));
    }

    public function test_nothing_happens_when_nobody_is_signed_in(): void
    {
        $member = $this->member('2022-01-01 09:00:00');

        $this->listen(null)->onRequest($this->request());

        self::assertSame('2022-01-01 09:00', $this->reload(Member::class, (int) $member->id)->lastActiveAt?->format('Y-m-d H:i'));
    }

    public function test_sub_requests_are_ignored(): void
    {
        $member = $this->member('2022-01-01 09:00:00');

        $this->listen($member)->onRequest($this->request(HttpKernelInterface::SUB_REQUEST));

        self::assertSame('2022-01-01 09:00', $this->reload(Member::class, (int) $member->id)->lastActiveAt?->format('Y-m-d H:i'));
    }

    public function test_a_window_of_zero_turns_the_listener_off(): void
    {
        $member = $this->member('2022-01-01 09:00:00');

        $this->listen($member, throttle: 0)->onRequest($this->request());

        self::assertSame('2022-01-01 09:00', $this->reload(Member::class, (int) $member->id)->lastActiveAt?->format('Y-m-d H:i'));
    }

    public function test_a_class_without_a_policy_is_ignored(): void
    {
        $member = $this->member('2022-01-01 09:00:00');

        $listener = new ActivityListener(
            $this->em,
            new PolicyRegistry([$this->policyOf(Session::class)]),
            $this->clock,
            new CurrentUser(new FakeTokenStorage($member)),
            15,
        );

        $listener->onRequest($this->request());

        self::assertSame('2022-01-01 09:00', $this->reload(Member::class, (int) $member->id)->lastActiveAt?->format('Y-m-d H:i'));
    }

    private function member(?string $lastActiveAt): Member
    {
        $member = new Member('nina@example.test', 'Nina', $lastActiveAt === null ? null : self::at($lastActiveAt));

        $this->save($member);

        return $member;
    }

    private function listen(?Member $user, int $throttle = 15): ActivityListener
    {
        return new ActivityListener(
            $this->em,
            new PolicyRegistry([$this->policyOf(Member::class)]),
            $this->clock,
            new CurrentUser(new FakeTokenStorage($user)),
            $throttle,
        );
    }

    private function request(int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };

        return new RequestEvent($kernel, new Request(), $type);
    }
}
