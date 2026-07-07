<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'tagline',
        'description',
        'status',
        'selection_mode',
    ];

    /** Route-model binding uses the slug (public URLs never expose ids). */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** Open for registration: catalog-active AND an Angkatan's intake window is open. */
    public function isOpen(): bool
    {
        return $this->status === 'active' && $this->cohorts()->openForRegistration()->exists();
    }

    /** Query counterpart of isOpen(), for the public chooser. */
    public function scopeOpenForRegistration(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereHas('cohorts', fn (Builder $q) => $q->openForRegistration());
    }

    /** The Angkatan currently accepting registrations (soonest-closing first). */
    public function openCohort(): ?Cohort
    {
        return $this->cohorts()
            ->openForRegistration()
            ->orderByRaw('registration_closes_at IS NULL, registration_closes_at ASC')
            ->first();
    }
}
