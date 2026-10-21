<?php

namespace App\Console\Commands;

use App\Actions\Academic\NormalizeDevelopmentAttendancePolicy as NormalizePolicy;
use Illuminate\Console\Command;
use Throwable;

class NormalizeDevelopmentAttendancePolicy extends Command
{
    protected $signature = 'attendance:normalize-development-policy {--database= : Base efectiva esperada: siaa o siaa_test} {--apply : Aplicar la actualización explícita}';

    protected $description = 'Revisa o unifica el reglamento de cómputos de desarrollo abiertos, sin tocar reportes ni cierres.';

    public function handle(NormalizePolicy $normalizer): int
    {
        try {
            $plan = $normalizer->run((string) $this->option('database'));
            $this->line('Base efectiva: '.$plan['database']);
            $this->line('Cómputos por actualizar: '.$plan['candidates']);
            $this->line('Cómputos protegidos por cierre/reportes: '.$plan['protected']);
            if ($this->option('apply')) {
                $result = $normalizer->run((string) $this->option('database'), true);
                $this->info('Cómputos actualizados: '.$result['updated']);
            } else {
                $this->comment('Solo revisión. Usa --apply para ejecutar; se volverán a comprobar los bloqueos y reportes.');
            }

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Operación detenida: verifica entorno local/testing, base efectiva explícita y conexión. No se aplicó una actualización parcial.');

            return self::FAILURE;
        }
    }
}
