<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\CohortSession;
use App\Support\SectionBodySanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    /**
     * Create-or-update the session's single assignment (one per kelas by
     * schema). Authorship fields are set server-side only — request input
     * never reaches them (mass-assignment guard).
     */
    public function upsert(Request $request, CohortSession $session, SectionBodySanitizer $sanitizer): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ], [
            'title.required' => 'Judul tugas wajib diisi.',
            'body.required' => 'Soal tugas wajib diisi.',
        ]);

        $assignment = $session->assignment;

        if ($assignment === null) {
            $assignment = new Assignment;
            $assignment->cohort_session_id = $session->id;
            $assignment->created_by = $request->user()->id;
        }

        $assignment->title = $data['title'];
        // Editor output is HTML; the same allowlist that guards content
        // sections guards the soal (server is the authority, not the editor).
        $assignment->body = $sanitizer->sanitize($data['body']);
        $assignment->updated_by = $request->user()->id;
        $assignment->save();

        return response()->json(['assignment' => self::row($assignment->fresh('updater'))]);
    }

    /**
     * Shared assignment shape for admin payloads (also embedded per session
     * by CohortController::show). pending_count = enrollments whose LATEST
     * submission is still ungraded (superseded ungraded rows are history).
     *
     * @return array{id: int, title: string, body: string, updated_by: ?string, pending_count: int}
     */
    public static function row(Assignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'body' => $assignment->body,
            'updated_by' => $assignment->updater?->name,
            'pending_count' => $assignment->submissions()
                ->whereIn('id', function ($q) use ($assignment) {
                    $q->selectRaw('MAX(id)')
                        ->from('assignment_submissions')
                        ->where('assignment_id', $assignment->id)
                        ->groupBy('enrollment_id');
                })
                ->whereNull('score')
                ->count(),
        ];
    }
}
