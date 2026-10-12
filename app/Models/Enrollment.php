<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    use HasFactory;

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->active) {
            return $query->whereRaw('1 = 0');
        }

        return match ($user->role) {
            Role::Coordinator => $query,
            Role::Teacher => $query->whereHas('offering', fn (Builder $q) => $q->where('teacher_id', $user->id)),
            Role::Student => $query->where('student_id', $user->id),
        };
    }

    protected $fillable = ['offering_id', 'student_id', 'active_from', 'active_to'];

    protected function casts(): array
    {
        return ['active_from' => 'date', 'active_to' => 'date'];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function absenceRequests(): HasMany
    {
        return $this->hasMany(AbsenceRequest::class);
    }
}
