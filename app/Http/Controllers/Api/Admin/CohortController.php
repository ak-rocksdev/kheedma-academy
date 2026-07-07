<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CohortController extends Controller
{
    /** All cohorts, newest first, with mentor name and participant count. */
    public function index(): JsonResponse
    {
        $cohorts = Cohort::query()
            ->with(['mentor:id,name', 'program:id,name'])
            ->withCount('enrollments')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Cohort $c) => $this->row($c));

        return response()->json(['data' => $cohorts]);
    }

    public function store(Request $request): JsonResponse
    {
        $cohort = Cohort::create($this->validated($request));

        return response()->json([
            'cohort' => $this->row($cohort->load(['mentor:id,name', 'program:id,name'])->loadCount('enrollments')),
        ], 201);
    }

    public function update(Request $request, Cohort $cohort): JsonResponse
    {
        $cohort->update($this->validated($request, $cohort));

        return response()->json([
            'cohort' => $this->row($cohort->fresh(['mentor:id,name', 'program:id,name'])->loadCount('enrollments')),
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

    /**
     * Shared validation; mentor_id must reference a user holding the mentor role.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Cohort $cohort = null): array
    {
        $creating = $cohort === null;

        $data = $request->validate([
            'name' => $creating
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],
            'program_id' => [
                $creating ? 'required' : 'sometimes',
                'required',
                'exists:programs,id',
            ],
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
            'registration_opens_at' => ['sometimes', 'nullable', 'date'],
            'registration_closes_at' => ['sometimes', 'nullable', 'date'],
        ]);

        // Validate the EFFECTIVE window (payload value when present, else stored)
        // so a partial update cannot silently close registration before it opens.
        $opensAt = array_key_exists('registration_opens_at', $data) ? $data['registration_opens_at'] : $cohort?->registration_opens_at;
        $closesAt = array_key_exists('registration_closes_at', $data) ? $data['registration_closes_at'] : $cohort?->registration_closes_at;

        if ($opensAt && $closesAt && ! Carbon::parse($closesAt)->gt(Carbon::parse($opensAt))) {
            throw ValidationException::withMessages([
                'registration_closes_at' => 'Tanggal tutup pendaftaran harus setelah tanggal buka.',
            ]);
        }

        return $data;
    }

    /**
     * @return array{id:int,name:string,program:?array{id:int,name:string},start_date:?string,end_date:?string,status:string,mentor:?array{id:int,name:string},enrollments_count:int,registration_opens_at:?string,registration_closes_at:?string,registration_open:bool}
     */
    private function row(Cohort $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'program' => $c->program ? ['id' => $c->program->id, 'name' => $c->program->name] : null,
            'start_date' => $c->start_date?->toDateString(),
            'end_date' => $c->end_date?->toDateString(),
            'status' => $c->status,
            'mentor' => $c->mentor ? ['id' => $c->mentor->id, 'name' => $c->mentor->name] : null,
            'enrollments_count' => (int) ($c->enrollments_count ?? 0),
            'registration_opens_at' => $c->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $c->registration_closes_at?->toIso8601String(),
            'registration_open' => $c->isOpenForRegistration(),
        ];
    }
}
