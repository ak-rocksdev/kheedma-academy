<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravolt\Indonesia\Models\Kabupaten;
use Laravolt\Indonesia\Models\Provinsi;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    /** Fixed choices for the GMV range selector. */
    public const GMV_RANGES = ['0-50', '50-100', '100+'];

    /** Fixed choices for the gender selector. */
    public const GENDERS = ['male', 'female'];

    protected $fillable = [
        'name',
        'phone',
        'email',
        'birth_date',
        'gender',
        'province_code',
        'city_code',
        'tiktok_username',
        'instagram_username',
        'tiktok_followers',
        'has_started_affiliate',
        'affiliate_level',
        'affiliate_gmv_range',
        'followed_socials',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'has_started_affiliate' => 'boolean',
            'followed_socials' => 'boolean',
        ];
    }

    /** Derived age in years (null when birth_date is unknown). */
    protected function age(): Attribute
    {
        return Attribute::make(get: fn (): ?int => $this->birth_date?->age);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'people_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'people_id');
    }

    /**
     * Relation state to a program: enrolled|accepted|pending|rejected|none.
     * Drives the anti-duplicate funnel UX — enrolled outranks any application
     * status; otherwise the LATEST application for that program decides.
     */
    public function applicationStateFor(Program $program): string
    {
        $isEnrolled = $this->enrollments()
            ->whereHas('cohort', fn ($q) => $q->where('program_id', $program->id))
            ->exists();

        if ($isEnrolled) {
            return 'enrolled';
        }

        $latest = $this->applications()
            ->where('program_id', $program->id)
            ->latest()
            ->first();

        return $latest?->status ?? 'none';
    }

    /** Community membership (the unselective second funnel door), if joined. */
    public function communityMembership(): HasOne
    {
        return $this->hasOne(CommunityMembership::class, 'people_id');
    }

    /** Optional login account (role: participant), provisioned only when needed. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Provinsi::class, 'province_code', 'code');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(Kabupaten::class, 'city_code', 'code');
    }
}
