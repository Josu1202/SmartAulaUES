<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentPeriod extends Model
{
    use HasFactory;

    protected $fillable = ['offering_id', 'number', 'starts_on', 'ends_on', 'state', 'policy_version', 'closed_at', 'format_period_id', 'current_report_id', 'reopened_at', 'closure_issues', 'closure_checked_at'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'closed_at' => 'immutable_datetime', 'reopened_at' => 'immutable_datetime', 'closure_issues' => 'array', 'closure_checked_at' => 'immutable_datetime'];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'period_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReportVersion::class, 'period_id');
    }
}
