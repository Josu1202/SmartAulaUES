<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class News extends Model
{
    protected $table = 'news';

    protected $fillable = ['offering_id', 'cycle_id', 'title', 'content', 'author_id', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }

    /** Identical scope for the index and direct-URL authorization. */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if (! $actor->active) {
            return $query->whereRaw('1 = 0');
        }
        if ($actor->role === Role::Coordinator) {
            return $query;
        }

        if ($actor->role === Role::Teacher) {
            return $query->where(function (Builder $q) use ($actor): void {
                $q->whereHas('offering', fn (Builder $g) => $g->where('teacher_id', $actor->id))
                    ->orWhere(function (Builder $byCycle) use ($actor): void {
                        $byCycle->whereNull('offering_id')->whereHas('cycle.offerings',
                            fn (Builder $g) => $g->where('teacher_id', $actor->id));
                    });
            })->where(fn (Builder $q) => $q->where('status', 'published')->orWhere('author_id', $actor->id))
                ->where('status', '!=', 'archived');
        }

        // PostgreSQL date of publication in the academic timezone; withdrawal is inclusive.
        $activeAtPublication = static function (Builder $q) use ($actor): void {
            $q->where('student_id', $actor->id)
                ->whereRaw("enrollments.active_from <= (news.published_at AT TIME ZONE 'America/El_Salvador')::date")
                ->where(fn (Builder $q) => $q->whereNull('active_to')
                    ->orWhereRaw("enrollments.active_to >= (news.published_at AT TIME ZONE 'America/El_Salvador')::date"));
        };

        return $query->where('status', 'published')->whereNotNull('published_at')
            ->where(function (Builder $q) use ($activeAtPublication): void {
                $q->whereHas('offering.enrollments', $activeAtPublication)
                    ->orWhere(function (Builder $byCycle) use ($activeAtPublication): void {
                        $byCycle->whereNull('offering_id')->whereHas('cycle.offerings.enrollments', $activeAtPublication);
                    });
            });
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class, 'cycle_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
