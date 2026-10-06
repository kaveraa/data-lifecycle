<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * No reminder, no disable step: its table only has the last_active_at column.
 */
class Ping extends Model
{
    protected $table = 'pings';

    protected $guarded = [];

    public $timestamps = false;
}
