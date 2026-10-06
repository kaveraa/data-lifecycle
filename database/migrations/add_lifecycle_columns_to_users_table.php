<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Example migration. Keep only the columns your policies need.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Required: the date of the last sign of life. Everything starts from it.
            $table->timestamp('last_active_at')->nullable();

            // Optional: number of reminders already sent.
            // Not needed if your policy has no reminder.
            $table->unsignedTinyInteger('lifecycle_warn_stage')->default(0);

            // Optional: date of the last reminder. For information only.
            $table->timestamp('lifecycle_warned_at')->nullable();

            // Optional: disable date.
            // Not needed if your policy has no grace period.
            $table->timestamp('disabled_at')->nullable();

            // Optional: anonymisation date.
            // Not needed if your policy ends with a deletion.
            $table->timestamp('anonymised_at')->nullable();

            // The two columns filtered on each run: we index them.
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
