<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kaveraa\DataLifecycle\Laravel\Concerns\HasLifecycle;

/**
 * The full journey: reminders, disable, anonymisation.
 */
class User extends Model
{
    use HasLifecycle;

    protected $table = 'users';

    protected $guarded = [];
}
