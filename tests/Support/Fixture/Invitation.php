<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Support\Fixture;

use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenDelete;

#[KeepFor('90 days', since: 'sent_at')]
#[ThenDelete]
final class Invitation
{
}
