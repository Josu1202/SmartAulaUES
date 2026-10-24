<?php

namespace App\Services\Attendance;

use App\Models\AssessmentPeriod;
use App\Models\ReportVersion;

/** Stable, independent of PostgreSQL jsonb key ordering. No live calculation on a closed read. */
final class ClosureSnapshot
{
    public static function hash(array $snapshot): string
    {
        $canonical = function (array $value) use (&$canonical): array {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $canonical($item);
                }
            }

            return $value;
        };

        return hash('sha256', json_encode($canonical($snapshot), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    public function valid(?ReportVersion $report, AssessmentPeriod $period): bool
    {
        if ($report === null || $report->period_id !== $period->id || $report->generated_at === null
            || ! in_array($report->origin, ['automatic', 'manual'], true) || $report->snapshot_hash === null) {
            return false;
        }
        $data = $report->snapshot;
        if (($data['schema_version'] ?? null) !== 1 || ($data['period']['id'] ?? null) !== $period->id
            || ($data['offering_id'] ?? null) !== $period->offering_id || ($data['version'] ?? null) !== $report->version
            || ($data['policy_version'] ?? null) !== $report->policy_version || ! isset($data['policy'], $data['rows'])
            || ! is_array($data['rows']) || ! array_is_list($data['rows'])) {
            return false;
        }
        $ids = [];
        foreach ($data['rows'] as $row) {
            if (! isset($row['enrollment_id'], $row['student_id'], $row['student_name'], $row['calculation']['counts'], $row['calculation']['session_facts'])
                || ! in_array($row['calculation']['state'] ?? null, ['provisional', 'no_sessions'], true)
                || isset($ids[$row['enrollment_id']])) {
                return false;
            }
            $ids[$row['enrollment_id']] = true;
        }

        return hash_equals($report->snapshot_hash, self::hash($data));
    }
}
