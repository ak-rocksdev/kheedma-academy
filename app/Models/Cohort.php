<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'program_id',
        'name',
        'start_date',
        'end_date',
        'registration_opens_at',
        'registration_closes_at',
        'mentor_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
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

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CohortSession::class)->orderBy('position')->orderBy('scheduled_at');
    }

    /**
     * Intake open: opens_at set-and-past (or null WITH closes_at set) is not
     * enough — a window only opens when at least one bound is set and now sits
     * inside it. Both nulls = intake not open.
     */
    public function isOpenForRegistration(): bool
    {
        if ($this->registration_opens_at === null && $this->registration_closes_at === null) {
            return false;
        }
        if ($this->registration_opens_at && $this->registration_opens_at->isFuture()) {
            return false;
        }
        if ($this->registration_closes_at && $this->registration_closes_at->isPast()) {
            return false;
        }

        return true;
    }

    /** Query counterpart of isOpenForRegistration(). */
    public function scopeOpenForRegistration(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNotNull('registration_opens_at')->orWhereNotNull('registration_closes_at'))
            ->where(fn (Builder $q) => $q->whereNull('registration_opens_at')->orWhere('registration_opens_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('registration_closes_at')->orWhere('registration_closes_at', '>=', now()));
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
