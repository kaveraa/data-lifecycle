<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kaveraa\DataLifecycle\Laravel\Concerns\HasLifecycle;

/**
 * Le parcours complet : rappels, désactivation, anonymisation.
 */
class User extends Model
{
    use HasLifecycle;

    protected $table = 'users';

    protected $guarded = [];
}
