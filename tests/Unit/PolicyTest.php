<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Unit;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Duration;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Exception\InvalidPolicy;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Strategy;
use PHPUnit\Framework\TestCase;

final class PolicyTest extends TestCase
{
    public function test_it_says_when_a_row_is_due(): void
    {
        $policy = $this->policy();

        self::assertSame('2026-01-01', $policy->dueAt(new DateTimeImmutable('2023-01-01'))->format('Y-m-d'));
    }

    public function test_the_first_reminder_is_the_one_furthest_from_the_deadline(): void
    {
        $policy = $this->policy(warnBefore: ['7 days', '30 days']);

        self::assertSame('30 days', (string) $policy->warnBefore[0]);
        self::assertSame('7 days', (string) $policy->warnBefore[1]);
    }

    public function test_a_reminder_cutoff_is_the_deadline_minus_the_warning(): void
    {
        $policy = $this->policy(warnBefore: ['30 days']);

        // On 5 December 2025, we warn the rows that reach their deadline in 30 days or less.
        self::assertSame('2023-01-04', $policy->warnCutoff(0, new DateTimeImmutable('2025-12-05'))->format('Y-m-d'));
        self::assertNull($policy->warnCutoff(1, new DateTimeImmutable('2025-12-05')));
    }

    public function test_the_disable_cutoff_is_the_retention_window(): void
    {
        self::assertSame('2023-01-02', $this->policy()->disableCutoff(new DateTimeImmutable('2026-01-02'))->format('Y-m-d'));
    }

    public function test_the_erase_cutoff_follows_the_grace_period(): void
    {
        $withGrace = $this->policy(grace: '30 days');
        $withoutGrace = $this->policy(grace: null);

        self::assertTrue($withGrace->hasDisableStep());
        self::assertSame('2026-01-06', $withGrace->eraseCutoff(new DateTimeImmutable('2026-02-05'))->format('Y-m-d'));

        self::assertFalse($withoutGrace->hasDisableStep());
        self::assertSame('2023-02-05', $withoutGrace->eraseCutoff(new DateTimeImmutable('2026-02-05'))->format('Y-m-d'));
    }

    public function test_it_reads_a_policy_from_configuration(): void
    {
        $policy = Policy::fromArray('App\Entity\User', [
            'keep_for' => '3 years',
            'warn_before' => ['30 days', '7 days'],
            'grace' => '30 days',
            'anonymise' => ['email' => 'email', 'name' => Strategy::Text, 'note'],
            'fields' => ['since' => 'seen_at', 'disabled_at' => 'blocked_at'],
        ]);

        self::assertSame(Ending::Anonymise, $policy->ending);
        self::assertSame('seen_at', $policy->fields->since);
        self::assertSame('blocked_at', $policy->fields->disabledAt);
        self::assertSame('lifecycle_warned_at', $policy->fields->warnedAt);
        self::assertSame(Strategy::Email, $policy->anonymise['email']);
        self::assertSame(Strategy::Text, $policy->anonymise['name']);
        self::assertSame(Strategy::Auto, $policy->anonymise['note']);
        self::assertCount(2, $policy->warnBefore);
    }

    public function test_a_configuration_can_ask_for_a_deletion(): void
    {
        $policy = Policy::fromArray('App\Entity\Invitation', ['keep_for' => '90 days', 'delete' => true]);

        self::assertSame(Ending::Delete, $policy->ending);
        self::assertSame([], $policy->warnBefore);
    }

    public function test_a_policy_needs_an_ending(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessage('#[ThenAnonymise(...)] or #[ThenDelete]');

        Policy::fromArray('App\Entity\User', ['keep_for' => '3 years']);
    }

    public function test_a_policy_cannot_both_anonymise_and_delete(): void
    {
        $this->expectException(InvalidPolicy::class);

        Policy::fromArray('App\Entity\User', ['keep_for' => '3 years', 'delete' => true, 'anonymise' => ['email']]);
    }

    public function test_a_policy_needs_a_retention_window(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessage('#[KeepFor(...)]');

        Policy::fromArray('App\Entity\User', ['delete' => true]);
    }

    public function test_it_shortens_the_class_name_for_reports(): void
    {
        self::assertSame('User', Policy::fromArray('App\Entity\User', ['keep_for' => '1 year', 'delete' => true])->shortName());
    }

    /**
     * @param list<string> $warnBefore
     */
    private function policy(array $warnBefore = [], ?string $grace = '30 days'): Policy
    {
        return new Policy(
            subject: 'App\Entity\User',
            keepFor: Duration::parse('3 years'),
            ending: Ending::Anonymise,
            warnBefore: $warnBefore,
            grace: $grace === null ? null : Duration::parse($grace),
            anonymise: ['email' => Strategy::Email],
        );
    }
}
