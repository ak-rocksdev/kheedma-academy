<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CohortController extends Controller
{
    /** All cohorts, newest first, with mentor name and participant count. */
    public function index(): JsonResponse
    {
        $cohorts = Cohort::query()
            ->with('mentor:id,name')
            ->withCount('enrollments')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Cohort $c) => $this->row($c));

        return response()->json(['data' => $cohorts]);
    }

    public function store(Request $request): JsonResponse
    {
        $cohort = Cohort::create($this->validated($request, creating: true));

        return response()->json([
            'cohort' => $this->row($cohort->load('mentor:id,name')->loadCount('enrollments')),
        ], 201);
    }

    public function update(Request $request, Cohort $cohort): JsonResponse
    {
        $cohort->update($this->validated($request, creating: false));

        return response()->json([
            'cohort' => $this->row($cohort->fresh(['mentor:id,name'])->loadCount('enrollments')),
        ]);
    }

    public function destroy(Cohort $cohort): JsonResponse
    {
        if ($cohort->enrollments()->exists()) {
            throw ValidationException::withMessages(['cohort' => 'Angkatan dengan peserta tidak bisa dihapus.']);
        }

        $cohort->delete();

        return response()->json(null, 204);
    }

    /** Shared validation; mentor_id must reference a user holding the mentor role. */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => $creating
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'mentor_id' => [
                'nullable',
                function (string $attribute, $value, $fail): void {
                    if ($value && ! User::role('mentor')->whereKey($value)->exists()) {
                        $fail('Mentor yang dipilih tidak valid.');
                    }
                },
            ],
        ]);
    }

    /**
     * @return array{id:int,name:string,start_date:?string,end_date:?string,status:string,mentor:?array{id:int,name:string},enrollments_count:int}
     */
    private function row(Cohort $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'start_date' => $c->start_date?->toDateString(),
            'end_date' => $c->end_date?->toDateString(),
            'status' => $c->status,
            'mentor' => $c->mentor ? ['id' => $c->mentor->id, 'name' => $c->mentor->name] : null,
            'enrollments_count' => (int) ($c->enrollments_count ?? 0),
        ];
    }
}
