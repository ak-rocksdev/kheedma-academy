<?php

namespace App\Models;

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

    protected $fillable = [
        'name',
        'phone',
        'email',
        'province_code',
        'city_code',
        'tiktok_username',
        'instagram_username',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'people_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'people_id');
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
