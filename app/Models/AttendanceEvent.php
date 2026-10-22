<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEvent extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'event_id';

    protected $keyType = 'string';

    protected $hidden = ['geo_validation', 'request_hash'];

    protected $fillable = ['event_id', 'actor_id', 'reader_session_id', 'credential_id', 'request_hash', 'outcome', 'received_at', 'attendance_id', 'session_id', 'response', 'http_status', 'geo_validation'];

    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime', 'response' => 'array', 'geo_validation' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function readerSession(): BelongsTo
    {
        return $this->belongsTo(ReaderSession::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(NfcCredential::class, 'credential_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
