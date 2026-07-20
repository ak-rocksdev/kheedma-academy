# Assignments & Grading UI (Spec 1 Phase 2 + 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mentors write and grade per-class assignments in the admin SPA; members read the soal, submit answer links, see scores/feedback, and track their program average toward the community gate.

**Architecture:** Everything derives from the already-shipped foundation: `assignments`/`assignment_submissions` tables, `App\Support\AssignmentScoring` (effective score = latest graded, per-person-per-program average, missing = 0, half-up 1-decimal, gate compares the same rounded value), and permissions `assignments.manage`/`assignments.grade` (admin + mentor). Phase 2 adds three admin API surfaces (assignment upsert, submission history, grade) + `CohortController::show` enrichment, then mounts the UI in `CohortDetail.vue`'s existing class blocks. Phase 3 adds one member submit endpoint + `MemberAreaController` view-models, then renders assignment cards and a program progress card in `akun.blade.php`'s class timeline.

**Tech Stack:** Laravel 13 / PHP 8.4, PHPUnit 12 (NOT Pest), Vue 3 `<script setup>` plain JS (shadcn-vue kit), Blade + Tailwind v4 member area.

**Spec:** `docs/superpowers/specs/2026-07-17-assignments-grading-score-gate-design.md` (sections: Derived rules, Admin panel UI, Member UI, phases 2–3).

## Global Constraints

- Code/comments 100% English; UI copy 100% Indonesian, warm "kamu" register, NO em-dashes in UI copy.
- PHPUnit classes only; `php artisan make:test --phpunit --no-interaction {Name}`; run `vendor/bin/pint --dirty --format agent` before each task's final commit; never delete existing tests.
- This project has NO Person/Enrollment/StatusEvent factories — build them with `Model::create([...])` exactly as existing tests do; Program/Cohort/CohortSession/Assignment/AssignmentSubmission factories DO exist (`AssignmentSubmission::factory()->graded(int)` sets score+graded_at).
- **Mass-assignment guard (final-review mandate):** controllers NEVER pass request arrays straight into `create()`/`update()` for the new models — authorship/grading fields (`created_by`, `updated_by`, `graded_by`, `graded_at`, `score`, `feedback`) are set explicitly server-side; client-sent values for them must be ignored (tests pin this).
- Grading targets the specific submission row by id shown in the panel, never "the latest" blindly.
- Effective score = latest GRADED submission; a newer ungraded retake never erases the standing grade; members may resubmit anytime (including while `menunggu_dinilai`).
- Status chip vocabulary on BOTH sides: `belum_dikerjakan` = neutral/slate, `menunggu_dinilai` = orange, `dinilai` = teal.
- The rounded average shown to users is the SAME value the gate compares (`AssignmentScoring::averageFor`, half-up 1 decimal) — never re-derive the comparison in UI or controller; call `AssignmentScoring`.
- Admin recap/progress surfaces appear ONLY when the program's `min_average_score` is set (and, for qualification, assignments exist — `averageFor` returns null otherwise).
- After Vue/Blade changes: `npm run build` must succeed.
- `php artisan test --compact` with `--filter` for the minimal set while iterating; each task lists its covering files.

---

# Part A — Admin backend (Phase 2 data layer)

### Task 1: Assignment upsert endpoint

**Files:**
- Create: `app/Http/Controllers/Api/Admin/AssignmentController.php`
- Modify: `routes/api.php` (admin group, after the attendance line)
- Test: create `tests/Feature/AssignmentAdminTest.php`

**Interfaces:**
- Produces: `PUT /api/admin/sessions/{session}/assignment` (permission `assignments.manage`), payload `{title: string, body: string}`, response `{assignment: {id, title, body, updated_by, pending_count}}` where `updated_by` is the user's name and `pending_count` is the number of submissions awaiting a grade. One assignment per session (upsert semantics — POST-then-edit both land here). Response shape is reused verbatim by Task 3's session payload.

- [ ] **Step 1: Create the test file**

Run: `php artisan make:test --phpunit --no-interaction AssignmentAdminTest`

```php
<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function mentor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('mentor');

        return $user;
    }

    private function session(): CohortSession
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);

        return CohortSession::factory()->for($cohort)->create();
    }

    public function test_mentor_can_create_and_update_the_assignment(): void
    {
        $session = $this->session();
        $mentor = $this->mentor();

        $this->actingAs($mentor)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", [
                'title' => 'Tugas Riset Produk',
                'body' => 'Cari 3 produk winning dan tulis alasannya.',
            ])
            ->assertOk()
            ->assertJsonPath('assignment.title', 'Tugas Riset Produk')
            ->assertJsonPath('assignment.updated_by', $mentor->name)
            ->assertJsonPath('assignment.pending_count', 0);

        $editor = $this->mentor();
        $this->actingAs($editor)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", [
                'title' => 'Tugas Riset Produk v2',
                'body' => 'Cari 5 produk winning.',
            ])
            ->assertOk()
            ->assertJsonPath('assignment.title', 'Tugas Riset Produk v2');

        $assignment = Assignment::sole();
        $this->assertSame($mentor->id, $assignment->created_by, 'creator must survive edits');
        $this->assertSame($editor->id, $assignment->updated_by);
    }

    public function test_client_cannot_spoof_authorship_fields(): void
    {
        $session = $this->session();
        $mentor = $this->mentor();

        $this->actingAs($mentor)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", [
                'title' => 'Tugas',
                'body' => 'Isi tugas.',
                'created_by' => 999,
                'updated_by' => 999,
            ])
            ->assertOk();

        $this->assertSame($mentor->id, Assignment::sole()->created_by);
        $this->assertSame($mentor->id, Assignment::sole()->updated_by);
    }

    public function test_title_and_body_are_required(): void
    {
        $session = $this->session();

        $this->actingAs($this->mentor())
            ->putJson("/api/admin/sessions/{$session->id}/assignment", ['title' => '', 'body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_participant_cannot_manage_assignments(): void
    {
        $session = $this->session();
        $user = User::factory()->create();
        $user->assignRole('participant');

        $this->actingAs($user)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", ['title' => 'X', 'body' => 'Y'])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact tests/Feature/AssignmentAdminTest.php`
Expected: FAIL — 404s (route missing).

- [ ] **Step 3: Add the route**

In `routes/api.php`, directly below the `PUT /sessions/{session}/attendance` line, add:

```php
Route::put('/sessions/{session}/assignment', [AssignmentController::class, 'upsert'])->middleware('permission:assignments.manage');
```

Add `use App\Http\Controllers\Api\Admin\AssignmentController;` alongside the other Api\Admin imports at the top.

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/Api/Admin/AssignmentController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\CohortSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    /**
     * Create-or-update the session's single assignment (one per kelas by
     * schema). Authorship fields are set server-side only — request input
     * never reaches them (mass-assignment guard).
     */
    public function upsert(Request $request, CohortSession $session): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ], [
            'title.required' => 'Judul tugas wajib diisi.',
            'body.required' => 'Soal tugas wajib diisi.',
        ]);

        $assignment = $session->assignment;

        if ($assignment === null) {
            $assignment = new Assignment;
            $assignment->cohort_session_id = $session->id;
            $assignment->created_by = $request->user()->id;
        }

        $assignment->title = $data['title'];
        $assignment->body = $data['body'];
        $assignment->updated_by = $request->user()->id;
        $assignment->save();

        return response()->json(['assignment' => self::row($assignment->fresh('updater'))]);
    }

    /**
     * Shared assignment shape for admin payloads (also embedded per session
     * by CohortController::show). pending_count = enrollments whose LATEST
     * submission is still ungraded (superseded ungraded rows are history).
     *
     * @return array{id: int, title: string, body: string, updated_by: ?string, pending_count: int}
     */
    public static function row(Assignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'body' => $assignment->body,
            'updated_by' => $assignment->updater?->name,
            'pending_count' => $assignment->submissions()
                ->whereIn('id', function ($q) use ($assignment) {
                    $q->selectRaw('MAX(id)')
                        ->from('assignment_submissions')
                        ->where('assignment_id', $assignment->id)
                        ->groupBy('enrollment_id');
                })
                ->whereNull('score')
                ->count(),
        ];
    }
}
```

- [ ] **Step 5: Run to verify pass**

Run: `php artisan test --compact tests/Feature/AssignmentAdminTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/AssignmentController.php routes/api.php tests/Feature/AssignmentAdminTest.php
git commit -m "feat: mentors write the per-class assignment via upsert endpoint"
```

---

### Task 2: Submission history + grading endpoints

**Files:**
- Create: `app/Http/Controllers/Api/Admin/SubmissionController.php`
- Modify: `routes/api.php`
- Test: create `tests/Feature/SubmissionGradingTest.php`

**Interfaces:**
- Consumes: `AssignmentScoring::effectiveScore(Assignment, Enrollment): ?int`, `submissionState(...): string` (exist).
- Produces:
  - `GET /api/admin/assignments/{assignment}/enrollments/{enrollment}/submissions` (permission `assignments.grade`) → `{submissions: [{id, url, note, score, feedback, graded_by, graded_at, created_at}] , state: string, effective_score: ?int}` — newest first.
  - `PATCH /api/admin/submissions/{submission}/grade` (permission `assignments.grade`), payload `{score: int 0..100, feedback?: string}` → `{submission: {...same row shape}}`. Sets `graded_by`/`graded_at` server-side.

- [ ] **Step 1: Create the test file**

Run: `php artisan make:test --phpunit --no-interaction SubmissionGradingTest`

```php
<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionGradingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function mentor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('mentor');

        return $user;
    }

    /** @return array{Assignment, Enrollment} */
    private function assignmentWithEnrollment(): array
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->for($cohort)->create();
        $assignment = Assignment::factory()->for($session, 'session')->create();
        $person = Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        return [$assignment, $enrollment];
    }

    public function test_mentor_grades_a_specific_submission(): void
    {
        [$assignment, $enrollment] = $this->assignmentWithEnrollment();
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);
        $mentor = $this->mentor();

        $this->actingAs($mentor)
            ->patchJson("/api/admin/submissions/{$submission->id}/grade", [
                'score' => 85,
                'feedback' => 'Riset produknya tajam, lanjutkan.',
            ])
            ->assertOk()
            ->assertJsonPath('submission.score', 85)
            ->assertJsonPath('submission.graded_by', $mentor->name);

        $fresh = $submission->fresh();
        $this->assertSame($mentor->id, $fresh->graded_by);
        $this->assertNotNull($fresh->graded_at);
    }

    public function test_grade_ignores_spoofed_grader_fields(): void
    {
        [$assignment, $enrollment] = $this->assignmentWithEnrollment();
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);
        $mentor = $this->mentor();

        $this->actingAs($mentor)
            ->patchJson("/api/admin/submissions/{$submission->id}/grade", [
                'score' => 70,
                'graded_by' => 999,
                'graded_at' => '2000-01-01 00:00:00',
                'url' => 'https://spoofed.example',
            ])
            ->assertOk();

        $fresh = $submission->fresh();
        $this->assertSame($mentor->id, $fresh->graded_by);
        $this->assertNotSame('https://spoofed.example', $fresh->url);
        $this->assertTrue($fresh->graded_at->isAfter(now()->subMinute()));
    }

    public function test_grading_an_older_row_leaves_the_newer_retake_pending(): void
    {
        [$assignment, $enrollment] = $this->assignmentWithEnrollment();
        $old = AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);

        $this->actingAs($this->mentor())
            ->patchJson("/api/admin/submissions/{$old->id}/grade", ['score' => 60])
            ->assertOk();

        // Latest row is still ungraded -> the pair still reads as waiting.
        $this->actingAs($this->mentor())
            ->getJson("/api/admin/assignments/{$assignment->id}/enrollments/{$enrollment->id}/submissions")
            ->assertOk()
            ->assertJsonPath('state', 'menunggu_dinilai')
            ->assertJsonPath('effective_score', 60)
            ->assertJsonCount(2, 'submissions')
            ->assertJsonPath('submissions.0.score', null);
    }

    public function test_score_must_be_an_integer_between_0_and_100(): void
    {
        [$assignment, $enrollment] = $this->assignmentWithEnrollment();
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($this->mentor())
            ->patchJson("/api/admin/submissions/{$submission->id}/grade", ['score' => 101])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['score']);
    }

    public function test_history_rejects_mismatched_enrollment(): void
    {
        [$assignment] = $this->assignmentWithEnrollment();
        [, $otherEnrollment] = $this->assignmentWithEnrollment(); // different cohort entirely

        $this->actingAs($this->mentor())
            ->getJson("/api/admin/assignments/{$assignment->id}/enrollments/{$otherEnrollment->id}/submissions")
            ->assertNotFound();
    }

    public function test_participant_cannot_grade(): void
    {
        [$assignment, $enrollment] = $this->assignmentWithEnrollment();
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);
        $user = User::factory()->create();
        $user->assignRole('participant');

        $this->actingAs($user)
            ->patchJson("/api/admin/submissions/{$submission->id}/grade", ['score' => 90])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --compact tests/Feature/SubmissionGradingTest.php` → FAIL (routes missing).

- [ ] **Step 3: Routes** — in `routes/api.php`, below the assignment upsert line:

```php
Route::middleware('permission:assignments.grade')->group(function () {
    Route::get('/assignments/{assignment}/enrollments/{enrollment}/submissions', [SubmissionController::class, 'index']);
    Route::patch('/submissions/{submission}/grade', [SubmissionController::class, 'grade']);
});
```

Import `App\Http\Controllers\Api\Admin\SubmissionController`.

- [ ] **Step 4: Controller**

`app/Http/Controllers/Api/Admin/SubmissionController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Support\AssignmentScoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function __construct(private readonly AssignmentScoring $scoring) {}

    /** Grading panel data: one student's full history on one assignment, newest first. */
    public function index(Assignment $assignment, Enrollment $enrollment): JsonResponse
    {
        // The pair must belong together: the enrollment's cohort owns the
        // assignment's session. Mismatch = wrong panel = 404, not a leak.
        abort_unless($enrollment->cohort_id === $assignment->session->cohort_id, 404);

        $submissions = $assignment->submissions()
            ->where('enrollment_id', $enrollment->id)
            ->with('grader:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AssignmentSubmission $s) => $this->row($s));

        return response()->json([
            'submissions' => $submissions,
            'state' => $this->scoring->submissionState($assignment, $enrollment),
            'effective_score' => $this->scoring->effectiveScore($assignment, $enrollment),
        ]);
    }

    /**
     * Grade THIS row (by id) — never "the latest", so a retake landing while
     * the mentor types cannot steal the grade. Grader fields are server-set
     * only (mass-assignment guard).
     */
    public function grade(Request $request, AssignmentSubmission $submission): JsonResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ], [
            'score.required' => 'Nilai wajib diisi.',
            'score.integer' => 'Nilai harus angka bulat 0 sampai 100.',
            'score.min' => 'Nilai paling rendah 0.',
            'score.max' => 'Nilai paling tinggi 100.',
        ]);

        $submission->score = $data['score'];
        $submission->feedback = $data['feedback'] ?? null;
        $submission->graded_by = $request->user()->id;
        $submission->graded_at = now();
        $submission->save();

        return response()->json(['submission' => $this->row($submission->fresh('grader'))]);
    }

    /**
     * @return array{id: int, url: string, note: ?string, score: ?int, feedback: ?string, graded_by: ?string, graded_at: ?string, created_at: ?string}
     */
    private function row(AssignmentSubmission $s): array
    {
        return [
            'id' => $s->id,
            'url' => $s->url,
            'note' => $s->note,
            'score' => $s->score,
            'feedback' => $s->feedback,
            'graded_by' => $s->grader?->name,
            'graded_at' => $s->graded_at?->toIso8601String(),
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 5: Run to verify pass** — `php artisan test --compact tests/Feature/SubmissionGradingTest.php` → PASS (6 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/SubmissionController.php routes/api.php tests/Feature/SubmissionGradingTest.php
git commit -m "feat: grading panel endpoints - history per student, grade by row id"
```

---

### Task 3: Cohort detail payload carries assignments, states, and recap

**Files:**
- Modify: `app/Http/Controllers/Api/Admin/CohortController.php` (`show()` and its `row()` PHPDoc untouched — only `show()` changes)
- Test: extend `tests/Feature/CohortSessionTest.php`

**Interfaces:**
- Consumes: `AssignmentController::row(Assignment): array` (Task 1), `AssignmentScoring::averageFor(Person, Program): ?float`.
- Produces (consumed by Task 5/6 UI): in `GET /api/admin/cohorts/{cohort}`:
  - each session gains `assignment: {id,title,body,updated_by,pending_count} | null`;
  - each roster row gains `assignment_states: {"<assignment_id>": {state: 'belum_dikerjakan'|'menunggu_dinilai'|'dinilai', score: ?int}}`;
  - each roster row gains `average: ?float` and `qualifies: ?bool` (both null when the program has no `min_average_score`);
  - the cohort object gains `min_average_score: ?int` (program's threshold, for the UI to know whether to show recap).

- [ ] **Step 1: Write failing tests** — append to `tests/Feature/CohortSessionTest.php` (file already has admin() helper + seeds; add imports `App\Models\Assignment`, `App\Models\AssignmentSubmission`):

```php
public function test_cohort_detail_carries_assignment_states_and_recap(): void
{
    $program = Program::factory()->active()->create(['min_average_score' => 75]);
    $cohort = Cohort::factory()->create(['program_id' => $program->id]);
    $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
    $assignment = Assignment::factory()->for($session, 'session')->create(['title' => 'Tugas Riset']);
    $person = Person::create([
        'name' => 'Peserta Rekap',
        'phone' => '+628'.fake()->unique()->numerify('##########'),
        'email' => fake()->unique()->safeEmail(),
    ]);
    $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
    AssignmentSubmission::factory()->graded(80)->create([
        'assignment_id' => $assignment->id,
        'enrollment_id' => $enrollment->id,
    ]);

    $this->actingAs($this->admin())
        ->getJson("/api/admin/cohorts/{$cohort->id}")
        ->assertOk()
        ->assertJsonPath('cohort.min_average_score', 75)
        ->assertJsonPath('sessions.0.assignment.title', 'Tugas Riset')
        ->assertJsonPath('sessions.0.assignment.pending_count', 0)
        ->assertJsonPath("roster.0.assignment_states.{$assignment->id}.state", 'dinilai')
        ->assertJsonPath("roster.0.assignment_states.{$assignment->id}.score", 80)
        ->assertJsonPath('roster.0.average', 80.0)
        ->assertJsonPath('roster.0.qualifies', true);
}

public function test_recap_is_null_without_a_threshold_and_assignment_null_without_soal(): void
{
    $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
    CohortSession::factory()->create(['cohort_id' => $cohort->id]);
    $person = Person::create([
        'name' => 'Peserta Polos',
        'phone' => '+628'.fake()->unique()->numerify('##########'),
        'email' => fake()->unique()->safeEmail(),
    ]);
    Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

    $this->actingAs($this->admin())
        ->getJson("/api/admin/cohorts/{$cohort->id}")
        ->assertOk()
        ->assertJsonPath('cohort.min_average_score', null)
        ->assertJsonPath('sessions.0.assignment', null)
        ->assertJsonPath('roster.0.average', null)
        ->assertJsonPath('roster.0.qualifies', null);
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --compact tests/Feature/CohortSessionTest.php` → the 2 new tests FAIL (missing keys), old ones PASS.

- [ ] **Step 3: Enrich `CohortController::show`**

Replace the body of `show()` with (imports to add: `App\Http\Controllers\Api\Admin\AssignmentController` is same namespace — no import needed; add `use App\Support\AssignmentScoring;`):

```php
public function show(Cohort $cohort, AssignmentScoring $scoring): JsonResponse
{
    $cohort->load(['mentor:id,name', 'program:id,name,min_average_score'])->loadCount('enrollments');

    $sessions = $cohort->sessions()->withCount('attendances')->with('assignment.updater:id,name')->get();
    $assignments = $sessions->pluck('assignment')->filter()->values();

    $enrollments = $cohort->enrollments()
        ->with(['person:id,name,phone', 'latestStatusEvent', 'attendances:id,enrollment_id,cohort_session_id'])
        ->get();

    // One submissions pass for the whole roster: latest row + latest graded
    // row per (assignment, enrollment) give state and effective score.
    $submissions = \App\Models\AssignmentSubmission::query()
        ->whereIn('assignment_id', $assignments->pluck('id'))
        ->whereIn('enrollment_id', $enrollments->pluck('id'))
        ->orderBy('id')
        ->get(['id', 'assignment_id', 'enrollment_id', 'score'])
        ->groupBy(fn ($s) => $s->assignment_id.':'.$s->enrollment_id);

    $threshold = $cohort->program?->min_average_score;

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
        'sessions' => $sessions->map(fn ($s) => [
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
        ]),
        'roster' => $roster,
    ]);
}
```

- [ ] **Step 4: Run to verify pass** — `php artisan test --compact tests/Feature/CohortSessionTest.php tests/Feature/AttendanceRecordingTest.php tests/Feature/EnrollmentManagementTest.php` → ALL PASS (existing consumers of the payload unchanged keys still present).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/CohortController.php tests/Feature/CohortSessionTest.php
git commit -m "feat: cohort detail payload carries assignments, states, and score recap"
```

---

### Task 4: Program threshold field (API + form)

**Files:**
- Modify: `app/Http/Controllers/Api/Admin/ProgramController.php` (validation + row)
- Modify: `resources/js/admin/components/ProgramFormDialog.vue`
- Test: extend `tests/Feature/ProgramManagementTest.php`

**Interfaces:**
- Produces: program API payloads round-trip `min_average_score: ?int (0..100)`; the form exposes "Nilai rata-rata minimum".

- [ ] **Step 1: Failing test** — append to `tests/Feature/ProgramManagementTest.php` (follow its existing admin-auth pattern):

```php
public function test_min_average_score_round_trips_and_validates(): void
{
    $program = Program::factory()->active()->create();

    $this->actingAs($this->admin())
        ->patchJson("/api/admin/programs/{$program->id}", ['min_average_score' => 75])
        ->assertOk()
        ->assertJsonPath('program.min_average_score', 75);

    $this->actingAs($this->admin())
        ->patchJson("/api/admin/programs/{$program->id}", ['min_average_score' => 101])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['min_average_score']);

    $this->actingAs($this->admin())
        ->patchJson("/api/admin/programs/{$program->id}", ['min_average_score' => null])
        ->assertOk()
        ->assertJsonPath('program.min_average_score', null);
}
```

(If the file's helper is named differently than `$this->admin()`, use its existing helper verbatim.)

- [ ] **Step 2: Run to verify failure** — `php artisan test --compact tests/Feature/ProgramManagementTest.php` → new test FAILS.

- [ ] **Step 3: API** — in `ProgramController` validation array (next to `locked_message`):

```php
'min_average_score' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
```

(`min:1`, not 0 — a 0 bar is always passed and only confuses; final-review note.) Message: `'min_average_score.integer' => 'Nilai minimum harus angka bulat 1 sampai 100.'` plus min/max variants:

```php
'min_average_score.min' => 'Nilai minimum paling rendah 1.',
'min_average_score.max' => 'Nilai minimum paling tinggi 100.',
```

In `row()`: `'min_average_score' => $p->min_average_score !== null ? (int) $p->min_average_score : null,`.

- [ ] **Step 4: Form field** — in `ProgramFormDialog.vue`: seed `min_average_score: props.program?.min_average_score ?? ''` in the form object (next to `locked_message`); include in the save payload as `min_average_score: form.value.min_average_score === '' ? null : Number(form.value.min_average_score)`; add the field markup after the "Status" block:

```html
<div>
    <label class="text-xs text-muted-foreground">Nilai rata-rata minimum</label>
    <Input v-model="form.min_average_score" type="number" min="1" max="100" placeholder="Contoh: 75" class="mt-1.5" />
    <p class="mt-1 text-xs text-muted-foreground">Kosongkan jika kelulusan tidak diukur dengan nilai.</p>
    <p v-if="formErrors.min_average_score" class="mt-1 text-xs text-destructive">{{ formErrors.min_average_score[0] }}</p>
</div>
```

- [ ] **Step 5: Verify** — `php artisan test --compact tests/Feature/ProgramManagementTest.php` → PASS; `npm run build` → success.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/ProgramController.php resources/js/admin/components/ProgramFormDialog.vue tests/Feature/ProgramManagementTest.php
git commit -m "feat: program form carries the minimum average score threshold"
```

---

# Part B — Admin UI (Phase 2 surface)

### Task 5: Assignment card + editor dialog in the cohort cockpit

**Files:**
- Modify: `resources/js/admin/api.js`
- Create: `resources/js/admin/components/AssignmentFormDialog.vue`
- Modify: `resources/js/admin/views/CohortDetail.vue`

**Interfaces:**
- Consumes: session payload `assignment: {id,title,body,updated_by,pending_count}|null` (Task 3); `PUT /admin/sessions/{id}/assignment` (Task 1).
- Produces: `assignments.upsert(sessionId, payload)` and `submissions.history(assignmentId, enrollmentId)` / `submissions.grade(id, payload)` in api.js (grading calls used by Task 6); `<AssignmentFormDialog v-model:open :session @saved>`.

- [ ] **Step 1: api.js** — add after the `sessions` export:

```js
export const assignments = {
    upsert(sessionId, payload) {
        return api(`/admin/sessions/${sessionId}/assignment`, { method: 'PUT', body: payload });
    },
};

export const submissions = {
    history(assignmentId, enrollmentId) {
        return api(`/admin/assignments/${assignmentId}/enrollments/${enrollmentId}/submissions`);
    },
    grade(id, payload) {
        return api(`/admin/submissions/${id}/grade`, { method: 'PATCH', body: payload });
    },
};
```

- [ ] **Step 2: AssignmentFormDialog.vue** (create — mirrors `SessionFormDialog`'s conventions):

```vue
<script setup>
import { ref, computed, watch } from 'vue';
import { assignments as assignmentsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

const props = defineProps({
    /** Session row (API shape) whose assignment is being written/edited. */
    session: { type: Object, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['saved']);

const isEditing = computed(() => props.session?.assignment != null);

const form = ref({});
const formErrors = ref({});
const saving = ref(false);

// Every open re-seeds the form so a reopened dialog never shows stale values.
watch(open, (isOpen) => {
    if (!isOpen) return;
    form.value = {
        title: props.session?.assignment?.title ?? '',
        body: props.session?.assignment?.body ?? '',
    };
    formErrors.value = {};
});

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const res = await assignmentsApi.upsert(props.session.id, { title: form.value.title, body: form.value.body });
        open.value = false;
        emit('saved', res.assignment);
    } catch (e) {
        if (e.sessionExpired) return;
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) formErrors.value = { title: [e.message ?? 'Gagal menyimpan.'] };
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" :title="isEditing ? 'Ubah Tugas' : 'Tulis Tugas'">
        <form class="space-y-3" @submit.prevent="save">
            <div>
                <label class="text-xs text-muted-foreground">Judul tugas</label>
                <Input v-model="form.title" placeholder="Contoh: Riset 3 produk winning" class="mt-1.5" />
                <p v-if="formErrors.title" class="mt-1 text-xs text-destructive">{{ formErrors.title[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Soal / instruksi untuk peserta</label>
                <Textarea v-model="form.body" rows="6" placeholder="Tulis instruksi tugasnya di sini." class="mt-1.5" />
                <p v-if="formErrors.body" class="mt-1 text-xs text-destructive">{{ formErrors.body[0] }}</p>
            </div>
            <p v-if="isEditing && session?.assignment?.updated_by" class="text-xs text-muted-foreground">
                Terakhir diubah oleh {{ session.assignment.updated_by }}.
            </p>
            <div class="mt-4 flex justify-end gap-2 border-t border-border pt-4">
                <Button type="button" variant="outline" size="sm" @click="open = false">Batal</Button>
                <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
            </div>
        </form>
    </Dialog>
</template>
```

- [ ] **Step 3: Mount in CohortDetail.vue**

Script additions (near the other dialog state, ~after `deleteError`):

```js
// Tugas kelas terpilih (spec 1 phase 2): satu tugas per kelas.
const assignmentFormOpen = ref(false);

function openAssignmentForm() {
    assignmentFormOpen.value = true;
}

async function onAssignmentSaved(assignment) {
    if (selectedSession.value) selectedSession.value.assignment = assignment;
}
```

Import: `import AssignmentFormDialog from '@/components/AssignmentFormDialog.vue';` and add `ClipboardList` to the lucide import list.

Template: insert a "Tugas" card between the class list and the roster card (anchor: right BEFORE the `<!-- Daftar hadir ... -->` comment):

```html
<!-- Tugas kelas terpilih: soal ditulis mentor, dinilai dari roster. -->
<div v-if="selectedSession" class="mt-6 rounded-xl border border-border bg-card px-4 py-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-100 via-sand-50 to-orange-200/70 ring-1 ring-inset ring-teal-900/10">
                <ClipboardList class="size-5 text-teal-700" />
            </span>
            <div class="min-w-0">
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Tugas · {{ selectedSession.title }}</p>
                <template v-if="selectedSession.assignment">
                    <p class="mt-0.5 font-semibold text-foreground">{{ selectedSession.assignment.title }}</p>
                    <p class="mt-1 line-clamp-2 whitespace-pre-line text-sm text-muted-foreground">{{ selectedSession.assignment.body }}</p>
                </template>
                <p v-else class="mt-0.5 text-sm text-muted-foreground/70 italic">Belum ada tugas untuk kelas ini.</p>
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <Badge v-if="selectedSession.assignment?.pending_count" variant="secondary" class="bg-orange-100 text-orange-700">
                {{ selectedSession.assignment.pending_count }} menunggu dinilai
            </Badge>
            <Button v-if="auth.can('assignments.manage')" variant="outline" size="sm" @click="openAssignmentForm">
                <Pencil v-if="selectedSession.assignment" class="mr-1 h-3.5 w-3.5" />
                <Plus v-else class="mr-1 h-3.5 w-3.5" />
                {{ selectedSession.assignment ? 'Ubah tugas' : 'Tulis tugas' }}
            </Button>
        </div>
    </div>
</div>
```

Dialog at the bottom (next to `SessionFormDialog`):

```html
<AssignmentFormDialog v-model:open="assignmentFormOpen" :session="selectedSession" @saved="onAssignmentSaved" />
```

- [ ] **Step 4: Build** — `npm run build` → success.

- [ ] **Step 5: Commit**

```bash
git add resources/js/admin/api.js resources/js/admin/components/AssignmentFormDialog.vue resources/js/admin/views/CohortDetail.vue
git commit -m "feat: mentors write the class assignment from the cohort cockpit"
```

---

### Task 6: Grading dialog, Nilai column, and recap column

**Files:**
- Create: `resources/js/admin/components/GradingDialog.vue`
- Modify: `resources/js/admin/views/CohortDetail.vue`

**Interfaces:**
- Consumes: `submissions.history/grade` (Task 5 api.js), roster `assignment_states` + `average`/`qualifies` + cohort `min_average_score` (Task 3), `selectedSession.assignment` (Task 5).
- Produces: click a roster row's Nilai cell → GradingDialog; after grading it emits `graded` and the parent refreshes via `load()`.

- [ ] **Step 1: GradingDialog.vue** (create):

```vue
<script setup>
import { ref, watch } from 'vue';
import { ExternalLink } from 'lucide-vue-next';
import { submissions as submissionsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { fmtDateTime } from '@/lib/format';

const props = defineProps({
    /** {assignment, enrollmentId, personName} — null closes the dialog. */
    target: { type: Object, default: null },
});

const emit = defineEmits(['close', 'graded']);

const loading = ref(false);
const error = ref('');
const history = ref([]);
const state = ref('belum_dikerjakan');

// Grade form always aims at the NEWEST row shown (by id, race-safe).
const score = ref('');
const feedback = ref('');
const saving = ref(false);
const formErrors = ref({});

watch(() => props.target, async (target) => {
    if (!target) return;
    loading.value = true;
    error.value = '';
    history.value = [];
    score.value = '';
    feedback.value = '';
    formErrors.value = {};
    try {
        const res = await submissionsApi.history(target.assignment.id, target.enrollmentId);
        history.value = res.submissions;
        state.value = res.state;
        const latest = res.submissions[0];
        if (latest?.score !== null && latest !== undefined) {
            score.value = latest?.score ?? '';
            feedback.value = latest?.feedback ?? '';
        }
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal memuat riwayat.';
    } finally {
        loading.value = false;
    }
});

async function saveGrade() {
    const latest = history.value[0];
    if (!latest) return;
    saving.value = true;
    formErrors.value = {};
    try {
        await submissionsApi.grade(latest.id, {
            score: score.value === '' ? null : Number(score.value),
            feedback: feedback.value || null,
        });
        emit('graded');
        emit('close');
    } catch (e) {
        if (e.sessionExpired) return;
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) error.value = e.message ?? 'Gagal menyimpan nilai.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog :open="target !== null" :title="`Nilai Tugas · ${target?.personName ?? ''}`" @update:open="emit('close')">
        <Alert v-if="error" class="mb-3 px-3.5 py-2.5">{{ error }}</Alert>
        <div v-if="loading" class="py-8 text-center text-sm text-muted-foreground">Memuat…</div>

        <template v-else>
            <p v-if="!history.length" class="py-6 text-center text-sm text-muted-foreground">
                Peserta ini belum mengirim jawaban.
            </p>

            <template v-else>
                <!-- History, newest first; older versions compact. -->
                <ul class="max-h-56 space-y-2 overflow-y-auto">
                    <li v-for="(s, i) in history" :key="s.id" class="rounded-lg border border-border px-3 py-2 text-sm" :class="i === 0 ? '' : 'opacity-70'">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <a :href="s.url" target="_blank" rel="noopener" class="inline-flex min-w-0 items-center gap-1 font-medium text-teal-700 hover:underline">
                                <ExternalLink class="size-3.5 shrink-0" /><span class="truncate">{{ s.url }}</span>
                            </a>
                            <Badge v-if="s.score !== null" variant="secondary">{{ s.score }}</Badge>
                            <Badge v-else-if="i === 0" variant="secondary" class="bg-orange-100 text-orange-700">menunggu dinilai</Badge>
                        </div>
                        <p v-if="s.note" class="mt-1 text-xs text-muted-foreground">"{{ s.note }}"</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">
                            Kiriman {{ history.length - i }} · {{ fmtDateTime(s.created_at) }}
                            <template v-if="s.graded_by"> · dinilai {{ s.graded_by }}</template>
                        </p>
                    </li>
                </ul>

                <!-- Grade form targets history[0] (the newest row) by id. -->
                <form class="mt-4 space-y-3 border-t border-border pt-4" @submit.prevent="saveGrade">
                    <div class="flex gap-3">
                        <div class="w-28">
                            <label class="text-xs text-muted-foreground">Nilai (0-100)</label>
                            <Input v-model="score" type="number" min="0" max="100" class="mt-1.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <label class="text-xs text-muted-foreground">Feedback untuk peserta (opsional)</label>
                            <Textarea v-model="feedback" rows="2" class="mt-1.5" placeholder="Tulis masukanmu di sini." />
                        </div>
                    </div>
                    <p v-if="formErrors.score" class="text-xs text-destructive">{{ formErrors.score[0] }}</p>
                    <p v-if="formErrors.feedback" class="text-xs text-destructive">{{ formErrors.feedback[0] }}</p>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" @click="emit('close')">Batal</Button>
                        <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan nilai' }}</Button>
                    </div>
                </form>
            </template>
        </template>
    </Dialog>
</template>
```

- [ ] **Step 2: Wire into CohortDetail.vue**

Script (after the assignment-dialog state):

```js
// Panel penilaian: sel Nilai di roster membuka riwayat + form nilai.
const gradingTarget = ref(null);

function openGrading(row) {
    if (!selectedSession.value?.assignment || !auth.can('assignments.grade')) return;
    gradingTarget.value = {
        assignment: selectedSession.value.assignment,
        enrollmentId: row.enrollment_id,
        personName: row.person.name,
    };
}

function assignmentStateFor(row) {
    const id = selectedSession.value?.assignment?.id;
    return id ? (row.assignment_states?.[id] ?? { state: 'belum_dikerjakan', score: null }) : null;
}

const STATE_LABELS = { belum_dikerjakan: 'Belum', menunggu_dinilai: 'Menunggu', dinilai: 'Dinilai' };
```

Import `GradingDialog from '@/components/GradingDialog.vue';`.

Template — roster header: after the `<th ...>Kehadiran</th>` line add:

```html
<th v-if="selectedSession?.assignment" class="px-2 py-3 text-center font-semibold sm:px-3">Nilai</th>
<th v-if="cohort.min_average_score !== null" class="hidden px-3 py-3 font-semibold sm:table-cell">Rata-rata</th>
```

Roster row: after the Kehadiran `<td>` add (chip colors follow the shared vocabulary — slate/orange/teal):

```html
<td v-if="selectedSession?.assignment" class="px-2 py-3 text-center sm:px-3">
    <button
        type="button"
        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold transition"
        :class="{
            'border-border text-muted-foreground hover:border-teal-600/50': assignmentStateFor(row)?.state === 'belum_dikerjakan',
            'border-orange-300 bg-orange-100 text-orange-700 hover:border-orange-400': assignmentStateFor(row)?.state === 'menunggu_dinilai',
            'border-teal-300 bg-teal-100 text-teal-700 hover:border-teal-500': assignmentStateFor(row)?.state === 'dinilai',
        }"
        :disabled="!auth.can('assignments.grade')"
        @click="openGrading(row)"
    >
        {{ assignmentStateFor(row)?.state === 'dinilai' ? assignmentStateFor(row)?.score : STATE_LABELS[assignmentStateFor(row)?.state] }}
    </button>
</td>
<td v-if="cohort.min_average_score !== null" class="hidden px-3 py-3 sm:table-cell">
    <template v-if="row.average !== null">
        <span class="font-semibold text-foreground">{{ row.average }}</span>
        <Badge :variant="row.qualifies ? 'success' : 'secondary'" class="ml-1.5">
            {{ row.qualifies ? 'Memenuhi syarat' : 'Belum memenuhi' }}
        </Badge>
    </template>
    <span v-else class="text-xs text-muted-foreground/50">—</span>
</td>
```

Empty-roster `colspan`: the existing "Belum ada peserta." cell uses `colspan="4"` — make it dynamic: `:colspan="4 + (selectedSession?.assignment ? 1 : 0) + (cohort.min_average_score !== null ? 1 : 0)"`.

Dialog at the bottom:

```html
<GradingDialog :target="gradingTarget" @close="gradingTarget = null" @graded="load" />
```

- [ ] **Step 3: Build** — `npm run build` → success.

- [ ] **Step 4: Commit**

```bash
git add resources/js/admin/components/GradingDialog.vue resources/js/admin/views/CohortDetail.vue
git commit -m "feat: grading panel and score recap live in the roster"
```

---

# Part C — Member (Phase 3)

### Task 7: Member submit endpoint

**Files:**
- Create: `app/Http/Controllers/MemberAssignmentSubmissionController.php`
- Modify: `routes/web.php`
- Test: create `tests/Feature/MemberSubmissionTest.php`

**Interfaces:**
- Produces: `POST /akun/tugas/{assignment}` (name `member.assignment.submit`, middleware `auth` + `throttle:10,1`), fields `{url: string, note?: string}`. Creates an append-only submission for the member's own active enrollment; redirects back with flash `tugas_terkirim = assignment id`.

- [ ] **Step 1: Test file**

Run: `php artisan make:test --phpunit --no-interaction MemberSubmissionTest`

```php
<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\StatusEvent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /** @return array{0: User, 1: Person} */
    private function member(): array
    {
        $user = User::factory()->create();
        $user->assignRole('participant');
        $person = Person::create([
            'name' => 'Member '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $person->user()->associate($user);
        $person->save();

        return [$user, $person];
    }

    private function assignment(): Assignment
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->for($cohort)->create();

        return Assignment::factory()->for($session, 'session')->create();
    }

    public function test_enrolled_member_submits_and_resubmits(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);

        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), [
                'url' => 'https://drive.google.com/jawaban-1',
                'note' => 'Versi pertama.',
            ])
            ->assertRedirect()
            ->assertSessionHas('tugas_terkirim', $assignment->id);

        // Resubmission while still ungraded is allowed: append, never update.
        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), ['url' => 'https://drive.google.com/jawaban-2'])
            ->assertRedirect();

        $this->assertSame(2, AssignmentSubmission::count());
        $this->assertSame('https://drive.google.com/jawaban-2', AssignmentSubmission::latest('id')->first()->url);
    }

    public function test_client_cannot_smuggle_grade_fields_into_a_submission(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);

        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), [
                'url' => 'https://drive.google.com/jawaban',
                'score' => 100,
                'graded_by' => 1,
            ])
            ->assertRedirect();

        $submission = AssignmentSubmission::sole();
        $this->assertNull($submission->score);
        $this->assertNull($submission->graded_by);
    }

    public function test_member_without_enrollment_gets_404(): void
    {
        [$user] = $this->member();
        $assignment = $this->assignment();

        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), ['url' => 'https://x.example/a'])
            ->assertNotFound();
    }

    public function test_dropped_enrollment_cannot_submit(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'dropped', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), ['url' => 'https://x.example/a'])
            ->assertNotFound();
    }

    public function test_url_must_be_a_valid_https_link(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);

        $this->actingAs($user)
            ->from('/akun?bagian=kelas')
            ->post(route('member.assignment.submit', $assignment), ['url' => 'bukan-link'])
            ->assertRedirect('/akun?bagian=kelas')
            ->assertSessionHasErrors('url');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $assignment = $this->assignment();

        // If the app's guest redirect targets a different named route, mirror
        // whatever assertion MemberAreaTest uses for guests hitting /akun.
        $this->post(route('member.assignment.submit', $assignment), ['url' => 'https://x.example/a'])
            ->assertRedirect(route('member.login'));
    }
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --compact tests/Feature/MemberSubmissionTest.php` → FAIL (route missing).

- [ ] **Step 3: Route** — in `routes/web.php`, below the `/akun` line:

```php
Route::post('/akun/tugas/{assignment}', [MemberAssignmentSubmissionController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('member.assignment.submit');
```

Import the controller at the top.

- [ ] **Step 4: Controller**

`app/Http/Controllers/MemberAssignmentSubmissionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberAssignmentSubmissionController extends Controller
{
    /**
     * A member sends (or re-sends) their answer link. Append-only: a retake
     * is a NEW row; the previous grade stands until the mentor grades the
     * new version. Only url/note ever come from the request — score and
     * grader fields are the mentor's alone (mass-assignment guard).
     */
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $person = $request->user()->person;

        $enrollment = $person?->enrollments()
            ->where('cohort_id', $assignment->session->cohort_id)
            ->with('latestStatusEvent')
            ->first();

        abort_unless($enrollment !== null && $enrollment->isActive(), 404);

        $data = $request->validate([
            'url' => ['required', 'url:https', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'url.required' => 'Link jawaban wajib diisi.',
            'url.url' => 'Formatnya harus link, contoh: https://drive.google.com/…',
            'url.max' => 'Link terlalu panjang (maksimal 500 karakter).',
        ]);

        $submission = new AssignmentSubmission;
        $submission->assignment_id = $assignment->id;
        $submission->enrollment_id = $enrollment->id;
        $submission->url = $data['url'];
        $submission->note = $data['note'] ?? null;
        $submission->save();

        return back()->with('tugas_terkirim', $assignment->id);
    }
}
```

- [ ] **Step 5: Run to verify pass** — `php artisan test --compact tests/Feature/MemberSubmissionTest.php` → PASS (6 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/MemberAssignmentSubmissionController.php routes/web.php tests/Feature/MemberSubmissionTest.php
git commit -m "feat: members submit answer links, append-only with ownership guard"
```

---

### Task 8: Member view-models — assignment cards + program progress

**Files:**
- Modify: `app/Http/Controllers/MemberAreaController.php`
- Test: extend `tests/Feature/MemberAreaTest.php`

**Interfaces:**
- Consumes: `AssignmentScoring` (all four methods), `cohort.sessions.assignment` relation.
- Produces (consumed by Task 9's Blade — all presentation logic computed HERE, per house preference, not in Blade):
  - each enrolled class's session gains, via a lookup array passed to the view: `$assignmentCards[session_id] = ['assignment' => Assignment, 'state' => string, 'score' => ?int, 'feedback' => ?string, 'latest_url' => ?string, 'latest_at' => ?Carbon, 'versions' => int]` (only for sessions that HAVE an assignment and the member has an active enrollment);
  - `$programProgress` = list of `['program' => Program, 'average' => ?float, 'threshold' => int, 'qualifies' => bool, 'rows' => [['title' => string, 'state' => string, 'score' => ?int], ...]]` — one entry per enrolled program WITH a threshold; `rows` span ALL the person's active enrollments of that program (multi-cohort legacy shape included).

- [ ] **Step 1: Failing tests** — append to `tests/Feature/MemberAreaTest.php` (imports to add: `App\Models\Assignment`, `App\Models\AssignmentSubmission`):

```php
public function test_member_sees_assignment_with_score_and_feedback(): void
{
    [$user, $person] = $this->member();
    $program = Program::factory()->active()->create(['min_average_score' => 75]);
    $cohort = Cohort::factory()->create(['program_id' => $program->id]);
    $session = CohortSession::factory()->for($cohort)->create(['title' => 'Kelas 1: Riset']);
    $assignment = Assignment::factory()->for($session, 'session')->create([
        'title' => 'Riset 3 Produk',
        'body' => 'Cari 3 produk winning.',
    ]);
    $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
    StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);
    AssignmentSubmission::factory()->graded(82)->create([
        'assignment_id' => $assignment->id,
        'enrollment_id' => $enrollment->id,
        'feedback' => 'Bagus, riset kamu tajam.',
    ]);

    $this->actingAs($user)->get('/akun?bagian=kelas')
        ->assertOk()
        ->assertSee('Riset 3 Produk')
        ->assertSee('Cari 3 produk winning.')
        ->assertSee('Bagus, riset kamu tajam.')
        ->assertSee('82')
        ->assertSee('Kirim ulang untuk perbaiki nilai')
        ->assertSee('Rata-ratamu')
        ->assertSee('Kamu memenuhi syarat! Lanjut ke kelas komunitas');
}

public function test_member_sees_submit_form_when_not_yet_submitted(): void
{
    [$user, $person] = $this->member();
    $program = Program::factory()->active()->create();
    $cohort = Cohort::factory()->create(['program_id' => $program->id]);
    $session = CohortSession::factory()->for($cohort)->create();
    Assignment::factory()->for($session, 'session')->create(['title' => 'Tugas Konten']);
    $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
    StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

    $this->actingAs($user)->get('/akun?bagian=kelas')
        ->assertOk()
        ->assertSee('Tugas Konten')
        ->assertSee('Kirim jawaban')
        // No threshold on this program -> no progress card.
        ->assertDontSee('Rata-ratamu');
}

public function test_waiting_state_shows_confirmation_and_fix_link(): void
{
    [$user, $person] = $this->member();
    $program = Program::factory()->active()->create(['min_average_score' => 75]);
    $cohort = Cohort::factory()->create(['program_id' => $program->id]);
    $session = CohortSession::factory()->for($cohort)->create();
    $assignment = Assignment::factory()->for($session, 'session')->create();
    $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
    StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);
    AssignmentSubmission::factory()->create([
        'assignment_id' => $assignment->id,
        'enrollment_id' => $enrollment->id,
    ]);

    $this->actingAs($user)->get('/akun?bagian=kelas')
        ->assertOk()
        ->assertSee('Jawabanmu sudah terkirim, menunggu dinilai mentor.')
        ->assertSee('Perbaiki kiriman')
        ->assertSee('Capai rata-rata 75 untuk membuka kelas komunitas');
}
```

- [ ] **Step 2: Run to verify failure** — `php artisan test --compact tests/Feature/MemberAreaTest.php` → 3 new tests FAIL, existing 20 PASS.

- [ ] **Step 3: Controller view-models**

In `MemberAreaController::index()`:

1. Change the enrolled-classes eager load to include assignments:

```php
->with(['cohort.program', 'cohort.mentor:id,name', 'cohort.sessions.assignment', 'latestStatusEvent'])
```

2. After `$enrolledClasses` is built, add (inject `AssignmentScoring $scoring` into `index()`'s signature — container resolves it):

```php
// Assignment cards, keyed by session id: everything the Blade shows is
// computed here (house rule: view logic lives in the controller).
$assignmentCards = [];
foreach ($enrolledClasses as $enrollment) {
    foreach ($enrollment->cohort->sessions as $session) {
        if ($session->assignment === null) {
            continue;
        }
        $assignment = $session->assignment;
        $rows = $assignment->submissions()
            ->where('enrollment_id', $enrollment->id)
            ->orderByDesc('id')
            ->get();
        $latest = $rows->first();
        $lastGraded = $rows->whereNotNull('score')->first();

        $assignmentCards[$session->id] = [
            'assignment' => $assignment,
            'state' => $scoring->submissionState($assignment, $enrollment),
            'score' => $lastGraded?->score,
            'feedback' => $lastGraded?->feedback,
            'latest_url' => $latest?->url,
            'latest_at' => $latest?->created_at,
            'versions' => $rows->count(),
            // Compact own history for the card (spec: versions with time +
            // grade if any), oldest last.
            'history' => $rows->map(fn ($s) => [
                'url' => $s->url,
                'at' => $s->created_at,
                'score' => $s->score,
            ])->values()->all(),
        ];
    }
}

// Progress per enrolled program that carries a threshold: the average is
// per person per program (AssignmentScoring), same rounded value the gate
// compares. rows = every assignment across the person's active
// enrollments of that program.
$programProgress = $enrolledClasses
    ->groupBy(fn ($e) => $e->cohort->program_id)
    ->map(function ($group) use ($person, $scoring) {
        $program = $group->first()->cohort->program;
        if ($program->min_average_score === null) {
            return null;
        }
        $rows = [];
        foreach ($group as $enrollment) {
            foreach ($enrollment->cohort->sessions as $session) {
                if ($session->assignment === null) {
                    continue;
                }
                $rows[] = [
                    'title' => $session->title,
                    'state' => $scoring->submissionState($session->assignment, $enrollment),
                    'score' => $scoring->effectiveScore($session->assignment, $enrollment),
                ];
            }
        }
        $average = $scoring->averageFor($person, $program);

        return [
            'program' => $program,
            'average' => $average,
            'threshold' => (int) $program->min_average_score,
            'qualifies' => $average !== null && $average >= $program->min_average_score,
            'rows' => $rows,
        ];
    })
    ->filter()
    ->values();
```

3. Pass both to the view: add `'assignmentCards' => $assignmentCards, 'programProgress' => $programProgress,` to the `view('member.akun', [...])` array.

- [ ] **Step 4: Tests still fail** (Blade doesn't render yet) — that is EXPECTED. Do NOT force them green here; Task 9 finishes the cycle. Run only the pre-existing tests to prove no regression: `php artisan test --compact tests/Feature/MemberAreaTest.php --filter=test_enrolled_member_sees_each_class_of_the_batch` → PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/MemberAreaController.php tests/Feature/MemberAreaTest.php
git commit -m "feat: member area computes assignment cards and program progress"
```

---

### Task 9: Member UI — assignment cards + progress card

**Files:**
- Modify: `resources/views/member/akun.blade.php`

**Interfaces:**
- Consumes: `$assignmentCards[session_id]` and `$programProgress` (Task 8 shapes), route `member.assignment.submit` (Task 7), `.kh-collapsible` CSS + pill affordance pattern (exists), flash `tugas_terkirim` + `$errors` for `url`/`note`.

- [ ] **Step 1: Assignment card inside each class sub-card**

In the `@foreach ($cohort->sessions as $session)` loop, AFTER the venue block (after the `@endif` that closes the online/offline branch, still inside the class sub-card `div`), insert:

```blade
@if (isset($assignmentCards[$session->id]))
    @php($tugas = $assignmentCards[$session->id])
    <details class="kh-collapsible group mt-3 overflow-hidden rounded-2xl border border-teal-900/10 bg-white" {{ session('tugas_terkirim') === $tugas['assignment']->id || $errors->has('url') ? 'open' : '' }}>
        <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 transition hover:bg-sand-50 [&::-webkit-details-marker]:hidden">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-100 via-sand-50 to-orange-200/70 ring-1 ring-inset ring-teal-900/10">
                <svg class="h-5 w-5 text-orange-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5h6m-7 3h8a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Zm1-3h4a1 1 0 0 1 1 1v2H9V3a1 1 0 0 1 1-1Z" stroke-linecap="round" stroke-linejoin="round"/><path d="m10 13 1.5 1.5L15 11" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-teal-900">Tugas: {{ $tugas['assignment']->title }}</span>
                <span @class([
                    'block text-xs font-semibold',
                    'text-teal-800/60' => $tugas['state'] === 'belum_dikerjakan',
                    'text-orange-700' => $tugas['state'] === 'menunggu_dinilai',
                    'text-teal-700' => $tugas['state'] === 'dinilai',
                ])>
                    @if ($tugas['state'] === 'belum_dikerjakan') Belum dikerjakan
                    @elseif ($tugas['state'] === 'menunggu_dinilai') Menunggu dinilai
                    @else Nilai kamu: {{ $tugas['score'] }}
                    @endif
                </span>
            </span>
            <span class="flex shrink-0 items-center gap-1.5 rounded-full border border-teal-900/15 px-3 py-1.5 text-xs font-semibold text-teal-700 transition group-hover:border-teal-600/40">
                <span class="group-open:hidden">Lihat tugas</span>
                <span class="hidden group-open:inline">Tutup tugas</span>
                <svg class="h-3.5 w-3.5 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 8 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
        </summary>
        <div class="border-t border-teal-900/10 px-4 py-4 text-sm text-teal-800/80">
            <p class="whitespace-pre-line">{{ $tugas['assignment']->body }}</p>

            @if (session('tugas_terkirim') === $tugas['assignment']->id)
                <p class="mt-3 rounded-xl border border-teal-600/30 bg-teal-50 px-4 py-3 text-teal-800">Jawabanmu terkirim. Mentor akan menilainya segera.</p>
            @endif

            @if ($tugas['state'] === 'dinilai')
                <div class="mt-4 rounded-2xl bg-sand-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-800/60">Nilai kamu</p>
                    <p class="mt-1 text-3xl font-bold text-teal-900">{{ $tugas['score'] }}</p>
                    @if ($tugas['feedback'])
                        <blockquote class="mt-2 border-l-2 border-orange-400 pl-3 text-sm italic text-teal-800/80">{{ $tugas['feedback'] }}</blockquote>
                    @endif
                </div>
            @elseif ($tugas['state'] === 'menunggu_dinilai')
                <p class="mt-3 font-medium text-orange-700">Jawabanmu sudah terkirim, menunggu dinilai mentor.</p>
                <p class="mt-1 truncate text-xs">Link terkirim: <a href="{{ $tugas['latest_url'] }}" target="_blank" rel="noopener" class="font-semibold text-teal-700 hover:underline">{{ $tugas['latest_url'] }}</a></p>
            @endif

            @php($formLabel = match ($tugas['state']) {
                'belum_dikerjakan' => 'Kirim jawaban',
                'menunggu_dinilai' => 'Perbaiki kiriman',
                default => 'Kirim ulang untuk perbaiki nilai',
            })
            <form method="POST" action="{{ route('member.assignment.submit', $tugas['assignment']) }}" data-submit-once class="mt-4 space-y-2">
                @csrf
                <label class="block text-xs font-semibold uppercase tracking-wide text-teal-800/60">{{ $formLabel }}</label>
                <input type="url" name="url" value="{{ old('url') }}" placeholder="https://drive.google.com/…" inputmode="url"
                       class="w-full rounded-lg border border-teal-900/15 bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                @error('url') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <input type="text" name="note" value="{{ old('note') }}" placeholder="Catatan untuk mentor (opsional)"
                       class="w-full rounded-lg border border-teal-900/15 bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-teal-700 px-5 py-2 text-sm font-semibold text-white transition hover:bg-teal-900">{{ $formLabel }}</button>
                @if ($tugas['versions'] > 1)
                    <p class="text-xs text-teal-800/50">Kiriman ke-{{ $tugas['versions'] }}. Nilai lama tetap berlaku sampai versi baru dinilai.</p>
                @endif
            </form>

            {{-- Compact own history (spec): every version with time + grade. --}}
            @if ($tugas['versions'] > 1)
                <ul class="mt-3 space-y-1 border-t border-teal-900/10 pt-3 text-xs text-teal-800/60">
                    @foreach ($tugas['history'] as $i => $item)
                        <li class="flex items-center justify-between gap-3">
                            <span class="min-w-0 truncate">Kiriman {{ $tugas['versions'] - $i }} · {{ $item['at']->locale('id')->translatedFormat('j M Y H.i') }}</span>
                            <span class="shrink-0 font-semibold {{ $item['score'] !== null ? 'text-teal-700' : 'text-teal-800/40' }}">{{ $item['score'] ?? 'belum dinilai' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </details>
@endif
```

- [ ] **Step 2: Progress card per program**

Right AFTER the `@foreach ($enrolledClasses as $enrollment) ... @endforeach` block closes (still inside the "Kelas Saya" section, after its wrapping `div`), insert:

```blade
@foreach ($programProgress as $progress)
    <div class="mt-4 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
        <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Progres Nilai</p>
        <p class="mt-2 text-lg font-bold text-teal-900">
            @if ($progress['average'] !== null)
                Rata-ratamu {{ rtrim(rtrim(number_format($progress['average'], 1, ',', '.'), '0'), ',') }} dari minimum {{ $progress['threshold'] }}
            @else
                Belum ada nilai. Minimum kelulusan: {{ $progress['threshold'] }}
            @endif
        </p>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-sand-100">
            <div class="h-full rounded-full {{ $progress['qualifies'] ? 'bg-teal-600' : 'bg-orange-400' }}"
                 style="width: {{ $progress['average'] !== null ? min(100, round($progress['average'] / max(1, $progress['threshold']) * 100)) : 0 }}%"></div>
        </div>
        <ul class="mt-4 space-y-1.5 text-sm">
            @foreach ($progress['rows'] as $row)
                <li class="flex items-center justify-between gap-3">
                    <span class="min-w-0 truncate text-teal-800/80">{{ $row['title'] }}</span>
                    <span @class([
                        'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        'bg-sand-100 text-teal-800/60' => $row['state'] === 'belum_dikerjakan',
                        'bg-orange-100 text-orange-700' => $row['state'] === 'menunggu_dinilai',
                        'bg-teal-100 text-teal-700' => $row['state'] === 'dinilai',
                    ])>{{ $row['state'] === 'dinilai' ? $row['score'] : ($row['state'] === 'menunggu_dinilai' ? 'Menunggu' : 'Belum') }}</span>
                </li>
            @endforeach
        </ul>
        @if ($progress['qualifies'])
            <a href="{{ route('daftar') }}" class="mt-4 inline-flex items-center gap-2 rounded-full bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600">
                Kamu memenuhi syarat! Lanjut ke kelas komunitas
            </a>
        @else
            <p class="mt-3 text-sm text-teal-800/70">Capai rata-rata {{ $progress['threshold'] }} untuk membuka kelas komunitas.</p>
        @endif
    </div>
@endforeach
```

- [ ] **Step 3: Verify** — `php artisan test --compact tests/Feature/MemberAreaTest.php` → ALL PASS (including Task 8's 3). `npm run build` → success.

- [ ] **Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/member/akun.blade.php
git commit -m "feat: members read the soal, submit answers, and track their average"
```

---

### Task 10: Closure — full suite, build, ledger

- [ ] **Step 1:** `vendor/bin/pint --dirty --format agent` → passed.
- [ ] **Step 2:** `php artisan test --compact` → ALL PASS (expect ~325+ tests).
- [ ] **Step 3:** `npm run build` → success.
- [ ] **Step 4:** Commit anything pint touched:

```bash
git add -u && git commit -m "chore: green suite after assignments and grading UI (spec 1 phase 2+3)" --allow-empty
```

---

## Out of scope for this plan (later)

- Spec 2 Phase B (session confirmations / RSVP) and Phase C (public class list + chooser meta + offline benefits copy).
- Spec 1 Phase 4 (program rename sweep + full design-review pass — the review skill run covers these new surfaces then).
- Notifications on new assignments/grades; deadlines; multiple assignments per session.
- Deploy (separate checklist; no data-bearing migration in this plan — schema untouched).
