<?php

namespace App\Queries;

use App\Enums\Role;
use App\Models\AbsenceRequest;
use App\Models\AcademicCycle;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardStatistics
{
    /**
     * Primer incremento B01: un grupo y un cómputo autorizados por consulta.
     * No replica reglas académicas: consume AttendanceSummary y su calculador A01.
     */
    public function teacherAbsenceRanking(User $teacher, ?int $groupId = null, ?int $periodId = null, ?int $enrollmentId = null): array
    {
        abort_unless($teacher->active && $teacher->role === Role::Teacher, 403);

        $groups = Offering::query()->visibleTo($teacher)->current()
            ->with(['subject', 'cycle'])->orderBy('offerings.id')->get();
        $group = $groupId === null ? $groups->first() : $groups->firstWhere('id', $groupId);
        abort_if($groupId !== null && $group === null, 404);

        $periods = $group?->periods()->orderBy('number')->orderBy('id')->get() ?? collect();
        $period = $periodId === null ? $periods->first() : $periods->firstWhere('id', $periodId);
        abort_if($periodId !== null && $period === null, 404);
        abort_if($enrollmentId !== null && $period === null, 404);

        $base = [
            'groups' => $groups, 'group' => $group, 'periods' => $periods, 'period' => $period,
            'rows' => [], 'total_students' => 0, 'detail' => null, 'error' => null, 'source' => null,
        ];
        if ($period === null) {
            return $base;
        }

        // Conserva el bloqueo de ciclo para que todas las páginas reflejen un mismo corte.
        // AttendanceSummary mantiene la autorización, los locks y los snapshots de A.
        return DB::transaction(function () use ($teacher, $group, $period, $enrollmentId, $base): array {
            AcademicCycle::sharedLock()->findOrFail($group->cycle_id);
            $summary = app(AttendanceSummary::class);
            $rows = [];
            $detail = null;
            $page = 1;
            $source = null;

            do {
                $batch = $summary->forOffering($teacher, $group, periodId: $period->id, page: $page, perPage: 100);
                if ($batch['snapshotError'] !== null) {
                    return array_replace($base, ['error' => 'No se pudo verificar la versión de cierre de este cómputo.']);
                }
                $source = $batch['source'];
                foreach ($batch['rows'] as $student) {
                    $calculation = $student['calculation'];
                    $counts = $calculation['counts'];
                    $eligible = (int) $counts['eligible'];
                    $row = [
                        'enrollment_id' => $student['enrollment_id'],
                        'name' => $student['student_name'],
                        'code' => $student['student_code'],
                        'absent' => (int) $counts['absent'],
                        'justified' => (int) $counts['justified'],
                        'eligible' => $eligible,
                        // Indicador visual de ausencias sin justificar, no umbral ni aptitud oficial.
                        'absence_percentage' => $calculation['state'] === 'provisional' && $eligible > 0
                            ? number_format(100 * $counts['absent'] / $eligible, 2, '.', '') : null,
                        'state' => $calculation['state'],
                        'pending_requests' => (int) $counts['pending_requests'],
                    ];
                    $rows[] = $row;
                    if ($enrollmentId === $student['enrollment_id']) {
                        $range = $calculation['range'];
                        $detail = [
                            'student' => $row,
                            'sessions' => array_values(array_filter($calculation['session_facts'],
                                fn (array $fact) => $fact['academic_date'] >= $range['from'] && $fact['academic_date'] <= $range['to'])),
                        ];
                    }
                }
                $page++;
            } while ($page <= $batch['rows']->lastPage());

            abort_if($enrollmentId !== null && $detail === null, 404);
            usort($rows, fn (array $a, array $b) => $b['absent'] <=> $a['absent']
                ?: strnatcasecmp($a['name'], $b['name'])
                ?: $a['enrollment_id'] <=> $b['enrollment_id']);

            return array_replace($base, [
                'rows' => array_slice($rows, 0, 10),
                'total_students' => count($rows),
                'detail' => $detail,
                'source' => $source,
            ]);
        });
    }

    public function forUser(User $user, ?Builder $groups = null): array
    {
        $groups = $groups === null ? Offering::query()->current() : clone $groups;
        $groups->visibleTo($user);
        $ids = (clone $groups)->reorder()->select('offerings.id');
        $sessions = ClassSession::whereIn('offering_id', $ids)->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');
        $attendances = Attendance::whereIn('offering_id', $ids)->whereHas('session', fn ($q) => $q->whereIn('state', ['in_progress', 'completed']))->whereHas('enrollment', fn ($q) => $q->visibleTo($user));
        $results = (clone $attendances)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $methods = (clone $attendances)->where('status', 'present')->whereNotNull('method')->selectRaw('method, count(*) as total')->groupBy('method')->pluck('total', 'method');
        $years = $user->role === Role::Coordinator ? User::where('role', 'student')->where('active', true)->selectRaw('study_year, graduated, count(*) as total')->groupBy('study_year', 'graduated')->orderBy('study_year')->get()->mapWithKeys(fn ($row) => [$row->graduated ? 'Egresados' : ($row->study_year ? $row->study_year.'º año' : 'Sin asignar') => $row->total])->all() : [];

        return ['sessions' => ['Programadas' => (int) ($sessions['scheduled'] ?? 0), 'En curso' => (int) ($sessions['in_progress'] ?? 0), 'Finalizadas' => (int) ($sessions['completed'] ?? 0), 'Canceladas' => (int) ($sessions['cancelled'] ?? 0), 'Sin inicio registrado' => (int) ($sessions['unstarted'] ?? 0)],
            'results' => ['Presentes' => (int) ($results['present'] ?? 0), 'Ausentes' => (int) ($results['absent'] ?? 0), 'Justificadas' => (int) ($results['justified'] ?? 0)],
            'methods' => ['NFC' => (int) ($methods['nfc'] ?? 0), 'Etiqueta compartida (declarado)' => (int) ($methods['nfc_tag'] ?? 0), 'QR' => (int) ($methods['qr'] ?? 0), 'Enlace' => (int) ($methods['link'] ?? 0), 'Manual' => (int) ($methods['manual'] ?? 0)], 'years' => $years,
            'pending_requests' => AbsenceRequest::whereIn('offering_id', $ids)->where('status', 'pending')->whereHas('enrollment', fn ($q) => $q->visibleTo($user))->count()];
    }
}
