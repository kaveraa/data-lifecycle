<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Unit;

use Kaveraa\DataLifecycle\Duration;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Exception\InvalidPolicy;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Tests\Support\Fixture\Employee;
use Kaveraa\DataLifecycle\Tests\Support\Fixture\Person;
use PHPUnit\Framework\TestCase;

final class PolicyRegistryTest extends TestCase
{
    public function test_it_keeps_one_policy_per_class(): void
    {
        $registry = new PolicyRegistry([$this->policy('App\Entity\User'), $this->policy('App\Entity\Invitation')]);

        self::assertCount(2, $registry->all());
        self::assertTrue($registry->has('App\Entity\User'));
        self::assertFalse($registry->has('App\Entity\Order'));
    }

    public function test_a_later_policy_replaces_the_first_one(): void
    {
        $registry = new PolicyRegistry([$this->policy('App\Entity\User', '1 year')]);
        $registry->add($this->policy('App\Entity\User', '3 years'));

        self::assertCount(1, $registry->all());
        self::assertSame('3 years', (string) $registry->get('App\Entity\User')->keepFor);
    }

    public function test_it_finds_the_policy_of_a_parent_class(): void
    {
        $registry = new PolicyRegistry([$this->policy(Person::class)]);

        self::assertNull($registry->for(new \stdClass()));
        self::assertSame(Person::class, $registry->for(new Employee())?->subject);
    }

    public function test_an_unknown_class_says_so_clearly(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessage('No lifecycle policy is registered for "App\Entity\Order"');

        (new PolicyRegistry())->get('App\Entity\Order');
    }

    private function policy(string $subject, string $keepFor = '1 year'): Policy
    {
        return new Policy($subject, Duration::parse($keepFor), Ending::Delete);
    }
}
