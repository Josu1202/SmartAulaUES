<?php

return [
    // Server policy for the pending physical trial; never taken from request parameters.
    'radius_m' => 30.0,
    'timeout_ms' => 15000,
    'maximum_age_ms' => 0,
    'max_evidence_age_ms' => 30000,
    'future_tolerance_ms' => 5000,
    'max_accuracy_m' => 10.0,
];
