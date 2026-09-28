<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Ni rappel ni désactivation : sa table n'a que la colonne last_active_at.
 */
class Ping extends Model
{
    protected $table = 'pings';

    protected $guarded = [];

    public $timestamps = false;
}
