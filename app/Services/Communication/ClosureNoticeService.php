<?php

namespace App\Services\Communication;

use App\Enums\Role;
use App\Models\AssessmentPeriod;
use App\Models\ReportVersion;
use App\Models\User;
use App\Notifications\AcademicNotice;
use App\Services\Attendance\ClosureSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Ramsey\Uuid\Uuid;

final class ClosureNoticeService
{
    public function send(int $periodId, string $event, ?int $reportId, ?string $issueDigest): void
    {
        $period = AssessmentPeriod::with('offering.subject')->find($periodId);
        if ($period === null || ! in_array($event, ['closed', 'blocked'], true)) {
            return;
        }
        $report = null;
        if ($event === 'closed') {
            $report = ReportVersion::where('period_id', $periodId)->find($reportId);
            if ($report === null || ! app(ClosureSnapshot::class)->valid($report, $period)) {
                return;
            }
        } elseif ($period->closure_issues === [] || $period->closure_issues === null
            || ! hash_equals($issueDigest ?? '', hash('sha256', json_encode($period->closure_issues, JSON_THROW_ON_ERROR)))) {
            return;
        }
        $teacherIds = [$period->offering->teacher_id];
        $coordinatorIds = User::where('role', Role::Coordinator)->where('active', true)->pluck('id')->all();
        $studentIds = $event === 'closed'
            ? collect($report->snapshot['rows'] ?? [])->pluck('student_id')->filter()->all()
            : [];
        $ids = array_values(array_unique(array_merge($teacherIds, $coordinatorIds, $studentIds)));
        $signature = $event === 'closed' ? (string) $reportId : ($issueDigest ?? '');
        User::whereIn('id', $ids)->where('active', true)->orderBy('id')->chunkById(100,
            function ($users) use ($period, $event, $signature, $report): void {
                foreach ($users as $actor) {
                    if (! Gate::forUser($actor)->allows('view', $period->offering)) {
                        continue;
                    }
                    $key = 'b03:closure:'.$event.':'.$period->id.':'.$signature.':'.$actor->id;
                    $title = $event === 'closed' ? 'Cómputo cerrado' : 'Cierre de cómputo pendiente';
                    $message = $event === 'closed'
                        ? 'El cómputo '.$period->number.' de '.$period->offering->subject->name
                            .' tiene un cierre certificado (versión '.$report->version.').'
                        : 'El cómputo '.$period->number.' de '.$period->offering->subject->name
                            .' requiere revisión antes de cerrar. Consulte los pendientes autorizados.';
                    $notice = new AcademicNotice($title, $message,
                        route('offerings.show', ['offering' => $period->offering_id, 'period_id' => $period->id], absolute: false));
                    $now = now();
                    DB::table('notifications')->insertOrIgnore([
                        'id' => Uuid::uuid5(Uuid::NAMESPACE_URL, $key)->toString(),
                        'type' => AcademicNotice::class, 'notifiable_type' => $actor->getMorphClass(),
                        'notifiable_id' => $actor->id,
                        'data' => json_encode($notice->toDatabase($actor) + ['event_key' => $key], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'read_at' => null, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            });
    }
}
