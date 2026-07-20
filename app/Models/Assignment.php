<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'cohort_session_id',
        'title',
        'body',
        'created_by',
        'updated_by',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CohortSession::class, 'cohort_session_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Soal as safe HTML. Editor-authored bodies are sanitized at write time
     * and pass through; legacy plain-text bodies (pre rich-text) are escaped
     * with their line breaks restored.
     */
    public function bodyHtml(): string
    {
        if (preg_match('/<(p|ul|ol|li|h[1-6]|strong|em|a|br|blockquote|img)\b/i', $this->body)) {
            return $this->body;
        }

        return nl2br(e($this->body));
    }
}
