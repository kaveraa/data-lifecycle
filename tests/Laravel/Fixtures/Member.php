<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Without a disable step: direct anonymisation.
 * No PHP attribute: also used to check that "discover" skips it silently.
 */
class Member extends Model
{
    protected $table = 'members';

    protected $guarded = [];
}
