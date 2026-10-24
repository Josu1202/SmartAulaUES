<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicCycle extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'starts_on', 'ends_on', 'status', 'closed_at', 'reopened_at', 'closure_issues', 'closure_checked_at'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'closed_at' => 'immutable_datetime', 'reopened_at' => 'immutable_datetime', 'closure_issues' => 'array', 'closure_checked_at' => 'immutable_datetime'];
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class, 'cycle_id');
    }

    public function formats(): HasMany
    {
        return $this->hasMany(CycleFormat::class, 'cycle_id');
    }
}
