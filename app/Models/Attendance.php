<?php

namespace App\Models;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = ['offering_id', 'enrollment_id', 'session_id', 'status', 'method', 'recorded_by', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'immutable_datetime', 'status' => AttendanceStatus::class, 'method' => AttendanceMethod::class];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
