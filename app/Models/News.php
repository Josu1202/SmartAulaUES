<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class News extends Model
{
    protected $table = 'news';

    protected $fillable = ['offering_id', 'cycle_id', 'title', 'content', 'author_id'];

    protected function casts(): array
    {
        return [];
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
