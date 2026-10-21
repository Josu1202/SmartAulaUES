<?php

use App\Services\Attendance\AttendancePolicy;

return [
    'policy_version' => AttendancePolicy::VERSION,
    'registration_window_minutes' => 15,
    'credential_pending_minutes' => 10,
    'evidence_max_kilobytes' => 5120,
];
