<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberAssignmentSubmissionController extends Controller
{
    /**
     * A member sends (or re-sends) their answer link. Append-only: a retake
     * is a NEW row; the previous grade stands until the mentor grades the
     * new version. Only url/note ever come from the request — score and
     * grader fields are the mentor's alone (mass-assignment guard).
     */
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $person = $request->user()->person;

        $enrollment = $person?->enrollments()
            ->where('cohort_id', $assignment->session->cohort_id)
            ->with('latestStatusEvent')
            ->first();

        abort_unless($enrollment !== null && $enrollment->isActive(), 404);

        // The form shows a fixed "https://" prefix, so members type (or paste)
        // the link without worrying about the scheme; normalize before
        // validating so both shapes pass the same rules.
        $request->merge(['url' => $this->normalizedUrl($request->input('url'))]);

        $data = $request->validate([
            'url' => [
                'required',
                'url:https',
                'max:500',
                // A URL host without a dot ("https://bukan-link") is technically
                // valid but never a real answer link.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $host = parse_url((string) $value, PHP_URL_HOST);
                    if ($host === null || $host === false || ! str_contains($host, '.')) {
                        $fail('Formatnya harus link, contoh: https://drive.google.com/…');
                    }
                },
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'url.required' => 'Link jawaban wajib diisi.',
            'url.url' => 'Formatnya harus link, contoh: https://drive.google.com/…',
            'url.max' => 'Link terlalu panjang (maksimal 500 karakter).',
        ]);

        $submission = new AssignmentSubmission;
        $submission->assignment_id = $assignment->id;
        $submission->enrollment_id = $enrollment->id;
        $submission->url = $data['url'];
        $submission->note = $data['note'] ?? null;
        $submission->save();

        return back()->with('tugas_terkirim', $assignment->id);
    }

    /** Prepend https:// when the member typed a bare link (the UI shows the prefix). */
    private function normalizedUrl(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '' || preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return 'https://'.$url;
    }
}
