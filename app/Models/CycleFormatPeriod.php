<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleFormatPeriod extends Model
{
    protected $fillable = ['cycle_format_id', 'number', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(CycleFormat::class, 'cycle_format_id');
    }
}
