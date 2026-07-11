<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\MergePeople;
use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonController extends Controller
{
    /** Paginated, searchable directory of every live Person (the mini-CRM). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'segment' => ['nullable', 'in:applicants,community,participants,with-account,needs-review'],
        ]);

        $people = Person::query()
            ->with('city:code,name')
            ->withCount([
                'applications',
                'enrollments',
                'applications as pending_applications_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->withExists('communityMembership')
            ->when($request->filled('segment'), fn ($q) => match ($request->string('segment')->toString()) {
                'applicants' => $q->whereHas('applications'),
                'community' => $q->whereHas('communityMembership'),
                'participants' => $q->whereHas('enrollments'),
                'with-account' => $q->whereNotNull('user_id'),
                'needs-review' => $q->whereHas('applications', fn ($a) => $a->where('status', 'pending')),
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Person $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'phone' => $p->phone,
                'email' => $p->email,
                'city' => $p->city?->name,
                'applications_count' => $p->applications_count,
                'pending_applications_count' => $p->pending_applications_count,
                'enrollments_count' => $p->enrollments_count,
                'is_community_member' => (bool) $p->community_membership_exists,
                'has_account' => $p->user_id !== null,
                'created_at' => $p->created_at?->toIso8601String(),
            ]);

        return response()->json($people);
    }

    /** A person's profile plus their full cross-attempt history. */
    public function show(Person $person): JsonResponse
    {
        $person->load([
            'province:code,name',
            'city:code,name',
            'user',
            'applications' => fn ($q) => $q->latest(),
            'applications.program:id,name',
            'enrollments.cohort:id,name,start_date,end_date',
            'enrollments.cohort.sessions',
            'enrollments.latestStatusEvent',
            'enrollments.attendances:id,enrollment_id,cohort_session_id,created_at',
        ]);

        // Oldest submission = attempt #1; the list itself stays newest-first.
        $total = $person->applications->count();

        return response()->json([
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'email' => $person->email,
                'province' => $person->province?->name,
                'city' => $person->city?->name,
                'birth_date' => $person->birth_date?->toDateString(),
                'age' => $person->age,
                'gender' => $person->gender,
                'tiktok_username' => $person->tiktok_username,
                'instagram_username' => $person->instagram_username,
                'tiktok_followers' => $person->tiktok_followers,
                'has_started_affiliate' => $person->has_started_affiliate,
                'affiliate_level' => $person->affiliate_level,
                'affiliate_gmv_range' => $person->affiliate_gmv_range,
                'followed_socials' => $person->followed_socials,
                'created_at' => $person->created_at?->toIso8601String(),
                'account' => $this->accountBlock($person),
                'applications' => $person->applications->values()->map(fn ($a, $index) => [
                    'id' => $a->id,
                    'attempt' => $total - $index,
                    'program' => $a->program?->name,
                    'program_id' => $a->program_id,
                    'status' => $a->status,
                    'motivation' => $a->motivation,
                    'reviewed_at' => $a->reviewed_at?->toIso8601String(),
                    'created_at' => $a->created_at?->toIso8601String(),
                ]),
                'enrollments' => $person->enrollments->map(function ($e) {
                    $attendedAt = $e->attendances->keyBy('cohort_session_id');

                    return [
                        'id' => $e->id,
                        'cohort' => $e->cohort?->name,
                        'cohort_id' => $e->cohort_id,
                        'hadir' => $e->attendances->count(),
                        'latest_status' => $e->latestStatusEvent?->status,
                        'latest_status_at' => $e->latestStatusEvent?->occurred_at?->toIso8601String(),
                        // Rincian per-kelas: pernah diikuti atau tidak.
                        'classes' => ($e->cohort?->sessions ?? collect())->map(fn ($s) => [
                            'id' => $s->id,
                            'title' => $s->title,
                            'scheduled_at' => $s->scheduled_at?->toIso8601String(),
                            'attended' => $attendedAt->has($s->id),
                            'attended_at' => $attendedAt->get($s->id)?->created_at?->toIso8601String(),
                        ])->values(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Manage the person's participant login: deactivate/reactivate and reset
     * the password (generated when none is supplied, shown once). Staff
     * accounts are defensively unreachable here — they belong to Tim.
     */
    public function updateAccount(Request $request, Person $person): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'reset_password' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
        ]);

        $user = $person->user;
        if ($user === null) {
            throw ValidationException::withMessages(['account' => 'Orang ini belum memiliki akun.']);
        }
        if (! $user->hasRole('participant')) {
            throw ValidationException::withMessages(['account' => 'Akun tertaut bukan akun peserta. Kelola akun staf lewat menu Tim.']);
        }

        if (array_key_exists('is_active', $data)) {
            $user->is_active = $data['is_active'];
        }

        $generated = null;
        if ($data['reset_password'] ?? false) {
            $supplied = filled($data['password'] ?? null);
            $plain = $supplied ? $data['password'] : Str::password(12);
            $user->password = Hash::make($plain);
            $generated = $supplied ? null : $plain;
        }

        $user->save();

        return response()->json([
            'account' => $this->accountBlock($person->fresh('user')),
            'generated_password' => $generated,
        ]);
    }

    /** Dry-run of a merge: what would move, or what blocks it. */
    public function mergePreview(Request $request, MergePeople $mergePeople): JsonResponse
    {
        [$survivor, $duplicate] = $this->mergePair($request);

        return response()->json($mergePeople->preview($survivor, $duplicate));
    }

    /** Absorb a duplicate Person into the survivor (tombstones the duplicate). */
    public function merge(Request $request, MergePeople $mergePeople): JsonResponse
    {
        [$survivor, $duplicate] = $this->mergePair($request);

        return response()->json([
            'merged' => true,
            'moves' => $mergePeople->merge($survivor, $duplicate),
        ]);
    }

    /**
     * Validate and resolve the survivor/duplicate pair. Mirrors the public
     * forms' soft-delete-aware exists checks — a tombstone can't merge again.
     *
     * @return array{0: Person, 1: Person}
     */
    private function mergePair(Request $request): array
    {
        $livePerson = Rule::exists('people', 'id')->whereNull('deleted_at');

        $data = $request->validate([
            'survivor_id' => ['required', 'integer', $livePerson],
            'duplicate_id' => ['required', 'integer', 'different:survivor_id', $livePerson],
        ]);

        return [Person::findOrFail($data['survivor_id']), Person::findOrFail($data['duplicate_id'])];
    }

    /**
     * @return array{is_active: bool, email: string, created_at: ?string}|null
     */
    private function accountBlock(Person $person): ?array
    {
        if ($person->user === null) {
            return null;
        }

        return [
            'is_active' => (bool) $person->user->is_active,
            'email' => $person->user->email,
            'created_at' => $person->user->created_at?->toIso8601String(),
        ];
    }
}
