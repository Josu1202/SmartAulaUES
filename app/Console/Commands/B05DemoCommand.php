<?php

namespace App\Console\Commands;

use App\Services\Demo\B05ScenarioBuilder;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class B05DemoCommand extends Command
{
    protected $signature = 'b05:demo {profile : small o full} {--reference= : Fecha YYYY-MM-DD de referencia} {--confirm-database= : Nombre exacto de la BD de prueba}';

    protected $description = 'Crea una sola vez un escenario B05 ficticio, aislado y versionado';

    public function handle(B05ScenarioBuilder $builder): int
    {
        try {
            $result = $builder->seed(
                (string) $this->argument('profile'),
                (string) ($this->option('reference') ?: now(config('smartaulaues.display_timezone'))->toDateString()),
                (string) $this->option('confirm-database')
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('No se pudo preparar el escenario. Revise el registro privado sin divulgar SQL ni datos sensibles.');

            return self::FAILURE;
        }
        $this->info(($result['created'] ? 'Creado' : 'Conservado sin cambios').': '.$result['code'].' · '.$result['database']);
        $this->line('Versión 1; referencia '.$result['reference'].'. No modifica datos ajenos ni claves existentes.');

        return self::SUCCESS;
    }
}
