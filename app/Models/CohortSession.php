<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CohortSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'cohort_id',
        'title',
        'scheduled_at',
        'position',
        'type',
        'location_name',
        'location_address',
        'location_lat',
        'location_lng',
        'meeting_url',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'location_lat' => 'float',
            'location_lng' => 'float',
        ];
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(SessionConfirmation::class);
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class);
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

    /** Class starts within the next N hours (false once it has started). */
    public function startsWithinHours(int $hours): bool
    {
        return $this->scheduled_at !== null
            && $this->scheduled_at->isFuture()
            && now()->diffInHours($this->scheduled_at) <= $hours;
    }

    /**
     * Prefilled Google Calendar event (assumed 2-hour class) — a template URL,
     * no API involved. Null without a real start time: a 00.00 event would
     * mislead.
     */
    public function googleCalendarUrl(): ?string
    {
        if (! $this->scheduled_at || $this->scheduled_at->format('H:i') === '00:00') {
            return null;
        }

        $location = $this->isOnline()
            ? ($this->meeting_url ?? 'Online')
            : trim(($this->location_name ? "{$this->location_name}, " : '').($this->location_address ?? ''), ', ');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => trim(($this->cohort?->program?->name ?? 'Kelas Kheedma Academy').' · '.$this->title),
            'dates' => $this->scheduled_at->format('Ymd\THis').'/'.$this->scheduled_at->copy()->addHours(2)->format('Ymd\THis'),
            'ctz' => 'Asia/Jakarta',
            'location' => $location,
        ]);
    }

    /** Human schedule for member-facing surfaces; clock hidden at midnight. */
    public function scheduledLabel(): ?string
    {
        if ($this->scheduled_at === null) {
            return null;
        }

        $date = $this->scheduled_at->locale('id')->translatedFormat('j F Y');

        if ($this->scheduled_at->format('H:i') === '00:00') {
            return $date;
        }

        return $date.' pukul '.$this->scheduled_at->format('H.i').' WIB';
    }

    /**
     * "Hari ini" / "Besok" / "N hari lagi" inside the final week before the
     * class; null outside that window (goal gradient, mirrors the old
     * cohort-level countdown).
     */
    public function countdownLabel(): ?string
    {
        if (! $this->scheduled_at || $this->scheduled_at->isPast()) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($this->scheduled_at->copy()->startOfDay());

        return match (true) {
            $days === 0 => 'Hari ini',
            $days === 1 => 'Besok',
            $days <= 7 => "{$days} hari lagi",
            default => null,
        };
    }
}
