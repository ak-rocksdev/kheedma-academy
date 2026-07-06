<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cohort extends Model
{
    use HasFactory;

    protected $appends = ['status'];

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'mentor_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** The mentor leading this cohort — a User with the `mentor` role. */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Derived lifecycle from the dates (never stored):
     * upcoming (starts in the future or no start), active, or ended.
     */
    protected function status(): Attribute
    {
        return Attribute::make(get: function (): string {
            $today = now()->startOfDay();

            if ($this->start_date && $this->start_date->gt($today)) {
                return 'upcoming';
            }
            if ($this->end_date && $this->end_date->lt($today)) {
                return 'ended';
            }

            return $this->start_date ? 'active' : 'upcoming';
        });
    }
}
