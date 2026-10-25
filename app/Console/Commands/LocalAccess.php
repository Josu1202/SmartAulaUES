<?php

namespace App\Console\Commands;

use App\Support\LocalDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

final class LocalAccess extends Command
{
    protected $signature = 'local:access';

    protected $description = 'Muestra la contraseña de las cuentas ficticias exclusivamente en la terminal local.';

    public function handle(): int
    {
        LocalDatabase::verify();
        if (! Storage::disk('private')->exists('exploration-access.enc')) {
            $this->error('No hay una contraseña local guardada. Configúrala al preparar una base local vacía.');

            return self::FAILURE;
        }
        $this->line(Crypt::decryptString(Storage::disk('private')->get('exploration-access.enc')));

        return self::SUCCESS;
    }
}
