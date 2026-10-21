<?php

namespace App\Services\Attendance;

/** Pure calculation over an authorized, consistent set of session facts. Never writes data. */
final class AttendanceCalculator
{
    public function calculate(string $version, array $sessions, string $from, ?string $to, string $asOf): array
    {
        $policy = AttendancePolicy::find($version);
        $counts = array_fill_keys(['eligible', 'present', 'justified', 'absent', 'missing', 'inconsistent', 'cancelled', 'future', 'in_progress', 'awaiting_start', 'overdue_unfinished', 'pending_requests'], 0);
        $incidents = [];
        $pending = [];
        foreach ($sessions as $session) {
            if ($session['academic_date'] < $from || ($to !== null && $session['academic_date'] > $to)) {
                continue;
            }
            $id = $session['id'];
            if ($session['state'] === 'cancelled') {
                $counts['cancelled']++;

                continue;
            }
            if ($session['request_status'] === 'pending') {
                $counts['pending_requests']++;
                $pending[] = $id;
            }
            if ($session['state'] !== 'completed') {
                $key = $session['ends_at'] <= $asOf ? 'overdue_unfinished'
                    : ($session['starts_at'] > $asOf ? 'future'
                        : ($session['state'] === 'in_progress' ? 'in_progress' : 'awaiting_start'));
                $counts[$key]++;

                continue;
            }
            $counts['eligible']++;
            $status = $session['status'];
            $issue = null;
            if ($status === null) {
                $counts['missing']++;
                $issue = 'missing_result';
            } elseif ($status === 'justified' && $session['request_status'] !== 'approved') {
                $issue = 'justified_without_approval';
            } elseif ($status === 'absent' && $session['request_status'] === 'approved') {
                $issue = 'approved_request_with_absent_result';
            } elseif (! in_array($status, ['present', 'absent', 'justified'], true)) {
                $issue = 'invalid_result';
            }
            if ($issue !== null) {
                if ($status !== null) {
                    $counts['inconsistent']++;
                }
                $incidents[] = ['session_id' => $id, 'code' => $issue];
            } else {
                $counts[$status]++;
            }
            if ($session['starts_at'] > $asOf) {
                $incidents[] = ['session_id' => $id, 'code' => 'completed_before_start'];
            }
        }
        $state = $policy === null ? 'policy_unavailable'
            : ($incidents !== [] ? 'incomplete' : ($counts['eligible'] === 0 ? 'no_sessions' : 'provisional'));
        $numerator = $policy === null ? null : $counts['present'] + $counts['justified'];
        $canCalculate = $state === 'provisional';
        $meets = $canCalculate ? 4 * $numerator >= 3 * $counts['eligible'] : null;

        return [
            'schema_version' => 1,
            'policy_version' => $version,
            'policy' => $policy,
            'state' => $state,
            'as_of' => $asOf,
            'counts' => $counts,
            'fraction' => ['numerator' => $numerator, 'denominator' => $counts['eligible']],
            'percentage' => $canCalculate ? number_format(100 * $numerator / $counts['eligible'], 2, '.', '') : null,
            'meets_minimum' => $meets,
            'conclusion' => $meets === null ? null : ($meets ? 'Alcanza el mínimo de asistencia' : 'No alcanza el mínimo de asistencia'),
            'incidents' => $incidents,
            'pending_request_session_ids' => $pending,
        ];
    }
}
