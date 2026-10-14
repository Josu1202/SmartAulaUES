<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const ENTITY_TYPES = [
        'User', 'AcademicCycle', 'CycleFormat', 'Subject', 'Offering', 'AssessmentPeriod', 'Enrollment',
        'CsvImport', 'ClassSession', 'AttendancePortal', 'ReaderSession', 'NfcCredential', 'Attendance', 'AbsenceRequest',
    ];

    public $timestamps = false;

    protected $fillable = ['actor_id', 'entity_type', 'entity_id', 'action', 'before', 'after', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
