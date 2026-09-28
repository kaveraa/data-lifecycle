<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenDelete;

/**
 * Règle lue sur les attributs PHP, fin par suppression définitive.
 */
#[KeepFor('1 year')]
#[ThenDelete(force: true)]
class Ticket extends Model
{
    use SoftDeletes;

    protected $table = 'tickets';

    protected $guarded = [];
}
