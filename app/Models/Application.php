<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    use HasFactory;

    /** Fixed choices for the public form's "tahu dari mana" select. */
    public const REFERRAL_SOURCES = ['instagram', 'tiktok', 'whatsapp', 'teman', 'google', 'lainnya'];

    protected $fillable = [
        'people_id',
        'program_id',
        'referral_source',
        'status',
        'prefilter_submitted',
        'prefilter_link',
        'prefilter_verdict',
        'prefilter_note',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'prefilter_submitted' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'people_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }
}
