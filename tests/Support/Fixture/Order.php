<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Support\Fixture;

use Kaveraa\DataLifecycle\Attribute\KeepFor;

#[KeepFor('10 years')]
final class Order
{
}
