<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AcademicCycle;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use App\Queries\AttendanceSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AcademicTimelineController extends Controller
{
    public function history(Request $request, AttendanceSummary $summary): View
    {
        $actor = $request->user();
        $data = $request->validate([
            'cycle_id' => ['nullable', 'integer', 'min:1'],
            'enrollment_id' => ['nullable', 'integer', 'min:1'],
            'period_id' => ['nullable', 'integer', 'min:1'],
            'session_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $cycleId = isset($data['cycle_id']) ? (int) $data['cycle_id'] : null;

        if ($actor->role !== Role::Student) {
            $groups = Offering::visibleTo($actor)->with(['subject', 'cycle'])
                ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
                ->orderByDesc('id')->paginate(12)->withQueryString();

            return view('academic.timeline.history', [
                'groups' => $groups, 'enrollments' => null, 'selection' => null,
                'periods' => collect(), 'selectedPeriod' => null, 'calculation' => null, 'historyFacts' => null,
                'cycles' => $this->cycles($actor), 'cycleId' => $cycleId,
            ]);
        }

        $base = Enrollment::visibleTo($actor)
            ->whereHas('offering', fn ($q) => $q->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId)));
        $enrollments = (clone $base)->with(['offering.subject', 'offering.cycle'])
            ->orderByDesc('id')->paginate(12)->withQueryString();
        $selection = isset($data['enrollment_id'])
            ? (clone $base)->with('offering')->findOrFail((int) $data['enrollment_id'])
            : $enrollments->getCollection()->first();
        $periods = $selection?->offering->periods()->orderBy('number')->get() ?? collect();
        $selectedPeriod = isset($data['period_id'])
            ? $selection?->offering->periods()->findOrFail((int) $data['period_id'])
            : $periods->first();
        abort_if(isset($data['period_id']) && $selectedPeriod === null, 404);
        $calculation = $selection && $selectedPeriod
            ? $summary->forEnrollment($actor, $selection, $selectedPeriod)
            : null;
        $historyFacts = null;
        if ($calculation !== null) {
            $facts = collect($calculation['session_facts'] ?? [])
                ->filter(fn ($fact) => $fact['academic_date'] >= ($calculation['range']['from'] ?? '0001-01-01')
                    && $fact['academic_date'] <= ($calculation['range']['to'] ?? '9999-12-31'))
                ->sortByDesc('starts_at')->values();
            $page = (int) ($data['session_page'] ?? 1);
            $historyFacts = new LengthAwarePaginator($facts->forPage($page, 25)->values(), $facts->count(), 25, $page,
                ['path' => $request->url(), 'pageName' => 'session_page']);
            $historyFacts->appends($request->query());
        }

        return view('academic.timeline.history', [
            'groups' => null, 'enrollments' => $enrollments, 'selection' => $selection,
            'periods' => $periods, 'selectedPeriod' => $selectedPeriod,
            'calculation' => $calculation, 'historyFacts' => $historyFacts,
            'cycles' => $this->cycles($actor), 'cycleId' => $cycleId,
        ]);
    }

    public function calendar(Request $request): View
    {
        $actor = $request->user();
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'cycle_id' => ['nullable', 'integer', 'min:1'],
            'offering_id' => ['nullable', 'integer', 'min:1'],
            'teacher_id' => ['nullable', 'integer', 'min:1'],
        ]);
        abort_if($actor->role !== Role::Coordinator && isset($input['teacher_id']), 403);
        $tz = config('smartaulaues.display_timezone', 'America/El_Salvador');
        $from = CarbonImmutable::parse($input['from'] ?? now($tz)->toDateString(), $tz)->startOfDay();
        $to = CarbonImmutable::parse($input['to'] ?? $from->addDays(30)->toDateString(), $tz)->endOfDay();
        if ($to->lessThan($from) || $from->diffInDays($to) > 91) {
            throw ValidationException::withMessages(['to' => 'Selecciona un intervalo de hasta 90 días.']);
        }
        $groups = Offering::visibleTo($actor)
            ->when($input['cycle_id'] ?? null, fn ($q, $id) => $q->where('cycle_id', $id))
            ->when($input['teacher_id'] ?? null, fn ($q, $id) => $q->where('teacher_id', $id));
        $offeringIds = (clone $groups)->select('offerings.id');
        $sessions = ClassSession::query()->whereIn('offering_id', $offeringIds)
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->when($input['offering_id'] ?? null, fn ($q, $id) => $q->where('offering_id', $id));

        if ($actor->role === Role::Student) {
            $enrollments = Enrollment::visibleTo($actor)->get();
            // Do not show sessions after a withdrawal, even for a still-visible group.
            $sessions->where(function ($query) use ($enrollments, $tz): void {
                foreach ($enrollments as $enrollment) {
                    $activeFrom = CarbonImmutable::parse($enrollment->active_from->toDateString(), $tz)->startOfDay()->utc();
                    $activeTo = $enrollment->active_to
                        ? CarbonImmutable::parse($enrollment->active_to->toDateString(), $tz)->endOfDay()->utc()
                        : null;
                    $query->orWhere(function ($q) use ($enrollment, $activeFrom, $activeTo): void {
                        $q->where('offering_id', $enrollment->offering_id)->where('starts_at', '>=', $activeFrom);
                        if ($activeTo !== null) {
                            $q->where('starts_at', '<=', $activeTo);
                        }
                    });
                }
                if ($enrollments->isEmpty()) {
                    $query->whereRaw('1 = 0');
                }
            });
        }

        return view('academic.timeline.calendar', [
            'sessions' => $sessions->with(['offering.subject', 'offering.cycle', 'period'])
                ->orderBy('starts_at')->orderBy('id')->paginate(15)->withQueryString(),
            'groups' => (clone $groups)->with('subject')->orderBy('id')->get(['id', 'subject_id', 'group_code']),
            'cycles' => $this->cycles($actor),
            'teachers' => $actor->role === Role::Coordinator
                ? User::where('role', Role::Teacher)->where('active', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'filters' => $input, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'timezone' => $tz,
        ]);
    }

    private function cycles(User $actor)
    {
        return AcademicCycle::whereHas('offerings', fn ($q) => $q->visibleTo($actor))
            ->orderByDesc('starts_on')->get(['id', 'code']);
    }
}
