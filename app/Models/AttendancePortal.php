<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendancePortal extends Model
{
    protected $hidden = ['token_hash'];

    protected $fillable = ['session_id', 'opens_at', 'expires_at', 'closed_at', 'enabled_methods', 'token_hash'];

    protected function casts(): array
    {
        return ['opens_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'enabled_methods' => 'array'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    public function readerSessions(): HasMany
    {
        return $this->hasMany(ReaderSession::class, 'portal_id');
    }
}
