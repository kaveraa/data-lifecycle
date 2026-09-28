<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Sans étape de désactivation : anonymisation directe.
 * Aucun attribut PHP : sert aussi à vérifier que "discover" l'ignore sans bruit.
 */
class Member extends Model
{
    protected $table = 'members';

    protected $guarded = [];
}
