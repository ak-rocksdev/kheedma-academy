<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberAssignmentSubmissionController extends Controller
{
    /** A freshly-sent submission stays editable this long (PO decision 2026-07-20). */
    public const EDIT_WINDOW_HOURS = 6;

    /**
     * A member sends their answer link. Append-only: a post-grade retake is
     * a NEW row; the previous grade stands until the new version is graded.
     * Only url/note ever come from the request — score and grader fields
     * are the mentor's alone (mass-assignment guard).
     */
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $person = $request->user()->person;

        $enrollment = $person?->enrollments()
            ->where('cohort_id', $assignment->session->cohort_id)
            ->with('latestStatusEvent')
            ->first();

        abort_unless($enrollment !== null && $enrollment->isActive(), 404);

        // Assignments unlock at the door: sending requires the mentor to have
        // marked this member hadir for the session (PO rule 2026-07-20).
        $hasAttended = $enrollment->attendances()
            ->where('cohort_session_id', $assignment->session->id)
            ->exists();

        if (! $hasAttended) {
            throw ValidationException::withMessages([
                'url' => 'Tugas terbuka setelah kehadiranmu ditandai mentor di kelas.',
            ]);
        }

        // Spam-guard: while the latest submission awaits grading, no new rows
        // may be added — the member edits that row instead (update()).
        $latest = $assignment->submissions()
            ->where('enrollment_id', $enrollment->id)
            ->latest('id')
            ->first();

        if ($latest !== null && $latest->score === null) {
            throw ValidationException::withMessages([
                'url' => 'Kirimanmu masih menunggu dinilai. Edit kiriman itu, bukan mengirim baru.',
            ]);
        }

        // The form shows a fixed "https://" prefix, so members type (or paste)
        // the link without worrying about the scheme; normalize before
        // validating so both shapes pass the same rules.
        $request->merge(['url' => $this->normalizedUrl($request->input('url'))]);

        $data = $request->validate($this->rules(), $this->messages());

        $submission = new AssignmentSubmission;
        $submission->assignment_id = $assignment->id;
        $submission->enrollment_id = $enrollment->id;
        $submission->url = $data['url'];
        $submission->note = $data['note'] ?? null;
        $submission->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Jawabanmu terkirim. Mentor akan menilainya segera.']);
    }

    /**
     * Edit the just-sent submission in place — while a submission waits for
     * grading the member cannot ADD rows, only fix the newest one, and only
     * within the edit window. Guards: own active enrollment, ungraded,
     * latest row, inside the window.
     */
    public function update(Request $request, AssignmentSubmission $submission): RedirectResponse
    {
        $person = $request->user()->person;
        $enrollment = $submission->enrollment;

        abort_unless(
            $person !== null
            && $enrollment->person?->is($person)
            && $enrollment->isActive(),
            404
        );

        $isLatest = ! $submission->assignment->submissions()
            ->where('enrollment_id', $enrollment->id)
            ->where('id', '>', $submission->id)
            ->exists();

        abort_unless($submission->score === null && $isLatest, 404);

        if ($submission->created_at->lt(now()->subHours(self::EDIT_WINDOW_HOURS))) {
            throw ValidationException::withMessages([
                'url' => 'Masa edit sudah lewat. Kirimanmu terkunci dan masuk antrean penilaian mentor.',
            ]);
        }

        $request->merge(['url' => $this->normalizedUrl($request->input('url'))]);

        $data = $request->validate($this->rules(), $this->messages());

        $submission->url = $data['url'];
        $submission->note = $data['note'] ?? null;
        $submission->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Kirimanmu diperbarui.']);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
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
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'url.required' => 'Link jawaban wajib diisi.',
            'url.url' => 'Formatnya harus link, contoh: https://drive.google.com/…',
            'url.max' => 'Link terlalu panjang (maksimal 500 karakter).',
        ];
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
