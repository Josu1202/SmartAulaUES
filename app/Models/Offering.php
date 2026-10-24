<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offering extends Model
{
    use HasFactory;

    protected $attributes = ['active' => true];

    /** Classification is derived only from the cycle, never its calendar end date. */
    public function scopeCatalog(Builder $query, string $view = 'current', string $availability = 'active', ?int $cycleId = null): Builder
    {
        return $query->whereHas('cycle', fn (Builder $cycle) => $view === 'history'
            ? $cycle->where('status', 'closed') : $cycle->where('status', '!=', 'closed'))
            ->when($availability !== 'all', fn (Builder $q) => $q->where('active', $availability === 'active'))
            ->when($cycleId !== null, fn (Builder $q) => $q->where('cycle_id', $cycleId));
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->catalog();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->active) {
            return $query->whereRaw('1 = 0');
        }

        return match ($user->role) {
            Role::Coordinator => $query,
            Role::Teacher => $query->where('teacher_id', $user->id),
            Role::Student => $query->whereHas('enrollments', fn (Builder $q) => $q->where('student_id', $user->id)),
        };
    }

    protected $fillable = ['cycle_id', 'subject_id', 'teacher_id', 'group_code', 'capacity', 'cycle_format_id'];

    public function cycleFormat(): BelongsTo
    {
        return $this->belongsTo(CycleFormat::class);
    }

    protected function casts(): array
    {
        return ['cycle_id' => 'integer', 'subject_id' => 'integer', 'teacher_id' => 'integer', 'capacity' => 'integer', 'cycle_format_id' => 'integer', 'active' => 'boolean'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class, 'cycle_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AssessmentPeriod::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }
}
