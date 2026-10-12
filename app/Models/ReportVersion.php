<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportVersion extends Model
{
    protected $fillable = ['period_id', 'version', 'snapshot', 'policy_version', 'pdf_path', 'file_hash', 'generated_by'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
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
