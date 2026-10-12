<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceRequest extends Model
{
    protected $fillable = ['offering_id', 'enrollment_id', 'session_id', 'reason', 'evidence_path', 'status', 'resolved_by', 'resolution_note', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime'];
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

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
