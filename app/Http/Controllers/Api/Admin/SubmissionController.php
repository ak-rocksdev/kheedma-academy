<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Support\AssignmentScoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function __construct(private readonly AssignmentScoring $scoring) {}

    /** Grading panel data: one student's full history on one assignment, newest first. */
    public function index(Assignment $assignment, Enrollment $enrollment): JsonResponse
    {
        // The pair must belong together: the enrollment's cohort owns the
        // assignment's session. Mismatch = wrong panel = 404, not a leak.
        abort_unless($enrollment->cohort_id === $assignment->session->cohort_id, 404);

        $submissions = $assignment->submissions()
            ->where('enrollment_id', $enrollment->id)
            ->with('grader:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AssignmentSubmission $s) => $this->row($s));

        return response()->json([
            'submissions' => $submissions,
            'state' => $this->scoring->submissionState($assignment, $enrollment),
            'effective_score' => $this->scoring->effectiveScore($assignment, $enrollment),
        ]);
    }

    /**
     * Grade THIS row (by id) — never "the latest", so a retake landing while
     * the mentor types cannot steal the grade. Grader fields are server-set
     * only (mass-assignment guard).
     */
    public function grade(Request $request, AssignmentSubmission $submission): JsonResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'feedback' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ], [
            'score.required' => 'Nilai wajib diisi.',
            'score.integer' => 'Nilai harus angka bulat 0 sampai 100.',
            'score.min' => 'Nilai paling rendah 0.',
            'score.max' => 'Nilai paling tinggi 100.',
        ]);

        $submission->score = $data['score'];
        if (array_key_exists('feedback', $data)) {
            $submission->feedback = $data['feedback'];
        }
        $submission->graded_by = $request->user()->id;
        $submission->graded_at = now();
        $submission->save();

        return response()->json(['submission' => $this->row($submission->fresh('grader'))]);
    }

    /**
     * @return array{id: int, url: string, note: ?string, score: ?int, feedback: ?string, graded_by: ?string, graded_at: ?string, created_at: ?string}
     */
    private function row(AssignmentSubmission $s): array
    {
        return [
            'id' => $s->id,
            'url' => $s->url,
            'note' => $s->note,
            'score' => $s->score,
            'feedback' => $s->feedback,
            'graded_by' => $s->grader?->name,
            'graded_at' => $s->graded_at?->toIso8601String(),
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }
}
