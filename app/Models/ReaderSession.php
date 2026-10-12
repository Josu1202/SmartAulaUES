<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReaderSession extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'portal_id', 'teacher_id', 'label', 'expires_at', 'state'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }

    public function portal(): BelongsTo
    {
        return $this->belongsTo(AttendancePortal::class, 'portal_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
