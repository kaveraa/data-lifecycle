<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Support\Fixture;

use Kaveraa\DataLifecycle\Attribute\DisableFirst;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenAnonymise;
use Kaveraa\DataLifecycle\Attribute\WarnBefore;
use Kaveraa\DataLifecycle\Strategy;

#[KeepFor('3 years')]
#[WarnBefore('30 days')]
#[WarnBefore('7 days')]
#[DisableFirst('30 days')]
#[ThenAnonymise('email', 'name', ['birth_date' => Strategy::YearOnly])]
final class Member
{
}
