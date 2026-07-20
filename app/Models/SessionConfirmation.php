<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's declared intent for one class: attending or cannot_attend.
 * One mutable row per (class, enrollment) — intent, never attendance;
 * the mentor still records actual presence in `attendances`.
 */
class SessionConfirmation extends Model
{
    use HasFactory;

    protected $fillable = [
        'cohort_session_id',
        'enrollment_id',
        'status',
        'note',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CohortSession::class, 'cohort_session_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
