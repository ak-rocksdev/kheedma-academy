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
        'type',
        'location_name',
        'location_address',
        'location_lat',
        'location_lng',
        'meeting_url',
        'materials_url',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'date',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'location_lat' => 'float',
            'location_lng' => 'float',
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

        // The class start is a hard ceiling: once the class begins, registration
        // is closed no matter the manual window.
        if ($this->start_date && $this->start_date->isPast()) {
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
            ->where(fn (Builder $q) => $q->whereNull('registration_closes_at')->orWhere('registration_closes_at', '>=', now()))
            ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhere('start_date', '>', now()));
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

    public function isOnline(): bool
    {
        return $this->type === 'online';
    }

    /** Universal Google Maps link for members — no API call involved. */
    public function mapsUrl(): ?string
    {
        if ($this->location_lat === null || $this->location_lng === null) {
            return null;
        }

        return "https://www.google.com/maps/search/?api=1&query={$this->location_lat},{$this->location_lng}";
    }

    /** Keyless Google Maps iframe embed for the member area's collapsible map. */
    public function mapsEmbedUrl(): ?string
    {
        if ($this->location_lat === null || $this->location_lng === null) {
            return null;
        }

        return "https://maps.google.com/maps?q={$this->location_lat},{$this->location_lng}&z=16&output=embed";
    }

    /** Universal directions link — opens the Google Maps app with a route on mobile. */
    public function mapsDirectionsUrl(): ?string
    {
        if ($this->location_lat === null || $this->location_lng === null) {
            return null;
        }

        return "https://www.google.com/maps/dir/?api=1&destination={$this->location_lat},{$this->location_lng}";
    }

    /**
     * Prefilled Google Calendar event (assumed 2-hour session) — a template
     * URL, no API involved. Null without a real start time: legacy midnight
     * conversions carry no clock, and a 00.00 event would mislead.
     */
    public function googleCalendarUrl(): ?string
    {
        if (! $this->start_date || $this->start_date->format('H:i') === '00:00') {
            return null;
        }

        $location = $this->isOnline()
            ? ($this->meeting_url ?? 'Online')
            : trim(($this->location_name ? "{$this->location_name}, " : '').($this->location_address ?? ''), ', ');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => trim(($this->program?->name ?? 'Kelas Kheedma Academy').' · '.$this->name),
            'dates' => $this->start_date->format('Ymd\THis').'/'.$this->start_date->copy()->addHours(2)->format('Ymd\THis'),
            'ctz' => 'Asia/Jakarta',
            'location' => $location,
        ]);
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
