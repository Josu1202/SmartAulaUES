<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NfcCredential extends Model
{
    protected $hidden = ['token_hash'];

    protected $fillable = ['user_id', 'token_hash', 'state', 'pending_expires_at', 'activated_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['pending_expires_at' => 'immutable_datetime', 'activated_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
