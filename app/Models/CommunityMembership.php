<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'people_id',
        'referral_source',
        'motivation',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'people_id');
    }
}
