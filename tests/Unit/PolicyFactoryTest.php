<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Unit;

use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Exception\InvalidPolicy;
use Kaveraa\DataLifecycle\Fields;
use Kaveraa\DataLifecycle\PolicyFactory;
use Kaveraa\DataLifecycle\Strategy;
use Kaveraa\DataLifecycle\Tests\Support\Fixture\Invitation;
use Kaveraa\DataLifecycle\Tests\Support\Fixture\Member;
use Kaveraa\DataLifecycle\Tests\Support\Fixture\Order;
use PHPUnit\Framework\TestCase;

final class PolicyFactoryTest extends TestCase
{
    public function test_it_reads_a_full_policy_from_the_attributes(): void
    {
        $policy = (new PolicyFactory())->fromAttributes(Member::class);

        self::assertNotNull($policy);
        self::assertSame(Member::class, $policy->subject);
        self::assertSame('3 years', (string) $policy->keepFor);
        self::assertSame(['30 days', '7 days'], array_map('strval', $policy->warnBefore));
        self::assertSame('30 days', (string) $policy->grace);
        self::assertSame(Ending::Anonymise, $policy->ending);
        self::assertSame(
            ['email' => Strategy::Auto, 'name' => Strategy::Auto, 'birth_date' => Strategy::YearOnly],
            $policy->anonymise,
        );
        self::assertSame('last_active_at', $policy->fields->since);
    }

    public function test_an_attribute_can_change_the_date_column(): void
    {
        $policy = (new PolicyFactory())->fromAttributes(Invitation::class);

        self::assertNotNull($policy);
        self::assertSame('sent_at', $policy->fields->since);
        self::assertSame(Ending::Delete, $policy->ending);
        self::assertFalse($policy->hasDisableStep());
        self::assertSame([], $policy->warnBefore);
    }

    public function test_it_keeps_the_column_names_given_by_the_application(): void
    {
        $factory = new PolicyFactory(new Fields(since: 'seen_at', disabledAt: 'blocked_at'));

        $policy = $factory->fromAttributes(Member::class);

        self::assertNotNull($policy);
        self::assertSame('seen_at', $policy->fields->since);
        self::assertSame('blocked_at', $policy->fields->disabledAt);
    }

    public function test_a_class_without_keep_for_has_no_policy(): void
    {
        self::assertNull((new PolicyFactory())->fromAttributes(self::class));
    }

    public function test_a_class_that_never_says_what_to_do_at_the_end_is_refused(): void
    {
        $this->expectException(InvalidPolicy::class);

        (new PolicyFactory())->fromAttributes(Order::class);
    }
}
