<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'page',
        'program_id',
        'heading',
        'body',
        'sort_order',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** Sections of the community join page, in display order. */
    public function scopeForCommunity(Builder $query): Builder
    {
        return $query->where('page', 'community')->orderBy('sort_order');
    }
}
