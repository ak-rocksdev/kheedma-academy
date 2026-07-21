<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\ProvisionParticipantAccount;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    /**
     * Enroll a person into a cohort — either from an accepted application
     * (door 1: Applicants) or directly by person id (door 2: cohort roster).
     * A person enrolled without an account gets one provisioned so every
     * participant can log in.
     */
    public function store(Request $request, ProvisionParticipantAccount $provisioner): JsonResponse
    {
        $data = $request->validate([
            'cohort_id' => ['required', 'exists:cohorts,id'],
            'application_id' => ['required_without:people_id', 'nullable', 'exists:applications,id'],
            'people_id' => ['required_without:application_id', 'nullable', 'exists:people,id'],
        ]);

        $cohort = Cohort::findOrFail($data['cohort_id']);
        $application = isset($data['application_id']) ? Application::findOrFail($data['application_id']) : null;

        if ($application !== null) {
            if ($application->status !== 'accepted') {
                throw ValidationException::withMessages(['application_id' => 'Hanya pelamar berstatus diterima yang bisa dimasukkan ke angkatan.']);
            }
            if ($application->program_id !== null && $application->program_id !== $cohort->program_id) {
                throw ValidationException::withMessages(['cohort_id' => 'Angkatan ini bukan milik program yang dilamar.']);
            }
        }

        $personId = $application?->people_id ?? $data['people_id'];

        if (Enrollment::where('people_id', $personId)->where('cohort_id', $cohort->id)->exists()) {
            throw ValidationException::withMessages(['people_id' => 'Peserta sudah terdaftar di angkatan ini.']);
        }

        $person = Person::findOrFail($personId);

        // Enrolment, its status event, and the provisioned login commit as one:
        // a failed account provision rolls the enrolment back rather than
        // leaving a participant who cannot log in.
        return DB::transaction(function () use ($request, $cohort, $person, $application, $provisioner) {
            $enrollment = Enrollment::create([
                'people_id' => $person->id,
                'cohort_id' => $cohort->id,
                'application_id' => $application?->id,
            ]);
            $enrollment->statusEvents()->create([
                'status' => 'accepted',
                'occurred_at' => now(),
                'created_by' => $request->user()->id,
            ]);

            return response()->json([
                'enrollment' => [
                    'id' => $enrollment->id,
                    'cohort_id' => $enrollment->cohort_id,
                    'person' => ['id' => $person->id, 'name' => $person->name],
                ],
                'generated_password' => $provisioner->ensureAccountFor($person),
            ], 201);
        });
    }

    /** Undo a mistaken enroll — only while no attendance/history has accrued. */
    public function destroy(Enrollment $enrollment): JsonResponse
    {
        $hasHistory = $enrollment->attendances()->exists()
            || $enrollment->statusEvents()->where('status', '!=', 'accepted')->exists();

        if ($hasHistory) {
            throw ValidationException::withMessages(['enrollment' => 'Enrollment sudah punya riwayat. Gunakan "Keluarkan" agar riwayat tetap tercatat.']);
        }

        $enrollment->statusEvents()->delete();
        $enrollment->delete();

        return response()->json(null, 204);
    }

    /** Record a dropped transition with the reason (append-only history). */
    public function drop(Request $request, Enrollment $enrollment): JsonResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $enrollment->statusEvents()->create([
            'status' => 'dropped',
            'note' => $data['note'],
            'occurred_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['enrollment' => ['id' => $enrollment->id, 'latest_status' => 'dropped']]);
    }
}
