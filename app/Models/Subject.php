<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class);
    }
}
