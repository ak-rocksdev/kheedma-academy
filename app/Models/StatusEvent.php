<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only transition log for an enrollment. Rows are inserted, never
 * updated — there is no `updated_at`; `created_at` is set by the database and
 * `occurred_at` records when the transition actually happened.
 */
class StatusEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null; // append-only: no updated_at column

    protected $fillable = [
        'enrollment_id',
        'status',
        'note',
        'occurred_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
