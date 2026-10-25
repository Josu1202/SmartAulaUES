<?php

namespace App\Queries;

use App\Enums\Role;
use App\Models\AbsenceRequest;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\ReportVersion;
use App\Models\User;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\ClosureSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AttendanceSummary
{
    public function __construct(private AttendanceCalculator $calculator) {}

    public function forEnrollment(User $actor, Enrollment $enrollment, AssessmentPeriod $period, ?string $from = null, ?string $to = null, ?int $reportVersion = null): array
    {
        $this->validateRange($from, $to);

        return DB::transaction(function () use ($actor, $enrollment, $period, $from, $to, $reportVersion): array {
            $enrollment = Enrollment::findOrFail($enrollment->getKey());
            $offering = $this->lockOffering(Offering::findOrFail($enrollment->offering_id));
            $period = $offering->periods()->sharedLock()->findOrFail($period->getKey());
            $enrollment = $offering->enrollments()->findOrFail($enrollment->id);
            Gate::forUser(User::findOrFail($actor->getKey()))->authorize('view', $enrollment->setRelation('offering', $offering));

            if ($period->state === 'closed' || $reportVersion !== null) {
                $report = $this->report($period, $reportVersion, $from, $to);
                $row = collect($report->snapshot['rows'])->firstWhere('enrollment_id', $enrollment->id);
                abort_if($row === null, 404, 'La inscripción no pertenece a esta versión.');

                return $row['calculation'] + ['source' => 'snapshot', 'report_version' => $report->version, 'generated_at' => $report->generated_at->toISOString()];
            }

            return $this->calculateFor($period, collect([$enrollment]), $from, $to)[$enrollment->id] + ['source' => $period->reopened_at ? 'review' : 'live'];
        });
    }

    /** A page of students, never a page of sessions. All rows share locks and as_of. */
    public function forOffering(User $actor, Offering $offering, ?int $periodId = null, int $page = 1, int $perPage = 20, ?string $from = null, ?string $to = null, ?int $reportVersion = null): array
    {
        $this->validateRange($from, $to);
        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw ValidationException::withMessages(['results_page' => 'Usa una página positiva y entre 1 y 100 estudiantes por página.']);
        }

        return DB::transaction(function () use ($actor, $offering, $periodId, $page, $perPage, $from, $to, $reportVersion): array {
            $offering = $this->lockOffering($offering);
            $actor = User::findOrFail($actor->getKey());
            Gate::forUser($actor)->authorize('view', $offering);
            $periods = $offering->periods()->orderBy('number')->orderBy('id')->get();
            $period = $periodId === null ? $periods->first() : $offering->periods()->findOrFail($periodId);
            if ($period !== null) {
                $period = $offering->periods()->sharedLock()->findOrFail($period->id);
            }
            if ($actor->role === Role::Student) {
                // Global closure blockers can mention other enrollments; keep them out of every consumer.
                $periods->each(fn ($item) => $item->setAttribute('closure_issues', []));
                $period?->setAttribute('closure_issues', []);
            }
            $versions = $period !== null && ($period->current_report_id !== null || $period->reopened_at !== null || $period->state === 'closed')
                ? $period->reports()->orderByDesc('version')->get(['version', 'generated_at', 'origin']) : collect();
            $source = $period?->reopened_at ? 'review' : 'live';
            $snapshotError = null;
            $report = null;
            if ($period !== null && ($period->state === 'closed' || $reportVersion !== null)) {
                try {
                    $report = $this->report($period, $reportVersion, $from, $to);
                } catch (ValidationException $exception) {
                    $snapshotError = $exception->errors()['snapshot'][0];
                }
                $source = 'snapshot';
                $frozen = collect($report?->snapshot['rows'] ?? [])->filter(fn ($row) => $actor->role !== Role::Student || $row['student_id'] === $actor->id)->values();
                $rows = new LengthAwarePaginator($frozen->forPage($page, $perPage)->values(), $frozen->count(), $perPage, $page, ['path' => request()->url(), 'pageName' => 'results_page']);
                $rows->appends(['period_id' => $period->id, 'report_version' => $reportVersion]);
                // Never return the unfiltered report model to a consumer acting for a student.
                $report = $report === null ? null : (object) $report->only(['version', 'generated_at', 'origin']);

                return compact('periods', 'period', 'rows', 'versions', 'source', 'snapshotError', 'report');
            }
            $rows = $offering->enrollments()->visibleTo($actor)->with('student')->orderBy('id')->paginate($perPage, ['*'], 'results_page', $page);
            $calculations = $period === null ? [] : $this->calculateFor($period, $rows->getCollection(), $from, $to);
            $rows->setCollection($rows->getCollection()->map(fn ($enrollment) => [
                'enrollment_id' => $enrollment->id,
                'student_name' => $enrollment->student->name,
                'student_code' => $enrollment->student->student_code,
                'student_active' => $enrollment->student->active,
                'calculation' => $calculations[$enrollment->id] ?? null,
            ]));
            $rows->appends(['period_id' => $period?->id]);

            return compact('periods', 'period', 'rows', 'versions', 'source', 'snapshotError', 'report');
        });
    }

    /** Internal close consumer: caller holds exclusive academic locks for this whole batch. */
    public function captureForClosure(AssessmentPeriod $period): array
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('El snapshot requiere una transacción académica.');
        }
        $enrollments = $period->offering->enrollments()->with('student')->orderBy('id')->get();
        $calculations = $this->calculateFor($period, $enrollments, null, null);

        return $enrollments->map(fn ($enrollment) => [
            'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id,
            'student_name' => $enrollment->student->name, 'student_code' => $enrollment->student->student_code,
            'student_active' => $enrollment->student->active,
            'active_from' => $enrollment->active_from->toDateString(), 'active_to' => $enrollment->active_to?->toDateString(),
            'calculation' => $calculations[$enrollment->id],
        ])->all();
    }

    private function report(AssessmentPeriod $period, ?int $version, ?string $from, ?string $to): ReportVersion
    {
        if ($from !== null || $to !== null) {
            throw ValidationException::withMessages(['snapshot' => 'Un cierre se consulta completo, sin recalcular filtros de fechas.']);
        }
        $report = $version === null ? $period->reports()->find($period->current_report_id) : $period->reports()->where('version', $version)->firstOrFail();
        if (! app(ClosureSnapshot::class)->valid($report, $period)) {
            throw ValidationException::withMessages(['snapshot' => 'Cierre inconsistente: no se pueden consultar sus resultados conservados. Solicita revisión a Coordinación.']);
        }

        return $report;
    }

    private function lockOffering(Offering $offering): Offering
    {
        $current = Offering::findOrFail($offering->getKey());
        $cycle = AcademicCycle::sharedLock()->findOrFail($current->cycle_id);
        $current = Offering::sharedLock()->findOrFail($current->id);
        if ($current->cycle_id !== $cycle->id) {
            throw ValidationException::withMessages(['offering' => 'El grupo cambió de ciclo. Reintenta la consulta.']);
        }

        return $current;
    }

    private function calculateFor(AssessmentPeriod $period, Collection $enrollments, ?string $from, ?string $to): array
    {
        $asOf = CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s.u\Z');
        if ($enrollments->isEmpty()) {
            return [];
        }
        // Every academic writer holds an exclusive cycle lock; these reads share one snapshot.
        $sessions = $period->sessions()->orderBy('id')->get(['id', 'starts_at', 'ends_at', 'state']);
        $enrollmentIds = $enrollments->pluck('id');
        $attendances = Attendance::whereIn('session_id', $sessions->modelKeys())->whereIn('enrollment_id', $enrollmentIds)
            ->get(['session_id', 'enrollment_id', 'status'])->keyBy(fn ($row) => $row->enrollment_id.':'.$row->session_id);
        $requests = AbsenceRequest::whereIn('session_id', $sessions->modelKeys())->whereIn('enrollment_id', $enrollmentIds)
            ->get(['session_id', 'enrollment_id', 'status'])->keyBy(fn ($row) => $row->enrollment_id.':'.$row->session_id);
        $baseFacts = $sessions->map(fn ($session) => [
            'id' => $session->id,
            'academic_date' => $session->starts_at->timezone('America/El_Salvador')->toDateString(),
            'starts_at' => $session->starts_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'ends_at' => $session->ends_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'state' => $session->state->value,
        ]);

        return $enrollments->mapWithKeys(function ($enrollment) use ($period, $baseFacts, $attendances, $requests, $from, $to, $asOf): array {
            $start = max($period->starts_on->toDateString(), $enrollment->active_from->toDateString(), $from ?? '0001-01-01');
            $end = min($period->ends_on->toDateString(), $enrollment->active_to?->toDateString() ?? '9999-12-31', $to ?? '9999-12-31');
            $facts = $baseFacts->map(fn ($fact) => $fact + [
                'status' => $attendances->get($enrollment->id.':'.$fact['id'])?->status->value,
                'request_status' => $requests->get($enrollment->id.':'.$fact['id'])?->status,
            ])->all();

            return [$enrollment->id => $this->calculator->calculate($period->policy_version, $facts, $start, $end, $asOf) + [
                'enrollment_id' => $enrollment->id,
                'offering_id' => $period->offering_id,
                'period_id' => $period->id,
                'period_state' => $period->state,
                'range' => ['from' => $start, 'to' => $end],
                'session_facts' => $facts,
            ]];
        })->all();
    }

    private function validateRange(?string $from, ?string $to): void
    {
        foreach ([$from, $to] as $date) {
            if ($date !== null && (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)))) {
                throw ValidationException::withMessages(['range' => 'Usa fechas académicas válidas AAAA-MM-DD.']);
            }
        }
        if ($from !== null && $to !== null && $from > $to) {
            throw ValidationException::withMessages(['range' => 'El inicio debe ser anterior o igual al final.']);
        }
    }
}
