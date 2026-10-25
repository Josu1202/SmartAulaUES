<?php

namespace App\Console\Commands;

use App\Rules\PasswordByteLimit;
use App\Support\LocalDatabase;
use Database\Seeders\ExplorationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class PrepareLocalScenario extends Command
{
    protected $signature = 'local:scenario {--password-stdin : Read the local password from standard input} {--generate-password : Generate a private local password}';

    protected $description = 'Prepara las cuentas y escenarios locales, sin duplicar ni sobrescribir datos.';

    public function handle(): int
    {
        $target = LocalDatabase::verify();
        $this->info('Conexión verificada: '.$target['host'].':'.$target['port'].'; lectura/escritura '.$target['write']);

        return DB::transaction(function (): int {
            DB::select("select pg_advisory_xact_lock(hashtextextended('smartaulaues:local-scenario', 0))");

            return $this->prepare();
        });
    }

    private function prepare(): int
    {
        if (DB::table('operation_runs')->where('name', ExplorationSeeder::MARKER)->exists()) {
            $this->info('El escenario ya existe. Se conservan cuentas, contraseñas, fechas y cambios posteriores.');

            return self::SUCCESS;
        }
        $password = $this->option('generate-password') ? Str::password(24) : ($this->option('password-stdin') ? rtrim(stream_get_contents(STDIN), "\r\n") : $this->secret('Elige la contraseña de las cuentas locales (mínimo 12 caracteres)'));
        Validator::make(['password' => $password], ['password' => ['required', 'string', 'min:12', new PasswordByteLimit]])->validate();
        config(['smartaulaues.exploration_password_hash' => Hash::make($password)]);
        if ($this->call('db:seed', ['--class' => ExplorationSeeder::class]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        Storage::disk('private')->put('exploration-access.enc', Crypt::encryptString($password));
        unset($password);
        $this->info('Escenario listo. Consulta docs/escenarios-y-cuentas-locales.md para los recorridos.');
        $this->info('Consulta la contraseña local con php artisan local:access. Se conserva cifrada y no se incluye en documentos ni registros.');

        return self::SUCCESS;
    }
}
