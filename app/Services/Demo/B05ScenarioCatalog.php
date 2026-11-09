<?php

namespace App\Services\Demo;

/** Immutable versioned sample values. Not an attendance formula or a policy. */
final class B05ScenarioCatalog
{
    public const VERSION = 1;

    public static function for(string $profile): array
    {
        return match ($profile) {
            'small' => [
                'code' => 'B05S-V1', 'prefix' => 'B05S', 'teachers' => 1, 'students' => 6,
                'active_groups' => 1, 'students_per_group' => 6, 'completed_per_group' => 8,
                'historical_students' => 4, 'historical_sessions' => 4,
                'expected_counts' => [
                    'users' => 8, 'subjects' => 2, 'academic_cycles' => 2, 'offerings' => 2,
                    'enrollments' => 10, 'class_sessions' => 14, 'report_versions' => 1,
                ],
            ],
            'full' => [
                'code' => 'B05F-V1', 'prefix' => 'B05F', 'teachers' => 3, 'students' => 60,
                'active_groups' => 6, 'students_per_group' => 20, 'completed_per_group' => 10,
                'historical_students' => 8, 'historical_sessions' => 4,
                'expected_counts' => [
                    'users' => 64, 'subjects' => 7, 'academic_cycles' => 2, 'offerings' => 7,
                    'enrollments' => 128, 'class_sessions' => 76, 'report_versions' => 1,
                ],
            ],
            default => throw new \InvalidArgumentException('Perfil B05 desconocido: use small o full.'),
        };
    }

    /** Reference statuses: A01 remains the only source for official calculation. */
    public static function statuses(int $studentIndex, int $sessionCount): array
    {
        $bucket = $studentIndex % 6;
        $absences = match ($bucket) {
            0 => 0, 1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => $sessionCount === 8 ? 4 : 5,
        };
        $justified = $bucket === 2 ? 1 : 0;
        $present = $sessionCount - $absences - $justified - ($bucket === 5 ? 1 : 0);
        $statuses = array_merge(
            array_fill(0, $present, 'present'),
            array_fill(0, $justified, 'justified'),
            array_fill(0, $absences, 'absent'),
            $bucket === 5 ? [null] : []
        );

        return $statuses;
    }

    public static function expectedSample(int $studentIndex, int $sessionCount): array
    {
        $statuses = self::statuses($studentIndex, $sessionCount);
        $missing = count(array_filter($statuses, static fn ($status) => $status === null));
        $numerator = count(array_filter($statuses, static fn ($status) => in_array($status, ['present', 'justified'], true)));

        return [
            'eligible' => $sessionCount,
            'present' => count(array_filter($statuses, static fn ($status) => $status === 'present')),
            'justified' => count(array_filter($statuses, static fn ($status) => $status === 'justified')),
            'absent' => count(array_filter($statuses, static fn ($status) => $status === 'absent')),
            'missing' => $missing,
            'fraction' => [$numerator, $sessionCount],
            'percentage' => $missing > 0 ? null : number_format(100 * $numerator / $sessionCount, 2, '.', ''),
        ];
    }
}
