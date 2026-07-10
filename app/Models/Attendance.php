<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row is "hadir" for one enrollment at one session; unmarking deletes the
 * row. Insert/delete only — no updated_at.
 */
class Attendance extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'cohort_session_id',
        'enrollment_id',
        'marked_by',
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
