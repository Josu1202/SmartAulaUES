<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SchedulerStatus extends Command
{
    protected $signature = 'attendance:scheduler-status {--max-age=180}';

    protected $description = 'Verifica la última ejecución completa del procesador sin mostrar credenciales.';

    public function handle(): int
    {
        $run = DB::table('operation_runs')->where('name', 'academic-closures')->first();
        $healthy = $run && $run->finished_at && $run->errors === 0
            && CarbonImmutable::parse($run->finished_at)->diffInSeconds(now(), absolute: true) <= (int) $this->option('max-age');
        $this->line($run?->finished_at ? 'Última ejecución completa UTC: '.$run->finished_at.'; errores: '.$run->errors : 'Sin ejecución completa reciente.');

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
