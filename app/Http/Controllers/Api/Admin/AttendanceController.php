<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CohortSession;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    /**
     * Declarative attendance for one session: the payload is the full "hadir"
     * set; the server inserts/deletes the diff. Attendance is the final fact
     * ("pernah diikuti") — there is no derived completion status.
     */
    public function update(Request $request, CohortSession $session): JsonResponse
    {
        $data = $request->validate([
            'enrollment_ids' => ['present', 'array'],
            'enrollment_ids.*' => ['integer'],
        ]);

        $wanted = collect($data['enrollment_ids'])->unique()->values();

        $validIds = Enrollment::where('cohort_id', $session->cohort_id)->pluck('id');
        if ($wanted->diff($validIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['enrollment_ids' => 'Ada peserta yang bukan anggota Angkatan ini.']);
        }

        $current = $session->attendances()->pluck('enrollment_id');
        $toAdd = $wanted->diff($current);
        $toRemove = $current->diff($wanted);

        foreach ($toAdd as $enrollmentId) {
            Attendance::create([
                'cohort_session_id' => $session->id,
                'enrollment_id' => $enrollmentId,
                'marked_by' => $request->user()->id,
            ]);
        }
        if ($toRemove->isNotEmpty()) {
            $session->attendances()->whereIn('enrollment_id', $toRemove)->delete();
        }

        return response()->json([
            'attended' => $session->attendances()->pluck('enrollment_id'),
        ]);
    }
}
