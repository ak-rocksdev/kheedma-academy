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
        'materials_url',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
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

    /**
     * `chaperone()` hydrates each loaded session's `cohort` inverse from this
     * already-loaded parent instead of lazy-loading it. The member area's
     * per-class timeline walks session->cohort->program per session
     * (via CohortSession::googleCalendarUrl()), so without it every session
     * in a batch would re-query its cohort and program (N+1).
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CohortSession::class)->orderBy('position')->orderBy('scheduled_at')->chaperone();
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

        // ONLY the registration window governs (PO decision 2026-07-17):
        // a class that has already started stays open for late joiners as
        // long as its window says so.
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
            if ($this->start_date && $this->start_date->isFuture()) {
                return 'upcoming';
            }
            if ($this->end_date && $this->end_date->lt(now()->startOfDay())) {
                return 'ended';
            }

            return $this->start_date ? 'active' : 'upcoming';
        });
    }

    /**
     * "Hari ini" / "Besok" / "N hari lagi" inside the final week before the
     * class starts; null outside that window. Anticipation sharpens as the
     * one-shot class day nears (goal gradient).
     */
    public function startCountdownLabel(): ?string
    {
        if (! $this->start_date || $this->start_date->isPast()) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($this->start_date->copy()->startOfDay());

        return match (true) {
            $days === 0 => 'Hari ini',
            $days === 1 => 'Besok',
            $days <= 7 => "{$days} hari lagi",
            default => null,
        };
    }

    /** Class starts within the next N hours (false once it has started). */
    public function startsWithinHours(int $hours): bool
    {
        return $this->start_date !== null
            && $this->start_date->isFuture()
            && now()->diffInHours($this->start_date) <= $hours;
    }

    /**
     * Human start date/time for member-facing surfaces. Legacy date→datetime
     * conversion left `00:00` times behind, so the clock is shown only when
     * it isn't midnight.
     */
    public function startLabel(): ?string
    {
        if ($this->start_date === null) {
            return null;
        }

        $date = $this->start_date->locale('id')->translatedFormat('j F Y');

        if ($this->start_date->format('H:i') === '00:00') {
            return $date;
        }

        return $date.' pukul '.$this->start_date->format('H.i').' WIB';
    }
}
