<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Laravel\Console;

use Illuminate\Console\Command;

/**
 * php artisan lifecycle:install
 */
final class InstallCommand extends Command
{
    protected $signature = 'lifecycle:install';

    protected $description = 'Publie la configuration et la migration d\'exemple de data-lifecycle';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'data-lifecycle-config']);
        $this->call('vendor:publish', ['--tag' => 'data-lifecycle-migrations']);

        $this->newLine();
        $this->info('Étapes suivantes :');
        $this->line('  1. Ouvrez config/data-lifecycle.php et déclarez vos règles dans "subjects" ou "discover"');
        $this->line('  2. Gardez dans la migration publiée les seules colonnes dont vos règles ont besoin');
        $this->line('  3. Lancez : php artisan migrate');
        $this->line('  4. Ajoutez le middleware Kaveraa\DataLifecycle\Laravel\Middleware\TrackActivity au groupe "web"');
        $this->line('  5. Regardez sans rien écrire : php artisan lifecycle:report');
        $this->line('  6. Planifiez ensuite php artisan lifecycle:run une fois par jour');

        return self::SUCCESS;
    }
}
