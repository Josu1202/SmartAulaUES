<?php

namespace App\Console\Commands;

use App\Actions\Attendance\ClosureWorkflow;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use Illuminate\Console\Command;

class ProcessAcademicClosures extends Command
{
    protected $signature = 'attendance:process-closures';

    protected $description = 'Finaliza sesiones vencidas por día local y procesa cierres iniciales de cómputos y ciclos.';

    public function handle(SessionWorkflow $sessions, ClosureWorkflow $closures): int
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
            ->where('started_at', '<', now('America/El_Salvador')->startOfDay()->utc())
            ->whereHas('period', fn ($q) => $q->where('state', 'open'))->whereHas('offering.cycle', fn ($q) => $q->where('status', '!=', 'closed')),
            fn ($session) => $sessions->finishOverdue($session));
        $process(AssessmentPeriod::where('state', 'open')->whereNull('reopened_at')->where('ends_on', '<', $today)
            ->whereHas('offering.cycle', fn ($q) => $q->where('status', '!=', 'closed')),
            fn ($period) => $closures->autoClosePeriod($period));
        $process(AcademicCycle::where('status', '!=', 'closed')->whereNull('reopened_at')->where('ends_on', '<', $today),
            fn ($cycle) => $closures->autoCloseCycle($cycle));
        $this->info('Procesamiento completado. Errores operativos: '.$errors.'. Los impedimentos académicos permanecen visibles para revisión.');

        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
