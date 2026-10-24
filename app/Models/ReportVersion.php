<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportVersion extends Model
{
    protected $fillable = ['period_id', 'version', 'snapshot', 'policy_version', 'pdf_path', 'file_hash', 'generated_by', 'generated_at', 'origin', 'snapshot_hash'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'generated_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $report): void {
            if ($report->isDirty(['period_id', 'version', 'snapshot', 'policy_version', 'generated_by', 'generated_at', 'origin', 'snapshot_hash'])) {
                throw new \LogicException('Una versión de cierre es inmutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Las versiones de cierre se conservan.'));
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'period_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
