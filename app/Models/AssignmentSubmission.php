<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use HasFactory;

    /**
     * Grading fields (score, feedback, graded_by, graded_at) are deliberately
     * excluded: they are grader-owned and set only via explicit assignment in
     * SubmissionController's grade action, never through mass assignment.
     */
    protected $fillable = [
        'assignment_id',
        'enrollment_id',
        'url',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'graded_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
