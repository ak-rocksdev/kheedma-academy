<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramController extends Controller
{
    /** Full catalog, newest first, with funnel counters. */
    public function index(): JsonResponse
    {
        $programs = Program::query()
            ->withCount(['cohorts', 'applications'])
            ->withExists(['cohorts as has_open_cohort' => fn ($q) => $q->openForRegistration()])
            ->latest()
            ->get()
            ->map(fn (Program $p) => $this->row($p));

        return response()->json(['data' => $programs]);
    }

    /** One program with its cohorts, applicants, and funnel stats (detail page). */
    public function show(Program $program): JsonResponse
    {
        $program->loadCount(['cohorts', 'applications'])
            ->loadExists(['cohorts as has_open_cohort' => fn ($q) => $q->openForRegistration()]);

        $cohorts = $program->cohorts()
            ->with('mentor:id,name')
            ->withCount('enrollments')
            ->orderByDesc('start_date')
            ->get();

        $applications = $program->applications()
            ->with(['person:id,name,phone,email', 'cohort:id,name', 'enrollment.cohort:id,name'])
            ->latest()
            ->get();

        $activeParticipants = Enrollment::query()
            ->whereHas('cohort', fn ($q) => $q->where('program_id', $program->id))
            ->with('latestStatusEvent')
            ->get()
            ->filter(fn (Enrollment $e) => $e->isActive())
            ->count();

        return response()->json([
            'program' => $this->row($program),
            'cohorts' => $cohorts->map(fn (Cohort $c) => $this->cohortRow($c))->values(),
            'applications' => $applications->map(fn (Application $a) => $this->applicationRow($a))->values(),
            'stats' => [
                'pending' => $applications->where('status', 'pending')->count(),
                'accepted' => $applications->where('status', 'accepted')->count(),
                'rejected' => $applications->where('status', 'rejected')->count(),
                'active_participants' => $activeParticipants,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $program = Program::create($this->validated($request));

        return response()->json([
            'program' => $this->row($program->loadCount(['cohorts', 'applications'])),
        ], 201);
    }

    public function update(Request $request, Program $program): JsonResponse
    {
        $program->update($this->validated($request, $program));

        return response()->json([
            'program' => $this->row($program->fresh()->loadCount(['cohorts', 'applications'])),
        ]);
    }

    /** Delete only when nothing hangs off the program yet. */
    public function destroy(Program $program): JsonResponse
    {
        if ($program->cohorts()->exists() || $program->applications()->exists()) {
            throw ValidationException::withMessages([
                'program' => 'Program dengan angkatan atau pendaftar tidak bisa dihapus. Nonaktifkan saja.',
            ]);
        }

        $program->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Program $program = null): array
    {
        $creating = $program === null;

        $type = $request->input('type', $program?->type ?? 'general');

        $data = $request->validate([
            'name' => $creating ? ['required', 'string', 'max:255'] : ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                ...($creating ? ['required'] : ['sometimes', 'required']),
                'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('programs', 'slug')->ignore($program?->id),
                Rule::notIn(['daftar', 'komunitas']),   // reserved public prefixes
            ],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'type' => ['sometimes', 'required', 'in:general,affiliate_community'],
            'level' => $type === 'affiliate_community'
                ? (($creating || $request->has('type'))
                    ? ['required', 'integer', 'min:1', 'max:255']
                    : ['sometimes', 'required', 'integer', 'min:1', 'max:255'])
                : ['prohibited'],
            'locked_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => $creating ? ['required', 'in:draft,active,inactive'] : ['sometimes', 'required', 'in:draft,active,inactive'],
            'selection_mode' => $creating ? ['required', 'in:selective,instant'] : ['sometimes', 'required', 'in:selective,instant'],
        ]);

        // Switching (or defaulting) to general must clear a stale level.
        if ($type === 'general') {
            $data['level'] = null;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Program $p): array
    {
        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'name' => $p->name,
            'tagline' => $p->tagline,
            'description' => $p->description,
            'status' => $p->status,
            'selection_mode' => $p->selection_mode,
            'type' => $p->type,
            'level' => $p->level !== null ? (int) $p->level : null,
            'locked_message' => $p->locked_message,
            'thumbnail_url' => $p->thumbnail_path ? Storage::disk('public')->url($p->thumbnail_path) : null,
            'is_open' => $p->hasAttribute('has_open_cohort') ? $p->status === 'active' && (bool) $p->has_open_cohort : $p->isOpen(),
            'cohorts_count' => (int) ($p->cohorts_count ?? 0),
            'applications_count' => (int) ($p->applications_count ?? 0),
        ];
    }

    /**
     * Cohort shape for the detail page. Key names match CohortController::row()
     * (minus the redundant program) so the frontend status maps carry over.
     *
     * @return array<string, mixed>
     */
    private function cohortRow(Cohort $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
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

    /**
     * Applicant shape for the detail page. Person is null-safe (soft deletes);
     * enrollment names the cohort the accepted applicant landed in.
     *
     * @return array<string, mixed>
     */
    private function applicationRow(Application $a): array
    {
        return [
            'id' => $a->id,
            'status' => $a->status,
            'created_at' => $a->created_at?->toIso8601String(),
            'reviewed_at' => $a->reviewed_at?->toIso8601String(),
            'motivation' => $a->motivation,
            'referral_source' => $a->referral_source,
            'review_note' => $a->review_note,
            'cohort_id' => $a->cohort_id,
            'person' => $a->person ? [
                'id' => $a->person->id,
                'name' => $a->person->name,
                'phone' => $a->person->phone,
                'email' => $a->person->email,
            ] : null,
            'enrollment' => $a->enrollment ? [
                'cohort_id' => $a->enrollment->cohort_id,
                'cohort_name' => $a->enrollment->cohort?->name,
            ] : null,
        ];
    }
}
