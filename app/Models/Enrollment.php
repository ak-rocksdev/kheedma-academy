<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'people_id',
        'cohort_id',
        'application_id',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'people_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(StatusEvent::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /** The most recent transition — convenient for showing current state. */
    public function latestStatusEvent(): HasOne
    {
        return $this->hasOne(StatusEvent::class)->latestOfMany('occurred_at');
    }

    /**
     * Single source of truth for "active": no status event yet reads as
     * accepted; only an explicit terminal status (dropped) excludes it.
     * Eager-load latestStatusEvent before calling this in a loop.
     */
    public function isActive(): bool
    {
        return ($this->latestStatusEvent?->status ?? 'accepted') === 'accepted';
    }
}
