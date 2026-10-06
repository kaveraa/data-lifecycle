<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenDelete;

/**
 * Policy read from the PHP attributes, ending with a permanent deletion.
 */
#[KeepFor('1 year')]
#[ThenDelete(force: true)]
class Ticket extends Model
{
    use SoftDeletes;

    protected $table = 'tickets';

    protected $guarded = [];
}
