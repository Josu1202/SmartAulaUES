<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\ClosureWorkflow;
use App\Actions\Attendance\ReviewSession;
use App\Models\AbsenceRequest;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcademicClosureController extends Controller
{
    public function closePeriod(Request $request, AssessmentPeriod $period, ClosureWorkflow $workflow): RedirectResponse
    {
        $period = $workflow->closePeriod($request->user(), $period);

        return to_route('offerings.show', ['offering' => $period->offering_id, 'period_id' => $period->id])->with('status', $period->state === 'closed' ? 'Cómputo cerrado.' : 'Cierre pendiente: resuelve los impedimentos indicados.');
    }

    public function reopenPeriod(Request $request, AssessmentPeriod $period, ClosureWorkflow $workflow): RedirectResponse
    {
        $workflow->reopenPeriod($request->user(), $period, $this->reason($request));

        return to_route('offerings.show', ['offering' => $period->offering_id, 'period_id' => $period->id])->with('status', 'Cómputo en revisión. Requiere un nuevo cierre manual.');
    }

    public function closeCycle(Request $request, AcademicCycle $cycle, ClosureWorkflow $workflow): RedirectResponse
    {
        $cycle = $workflow->closeCycle($request->user(), $cycle);

        return to_route('admin.cycles.index')->with('status', $cycle->status === 'closed' ? 'Ciclo cerrado.' : 'Cierre pendiente: consulta los grupos y cómputos que lo impiden.');
    }

    public function reopenCycle(Request $request, AcademicCycle $cycle, ClosureWorkflow $workflow): RedirectResponse
    {
        $workflow->reopenCycle($request->user(), $cycle, $this->reason($request));

        return to_route('admin.cycles.index')->with('status', 'Ciclo en revisión. Sus cómputos conservan sus cierres.');
    }

    public function reviewSession(Request $request, ClassSession $session, string $operation, ReviewSession $review): RedirectResponse
    {
        $review->execute($request->user(), $session, $operation, $this->reason($request));

        return to_route('sessions.show', $session)->with('status', 'Revisión administrativa registrada.');
    }

    public function reopenDecision(Request $request, AbsenceRequest $absenceRequest, ReviewSession $review): RedirectResponse
    {
        $review->reopenDecision($request->user(), $absenceRequest, $this->reason($request));

        return to_route('requests.show', $absenceRequest)->with('status', 'Resolución en revisión; la decisión anterior se conserva en la bitácora.');
    }

    private function reason(Request $request): string
    {
        return $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']])['reason'];
    }
}
