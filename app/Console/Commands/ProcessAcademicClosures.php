<?php

namespace App\Console\Commands;

use App\Actions\Attendance\ClosureWorkflow;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessAcademicClosures extends Command
{
    protected $signature = 'attendance:process-closures';

    protected $description = 'Finaliza sesiones al vencer ends_at y procesa cierres iniciales de cómputos y ciclos.';

    public function handle(SessionWorkflow $sessions, ClosureWorkflow $closures): int
    {
        $connection = DB::connection();
        if (! $connection->selectOne("SELECT pg_try_advisory_lock(hashtextextended('smartaulaues:closures', 0)) AS acquired")->acquired) {
            $this->info('Ya existe un procesador de cierres activo.');

            return self::SUCCESS;
        }
        try {
            $connection->table('operation_runs')->updateOrInsert(['name' => 'academic-closures'], ['started_at' => now(), 'finished_at' => null, 'errors' => 0]);

            return $this->process($sessions, $closures);
        } finally {
            $connection->select("SELECT pg_advisory_unlock(hashtextextended('smartaulaues:closures', 0))");
        }
    }

    private function process(SessionWorkflow $sessions, ClosureWorkflow $closures): int
    {
        $errors = 0;
        $process = function ($query, callable $action) use (&$errors): void {
            $query->orderBy('id')->chunkById(100, function ($records) use ($action, &$errors): void {
                foreach ($records as $record) {
                    try {
                        $action($record);
                    } catch (\Throwable $exception) {
                        $errors++;
                        report($exception);
                        $this->error(class_basename($record).' #'.$record->id.': error al procesar; revisa el registro privado de la aplicación.');
                    }
                }
            });
        };
        $today = now('America/El_Salvador')->toDateString();
        $process(ClassSession::where('state', 'in_progress')->whereNotNull('started_at')
            ->where('ends_at', '<=', now())
            ->whereHas('period', fn ($q) => $q->where('state', 'open'))->whereHas('offering.cycle', fn ($q) => $q->where('status', '!=', 'closed')),
            fn ($session) => $sessions->finishOverdue($session));
        $process(AssessmentPeriod::where('state', 'open')->whereNull('reopened_at')->where('ends_on', '<', $today)
            ->whereHas('offering.cycle', fn ($q) => $q->where('status', '!=', 'closed')),
            fn ($period) => $closures->autoClosePeriod($period));
        $process(AcademicCycle::where('status', '!=', 'closed')->whereNull('reopened_at')->where('ends_on', '<', $today),
            fn ($cycle) => $closures->autoCloseCycle($cycle));
        $this->info('Procesamiento completado. Errores operativos: '.$errors.'. Los impedimentos académicos permanecen visibles para revisión.');
        DB::table('operation_runs')->where('name', 'academic-closures')->update(['finished_at' => now(), 'errors' => $errors]);

        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
