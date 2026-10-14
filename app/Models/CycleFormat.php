<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CycleFormat extends Model
{
    protected $fillable = ['cycle_id', 'name', 'version', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class, 'cycle_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(CycleFormatPeriod::class)->orderBy('number');
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class);
    }
}
