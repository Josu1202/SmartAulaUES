<?php

namespace App\Console\Commands;

use App\Actions\Academic\AuditRecorder;
use App\Models\User;
use App\Rules\PasswordByteLimit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CreateCoordinator extends Command
{
    protected $signature = 'app:create-coordinator {--name=} {--email=} {--password-stdin}';

    protected $description = 'Crea la primera cuenta de Coordinación de una instalación vacía, sin contraseña predeterminada.';

    public function handle(AuditRecorder $audit): int
    {
        $database = DB::connection()->getPdo()->query('select current_database()')->fetchColumn();
        $this->line('Base efectiva: '.$database.'. No se sustituyen cuentas existentes.');
        if (User::where('role', 'coordinator')->exists()) {
            $this->error('Ya existe Coordinación. Usa la administración de cuentas de la aplicación.');

            return self::FAILURE;
        }
        $name = $this->option('name') ?: $this->ask('Nombre completo');
        $email = mb_strtolower(trim($this->option('email') ?: $this->ask('Correo')));
        $password = $this->option('password-stdin') ? rtrim(stream_get_contents(STDIN), "\r\n") : $this->secret('Contraseña (mínimo 12 caracteres)');
        if (! $this->option('password-stdin') && $password !== $this->secret('Confirma la contraseña')) {
            $this->error('Las contraseñas no coinciden. No se creó la cuenta.');

            return self::FAILURE;
        }
        Validator::make(compact('name', 'email', 'password'), ['name' => 'required|string|max:255', 'email' => 'required|email:rfc|max:255|unique:users',
            'password' => ['required', 'string', 'min:12', new PasswordByteLimit]])->validate();

        return DB::transaction(function () use ($name, $email, $password, $audit): int {
            DB::select("select pg_advisory_xact_lock(hashtextextended('smartaulaues:first-coordinator', 0))");
            if (User::where('role', 'coordinator')->exists()) {
                $this->error('Otra operación ya creó Coordinación. No se modificaron sus datos.');

                return self::FAILURE;
            }
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'coordinator', 'active' => true]);
            $audit->record($user, $user, 'user.created', reason: 'Alta inicial de Coordinación desde la administración del servidor.');
            $this->info('Cuenta de Coordinación creada. Inicia sesión con la contraseña elegida.');

            return self::SUCCESS;
        });
    }
}
