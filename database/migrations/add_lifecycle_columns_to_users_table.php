<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration d'exemple. Gardez seulement les colonnes dont vos règles ont besoin.
 *
 * Example migration. Keep only the columns your policies need.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Obligatoire : la date du dernier signe de vie. Tout part de là.
            $table->timestamp('last_active_at')->nullable();

            // Facultative : nombre de rappels déjà envoyés.
            // Inutile si votre règle ne prévoit aucun rappel.
            $table->unsignedTinyInteger('lifecycle_warn_stage')->default(0);

            // Facultative : date du dernier rappel. Purement informative.
            $table->timestamp('lifecycle_warned_at')->nullable();

            // Facultative : date de désactivation.
            // Inutile si votre règle n'a pas de période de grâce.
            $table->timestamp('disabled_at')->nullable();

            // Facultative : date d'anonymisation.
            // Inutile si votre règle se termine par une suppression.
            $table->timestamp('anonymised_at')->nullable();

            // Les deux colonnes filtrées à chaque passage : on les indexe.
            $table->index('last_active_at');
            $table->index('disabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['last_active_at']);
            $table->dropIndex(['disabled_at']);

            $table->dropColumn([
                'last_active_at',
                'lifecycle_warn_stage',
                'lifecycle_warned_at',
                'disabled_at',
                'anonymised_at',
            ]);
        });
    }
};
