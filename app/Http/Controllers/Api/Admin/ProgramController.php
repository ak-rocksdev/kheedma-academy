<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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
}
