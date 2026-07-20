<?php

namespace App\Http\Controllers;

use App\Models\CohortSession;
use App\Models\SessionConfirmation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberSessionConfirmationController extends Controller
{
    /**
     * A member declares intent for one class: attending or cannot_attend
     * (+ optional note). One mutable row per (class, enrollment); editable
     * until the class starts, then attendance takes over as the fact.
     * Never writes `attendances`.
     */
    public function store(Request $request, CohortSession $session): RedirectResponse
    {
        $person = $request->user()->person;

        $enrollment = $person?->enrollments()
            ->where('cohort_id', $session->cohort_id)
            ->with('latestStatusEvent')
            ->first();

        abort_unless($enrollment !== null && $enrollment->isActive(), 404);

        // Freeze at start: a class without a schedule has nothing to freeze
        // against and stays editable.
        if ($session->scheduled_at !== null && $session->scheduled_at->isPast()) {
            throw ValidationException::withMessages([
                'status' => 'Kelas sudah dimulai. Konfirmasi ditutup, kehadiranmu dicatat mentor di kelas.',
            ]);
        }

        $data = $request->validate([
            'status' => ['required', 'in:attending,cannot_attend'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'status.required' => 'Pilih salah satu ya.',
            'status.in' => 'Pilihan tidak dikenal.',
            'note.max' => 'Catatan terlalu panjang (maksimal 500 karakter).',
        ]);

        SessionConfirmation::updateOrCreate(
            ['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id],
            [
                'status' => $data['status'],
                // The note belongs to "berhalangan"; switching back wipes it.
                'note' => $data['status'] === 'cannot_attend' ? ($data['note'] ?? null) : null,
            ],
        );

        return back()->with('konfirmasi_tersimpan', $session->id);
    }
}
