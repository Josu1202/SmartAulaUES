<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicCycle extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'starts_on', 'ends_on', 'status'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
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
