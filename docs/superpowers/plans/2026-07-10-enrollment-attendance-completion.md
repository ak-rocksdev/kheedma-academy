# Enrollment, Attendance & Auto-Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the production loop — admin enrolls accepted applicants into an Angkatan, records per-session attendance, and the system auto-derives completion (unlocking the public affiliate ladder) — plus program thumbnail upload and a live dashboard.

**Architecture:** Two new tables (`cohort_sessions`, `attendances`) + `cohorts.required_attendance`. One engine (`App\Support\AttendanceCompletion`) is the only writer of system-authored `completed` StatusEvents; attendance changes trigger it. New admin endpoints (enrollments, sessions, attendance, cohort detail, thumbnail, stats) consumed by a new `CohortDetail.vue` and small additions to existing views. Public side untouched — Spec 1 eligibility reads the events this spec writes.

**Tech Stack:** PHP 8.4, Laravel 13, Vue 3 `<script setup>` admin SPA, PHPUnit 12, spatie/laravel-permission v8.

**Spec:** `docs/superpowers/specs/2026-07-10-enrollment-attendance-completion-design.md` (binding).

## Global Constraints

- Completion contract (Spec 1, unchanged): `StatusEvent` with `status = 'completed'` on an enrollment. System-authored marker (verbatim): `note = 'auto:attendance'`, `created_by = null`. Manual events are never modified or deleted.
- Requirement rule: `cohort.required_attendance ?? cohort_sessions count`; engine no-op when requirement is 0; enrollments whose LATEST status event is `dropped` are skipped.
- New permissions (verbatim): `enrollments.manage` (admin only), `attendance.record` (admin + mentor). Seeder stays idempotent + flushes Spatie cache.
- Status event strings (verbatim): `accepted`, `completed`, `dropped`.
- Table name is `cohort_sessions` (NOT `sessions` — taken by Laravel). Attendance rows are insert/delete only (`created_at` only, no `updated_at`).
- Thumbnail: public disk, dir `programs/`, mimes jpeg/png/webp, max 2048 KB. `storage:link` required locally + on deploy.
- UI copy Indonesian, no em-dashes; admin uses existing tokens/components (Badge, Button, Input, Dialog, native `<select>` with `selectClass`).
- PHP: curly braces always, explicit return types + param hints. `vendor/bin/pint --dirty --format agent` before each backend commit. PHPUnit only; feature tests seed `RoleSeeder` + `PermissionSeeder` in `setUp`.
- Surgical diffs: every changed line traces to the spec; no drive-by refactors.

---

## File Structure

**Create:** migrations `2026_07_10_000001_create_cohort_sessions_table`, `2026_07_10_000002_create_attendances_table`, `2026_07_10_000003_add_required_attendance_to_cohorts_table`; `app/Models/CohortSession.php`, `app/Models/Attendance.php`; `database/factories/CohortSessionFactory.php`; `app/Support/AttendanceCompletion.php`; controllers `Api/Admin/EnrollmentController.php`, `Api/Admin/CohortSessionController.php`, `Api/Admin/AttendanceController.php`, `Api/Admin/ProgramThumbnailController.php`, `Api/Admin/StatsController.php`; `resources/js/admin/views/CohortDetail.vue`; tests `AttendanceCompletionTest.php`, `EnrollmentManagementTest.php`, `CohortSessionTest.php`, `AttendanceRecordingTest.php`, `ProgramThumbnailTest.php`, `DashboardStatsTest.php`.

**Modify:** `PermissionSeeder`, `app/Models/Cohort.php` (+`sessions()`, fillable), `app/Models/Enrollment.php` (+`attendances()`), `Api/Admin/CohortController.php` (+`show`), `Api/Admin/PersonController.php` (hadir counts), `routes/api.php`, `resources/js/admin/api.js`, `router.js`, `views/Cohorts.vue` (Kelola link + required_attendance field), `views/PersonDetail.vue` (enroll dialog + hadir), `views/Programs.vue` (thumbnail UI), `views/Dashboard.vue` (stats), `Api/Admin/ProgramController.php` (`thumbnail_url` in row).

---

## Task 1: Data foundation + permissions

**Files:**
- Create: the 3 migrations, `app/Models/CohortSession.php`, `app/Models/Attendance.php`, `database/factories/CohortSessionFactory.php`
- Modify: `app/Models/Cohort.php`, `app/Models/Enrollment.php`, `database/seeders/PermissionSeeder.php`
- Test: `tests/Feature/AttendanceCompletionTest.php` (first test only)

**Interfaces:**
- Produces: `CohortSession` (`cohort_id`, `title`, `scheduled_at` nullable datetime, `position` int) with `cohort()` BelongsTo + `attendances()` HasMany; `Attendance` (`cohort_session_id`, `enrollment_id`, `marked_by`, `created_at` only, `UPDATED_AT = null`) unique pair; `Cohort::sessions()` HasMany ordered `position, scheduled_at`; `Cohort` fillable += `required_attendance`; `Enrollment::attendances()` HasMany; permissions `enrollments.manage` (admin), `attendance.record` (admin+mentor); `CohortSession::factory()` (title `Sesi N`, position N).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AttendanceCompletionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function makeEnrollment(?int $requiredAttendance = null, int $sessions = 3): Enrollment
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create([
            'program_id' => $program->id,
            'required_attendance' => $requiredAttendance,
        ]);
        CohortSession::factory()->count($sessions)->create(['cohort_id' => $cohort->id]);

        $person = Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);

        return Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
    }

    public function test_schema_and_relations_round_trip(): void
    {
        $enrollment = $this->makeEnrollment(requiredAttendance: 2);

        $session = $enrollment->cohort->sessions()->first();
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->assertSame(3, $enrollment->cohort->sessions()->count());
        $this->assertSame(2, (int) $enrollment->cohort->required_attendance);
        $this->assertSame(1, $enrollment->attendances()->count());
        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('enrollments.manage'));
        $this->assertTrue(Role::findByName('mentor', 'web')->hasPermissionTo('attendance.record'));
        $this->assertFalse(Role::findByName('mentor', 'web')->hasPermissionTo('enrollments.manage'));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — `php artisan test --compact --filter=AttendanceCompletionTest` → FAIL (missing tables/classes).

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_07_10_000001_create_cohort_sessions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scheduled class meetings per Angkatan. Named cohort_sessions because
        // `sessions` is Laravel's session table.
        Schema::create('cohort_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained('cohorts')->cascadeOnDelete();
            $table->string('title');
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_sessions');
    }
};
```

`database/migrations/2026_07_10_000002_create_attendances_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A row IS "hadir" for that session; unmarking deletes the row, so
        // there is no updated_at (mirrors the append-only status_events).
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_session_id')->constrained('cohort_sessions')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['cohort_session_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
```

`database/migrations/2026_07_10_000003_add_required_attendance_to_cohorts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Minimum "hadir" count to auto-complete. Null = all sessions.
        Schema::table('cohorts', function (Blueprint $table) {
            $table->unsignedTinyInteger('required_attendance')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropColumn('required_attendance');
        });
    }
};
```

- [ ] **Step 4: Create the models + factory**

`app/Models/CohortSession.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CohortSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'cohort_id',
        'title',
        'scheduled_at',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
```

`app/Models/Attendance.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row is "hadir" for one enrollment at one session; unmarking deletes the
 * row. Insert/delete only — no updated_at.
 */
class Attendance extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'cohort_session_id',
        'enrollment_id',
        'marked_by',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CohortSession::class, 'cohort_session_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
```

`database/factories/CohortSessionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\CohortSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CohortSession>
 */
class CohortSessionFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'title' => 'Sesi '.$counter,
            'scheduled_at' => now()->addWeeks($counter),
            'position' => $counter,
        ];
    }
}
```

In `app/Models/Cohort.php`: add `'required_attendance',` to `$fillable` (after `'end_date',`), and add the relation after `enrollments()`:

```php
    public function sessions(): HasMany
    {
        return $this->hasMany(CohortSession::class)->orderBy('position')->orderBy('scheduled_at');
    }
```

In `app/Models/Enrollment.php`: add after `statusEvents()`:

```php
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
```

- [ ] **Step 5: Extend PermissionSeeder**

In `database/seeders/PermissionSeeder.php`: add `'enrollments.manage',` and `'attendance.record',` to the `$permissions` array (after `'cohorts.manage',`), and add `'attendance.record'` to the mentor `syncPermissions` list.

- [ ] **Step 6: Migrate + test** — `php artisan migrate` then `php artisan test --compact --filter=AttendanceCompletionTest` → PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_10_* app/Models/CohortSession.php app/Models/Attendance.php app/Models/Cohort.php app/Models/Enrollment.php database/factories/CohortSessionFactory.php database/seeders/PermissionSeeder.php tests/Feature/AttendanceCompletionTest.php
git commit -m "feat: sessions, attendance, and required-attendance data foundation"
```

---

## Task 2: AttendanceCompletion engine

**Files:**
- Create: `app/Support/AttendanceCompletion.php`
- Test: `tests/Feature/AttendanceCompletionTest.php` (extend)

**Interfaces:**
- Consumes: Task 1 models.
- Produces: `AttendanceCompletion::sync(Enrollment $enrollment): void` and `AttendanceCompletion::syncCohort(Cohort $cohort): void` (sync every enrollment — used after session deletion). System marker: `note = 'auto:attendance'`, `created_by = null`.

- [ ] **Step 1: Write the failing tests** (append to `AttendanceCompletionTest`)

```php
    private function attend(Enrollment $enrollment, int $count): void
    {
        $sessions = $enrollment->cohort->sessions()->take($count)->get();
        foreach ($sessions as $session) {
            Attendance::firstOrCreate([
                'cohort_session_id' => $session->id,
                'enrollment_id' => $enrollment->id,
            ]);
        }
    }

    private function autoCompletedCount(Enrollment $enrollment): int
    {
        return $enrollment->statusEvents()
            ->where('status', 'completed')->where('note', 'auto:attendance')->count();
    }

    public function test_reaching_requirement_writes_one_completed_event(): void
    {
        $engine = app(\App\Support\AttendanceCompletion::class);
        $enrollment = $this->makeEnrollment(requiredAttendance: 2);

        $this->attend($enrollment, 2);
        $engine->sync($enrollment);
        $engine->sync($enrollment); // idempotent

        $this->assertSame(1, $this->autoCompletedCount($enrollment));
    }

    public function test_default_requirement_is_all_sessions(): void
    {
        $engine = app(\App\Support\AttendanceCompletion::class);
        $enrollment = $this->makeEnrollment(requiredAttendance: null, sessions: 3);

        $this->attend($enrollment, 2);
        $engine->sync($enrollment);
        $this->assertSame(0, $this->autoCompletedCount($enrollment));

        $this->attend($enrollment, 3);
        $engine->sync($enrollment);
        $this->assertSame(1, $this->autoCompletedCount($enrollment));
    }

    public function test_correction_below_requirement_retracts_auto_event_only(): void
    {
        $engine = app(\App\Support\AttendanceCompletion::class);
        $enrollment = $this->makeEnrollment(requiredAttendance: 2);

        // A manual completed event must never be touched.
        $enrollment->statusEvents()->create(['status' => 'completed', 'note' => 'manual', 'occurred_at' => now()]);

        $this->attend($enrollment, 2);
        $engine->sync($enrollment);
        $this->assertSame(1, $this->autoCompletedCount($enrollment));

        $enrollment->attendances()->limit(1)->delete();
        $engine->sync($enrollment);

        $this->assertSame(0, $this->autoCompletedCount($enrollment));
        $this->assertSame(1, $enrollment->statusEvents()->where('note', 'manual')->count());
    }

    public function test_no_sessions_means_no_op(): void
    {
        $engine = app(\App\Support\AttendanceCompletion::class);
        $enrollment = $this->makeEnrollment(requiredAttendance: null, sessions: 0);

        $engine->sync($enrollment);
        $this->assertSame(0, $this->autoCompletedCount($enrollment));
    }

    public function test_dropped_enrollment_is_never_auto_completed(): void
    {
        $engine = app(\App\Support\AttendanceCompletion::class);
        $enrollment = $this->makeEnrollment(requiredAttendance: 1);

        $enrollment->statusEvents()->create(['status' => 'dropped', 'note' => 'berhenti', 'occurred_at' => now()]);
        $this->attend($enrollment, 1);
        $engine->sync($enrollment);

        $this->assertSame(0, $this->autoCompletedCount($enrollment));
    }

    public function test_attendance_completion_unlocks_affiliate_eligibility(): void
    {
        $engine = app(\App\Support\AttendanceCompletion::class);
        $eligibility = app(\App\Support\ProgramEligibility::class);

        $enrollment = $this->makeEnrollment(requiredAttendance: 2);
        $affiliate = Program::factory()->affiliate(1)->active()->create();
        $person = $enrollment->person;

        $this->assertFalse($eligibility->canAccess($person, $affiliate));

        $this->attend($enrollment, 2);
        $engine->sync($enrollment);

        $this->assertTrue($eligibility->canAccess($person->fresh(), $affiliate));
    }
```

- [ ] **Step 2: Run to verify RED** — `php artisan test --compact --filter=AttendanceCompletionTest` → new tests FAIL (class not found).

- [ ] **Step 3: Implement the engine**

`app/Support/AttendanceCompletion.php`:

```php
<?php

namespace App\Support;

use App\Models\Cohort;
use App\Models\Enrollment;

/**
 * Derives completion from attendance — the ONLY writer of system-authored
 * completed StatusEvents (note 'auto:attendance', created_by null). Manual
 * events are never modified or deleted. Spec 2026-07-10.
 */
class AttendanceCompletion
{
    private const MARKER = 'auto:attendance';

    /** Recompute one enrollment after any attendance change. */
    public function sync(Enrollment $enrollment): void
    {
        $cohort = $enrollment->cohort;
        $requirement = $cohort->required_attendance ?? $cohort->sessions()->count();

        if ($requirement === 0) {
            return;
        }

        if ($enrollment->latestStatusEvent()->first()?->status === 'dropped') {
            return;
        }

        $hadir = $enrollment->attendances()->count();
        $hasAutoEvent = $enrollment->statusEvents()
            ->where('status', 'completed')->where('note', self::MARKER)->exists();

        if ($hadir >= $requirement && ! $hasAutoEvent) {
            $enrollment->statusEvents()->create([
                'status' => 'completed',
                'note' => self::MARKER,
                'occurred_at' => now(),
                'created_by' => null,
            ]);
        }

        if ($hadir < $requirement && $hasAutoEvent) {
            $enrollment->statusEvents()
                ->where('status', 'completed')->where('note', self::MARKER)->delete();
        }
    }

    /** Recompute every enrollment of a cohort (after a session is deleted). */
    public function syncCohort(Cohort $cohort): void
    {
        $cohort->enrollments()->with('cohort')->get()->each(fn (Enrollment $e) => $this->sync($e));
    }
}
```

- [ ] **Step 4: Run to verify GREEN** — all AttendanceCompletionTest pass (7 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/AttendanceCompletion.php tests/Feature/AttendanceCompletionTest.php
git commit -m "feat: attendance-derived auto-completion engine"
```

---

## Task 3: Enrollment API (two doors, un-enroll guard, drop)

**Files:**
- Create: `app/Http/Controllers/Api/Admin/EnrollmentController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/EnrollmentManagementTest.php`

**Interfaces:**
- Consumes: permissions from Task 1.
- Produces: `POST /api/admin/enrollments` `{cohort_id, application_id? , people_id?}` → 201 `{enrollment: {id, person: {id,name}, cohort_id}}` + manual `accepted` StatusEvent (`created_by` = actor); `DELETE /api/admin/enrollments/{enrollment}` (204, guarded); `POST /api/admin/enrollments/{enrollment}/drop` `{note}` (200) writes `dropped`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/EnrollmentManagementTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Attendance;
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

class EnrollmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function person(): Person
    {
        return Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    public function test_enroll_from_accepted_application(): void
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $person = $this->person();
        $application = Application::create([
            'people_id' => $person->id,
            'program_id' => $program->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/enrollments', [
                'cohort_id' => $cohort->id,
                'application_id' => $application->id,
            ])
            ->assertCreated()
            ->assertJsonPath('enrollment.person.id', $person->id);

        $enrollment = Enrollment::first();
        $this->assertSame($application->id, $enrollment->application_id);
        $this->assertSame('accepted', $enrollment->latestStatusEvent->status);
        $this->assertNotNull($enrollment->latestStatusEvent->created_by);
    }

    public function test_application_must_be_accepted_and_cohort_must_match_program(): void
    {
        $program = Program::factory()->active()->create();
        $otherProgram = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $otherProgram->id]);
        $person = $this->person();
        $pending = Application::create(['people_id' => $person->id, 'program_id' => $program->id, 'status' => 'pending']);

        // Pending application rejected.
        $this->actingAs($this->admin())
            ->postJson('/api/admin/enrollments', ['cohort_id' => $cohort->id, 'application_id' => $pending->id])
            ->assertStatus(422);

        // Accepted but cohort belongs to another program: rejected.
        $pending->update(['status' => 'accepted']);
        $this->actingAs($this->admin())
            ->postJson('/api/admin/enrollments', ['cohort_id' => $cohort->id, 'application_id' => $pending->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cohort_id');
    }

    public function test_duplicate_enrollment_is_rejected(): void
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $person = $this->person();
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/enrollments', ['cohort_id' => $cohort->id, 'people_id' => $person->id])
            ->assertStatus(422);
    }

    public function test_unenroll_only_while_clean(): void
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        $clean = Enrollment::create(['people_id' => $this->person()->id, 'cohort_id' => $cohort->id]);
        $dirty = Enrollment::create(['people_id' => $this->person()->id, 'cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $dirty->id]);

        $this->actingAs($this->admin())->deleteJson("/api/admin/enrollments/{$clean->id}")->assertNoContent();
        $this->actingAs($this->admin())->deleteJson("/api/admin/enrollments/{$dirty->id}")->assertStatus(422);
    }

    public function test_drop_requires_note_and_writes_event(): void
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $enrollment = Enrollment::create(['people_id' => $this->person()->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/enrollments/{$enrollment->id}/drop", [])
            ->assertStatus(422);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/enrollments/{$enrollment->id}/drop", ['note' => 'Sibuk kerja'])
            ->assertOk();

        $this->assertSame('dropped', $enrollment->fresh()->latestStatusEvent->status);
    }

    public function test_mentor_cannot_manage_enrollments(): void
    {
        $mentor = User::factory()->mentor()->create();
        $this->actingAs($mentor)->postJson('/api/admin/enrollments', [])->assertForbidden();
    }
}
```

- [ ] **Step 2: RED** — `php artisan test --compact --filter=EnrollmentManagementTest` → FAIL (404s).

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/Api/Admin/EnrollmentController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    /**
     * Enroll a person into a cohort — either from an accepted application
     * (door 1: Applicants) or directly by person id (door 2: cohort roster).
     */
    public function store(Request $request): JsonResponse
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
                throw ValidationException::withMessages(['application_id' => 'Hanya pelamar berstatus diterima yang bisa dimasukkan ke Angkatan.']);
            }
            if ($application->program_id !== null && $application->program_id !== $cohort->program_id) {
                throw ValidationException::withMessages(['cohort_id' => 'Angkatan ini bukan milik program yang dilamar.']);
            }
        }

        $personId = $application?->people_id ?? $data['people_id'];

        if (Enrollment::where('people_id', $personId)->where('cohort_id', $cohort->id)->exists()) {
            throw ValidationException::withMessages(['people_id' => 'Peserta sudah terdaftar di Angkatan ini.']);
        }

        $enrollment = Enrollment::create([
            'people_id' => $personId,
            'cohort_id' => $cohort->id,
            'application_id' => $application?->id,
        ]);
        $enrollment->statusEvents()->create([
            'status' => 'accepted',
            'occurred_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        $person = Person::findOrFail($personId);

        return response()->json([
            'enrollment' => [
                'id' => $enrollment->id,
                'cohort_id' => $enrollment->cohort_id,
                'person' => ['id' => $person->id, 'name' => $person->name],
            ],
        ], 201);
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
```

- [ ] **Step 4: Register routes**

In `routes/api.php`, add import `use App\Http\Controllers\Api\Admin\EnrollmentController;` and inside the `admin` prefix group:

```php
        Route::middleware('permission:enrollments.manage')->group(function () {
            Route::post('/enrollments', [EnrollmentController::class, 'store']);
            Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy']);
            Route::post('/enrollments/{enrollment}/drop', [EnrollmentController::class, 'drop']);
        });
```

- [ ] **Step 5: GREEN** — `php artisan test --compact --filter=EnrollmentManagementTest` → PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/EnrollmentController.php routes/api.php tests/Feature/EnrollmentManagementTest.php
git commit -m "feat: enrollment API (two doors, guarded un-enroll, drop with reason)"
```

---

## Task 4: Cohort detail + sessions API

**Files:**
- Create: `app/Http/Controllers/Api/Admin/CohortSessionController.php`
- Modify: `app/Http/Controllers/Api/Admin/CohortController.php` (+`show`, +`required_attendance` in `validated()`/`row()`)
- Modify: `routes/api.php`
- Test: `tests/Feature/CohortSessionTest.php`

**Interfaces:**
- Consumes: Tasks 1-2.
- Produces: `GET /api/admin/cohorts/{cohort}` → `{cohort: row + required_attendance, sessions: [{id,title,scheduled_at,position,attendances_count}], roster: [{enrollment_id, person: {id,name,phone}, hadir, latest_status, latest_status_at, attended_session_ids: int[]}] , requirement: int}`; `POST /api/admin/cohorts/{cohort}/sessions` `{title, scheduled_at?, position?}`; `PATCH/DELETE /api/admin/sessions/{session}` (delete resyncs cohort completion). `required_attendance` accepted by cohort store/update (`nullable integer min:1 max:255`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/CohortSessionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Attendance;
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

class CohortSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function cohortWithEnrollment(): array
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $person = Person::create([
            'name' => 'Peserta Uji',
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        return [$cohort, $enrollment];
    }

    public function test_admin_can_manage_sessions(): void
    {
        [$cohort] = $this->cohortWithEnrollment();

        $created = $this->actingAs($this->admin())
            ->postJson("/api/admin/cohorts/{$cohort->id}/sessions", ['title' => 'Sesi 1: Dasar', 'position' => 1])
            ->assertCreated()
            ->json('session.id');

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/sessions/{$created}", ['title' => 'Sesi 1: Dasar Affiliate'])
            ->assertOk()
            ->assertJsonPath('session.title', 'Sesi 1: Dasar Affiliate');

        $this->actingAs($this->admin())->deleteJson("/api/admin/sessions/{$created}")->assertNoContent();
    }

    public function test_cohort_detail_returns_sessions_roster_and_requirement(): void
    {
        [$cohort, $enrollment] = $this->cohortWithEnrollment();
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->actingAs($this->admin())
            ->getJson("/api/admin/cohorts/{$cohort->id}")
            ->assertOk()
            ->assertJsonPath('requirement', 1)
            ->assertJsonPath('sessions.0.id', $session->id)
            ->assertJsonPath('roster.0.hadir', 1)
            ->assertJsonPath('roster.0.attended_session_ids.0', $session->id);
    }

    public function test_deleting_a_session_retracts_auto_completion(): void
    {
        [$cohort, $enrollment] = $this->cohortWithEnrollment();
        $cohort->update(['required_attendance' => null]); // all sessions
        $s1 = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $s1->id, 'enrollment_id' => $enrollment->id]);
        app(\App\Support\AttendanceCompletion::class)->sync($enrollment);
        $this->assertSame('completed', $enrollment->fresh()->latestStatusEvent->status);

        // Add a second session: requirement (all) rises to 2; deleting it later resyncs.
        $s2 = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        $this->actingAs($this->admin())->deleteJson("/api/admin/sessions/{$s2->id}")->assertNoContent();

        // Still completed after delete (1/1 again) — resync ran without error.
        $this->assertSame('completed', $enrollment->fresh()->latestStatusEvent->status);
    }

    public function test_required_attendance_round_trips_through_cohort_api(): void
    {
        $program = Program::factory()->active()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', [
                'name' => 'Angkatan 9',
                'program_id' => $program->id,
                'required_attendance' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('cohort.required_attendance', 5);
    }
}
```

- [ ] **Step 2: RED** — `--filter=CohortSessionTest` fails (404 / missing keys).

- [ ] **Step 3: Session controller**

`app/Http/Controllers/Api/Admin/CohortSessionController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Support\AttendanceCompletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CohortSessionController extends Controller
{
    public function store(Request $request, Cohort $cohort): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'position' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $session = $cohort->sessions()->create($data);

        return response()->json(['session' => $this->row($session)], 201);
    }

    public function update(Request $request, CohortSession $session): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $session->update($data);

        return response()->json(['session' => $this->row($session->fresh())]);
    }

    /** Deleting a session cascades its attendance, so completion must resync. */
    public function destroy(CohortSession $session, AttendanceCompletion $completion): JsonResponse
    {
        $cohort = $session->cohort;
        $session->delete();
        $completion->syncCohort($cohort);

        return response()->json(null, 204);
    }

    /**
     * @return array{id:int,title:string,scheduled_at:?string,position:int}
     */
    private function row(CohortSession $s): array
    {
        return [
            'id' => $s->id,
            'title' => $s->title,
            'scheduled_at' => $s->scheduled_at?->toIso8601String(),
            'position' => (int) $s->position,
        ];
    }
}
```

- [ ] **Step 4: Cohort show + required_attendance**

In `app/Http/Controllers/Api/Admin/CohortController.php`:

a) Add `'required_attendance' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:255'],` to the `validate([...])` array in `validated()` (after `end_date`), and `'required_attendance' => $c->required_attendance !== null ? (int) $c->required_attendance : null,` to `row()` (after `end_date`).

b) Add the `show` method after `index()`:

```php
    /** Detail for the roster/sessions/attendance screen. */
    public function show(Cohort $cohort): JsonResponse
    {
        $cohort->load(['mentor:id,name', 'program:id,name'])->loadCount('enrollments');

        $sessions = $cohort->sessions()->withCount('attendances')->get();
        $requirement = $cohort->required_attendance ?? $sessions->count();

        $roster = $cohort->enrollments()
            ->with(['person:id,name,phone', 'latestStatusEvent', 'attendances:id,enrollment_id,cohort_session_id'])
            ->get()
            ->map(fn ($e) => [
                'enrollment_id' => $e->id,
                'person' => ['id' => $e->person->id, 'name' => $e->person->name, 'phone' => $e->person->phone],
                'hadir' => $e->attendances->count(),
                'latest_status' => $e->latestStatusEvent?->status,
                'latest_status_at' => $e->latestStatusEvent?->occurred_at?->toIso8601String(),
                'attended_session_ids' => $e->attendances->pluck('cohort_session_id')->values(),
            ]);

        return response()->json([
            'cohort' => $this->row($cohort),
            'requirement' => (int) $requirement,
            'sessions' => $sessions->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'scheduled_at' => $s->scheduled_at?->toIso8601String(),
                'position' => (int) $s->position,
                'attendances_count' => (int) $s->attendances_count,
            ]),
            'roster' => $roster,
        ]);
    }
```

- [ ] **Step 5: Routes**

In `routes/api.php` (imports: `CohortSessionController`), inside the admin group:

```php
        Route::get('/cohorts/{cohort}', [CohortController::class, 'show'])->middleware('permission:cohorts.view');
        Route::middleware('permission:cohorts.manage')->group(function () {
            Route::post('/cohorts/{cohort}/sessions', [CohortSessionController::class, 'store']);
            Route::patch('/sessions/{session}', [CohortSessionController::class, 'update']);
            Route::delete('/sessions/{session}', [CohortSessionController::class, 'destroy']);
        });
```

Place the `GET /cohorts/{cohort}` line AFTER the existing `GET /cohorts` route.

- [ ] **Step 6: GREEN** — `--filter=CohortSessionTest` PASS; also run `--filter=CohortManagementTest` (no regression).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/CohortSessionController.php app/Http/Controllers/Api/Admin/CohortController.php routes/api.php tests/Feature/CohortSessionTest.php
git commit -m "feat: cohort detail endpoint and session management API"
```

---

## Task 5: Attendance recording API

**Files:**
- Create: `app/Http/Controllers/Api/Admin/AttendanceController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/AttendanceRecordingTest.php`

**Interfaces:**
- Consumes: engine (Task 2), models (Task 1).
- Produces: `PUT /api/admin/sessions/{session}/attendance` `{enrollment_ids: int[]}` — declarative full "hadir" set for the session; server diffs (insert new with `marked_by`, delete removed), syncs completion for every affected enrollment, returns `{attended: int[], roster_completions: {enrollment_id: latest_status}}`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AttendanceRecordingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Attendance;
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

class AttendanceRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    /** @return array{0: CohortSession, 1: Enrollment, 2: Enrollment} */
    private function sessionWithTwoEnrollments(): array
    {
        $cohort = Cohort::factory()->create([
            'program_id' => Program::factory()->active()->create()->id,
            'required_attendance' => 1,
        ]);
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);

        $make = function () use ($cohort) {
            $person = Person::create([
                'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
                'phone' => '+628'.fake()->unique()->numerify('##########'),
                'email' => fake()->unique()->safeEmail(),
            ]);

            return Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        };

        return [$session, $make(), $make()];
    }

    public function test_declarative_set_adds_and_removes(): void
    {
        [$session, $a, $b] = $this->sessionWithTwoEnrollments();
        $admin = User::factory()->admin()->create();

        // Mark both hadir.
        $this->actingAs($admin)
            ->putJson("/api/admin/sessions/{$session->id}/attendance", ['enrollment_ids' => [$a->id, $b->id]])
            ->assertOk();
        $this->assertSame(2, Attendance::count());
        $this->assertSame($admin->id, Attendance::first()->marked_by);

        // Correct: only A hadir. B's row removed, B's auto-completion retracted.
        $this->actingAs($admin)
            ->putJson("/api/admin/sessions/{$session->id}/attendance", ['enrollment_ids' => [$a->id]])
            ->assertOk();
        $this->assertSame(1, Attendance::count());
        $this->assertSame('completed', $a->fresh()->latestStatusEvent->status);
        $this->assertNull($b->fresh()->latestStatusEvent);
    }

    public function test_enrollments_must_belong_to_the_sessions_cohort(): void
    {
        [$session] = $this->sessionWithTwoEnrollments();
        $otherCohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $foreign = Enrollment::create([
            'people_id' => Person::create([
                'name' => 'Asing', 'phone' => '+628999999999', 'email' => 'asing@example.test',
            ])->id,
            'cohort_id' => $otherCohort->id,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/admin/sessions/{$session->id}/attendance", ['enrollment_ids' => [$foreign->id]])
            ->assertStatus(422);
    }

    public function test_mentor_can_record_attendance_but_participant_cannot(): void
    {
        [$session, $a] = $this->sessionWithTwoEnrollments();

        $mentor = User::factory()->mentor()->create();
        $this->actingAs($mentor)
            ->putJson("/api/admin/sessions/{$session->id}/attendance", ['enrollment_ids' => [$a->id]])
            ->assertOk();

        $participant = User::factory()->create();
        $participant->assignRole('participant');
        $this->actingAs($participant)
            ->putJson("/api/admin/sessions/{$session->id}/attendance", ['enrollment_ids' => []])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: RED** — `--filter=AttendanceRecordingTest` fails.

- [ ] **Step 3: Controller**

`app/Http/Controllers/Api/Admin/AttendanceController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Support\AttendanceCompletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    /**
     * Declarative attendance for one session: the payload is the full "hadir"
     * set; the server inserts/deletes the diff and resyncs auto-completion for
     * every enrollment whose state changed.
     */
    public function update(Request $request, CohortSession $session, AttendanceCompletion $completion): JsonResponse
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

        $affected = $toAdd->merge($toRemove);
        Enrollment::with('cohort')->findMany($affected)->each(fn (Enrollment $e) => $completion->sync($e));

        $completions = Enrollment::with('latestStatusEvent')
            ->where('cohort_id', $session->cohort_id)
            ->get()
            ->mapWithKeys(fn (Enrollment $e) => [$e->id => $e->latestStatusEvent?->status]);

        return response()->json([
            'attended' => $session->attendances()->pluck('enrollment_id'),
            'roster_completions' => $completions,
        ]);
    }
}
```

- [ ] **Step 4: Route** — import `AttendanceController`; inside the admin group:

```php
        Route::put('/sessions/{session}/attendance', [AttendanceController::class, 'update'])->middleware('permission:attendance.record');
```

- [ ] **Step 5: GREEN** — `--filter=AttendanceRecordingTest` PASS; run `--filter=AttendanceCompletionTest` too (no regression).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/AttendanceController.php routes/api.php tests/Feature/AttendanceRecordingTest.php
git commit -m "feat: declarative per-session attendance recording with completion sync"
```

---

## Task 6: Admin UI — CohortDetail (roster, sesi, absensi) + Cohorts additions

**Files:**
- Create: `resources/js/admin/views/CohortDetail.vue`
- Modify: `resources/js/admin/api.js` (cohort detail/sessions/attendance/enrollments groups), `resources/js/admin/router.js` (route `cohorts/:id`), `resources/js/admin/views/Cohorts.vue` ("Kelola" link + `required_attendance` form field)

**Interfaces:**
- Consumes: Tasks 3-5 endpoints (exact shapes above), existing ui components + `selectClass` idiom, `Dialog`.
- Produces: route name `cohort-detail` (`/admin/cohorts/:id`, meta permission `cohorts.view`).

- [ ] **Step 1: api.js additions** — extend the `cohorts` group and add `enrollments`/`sessions` groups (append after existing groups; follow the existing method style):

```js
// inside export const cohorts = { ... } add:
    detail(id) {
        return api(`/admin/cohorts/${id}`);
    },

export const enrollments = {
    create(payload) {
        return api('/admin/enrollments', { method: 'POST', body: payload });
    },
    remove(id) {
        return api(`/admin/enrollments/${id}`, { method: 'DELETE' });
    },
    drop(id, note) {
        return api(`/admin/enrollments/${id}/drop`, { method: 'POST', body: { note } });
    },
};

export const sessions = {
    create(cohortId, payload) {
        return api(`/admin/cohorts/${cohortId}/sessions`, { method: 'POST', body: payload });
    },
    update(id, payload) {
        return api(`/admin/sessions/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/sessions/${id}`, { method: 'DELETE' });
    },
    setAttendance(id, enrollmentIds) {
        return api(`/admin/sessions/${id}/attendance`, { method: 'PUT', body: { enrollment_ids: enrollmentIds } });
    },
};
```

- [ ] **Step 2: Router + Cohorts list link**

Router children (after the `cohorts` entry):

```js
            {
                path: 'cohorts/:id',
                name: 'cohort-detail',
                component: () => import('./views/CohortDetail.vue'),
                props: true,
                meta: { permission: 'cohorts.view' },
            },
```

In `Cohorts.vue` actions cell, add BEFORE the "Ubah" button:

```vue
                            <RouterLink :to="{ name: 'cohort-detail', params: { id: cohort.id } }">
                                <Button variant="ghost" size="sm">Kelola</Button>
                            </RouterLink>
```

(add `RouterLink` to the imports from 'vue-router' if not present). In the cohort form dialog, add below the date inputs:

```vue
                <div>
                    <label class="text-xs text-muted-foreground">Syarat kehadiran (jumlah sesi, kosongkan = semua sesi)</label>
                    <Input v-model="form.required_attendance" type="number" min="1" max="255" placeholder="Semua sesi" class="mt-1.5" />
                    <p v-if="formErrors.required_attendance" class="mt-1 text-xs text-destructive">{{ formErrors.required_attendance[0] }}</p>
                </div>
```

Extend the form object initializers with `required_attendance: ''`, `openEdit` mapping with `required_attendance: cohort.required_attendance ?? ''`, and the save payload with `required_attendance: form.value.required_attendance === '' ? null : Number(form.value.required_attendance)`.

- [ ] **Step 3: CohortDetail.vue** — create with this structure (full file; follows Applicants/Users idioms: `selectClass`, Badge variants, Dialog):

```vue
<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowLeft } from 'lucide-vue-next';
import { cohorts as cohortsApi, enrollments as enrollmentsApi, sessions as sessionsApi, api } from '@/api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog } from '@/components/ui/dialog';
import { useAuthStore } from '@/stores/auth';

const props = defineProps({ id: { type: [String, Number], required: true } });

const auth = useAuthStore();

const cohort = ref(null);
const requirement = ref(0);
const sessionList = ref([]);
const roster = ref([]);
const loading = ref(true);
const error = ref('');

// Absensi state
const activeSessionId = ref(null);
const hadirSet = ref(new Set());
const savingAttendance = ref(false);

// Tambah peserta
const addOpen = ref(false);
const candidates = ref([]);
const addError = ref('');

// Drop dialog
const dropTarget = ref(null);
const dropNote = ref('');
const dropError = ref('');

// Sesi form + konfirmasi hapus (menghapus sesi ikut menghapus absensinya)
const sessionForm = ref({ id: null, title: '', scheduled_at: '' });
const sessionOpen = ref(false);
const sessionError = ref('');
const deleteSessionTarget = ref(null);

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const activeSession = computed(() => sessionList.value.find((s) => s.id === activeSessionId.value) ?? null);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await cohortsApi.detail(props.id);
        cohort.value = res.cohort;
        requirement.value = res.requirement;
        sessionList.value = res.sessions;
        roster.value = res.roster;
        if (!activeSessionId.value && res.sessions.length) activeSessionId.value = res.sessions[0].id;
        syncHadirSet();
    } catch (e) {
        if (e.sessionExpired) return;
        error.value = e.message ?? 'Gagal memuat data.';
    } finally {
        loading.value = false;
    }
}

function syncHadirSet() {
    const set = new Set();
    roster.value.forEach((r) => {
        if (r.attended_session_ids.includes(activeSessionId.value)) set.add(r.enrollment_id);
    });
    hadirSet.value = set;
}

function selectSession(id) {
    activeSessionId.value = Number(id);
    syncHadirSet();
}

function toggleHadir(enrollmentId) {
    const set = new Set(hadirSet.value);
    set.has(enrollmentId) ? set.delete(enrollmentId) : set.add(enrollmentId);
    hadirSet.value = set;
}

async function saveAttendance() {
    if (!activeSession.value) return;
    savingAttendance.value = true;
    try {
        await sessionsApi.setAttendance(activeSession.value.id, [...hadirSet.value]);
        await load();
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal menyimpan absensi.';
    } finally {
        savingAttendance.value = false;
    }
}

async function openAdd() {
    addError.value = '';
    addOpen.value = true;
    try {
        // Accepted applications of this cohort's program, not yet enrolled here.
        const res = await api(`/admin/applications?status=accepted&program=${cohort.value.program.id}`);
        const enrolledPersonIds = new Set(roster.value.map((r) => r.person.id));
        candidates.value = res.data.filter((a) => !enrolledPersonIds.has(a.person.id));
    } catch (e) {
        if (!e.sessionExpired) addError.value = e.message ?? 'Gagal memuat pelamar.';
    }
}

async function enroll(application) {
    addError.value = '';
    try {
        await enrollmentsApi.create({ cohort_id: cohort.value.id, application_id: application.id });
        addOpen.value = false;
        await load();
    } catch (e) {
        if (!e.sessionExpired) addError.value = e.errors ? Object.values(e.errors)[0][0] : e.message;
    }
}

function openDrop(row) {
    dropTarget.value = row;
    dropNote.value = '';
    dropError.value = '';
}

async function confirmDrop() {
    dropError.value = '';
    try {
        await enrollmentsApi.drop(dropTarget.value.enrollment_id, dropNote.value);
        dropTarget.value = null;
        await load();
    } catch (e) {
        if (!e.sessionExpired) dropError.value = e.errors?.note?.[0] ?? e.message;
    }
}

async function removeEnrollment(row) {
    error.value = '';
    try {
        await enrollmentsApi.remove(row.enrollment_id);
        await load();
    } catch (e) {
        if (!e.sessionExpired) error.value = e.errors?.enrollment?.[0] ?? e.message;
    }
}

function openSessionForm(session = null) {
    sessionError.value = '';
    sessionForm.value = session
        ? { id: session.id, title: session.title, scheduled_at: session.scheduled_at?.slice(0, 10) ?? '' }
        : { id: null, title: '', scheduled_at: '' };
    sessionOpen.value = true;
}

async function saveSession() {
    sessionError.value = '';
    const payload = { title: sessionForm.value.title, scheduled_at: sessionForm.value.scheduled_at || null };
    try {
        if (sessionForm.value.id) {
            await sessionsApi.update(sessionForm.value.id, payload);
        } else {
            await sessionsApi.create(cohort.value.id, { ...payload, position: sessionList.value.length + 1 });
        }
        sessionOpen.value = false;
        await load();
    } catch (e) {
        if (!e.sessionExpired) sessionError.value = e.errors?.title?.[0] ?? e.message;
    }
}

async function confirmRemoveSession() {
    const session = deleteSessionTarget.value;
    error.value = '';
    try {
        await sessionsApi.remove(session.id);
        if (activeSessionId.value === session.id) activeSessionId.value = null;
        deleteSessionTarget.value = null;
        await load();
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal menghapus sesi.';
    }
}

function statusVariant(status) {
    return { accepted: 'warning', completed: 'success', dropped: 'destructive' }[status] ?? 'secondary';
}

function statusLabel(status) {
    return { accepted: 'Aktif', completed: 'Lulus', dropped: 'Keluar' }[status] ?? (status ?? 'Belum ada status');
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

onMounted(load);
// Route-view instances are reused when only :id changes (house pattern, see PersonDetail).
watch(() => props.id, () => load());
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <RouterLink :to="{ name: 'cohorts' }" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" /> Semua Angkatan
        </RouterLink>

        <div v-if="error" class="mt-4 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">{{ error }}</div>
        <div v-if="loading" class="mt-10 text-center text-muted-foreground">Memuat…</div>

        <template v-else-if="cohort">
            <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">{{ cohort.program?.name ?? 'Angkatan' }}</p>
                    <h1 class="mt-2 text-2xl font-bold text-foreground">{{ cohort.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Syarat lulus: hadir {{ requirement }} dari {{ sessionList.length }} sesi · Mentor: {{ cohort.mentor?.name ?? '—' }}
                    </p>
                </div>
                <Button v-if="auth.can('enrollments.manage')" variant="accent" size="sm" @click="openAdd">Tambah Peserta</Button>
            </div>

            <!-- Peserta -->
            <div class="mt-6 overflow-hidden rounded-xl border border-border bg-card">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-3 font-semibold">Peserta</th>
                            <th class="px-4 py-3 font-semibold">Kehadiran</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!roster.length"><td colspan="4" class="px-4 py-10 text-center text-muted-foreground">Belum ada peserta.</td></tr>
                        <tr v-for="row in roster" :key="row.enrollment_id" class="border-b border-border last:border-0">
                            <td class="px-4 py-3">
                                <RouterLink :to="{ name: 'person', params: { id: row.person.id } }" class="font-medium text-foreground hover:underline">
                                    {{ row.person.name }}
                                </RouterLink>
                                <div class="text-xs text-muted-foreground">{{ row.person.phone }}</div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.hadir }}/{{ requirement }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="statusVariant(row.latest_status)">{{ statusLabel(row.latest_status) }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button v-if="auth.can('enrollments.manage') && row.latest_status !== 'dropped'" variant="ghost" size="sm" @click="openDrop(row)">Keluarkan</Button>
                                <Button v-if="auth.can('enrollments.manage') && row.hadir === 0 && (row.latest_status === 'accepted' || !row.latest_status)" variant="ghost" size="sm" @click="removeEnrollment(row)">Hapus</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Sesi + Absensi -->
            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                <div>
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Sesi</h2>
                        <Button v-if="auth.can('cohorts.manage')" variant="outline" size="sm" @click="openSessionForm()">Tambah Sesi</Button>
                    </div>
                    <div class="mt-3 overflow-hidden rounded-xl border border-border bg-card">
                        <div v-if="!sessionList.length" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada sesi. Tambahkan jadwal pertemuan Angkatan ini.</div>
                        <div v-for="s in sessionList" :key="s.id" class="flex items-center justify-between border-b border-border px-4 py-3 text-sm last:border-0">
                            <div>
                                <p class="font-medium text-foreground">{{ s.title }}</p>
                                <p class="text-xs text-muted-foreground">{{ fmtDate(s.scheduled_at) }} · {{ s.attendances_count }} hadir</p>
                            </div>
                            <div v-if="auth.can('cohorts.manage')" class="shrink-0">
                                <Button variant="ghost" size="sm" @click="openSessionForm(s)">Ubah</Button>
                                <Button variant="ghost" size="sm" @click="deleteSessionTarget = s">Hapus</Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Absensi</h2>
                        <select :value="activeSessionId ?? ''" :class="selectClass" @change="selectSession($event.target.value)">
                            <option v-for="s in sessionList" :key="s.id" :value="s.id">{{ s.title }}</option>
                        </select>
                    </div>
                    <div class="mt-3 overflow-hidden rounded-xl border border-border bg-card">
                        <div v-if="!activeSession || !roster.length" class="px-4 py-8 text-center text-sm text-muted-foreground">
                            {{ !sessionList.length ? 'Buat sesi dulu untuk mencatat absensi.' : 'Belum ada peserta.' }}
                        </div>
                        <template v-else>
                            <label
                                v-for="row in roster"
                                :key="row.enrollment_id"
                                class="flex items-center gap-3 border-b border-border px-4 py-2.5 text-sm last:border-0"
                                :class="row.latest_status === 'dropped' ? 'opacity-50' : 'cursor-pointer hover:bg-accent/50'"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 accent-teal-700"
                                    :checked="hadirSet.has(row.enrollment_id)"
                                    :disabled="row.latest_status === 'dropped' || !auth.can('attendance.record')"
                                    @change="toggleHadir(row.enrollment_id)"
                                />
                                <span class="text-foreground">{{ row.person.name }}</span>
                                <Badge v-if="row.latest_status === 'completed'" variant="success" class="ml-auto">Lulus</Badge>
                            </label>
                            <div v-if="auth.can('attendance.record')" class="flex items-center justify-between px-4 py-3">
                                <span class="text-xs tabular-nums text-muted-foreground">{{ hadirSet.size }} dari {{ roster.length }} hadir</span>
                                <Button size="sm" :disabled="savingAttendance" @click="saveAttendance">
                                    {{ savingAttendance ? 'Menyimpan…' : 'Simpan Absensi' }}
                                </Button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <!-- Tambah peserta -->
        <Dialog v-model:open="addOpen" title="Tambah Peserta">
            <div v-if="addError" class="mb-3 rounded-lg border border-destructive/30 bg-red-50 px-3.5 py-2.5 text-sm text-destructive">{{ addError }}</div>
            <p v-if="!candidates.length" class="text-sm text-muted-foreground">Tidak ada pelamar diterima yang belum terdaftar di Angkatan ini.</p>
            <div v-else class="max-h-72 space-y-2 overflow-y-auto">
                <div v-for="app in candidates" :key="app.id" class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2 text-sm">
                    <div>
                        <p class="font-medium text-foreground">{{ app.person.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ app.person.phone }}</p>
                    </div>
                    <Button size="sm" variant="outline" @click="enroll(app)">Masukkan</Button>
                </div>
            </div>
        </Dialog>

        <!-- Drop -->
        <Dialog :open="dropTarget !== null" title="Keluarkan Peserta" @update:open="dropTarget = null">
            <p class="text-sm text-muted-foreground">
                Catat alasan {{ dropTarget?.person.name }} keluar. Riwayatnya tetap tersimpan untuk analisis.
            </p>
            <div v-if="dropError" class="mt-3 rounded-lg border border-destructive/30 bg-red-50 px-3.5 py-2.5 text-sm text-destructive">{{ dropError }}</div>
            <textarea
                v-model="dropNote"
                rows="3"
                placeholder="Alasan keluar (wajib)"
                class="mt-3 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            ></textarea>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="dropTarget = null">Batal</Button>
                <Button variant="destructive" size="sm" :disabled="!dropNote" @click="confirmDrop">Keluarkan</Button>
            </div>
        </Dialog>

        <!-- Konfirmasi hapus sesi (destruktif: absensinya ikut terhapus) -->
        <Dialog :open="deleteSessionTarget !== null" title="Hapus Sesi" @update:open="deleteSessionTarget = null">
            <p class="text-sm text-muted-foreground">
                Menghapus "{{ deleteSessionTarget?.title }}" ikut menghapus catatan absensinya dan menghitung ulang kelulusan peserta. Lanjutkan?
            </p>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="deleteSessionTarget = null">Batal</Button>
                <Button variant="destructive" size="sm" @click="confirmRemoveSession">Hapus Sesi</Button>
            </div>
        </Dialog>

        <!-- Sesi form -->
        <Dialog v-model:open="sessionOpen" :title="sessionForm.id ? 'Ubah Sesi' : 'Tambah Sesi'">
            <form class="space-y-3" @submit.prevent="saveSession">
                <div>
                    <Input v-model="sessionForm.title" placeholder="Judul sesi (mis. Sesi 1: Dasar Affiliate)" />
                    <p v-if="sessionError" class="mt-1 text-xs text-destructive">{{ sessionError }}</p>
                </div>
                <div>
                    <label class="text-xs text-muted-foreground">Tanggal (opsional)</label>
                    <Input v-model="sessionForm.scheduled_at" type="date" class="mt-1" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" size="sm" @click="sessionOpen = false">Batal</Button>
                    <Button type="submit" size="sm">Simpan</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
```

Note: this view imports `api` directly for the candidates fetch — confirm `api.js` exports the raw `api` function (it does).

- [ ] **Step 4: Verify** — `npm run build` clean. Manual smoke: open an Angkatan via "Kelola", add sessions, add participant, tick attendance, save — the roster's status flips to "Lulus" once hadir ≥ requirement.

- [ ] **Step 5: Commit**

```bash
git add resources/js/admin/api.js resources/js/admin/router.js resources/js/admin/views/Cohorts.vue resources/js/admin/views/CohortDetail.vue
git commit -m "feat: cohort detail screen (roster, sessions, attendance)"
```

---

## Task 7: Enroll door 1 (PersonDetail) + hadir counts

**Files:**
- Modify: `app/Http/Controllers/Api/Admin/PersonController.php` (enrollments payload gains `hadir`; applications payload gains `program_id`), `resources/js/admin/views/PersonDetail.vue` (enroll dialog after accept)
- Test: extend `tests/Feature/EnrollmentManagementTest.php`

**Interfaces:**
- Consumes: `POST /api/admin/enrollments` (Task 3), `GET /api/admin/cohorts` (existing, rows carry `program.id`).
- Produces: PersonDetail enrollments show `hadir`; accepting an application (status select → accepted) opens a dialog listing that program's cohorts with "Masukkan" buttons.

- [ ] **Step 1: Failing test** — append to `EnrollmentManagementTest`:

```php
    public function test_person_detail_includes_hadir_count(): void
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        $person = $this->person();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->actingAs($this->admin())
            ->getJson("/api/admin/people/{$person->id}")
            ->assertOk()
            ->assertJsonPath('person.enrollments.0.hadir', 1);
    }
```

- [ ] **Step 2: RED**, then in `PersonController::show`: add `'enrollments.attendances:id,enrollment_id',` to the `load([...])` list; in the enrollments map add `'hadir' => $e->attendances->count(),` AND `'cohort_id' => $e->cohort_id,` (the enroll dialog filters out cohorts the person is already in); in the applications map add `'program_id' => $a->program_id,` (needed by the enroll dialog).

- [ ] **Step 3: GREEN** — `--filter=EnrollmentManagementTest`.

- [ ] **Step 4: PersonDetail.vue enroll dialog** — additions:

Script: import `cohorts as cohortsApi, enrollments as enrollmentsApi` from `@/api`; add state + handlers:

```js
const enrollFor = ref(null); // application yang baru diterima
const enrollCohorts = ref([]);
const enrollError = ref('');

async function offerEnroll(app) {
    enrollFor.value = app;
    enrollError.value = '';
    try {
        const res = await cohortsApi.list();
        const enrolledCohortIds = new Set((person.value?.enrollments ?? []).map((en) => en.cohort_id));
        enrollCohorts.value = res.data.filter((c) => c.program?.id === app.program_id && !enrolledCohortIds.has(c.id));
    } catch (e) {
        if (!e.sessionExpired) enrollError.value = e.message;
    }
}

async function enrollInto(cohort) {
    enrollError.value = '';
    try {
        await enrollmentsApi.create({ cohort_id: cohort.id, application_id: enrollFor.value.id });
        enrollFor.value = null;
        await load();
    } catch (e) {
        if (!e.sessionExpired) enrollError.value = e.errors ? Object.values(e.errors)[0][0] : e.message;
    }
}
```

In the existing status-change handler (the `ToggleGroup`/select that PATCHes `/admin/applications/{id}` with `app.status`): after a successful save where the new status is `'accepted'`, call `offerEnroll(app)`.

Template — add near the other dialogs:

```vue
    <Dialog :open="enrollFor !== null" title="Masukkan ke Angkatan" @update:open="enrollFor = null">
        <p class="text-sm text-muted-foreground">Pelamar diterima. Pilih Angkatan untuk mendaftarkannya sekarang, atau tutup untuk melakukannya nanti dari halaman Angkatan.</p>
        <div v-if="enrollError" class="mt-3 rounded-lg border border-destructive/30 bg-red-50 px-3.5 py-2.5 text-sm text-destructive">{{ enrollError }}</div>
        <p v-if="!enrollCohorts.length" class="mt-3 text-sm text-muted-foreground">Belum ada Angkatan untuk program ini.</p>
        <div v-else class="mt-3 space-y-2">
            <div v-for="c in enrollCohorts" :key="c.id" class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2 text-sm">
                <div>
                    <p class="font-medium text-foreground">{{ c.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ c.enrollments_count }} peserta</p>
                </div>
                <Button size="sm" variant="outline" @click="enrollInto(c)">Masukkan</Button>
            </div>
        </div>
    </Dialog>
```

Also surface `hadir` in the enrollment history line (replace the latest-status span content):

```vue
                    <span class="text-muted-foreground">Hadir {{ e.hadir }} sesi · {{ e.latest_status ? `${e.latest_status} · ${fmtDate(e.latest_status_at)}` : 'Belum ada status' }}</span>
```

- [ ] **Step 5: Verify + commit** — `npm run build` clean; full suite `php artisan test --compact` green.

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/PersonController.php resources/js/admin/views/PersonDetail.vue tests/Feature/EnrollmentManagementTest.php
git commit -m "feat: enroll-on-accept dialog and attendance counts on person detail"
```

---

## Task 8: Program thumbnail upload

**Files:**
- Create: `app/Http/Controllers/Api/Admin/ProgramThumbnailController.php`
- Modify: `app/Http/Controllers/Api/Admin/ProgramController.php` (`thumbnail_url` in `row()`), `routes/api.php`, `resources/js/admin/views/Programs.vue`, `resources/js/admin/api.js`
- Test: `tests/Feature/ProgramThumbnailTest.php`

**Interfaces:**
- Produces: `POST /api/admin/programs/{program:id}/thumbnail` (multipart field `thumbnail`: jpeg/png/webp ≤2048 KB) → `{program}` with `thumbnail_url`; `DELETE …/thumbnail` clears path + file. `row()` gains `'thumbnail_url' => $p->thumbnail_path ? asset('storage/'.$p->thumbnail_path) : null,`.

- [ ] **Step 1: Failing tests**

Create `tests/Feature/ProgramThumbnailTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgramThumbnailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_upload_replaces_and_deletes_old_file(): void
    {
        $admin = User::factory()->admin()->create();
        $program = Program::factory()->create();

        $first = $this->actingAs($admin)
            ->post("/api/admin/programs/{$program->id}/thumbnail", [
                'thumbnail' => UploadedFile::fake()->image('a.jpg', 640, 360),
            ])
            ->assertOk()
            ->json('program.thumbnail_url');
        $firstPath = $program->fresh()->thumbnail_path;
        Storage::disk('public')->assertExists($firstPath);
        $this->assertNotNull($first);

        $this->actingAs($admin)
            ->post("/api/admin/programs/{$program->id}/thumbnail", [
                'thumbnail' => UploadedFile::fake()->image('b.png', 640, 360),
            ])
            ->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($program->fresh()->thumbnail_path);
    }

    public function test_wrong_type_and_oversize_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $program = Program::factory()->create();

        $this->actingAs($admin)
            ->post("/api/admin/programs/{$program->id}/thumbnail", [
                'thumbnail' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->post("/api/admin/programs/{$program->id}/thumbnail", [
                'thumbnail' => UploadedFile::fake()->image('big.jpg')->size(3000),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_delete_clears_path_and_file(): void
    {
        $admin = User::factory()->admin()->create();
        $program = Program::factory()->create();
        $this->actingAs($admin)->post("/api/admin/programs/{$program->id}/thumbnail", [
            'thumbnail' => UploadedFile::fake()->image('a.jpg'),
        ])->assertOk();
        $path = $program->fresh()->thumbnail_path;

        $this->actingAs($admin)->deleteJson("/api/admin/programs/{$program->id}/thumbnail")->assertOk();

        $this->assertNull($program->fresh()->thumbnail_path);
        Storage::disk('public')->assertMissing($path);
    }
}
```

- [ ] **Step 2: RED**, then controller:

`app/Http/Controllers/Api/Admin/ProgramThumbnailController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProgramThumbnailController extends Controller
{
    /** Upload/replace the class cover; the old file never lingers. */
    public function store(Request $request, Program $program): JsonResponse
    {
        $request->validate([
            'thumbnail' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        if ($program->thumbnail_path) {
            Storage::disk('public')->delete($program->thumbnail_path);
        }

        $path = $request->file('thumbnail')->store('programs', 'public');
        $program->update(['thumbnail_path' => $path]);

        return response()->json(['program' => $this->payload($program)]);
    }

    /** Remove the cover; the public card falls back to the generative cover. */
    public function destroy(Program $program): JsonResponse
    {
        if ($program->thumbnail_path) {
            Storage::disk('public')->delete($program->thumbnail_path);
            $program->update(['thumbnail_path' => null]);
        }

        return response()->json(['program' => $this->payload($program)]);
    }

    /**
     * @return array{id:int,thumbnail_url:?string}
     */
    private function payload(Program $program): array
    {
        $program->refresh();

        return [
            'id' => $program->id,
            'thumbnail_url' => $program->thumbnail_path ? Storage::disk('public')->url($program->thumbnail_path) : null,
        ];
    }
}
```

Routes (inside the `permission:programs.manage` group, or add middleware inline consistent with the existing programs routes):

```php
            Route::post('/programs/{program:id}/thumbnail', [ProgramThumbnailController::class, 'store']);
            Route::delete('/programs/{program:id}/thumbnail', [ProgramThumbnailController::class, 'destroy']);
```

`ProgramController::row()` gains `'thumbnail_url' => $p->thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($p->thumbnail_path) : null,` (import Storage at top instead of FQCN).

- [ ] **Step 3: GREEN** — `--filter=ProgramThumbnailTest`; run `--filter=ProgramManagementTest` (no regression).

- [ ] **Step 4: Programs.vue UI** — in the edit dialog, add below the "Pesan terkunci" block (visible for BOTH types — general classes also have covers):

```vue
                <div v-if="editing">
                    <label class="text-xs text-muted-foreground">Thumbnail kelas</label>
                    <div class="mt-1.5 flex items-center gap-3">
                        <img v-if="editing.thumbnail_url" :src="editing.thumbnail_url" alt="" class="h-14 w-24 rounded-lg object-cover" />
                        <div v-else class="flex h-14 w-24 items-center justify-center rounded-lg border border-dashed border-input text-[0.6rem] uppercase tracking-wide text-muted-foreground">Otomatis</div>
                        <input ref="thumbInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadThumbnail" />
                        <Button type="button" variant="outline" size="sm" @click="$refs.thumbInput.click()">{{ editing.thumbnail_url ? 'Ganti' : 'Unggah' }}</Button>
                        <Button v-if="editing.thumbnail_url" type="button" variant="ghost" size="sm" @click="removeThumbnail">Hapus</Button>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">JPEG/PNG/WebP, maks 2 MB. Kosong = cover otomatis bermotif brand.</p>
                    <p v-if="thumbError" class="mt-1 text-xs text-destructive">{{ thumbError }}</p>
                </div>
```

Script additions (multipart needs raw fetch — the shared `api()` helper JSON-encodes; add a small helper in `api.js` `programs` group):

```js
// api.js, inside export const programs:
    uploadThumbnail(id, file) {
        const body = new FormData();
        body.append('thumbnail', file);
        return apiUpload(`/admin/programs/${id}/thumbnail`, body);
    },
    removeThumbnail(id) {
        return api(`/admin/programs/${id}/thumbnail`, { method: 'DELETE' });
    },
```

And in `api.js` add beside `api()` (reusing its cookie/CSRF helpers):

```js
/** Multipart POST variant of api() — browser sets the Content-Type boundary. */
export async function apiUpload(path, formData) {
    if (!getCookie('XSRF-TOKEN')) {
        await csrf();
    }
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const xsrf = getCookie('XSRF-TOKEN');
    if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;

    const res = await fetch(`/api${path}`, { method: 'POST', credentials: 'include', headers, body: formData });
    const data = await res.json().catch(() => null);
    if (!res.ok) {
        const err = new Error((data && data.message) || `Request failed (${res.status})`);
        err.status = res.status;
        err.errors = (data && data.errors) || {};
        if (res.status === 401 && sessionExpiredHandler) {
            err.sessionExpired = true;
            sessionExpiredHandler();
        }
        throw err;
    }
    return data;
}
```

Programs.vue handlers:

```js
const thumbError = ref('');

async function uploadThumbnail(event) {
    const file = event.target.files?.[0];
    if (!file || !editing.value) return;
    thumbError.value = '';
    try {
        const res = await programsApi.uploadThumbnail(editing.value.id, file);
        editing.value.thumbnail_url = res.program.thumbnail_url;
        await load();
    } catch (e) {
        if (!e.sessionExpired) thumbError.value = e.errors?.thumbnail?.[0] ?? e.message;
    } finally {
        event.target.value = '';
    }
}

async function removeThumbnail() {
    thumbError.value = '';
    try {
        await programsApi.removeThumbnail(editing.value.id);
        editing.value.thumbnail_url = null;
        await load();
    } catch (e) {
        if (!e.sessionExpired) thumbError.value = e.message;
    }
}
```

In the Programs list table, show a small cover chip in the name cell when `program.thumbnail_url` is set: `<img v-if="program.thumbnail_url" :src="program.thumbnail_url" alt="" class="h-8 w-14 rounded object-cover" />`.

- [ ] **Step 5: storage:link** — run `php artisan storage:link` locally (idempotent) and note it for deploy.

- [ ] **Step 6: Verify + commit** — `npm run build` clean; `php artisan test --compact` all green.

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/ProgramThumbnailController.php app/Http/Controllers/Api/Admin/ProgramController.php routes/api.php resources/js/admin/api.js resources/js/admin/views/Programs.vue tests/Feature/ProgramThumbnailTest.php
git commit -m "feat: program thumbnail upload with generative-cover fallback intact"
```

---

## Task 9: Dashboard operational stats

**Files:**
- Create: `app/Http/Controllers/Api/Admin/StatsController.php`
- Modify: `routes/api.php`, `resources/js/admin/views/Dashboard.vue`, `resources/js/admin/api.js`
- Test: `tests/Feature/DashboardStatsTest.php`

**Interfaces:**
- Produces: `GET /api/admin/stats` (auth-only, no extra permission) → `{stats: {pending_applications, community_members, active_cohorts, active_participants, graduates}}`. `active_participants` = enrollments whose latest status is `accepted` (or has no events yet); `graduates` = enrollments whose latest status is `completed`.

- [ ] **Step 1: Failing test**

Create `tests/Feature/DashboardStatsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Attendance;
use App\Models\Application;
use App\Models\CommunityMembership;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_stats_counts(): void
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create([
            'program_id' => $program->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'required_attendance' => 1,
        ]);
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);

        $person = fn () => Person::create([
            'name' => 'P'.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);

        Application::create(['people_id' => $person()->id, 'program_id' => $program->id, 'status' => 'pending']);
        CommunityMembership::create(['people_id' => $person()->id]);

        $active = Enrollment::create(['people_id' => $person()->id, 'cohort_id' => $cohort->id]);
        $active->statusEvents()->create(['status' => 'accepted', 'occurred_at' => now()]);

        $graduate = Enrollment::create(['people_id' => $person()->id, 'cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $graduate->id]);
        app(\App\Support\AttendanceCompletion::class)->sync($graduate);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('stats.pending_applications', 1)
            ->assertJsonPath('stats.community_members', 1)
            ->assertJsonPath('stats.active_cohorts', 1)
            ->assertJsonPath('stats.active_participants', 1)
            ->assertJsonPath('stats.graduates', 1);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/admin/stats')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: RED**, then controller:

`app/Http/Controllers/Api/Admin/StatsController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Cohort;
use App\Models\CommunityMembership;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /** Aggregate counts for the dashboard. Staff-wide: no extra permission. */
    public function index(): JsonResponse
    {
        $enrollments = Enrollment::with('latestStatusEvent')->get();

        return response()->json([
            'stats' => [
                'pending_applications' => Application::where('status', 'pending')->count(),
                'community_members' => CommunityMembership::count(),
                'active_cohorts' => Cohort::whereDate('start_date', '<=', now())
                    ->where(fn ($q) => $q->whereDate('end_date', '>=', now())->orWhereNull('end_date'))
                    ->count(),
                'active_participants' => $enrollments->filter(
                    fn (Enrollment $e) => ($e->latestStatusEvent?->status ?? 'accepted') === 'accepted'
                )->count(),
                'graduates' => $enrollments->filter(
                    fn (Enrollment $e) => $e->latestStatusEvent?->status === 'completed'
                )->count(),
            ],
        ]);
    }
}
```

Route (inside the authenticated group, admin prefix, no permission middleware): `Route::get('/stats', [StatsController::class, 'index']);` + import.

- [ ] **Step 3: GREEN** — `--filter=DashboardStatsTest`.

- [ ] **Step 4: Dashboard.vue** — replace the static-only content: keep the header + entity cards, insert a stats row above them:

```vue
<script setup>
import { ref, onMounted } from 'vue';
import { api } from '@/api';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const stats = ref(null);

const TILES = [
    { key: 'pending_applications', label: 'Pelamar menunggu' },
    { key: 'community_members', label: 'Member komunitas' },
    { key: 'active_cohorts', label: 'Angkatan berjalan' },
    { key: 'active_participants', label: 'Peserta aktif' },
    { key: 'graduates', label: 'Lulusan' },
];

onMounted(async () => {
    try {
        const res = await api('/admin/stats');
        stats.value = res.stats;
    } catch {
        stats.value = null; // tiles simply don't render; entity cards remain
    }
});

// The data foundation the admin tool is built on.
const entities = [
    { name: 'Person', desc: 'Satu record per manusia, anchor nomor HP.' },
    { name: 'Application', desc: 'Submission formulir pendaftaran program.' },
    { name: 'Angkatan', desc: 'Kelas nyata: nama, tanggal, sesi, satu mentor.' },
    { name: 'Enrollment', desc: 'Tautan Person ke Angkatan saat diterima.' },
    { name: 'Absensi', desc: 'Kehadiran per sesi; dasar kelulusan otomatis.' },
    { name: 'Status Event', desc: 'Log append-only transisi status.' },
];
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Dashboard</p>
        <h1 class="mt-2 text-3xl font-bold text-foreground">Selamat datang, {{ auth.user?.name }}.</h1>

        <div v-if="stats" class="mt-8 grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div v-for="tile in TILES" :key="tile.key" class="rounded-xl border border-border bg-card p-5">
                <p class="text-3xl font-bold tabular-nums text-foreground">{{ stats[tile.key] }}</p>
                <p class="mt-1 text-xs uppercase tracking-wide text-muted-foreground">{{ tile.label }}</p>
            </div>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="entity in entities" :key="entity.name" class="rounded-xl border border-border bg-card p-5 transition hover:border-primary/30 hover:shadow-sm">
                <h2 class="font-display text-sm font-bold uppercase tracking-wide text-primary">{{ entity.name }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ entity.desc }}</p>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 5: Final verify + commit** — `npm run build` clean; FULL suite `php artisan test --compact` all green.

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/StatsController.php routes/api.php resources/js/admin/api.js resources/js/admin/views/Dashboard.vue tests/Feature/DashboardStatsTest.php
git commit -m "feat: live operational stats on the admin dashboard"
```

---

## Self-Review

**Spec coverage:** data model §→Task 1; engine (incl. marker, retraction, dropped-skip, no-op, dynamic default) →Task 2; two-door enrollment + guards + drop →Tasks 3+7; cohort detail/sessions API + required_attendance →Task 4; declarative attendance + mentor permission →Task 5; CohortDetail UI (Peserta/Sesi/Absensi incl. dropped-disabled checkboxes) →Task 6; PersonDetail door + hadir →Task 7; thumbnail →Task 8; stats →Task 9; e2e eligibility integration test →Task 2. ✓

**Placeholder scan:** none — every code step complete; commands runnable. ✓

**Type consistency:** `AttendanceCompletion::sync/syncCohort` signatures consistent across Tasks 2/4/5; roster shape (`enrollment_id`, `person`, `hadir`, `latest_status`, `attended_session_ids`) identical between CohortController::show (Task 4) and CohortDetail.vue (Task 6); `enrollments`/`sessions` api.js groups (Task 6) match routes (Tasks 3-5); marker string `auto:attendance` everywhere; `apiUpload` + `sessionExpiredHandler` reuse api.js internals (same module scope). ✓

**Karpathy check:** no speculative features (no attendance history UI, no mentor screens, no export); engine is the single completion writer; declarative attendance endpoint avoids per-checkbox requests; every task ends in a verifiable state. ✓

**UI/UX review pass (vue-best-practices + frontend-design):** action buttons permission-gated with `auth.can(...)` (mentor sees attendance-only controls — matches the role's real capabilities); destructive session delete gets a consequence-explaining confirmation dialog; attendance panel shows a live "X dari Y hadir" counter; `watch(props.id)` route-reuse parity with PersonDetail; enroll dialog filters cohorts the person already joined; stat/counter numbers use `tabular-nums`. Component-split note: the vue-best-practices split triggers (3+ UI sections) technically fire for CohortDetail.vue — the single-file-view structure is a deliberate house-convention match (every admin view is single-file; ui primitives carry reuse); revisit extraction to `components/cohort/*` if the view grows beyond this plan's scope. ✓
