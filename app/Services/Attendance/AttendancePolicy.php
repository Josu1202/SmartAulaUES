<?php

namespace App\Services\Attendance;

final class AttendancePolicy
{
    public const VERSION = 'project-2026-v1';

    public static function find(string $version): ?array
    {
        if ($version !== self::VERSION) {
            return null;
        }

        return [
            'version' => self::VERSION,
            'minimum_percentage' => 75,
            'numerator' => 'present + justified_with_approved_request',
            'denominator' => 'eligible_completed_sessions',
            'timezone' => 'America/El_Salvador',
            'approval' => 'project_confirmed',
            'institutional_approval' => null,
        ];
    }
}
