<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class B07OperationsStatus extends Command
{
    protected $signature = 'b07:status
        {--max-pending=250 : Limite de trabajos en la cola}
        {--max-oldest-seconds=900 : Antiguedad permitida del trabajo mas viejo}
        {--max-failed=0 : Limite de trabajos fallidos}
        {--require-scheduler : Fallar si el scheduler no termino recientemente}
        {--scheduler-max-age=180 : Antiguedad maxima del scheduler, en segundos}';

    protected $description = 'Estado operativo de la cola y scheduler, sin leer cargas ni revelar datos privados.';

    public function handle(): int
    {
        foreach (['max-pending', 'max-oldest-seconds', 'max-failed', 'scheduler-max-age'] as $option) {
            if (! ctype_digit((string) $this->option($option))) {
                $this->error('B07: los umbrales deben ser enteros no negativos.');

                return self::FAILURE;
            }
        }

        if (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs') || ! Schema::hasTable('operation_runs')) {
            $this->error('B07: esquema operativo incompleto; aplique migraciones incrementales.');

            return self::FAILURE;
        }

        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $oldest = DB::table('jobs')->min('created_at');
        $age = $oldest === null ? 0 : max(0, now()->timestamp - (int) $oldest);
        $run = DB::table('operation_runs')->where('name', 'academic-closures')->first();
        $recent = $run !== null && $run->finished_at !== null && (int) $run->errors === 0
            && CarbonImmutable::parse($run->finished_at)->diffInSeconds(now(), absolute: true)
                <= (int) $this->option('scheduler-max-age');

        $this->line('B07: trabajos pendientes='.$pending.'; edad mayor='.$age.'s; fallidos='.$failed.'.');
        $this->line('B07: scheduler='.($recent ? 'reciente y sin errores' : 'sin ejecucion reciente correcta').'.');

        if (app()->environment('production') && config('queue.default') !== 'database') {
            $this->error('B07: production requiere cola database y worker persistente.');

            return self::FAILURE;
        }

        return $pending <= (int) $this->option('max-pending')
            && $age <= (int) $this->option('max-oldest-seconds')
            && $failed <= (int) $this->option('max-failed')
            && (! $this->option('require-scheduler') || $recent)
                ? self::SUCCESS : self::FAILURE;
    }
}
