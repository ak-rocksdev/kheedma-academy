<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\Cohort;
use App\Models\User;
use App\Support\AssignmentScoring;
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

    /** Detail for the roster/sessions/attendance screen. */
    public function show(Cohort $cohort, AssignmentScoring $scoring): JsonResponse
    {
        $cohort->load(['mentor:id,name', 'program:id,name,min_average_score'])->loadCount('enrollments');

        $sessions = $cohort->sessions()
            ->withCount('attendances')
            ->with(['assignment.updater:id,name', 'confirmations.enrollment.person:id,name'])
            ->get();
        $assignments = $sessions->pluck('assignment')->filter()->values();

        $enrollments = $cohort->enrollments()
            ->with(['person:id,name,phone', 'latestStatusEvent', 'attendances:id,enrollment_id,cohort_session_id'])
            ->get();

        // One submissions pass for the whole roster: latest row + latest graded
        // row per (assignment, enrollment) give state and effective score.
        $submissions = AssignmentSubmission::query()
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->orderBy('id')
            ->get(['id', 'assignment_id', 'enrollment_id', 'score'])
            ->groupBy(fn ($s) => $s->assignment_id.':'.$s->enrollment_id);

        $threshold = $cohort->program?->min_average_score;

        // A dropped member's stale intent must not inflate the recap or leak
        // their name; only active enrollments count.
        $activeEnrollmentIds = $enrollments->filter(fn ($e) => $e->isActive())->pluck('id')->flip();

        $roster = $enrollments->map(function ($e) use ($assignments, $submissions, $threshold, $scoring, $cohort) {
            $states = [];
            foreach ($assignments as $assignment) {
                $rows = $submissions->get($assignment->id.':'.$e->id, collect());
                $lastGraded = $rows->whereNotNull('score')->last();
                $states[$assignment->id] = [
                    'state' => $rows->isEmpty()
                        ? 'belum_dikerjakan'
                        : ($rows->last()->score === null ? 'menunggu_dinilai' : 'dinilai'),
                    'score' => $lastGraded?->score,
                ];
            }

            // Program-wide average (spans the person's other classes too);
            // the SAME rounded value the community gate compares. Computed
            // once per row.
            $avg = $threshold !== null ? $scoring->averageFor($e->person, $cohort->program) : null;

            return [
                'enrollment_id' => $e->id,
                'person' => ['id' => $e->person->id, 'name' => $e->person->name, 'phone' => $e->person->phone],
                'hadir' => $e->attendances->count(),
                'latest_status' => $e->latestStatusEvent?->status,
                'latest_status_at' => $e->latestStatusEvent?->occurred_at?->toIso8601String(),
                'attended_session_ids' => $e->attendances->pluck('cohort_session_id')->values(),
                'assignment_states' => (object) $states,
                'average' => $avg,
                'qualifies' => $threshold !== null ? ($avg !== null && $avg >= $threshold) : null,
            ];
        });

        return response()->json([
            'cohort' => array_merge($this->row($cohort), ['min_average_score' => $threshold]),
            'sessions' => $sessions->map(function ($s) use ($activeEnrollmentIds) {
                $confirmations = $s->confirmations->filter(fn ($c) => $activeEnrollmentIds->has($c->enrollment_id));

                return [
                    'id' => $s->id,
                    'title' => $s->title,
                    'scheduled_at' => $s->scheduled_at?->toIso8601String(),
                    'position' => (int) $s->position,
                    'attendances_count' => (int) $s->attendances_count,
                    'type' => $s->type,
                    'location_name' => $s->location_name,
                    'location_address' => $s->location_address,
                    'location_lat' => $s->location_lat,
                    'location_lng' => $s->location_lng,
                    'meeting_url' => $s->meeting_url,
                    'maps_url' => $s->mapsUrl(),
                    'assignment' => $s->assignment ? AssignmentController::row($s->assignment) : null,
                    'confirmations' => [
                        'attending' => $confirmations->where('status', 'attending')->count(),
                        'cannot_attend' => $confirmations->where('status', 'cannot_attend')->count(),
                        'entries' => $confirmations->map(fn ($c) => [
                            'name' => $c->enrollment->person?->name ?? '-',
                            'status' => $c->status,
                            'note' => $c->note,
                        ])->values(),
                    ],
                ];
            }),
            'roster' => $roster,
            // Whole-number averages (e.g. 80.0) must keep their decimal so
            // the field round-trips as a float, not silently become an int.
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function store(Request $request): JsonResponse
    {
        $cohort = Cohort::create($this->validated($request));

        // Spec 2026-07-18: cohort = batch. Classes (sessions) are created
        // explicitly by the admin, one per meeting.

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
        if ($cohort->enrollments()->exists() || $cohort->applications()->exists()) {
            throw ValidationException::withMessages(['cohort' => 'Angkatan dengan peserta atau pendaftar tidak bisa dihapus.']);
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
            'end_date' => [
                'nullable',
                'date',
                // Compare by DAY, not moment: end_date is date-only while
                // start_date now carries a time, so a one-day class starting
                // at 09.30 must still accept an equal end date.
                function (string $attribute, $value, $fail) use ($request, $cohort): void {
                    $start = $request->input('start_date', $cohort?->start_date?->toDateTimeString());
                    if ($start && Carbon::parse($value)->startOfDay()->lt(Carbon::parse($start)->startOfDay())) {
                        $fail('Tanggal selesai harus setelah atau sama dengan tanggal mulai.');
                    }
                },
            ],
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
            'materials_url' => ['nullable', 'url:https', 'max:500'],
        ]);

        if (! empty($data['registration_closes_at']) && strlen($data['registration_closes_at']) === 10) {
            $data['registration_closes_at'] .= ' 23:59:59';
        }

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
     * @return array{id:int,name:string,program:?array{id:int,name:string},start_date:?string,end_date:?string,status:string,mentor:?array{id:int,name:string},enrollments_count:int,registration_opens_at:?string,registration_closes_at:?string,registration_open:bool,materials_url:?string}
     */
    private function row(Cohort $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'program' => $c->program ? ['id' => $c->program->id, 'name' => $c->program->name] : null,
            'start_date' => $c->start_date?->toIso8601String(),
            'end_date' => $c->end_date?->toDateString(),
            'status' => $c->status,
            'mentor' => $c->mentor ? ['id' => $c->mentor->id, 'name' => $c->mentor->name] : null,
            'enrollments_count' => (int) ($c->enrollments_count ?? 0),
            'registration_opens_at' => $c->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $c->registration_closes_at?->toIso8601String(),
            'registration_open' => $c->isOpenForRegistration(),
            'materials_url' => $c->materials_url,
        ];
    }
}
