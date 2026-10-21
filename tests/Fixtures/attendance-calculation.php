<?php

// Shared examples for A01 and consumers B01/B02. Never seed the development database.
return [
    'ten_sessions' => ['statuses' => ['present', 'present', 'present', 'present', 'present', 'present', 'present', 'present', 'justified', 'absent'], 'percentage' => '90.00', 'meets' => true],
    'exact_limit' => ['statuses' => ['present', 'present', 'present', 'present', 'present', 'present', 'absent', 'absent'], 'percentage' => '75.00', 'meets' => true],
    'approved_justification' => ['statuses' => ['present', 'present', 'present', 'present', 'present', 'justified', 'absent', 'absent'], 'percentage' => '75.00', 'meets' => true],
    'below_limit' => ['statuses' => ['present', 'present', 'present', 'present', 'present', 'absent', 'absent', 'absent'], 'percentage' => '62.50', 'meets' => false],
    'all_present' => ['statuses' => ['present'], 'percentage' => '100.00', 'meets' => true],
    'all_absent' => ['statuses' => ['absent'], 'percentage' => '0.00', 'meets' => false],
    'empty' => ['statuses' => [], 'percentage' => null, 'meets' => null],
];
