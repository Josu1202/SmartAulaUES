<?php

namespace App\Console\Commands;

use App\Support\LocalDatabase;
use Illuminate\Console\Command;

final class RebuildLocalDatabase extends Command
{
    protected $signature = 'local:rebuild {--confirm-database= : Explicitly name smartaulaues_local}';

    protected $description = 'Reconstruye únicamente smartaulaues_local después de verificar ambas conexiones.';

    public function handle(): int
    {
        $target = LocalDatabase::verify();
        $this->line(json_encode($target, JSON_UNESCAPED_SLASHES));
        if ($this->option('confirm-database') !== 'smartaulaues_local') {
            $this->error('No se borró nada. Detén el scheduler y confirma explícitamente --confirm-database=smartaulaues_local.');

            return self::FAILURE;
        }

        return $this->call('migrate:fresh', ['--force' => true]);
    }
}
