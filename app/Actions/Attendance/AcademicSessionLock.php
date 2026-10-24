<?php

namespace App\Actions\Attendance;

use App\Actions\Academic\AcademicRegistry;
use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use Illuminate\Validation\ValidationException;

class AcademicSessionLock
{
    public function __construct(private AcademicRegistry $registry) {}

    /** Call inside a transaction. All academic writers lock cycle → offering → period → session. */
    public function get(ClassSession $session, bool $allowInactive = false): ClassSession
    {
        $offering = $this->registry->lockOffering($session->offering, $allowInactive);
        $period = AssessmentPeriod::lockForUpdate()->findOrFail($session->period_id);
        $record = ClassSession::lockForUpdate()->findOrFail($session->id);
        if ($record->offering_id !== $offering->id || $record->period_id !== $period->id || $period->offering_id !== $offering->id) {
            throw ValidationException::withMessages(['session' => 'La sesión cambió. Actualiza la página.']);
        }
        if ($period->state !== 'open') {
            throw ValidationException::withMessages(['session' => 'El cómputo está cerrado o en cierre.']);
        }

        return $record->setRelation('offering', $offering)->setRelation('period', $period);
    }
}
