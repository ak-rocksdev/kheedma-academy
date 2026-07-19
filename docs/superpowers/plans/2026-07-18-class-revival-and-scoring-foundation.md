# Class Revival & Scoring Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Revive multi-class cohorts (cohort = batch, session = kelas, venue per class) and lay the assignments/scoring data foundation with the score-based community gate.

**Architecture:** Two sequential parts on one branch. Part 1 (Spec 2 Phase A) moves venue fields from `cohorts` to `cohort_sessions`, stops the invisible auto-seeded session, and reworks admin + member UI to per-class blocks. Part 2 (Spec 1 Phase 1) adds `assignments` + `assignment_submissions` + `programs.min_average_score`, a pure `AssignmentScoring` rules class, and reworks `ProgramEligibility` to gate on score with attendance fallback. No UI for assignments yet (that is Spec 1 Phase 2/3, planned separately).

**Tech Stack:** Laravel 13 / PHP 8.4, PHPUnit 12 (NOT Pest), Vue 3 `<script setup>` admin SPA (shadcn-vue), Blade + Tailwind member area, MySQL.

**Specs:** `docs/superpowers/specs/2026-07-18-batch-cohorts-class-revival-rsvp-design.md` (Phase A only) and `docs/superpowers/specs/2026-07-17-assignments-grading-score-gate-design.md` (Phase 1 only).

## Global Constraints

- Code 100% English (identifiers, columns, routes, comments, commit messages); UI copy 100% Indonesian, warm "kamu" register, no em-dashes.
- PHPUnit classes only; if a test looks like Pest, it's wrong. Create tests with `php artisan make:test --phpunit --no-interaction {Name}`.
- After modifying PHP files run `vendor/bin/pint --dirty --format agent` before the final commit of each task.
- Never delete existing tests without approval — this plan MOVES several venue tests (delete here + recreate adapted there is expected and listed explicitly).
- Run the minimal filtered tests per step; full suite only where a step says so.
- Migrations via `php artisan make:migration --no-interaction`; factories for all new models.
- Frontend check: `npm run build` must succeed after Vue/Blade tasks.
- Commit after every task with the message given in the task.

---

# Part 1 — Class revival (Spec 2 Phase A)

### Task 1: Venue moves to `cohort_sessions` (migration + models + factories)

**Files:**
- Create: `database/migrations/2026_07_18_XXXXXX_move_venue_to_cohort_sessions_table.php` (timestamp from artisan)
- Modify: `app/Models/CohortSession.php`, `app/Models/Cohort.php`
- Modify: `database/factories/CohortSessionFactory.php`, `database/factories/CohortFactory.php`
- Test: `tests/Feature/CohortSessionTest.php` (add model tests), `tests/Feature/CohortModelTest.php` (remove moved tests)

**Interfaces:**
- Consumes: existing `Cohort` venue columns/helpers.
- Produces: `CohortSession` with fillable `type, location_name, location_address, location_lat, location_lng, meeting_url` and methods `isOnline(): bool`, `mapsUrl(): ?string`, `mapsEmbedUrl(): ?string`, `mapsDirectionsUrl(): ?string`, `googleCalendarUrl(): ?string`, `scheduledLabel(): ?string`, `countdownLabel(): ?string`, `startsWithinHours(int $hours): bool`. `Cohort` KEEPS `start_date` schedule helpers (`startLabel`, `startCountdownLabel`, `startsWithinHours`) and `materials_url`; LOSES `type`, location fields, `meeting_url`, `isOnline()`, all maps helpers, `googleCalendarUrl()`.

- [ ] **Step 1: Write failing session model tests** — append to `tests/Feature/CohortSessionTest.php`:

```php
public function test_session_maps_urls_follow_the_coordinates(): void
{
    $cohort = Cohort::factory()->create();
    $session = CohortSession::factory()->for($cohort)->atLocation()->create();

    $this->assertSame(
        'https://www.google.com/maps/search/?api=1&query=-7.5755,110.8317',
        $session->mapsUrl()
    );
    $this->assertStringContainsString('output=embed', $session->mapsEmbedUrl());
    $this->assertStringContainsString('maps/dir', $session->mapsDirectionsUrl());
}

public function test_session_without_coordinates_has_no_maps_urls(): void
{
    $session = CohortSession::factory()->for(Cohort::factory())->create();

    $this->assertNull($session->mapsUrl());
    $this->assertNull($session->mapsEmbedUrl());
    $this->assertNull($session->mapsDirectionsUrl());
}

public function test_session_is_online_by_type(): void
{
    $cohort = Cohort::factory()->create();

    $this->assertTrue(CohortSession::factory()->for($cohort)->online()->create()->isOnline());
    $this->assertFalse(CohortSession::factory()->for($cohort)->atLocation()->create()->isOnline());
}

public function test_session_google_calendar_url_needs_a_real_start_time(): void
{
    $cohort = Cohort::factory()->create();
    // Single-word title: http_build_query encodes spaces as '+', so a
    // spaceless title keeps the containment assertion encoding-proof.
    $timed = CohortSession::factory()->for($cohort)->online()->create(['title' => 'Onboarding', 'scheduled_at' => '2026-08-08 09:30:00']);
    $midnight = CohortSession::factory()->for($cohort)->create(['scheduled_at' => '2026-08-08 00:00:00']);
    $bare = CohortSession::factory()->for($cohort)->create(['scheduled_at' => null]);

    $this->assertStringContainsString('calendar.google.com', $timed->googleCalendarUrl());
    $this->assertStringContainsString('Onboarding', $timed->googleCalendarUrl());
    $this->assertNull($midnight->googleCalendarUrl());
    $this->assertNull($bare->googleCalendarUrl());
}

public function test_session_scheduled_label_includes_time_when_not_midnight(): void
{
    $cohort = Cohort::factory()->create();
    $session = CohortSession::factory()->for($cohort)->create(['scheduled_at' => '2026-08-08 09:30:00']);

    $this->assertSame('8 Agustus 2026 pukul 09.30 WIB', $session->scheduledLabel());
    $this->assertNull(CohortSession::factory()->for($cohort)->create(['scheduled_at' => null])->scheduledLabel());
}
```

Add missing imports at the top of the test class file if absent: `use App\Models\Cohort;` `use App\Models\CohortSession;`.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact tests/Feature/CohortSessionTest.php`
Expected: FAIL — `atLocation`/`online` states and helper methods undefined.

- [ ] **Step 3: Create the migration**

Run: `php artisan make:migration move_venue_to_cohort_sessions_table --no-interaction`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cohort = batch, session = class (spec 2026-07-18). Venue is a property
     * of the meeting, not the batch — a Saturday offline class can move to a
     * Friday-night online one. Values are copied onto ALL existing sessions
     * (venue was cohort-wide truth until now), then the cohort columns drop.
     */
    public function up(): void
    {
        Schema::table('cohort_sessions', function (Blueprint $table) {
            $table->string('type')->nullable(); // 'offline' | 'online'
            $table->string('location_name')->nullable();
            $table->string('location_address')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->string('meeting_url')->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE cohort_sessions cs
            JOIN cohorts c ON c.id = cs.cohort_id
            SET cs.type = c.type,
                cs.location_name = c.location_name,
                cs.location_address = c.location_address,
                cs.location_lat = c.location_lat,
                cs.location_lng = c.location_lng,
                cs.meeting_url = c.meeting_url
        SQL);

        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropColumn(['type', 'location_name', 'location_address', 'location_lat', 'location_lng', 'meeting_url']);
        });
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->string('type')->default('offline');
            $table->string('location_name')->nullable();
            $table->string('location_address')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->string('meeting_url')->nullable();
        });

        // Best effort: restore each cohort's venue from its first session.
        DB::statement(<<<'SQL'
            UPDATE cohorts c
            JOIN cohort_sessions cs ON cs.id = (
                SELECT id FROM cohort_sessions WHERE cohort_id = c.id ORDER BY position, id LIMIT 1
            )
            SET c.type = COALESCE(cs.type, 'offline'),
                c.location_name = cs.location_name,
                c.location_address = cs.location_address,
                c.location_lat = cs.location_lat,
                c.location_lng = cs.location_lng,
                c.meeting_url = cs.meeting_url
        SQL);

        Schema::table('cohort_sessions', function (Blueprint $table) {
            $table->dropColumn(['type', 'location_name', 'location_address', 'location_lat', 'location_lng', 'meeting_url']);
        });
    }
};
```

Run: `php artisan migrate --no-interaction` — expected: migration runs clean.

Note: the data copy cannot be exercised by `RefreshDatabase` tests (they migrate an empty
schema). Verify it by hand once against local data, e.g.:
`php artisan tinker --execute 'echo \App\Models\CohortSession::whereNotNull("type")->count();'`
should match the number of sessions whose cohorts carried a venue. The same manual check
belongs on the staging/production backup before deploy (see the closing deploy note).

- [ ] **Step 4: Update `CohortSession` model** — replace the whole file body with:

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
        'type',
        'location_name',
        'location_address',
        'location_lat',
        'location_lng',
        'meeting_url',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'location_lat' => 'float',
            'location_lng' => 'float',
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

    public function isOnline(): bool
    {
        return $this->type === 'online';
    }

    /** Universal Google Maps link for members — no API call involved. */
    public function mapsUrl(): ?string
    {
        if ($this->location_lat === null || $this->location_lng === null) {
            return null;
        }

        return "https://www.google.com/maps/search/?api=1&query={$this->location_lat},{$this->location_lng}";
    }

    /** Keyless Google Maps iframe embed for the member area's collapsible map. */
    public function mapsEmbedUrl(): ?string
    {
        if ($this->location_lat === null || $this->location_lng === null) {
            return null;
        }

        return "https://maps.google.com/maps?q={$this->location_lat},{$this->location_lng}&z=16&output=embed";
    }

    /** Universal directions link — opens the Google Maps app with a route on mobile. */
    public function mapsDirectionsUrl(): ?string
    {
        if ($this->location_lat === null || $this->location_lng === null) {
            return null;
        }

        return "https://www.google.com/maps/dir/?api=1&destination={$this->location_lat},{$this->location_lng}";
    }

    /** Class starts within the next N hours (false once it has started). */
    public function startsWithinHours(int $hours): bool
    {
        return $this->scheduled_at !== null
            && $this->scheduled_at->isFuture()
            && now()->diffInHours($this->scheduled_at) <= $hours;
    }

    /**
     * Prefilled Google Calendar event (assumed 2-hour class) — a template URL,
     * no API involved. Null without a real start time: a 00.00 event would
     * mislead.
     */
    public function googleCalendarUrl(): ?string
    {
        if (! $this->scheduled_at || $this->scheduled_at->format('H:i') === '00:00') {
            return null;
        }

        $location = $this->isOnline()
            ? ($this->meeting_url ?? 'Online')
            : trim(($this->location_name ? "{$this->location_name}, " : '').($this->location_address ?? ''), ', ');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => trim(($this->cohort?->program?->name ?? 'Kelas Kheedma Academy').' · '.$this->title),
            'dates' => $this->scheduled_at->format('Ymd\THis').'/'.$this->scheduled_at->copy()->addHours(2)->format('Ymd\THis'),
            'ctz' => 'Asia/Jakarta',
            'location' => $location,
        ]);
    }

    /** Human schedule for member-facing surfaces; clock hidden at midnight. */
    public function scheduledLabel(): ?string
    {
        if ($this->scheduled_at === null) {
            return null;
        }

        $date = $this->scheduled_at->locale('id')->translatedFormat('j F Y');

        if ($this->scheduled_at->format('H:i') === '00:00') {
            return $date;
        }

        return $date.' pukul '.$this->scheduled_at->format('H.i').' WIB';
    }

    /**
     * "Hari ini" / "Besok" / "N hari lagi" inside the final week before the
     * class; null outside that window (goal gradient, mirrors the old
     * cohort-level countdown).
     */
    public function countdownLabel(): ?string
    {
        if (! $this->scheduled_at || $this->scheduled_at->isPast()) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($this->scheduled_at->copy()->startOfDay());

        return match (true) {
            $days === 0 => 'Hari ini',
            $days === 1 => 'Besok',
            $days <= 7 => "{$days} hari lagi",
            default => null,
        };
    }
}
```

- [ ] **Step 5: Slim `Cohort` model** — in `app/Models/Cohort.php` remove from `$fillable`: `'type'`, `'location_name'`, `'location_address'`, `'location_lat'`, `'location_lng'`, `'meeting_url'` (KEEP `'materials_url'`). Remove from `casts()`: `'location_lat'`, `'location_lng'`. Delete methods: `isOnline()`, `mapsUrl()`, `mapsEmbedUrl()`, `mapsDirectionsUrl()`, `googleCalendarUrl()`. KEEP `startCountdownLabel()`, `startsWithinHours()`, `startLabel()` (batch-start display still uses them).

- [ ] **Step 6: Move factory states** — in `database/factories/CohortFactory.php` delete the `online()` and `atLocation()` states. In `database/factories/CohortSessionFactory.php` add:

```php
public function online(): static
{
    return $this->state(fn () => [
        'type' => 'online',
        'meeting_url' => 'https://meet.google.com/'.fake()->lexify('???-????-???'),
    ]);
}

public function atLocation(): static
{
    return $this->state(fn () => [
        'type' => 'offline',
        'location_name' => 'Kantor Kheedma Indonesia',
        'location_address' => 'Jl. Kapten Mulyadi, Pasar Kliwon, Surakarta',
        'location_lat' => -7.5755,
        'location_lng' => 110.8317,
    ]);
}
```

- [ ] **Step 7: Delete the moved venue tests from `CohortModelTest`** — remove these methods (they are recreated session-side in Step 1): `test_maps_url_requires_both_coordinates`, `test_google_calendar_url_needs_a_real_start_time`, `test_maps_embed_and_directions_urls_follow_the_coordinates`, `test_is_online_by_type`. KEEP all window/start-label/countdown tests.

- [ ] **Step 8: Run both files**

Run: `php artisan test --compact tests/Feature/CohortSessionTest.php tests/Feature/CohortModelTest.php`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add -A && git commit -m "feat: venue moves from cohorts to cohort_sessions (cohort=batch, session=class)"
```

---

### Task 2: API — session venue validation, no auto-seed, slimmer cohort payload

**Files:**
- Modify: `app/Http/Controllers/Api/Admin/CohortSessionController.php`
- Modify: `app/Http/Controllers/Api/Admin/CohortController.php`
- Test: `tests/Feature/CohortSessionTest.php` (add API tests), `tests/Feature/CohortManagementTest.php` (adapt)

**Interfaces:**
- Produces: session API row `{id, title, scheduled_at, position, type, location_name, location_address, location_lat, location_lng, meeting_url, maps_url}`; cohort API row loses `type/location_*/meeting_url/maps_url` (keeps `materials_url`); `POST /admin/cohorts` no longer creates a session.

- [ ] **Step 1: Write failing API tests** — append to `tests/Feature/CohortSessionTest.php`. The file already seeds Role+Permission seeders in `setUp()` and has an `admin()` helper — every request below MUST go through `$this->actingAs($this->admin())` like its existing tests. ALSO adapt the existing `test_admin_can_manage_sessions`: its create payload gains `'type' => 'online'` (type becomes required on create in Step 3).

```php
public function test_offline_session_requires_location_fields(): void
{
    $cohort = Cohort::factory()->create();

    $this->actingAs($this->admin())->postJson("/api/admin/cohorts/{$cohort->id}/sessions", [
        'title' => 'Kelas 1: Riset Produk',
        'type' => 'offline',
    ])->assertUnprocessable()->assertJsonValidationErrors(['location_address', 'location_lat', 'location_lng']);
}

public function test_online_session_stores_venue_and_returns_it(): void
{
    $cohort = Cohort::factory()->create();

    $res = $this->actingAs($this->admin())->postJson("/api/admin/cohorts/{$cohort->id}/sessions", [
        'title' => 'Kelas 2: Konten',
        'scheduled_at' => '2026-08-15T09:30',
        'type' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
    ])->assertCreated();

    $res->assertJsonPath('session.type', 'online')
        ->assertJsonPath('session.meeting_url', 'https://meet.google.com/abc-defg-hij');
}

public function test_session_meeting_url_must_be_https(): void
{
    $cohort = Cohort::factory()->create();

    $this->actingAs($this->admin())->postJson("/api/admin/cohorts/{$cohort->id}/sessions", [
        'title' => 'Kelas',
        'type' => 'online',
        'meeting_url' => 'http://insecure.example',
    ])->assertUnprocessable()->assertJsonValidationErrors(['meeting_url']);
}

public function test_legacy_session_without_location_can_update_title_alone(): void
{
    $cohort = Cohort::factory()->create();
    $session = CohortSession::factory()->for($cohort)->create(['type' => 'offline']);

    $this->actingAs($this->admin())->patchJson("/api/admin/sessions/{$session->id}", ['title' => 'Judul Baru'])
        ->assertOk()->assertJsonPath('session.title', 'Judul Baru');
}

public function test_mentor_cannot_manage_classes(): void
{
    // Class CRUD sits under cohorts.manage (admin); mentors only record
    // attendance (spec 2026-07-18). Roles are already seeded by setUp().
    $mentor = User::factory()->create();
    $mentor->assignRole('mentor');
    $cohort = Cohort::factory()->create();

    $this->actingAs($mentor)
        ->postJson("/api/admin/cohorts/{$cohort->id}/sessions", ['title' => 'Kelas', 'type' => 'online'])
        ->assertForbidden();
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact tests/Feature/CohortSessionTest.php`
Expected: FAIL — validation errors missing / unknown JSON paths.

- [ ] **Step 3: Extend `CohortSessionController`** — replace `store`, `update`, and `row` with (ports the exact requiredIf semantics from `CohortController::validated`):

```php
public function store(Request $request, Cohort $cohort): JsonResponse
{
    $session = $cohort->sessions()->create($this->validated($request));

    return response()->json(['session' => $this->row($session)], 201);
}

public function update(Request $request, CohortSession $session): JsonResponse
{
    $session->update($this->validated($request, $session));

    return response()->json(['session' => $this->row($session->fresh())]);
}

/**
 * Venue rules mirror the old cohort-level ones (spec 2026-07-18): offline
 * requires a picked location; partial updates on legacy venueless sessions
 * must not brick.
 *
 * @return array<string, mixed>
 */
private function validated(Request $request, ?CohortSession $session = null): array
{
    $creating = $session === null;

    $isOffline = fn () => $request->input('type', $session?->type ?? 'offline') === 'offline';
    $locationTouched = $request->hasAny(['type', 'location_address', 'location_lat', 'location_lng']);
    $locationRequiredness = $locationTouched ? [Rule::requiredIf($isOffline), 'nullable'] : ['nullable'];

    return $request->validate([
        'title' => $creating ? ['required', 'string', 'max:255'] : ['sometimes', 'required', 'string', 'max:255'],
        'scheduled_at' => ['sometimes', 'nullable', 'date'],
        'position' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
        'type' => [$creating ? 'required' : 'sometimes', 'required', Rule::in(['offline', 'online'])],
        'location_name' => ['nullable', 'string', 'max:255'],
        'location_address' => [...$locationRequiredness, 'string', 'max:500'],
        'location_lat' => [...$locationRequiredness, 'numeric', 'between:-90,90'],
        'location_lng' => [...$locationRequiredness, 'numeric', 'between:-180,180'],
        'meeting_url' => ['nullable', 'url:https', 'max:500'],
    ], [
        'location_address.required_if' => 'Kelas offline butuh alamat lokasi.',
        'location_lat.required_if' => 'Pilih titik lokasi dari pencarian tempat.',
        'location_lng.required_if' => 'Pilih titik lokasi dari pencarian tempat.',
    ]);
}

/**
 * @return array<string, mixed>
 */
private function row(CohortSession $s): array
{
    return [
        'id' => $s->id,
        'title' => $s->title,
        'scheduled_at' => $s->scheduled_at?->toIso8601String(),
        'position' => (int) $s->position,
        'type' => $s->type,
        'location_name' => $s->location_name,
        'location_address' => $s->location_address,
        'location_lat' => $s->location_lat,
        'location_lng' => $s->location_lng,
        'meeting_url' => $s->meeting_url,
        'maps_url' => $s->mapsUrl(),
    ];
}
```

Add `use Illuminate\Validation\Rule;` to the imports. Note `test_legacy_session_without_location_can_update_title_alone` exercises the untouched-venue path.

- [ ] **Step 4: Slim `CohortController`** — three edits:

1. In `store()` delete the auto-seed block and its comment (the `$cohort->sessions()->create([...])` call). Leave a one-line comment in its place:

```php
// Spec 2026-07-18: cohort = batch. Classes (sessions) are created
// explicitly by the admin, one per meeting.
```

2. In `validated()` delete the `$isOffline`/`$locationTouched`/`$locationRequiredness` lines and the rules for `type`, `location_name`, `location_address`, `location_lat`, `location_lng`, `meeting_url`, plus the three custom messages (keep `materials_url`).
3. In `row()` delete the keys `type`, `location_name`, `location_address`, `location_lat`, `location_lng`, `meeting_url`, `maps_url` (keep `materials_url`) and update the PHPDoc shape line. In `show()`, make the sessions payload reuse the session shape by adding the venue keys:

```php
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
]),
```

- [ ] **Step 5: Adapt `CohortManagementTest`** — three changes:

1. Rename `test_creating_a_cohort_seeds_one_default_session` to `test_creating_a_cohort_seeds_no_sessions` and invert the body's assertion to:

```php
$this->assertSame(0, Cohort::find($cohortId)->sessions()->count());
```

(keep the creation request part of the body as-is).
2. Delete these now-session-side tests (recreated in Step 1 of this task): `test_offline_cohort_requires_location_fields`, `test_online_cohort_can_be_created_with_only_meeting_url`, `test_meeting_url_must_be_https`, `test_meeting_url_rejects_garbage_values`, `test_meeting_url_can_be_changed_later`, `test_cohort_payload_includes_type_and_maps_url_for_a_located_cohort`, `test_legacy_offline_cohort_without_location_can_update_name_alone`.
3. Any remaining creation payloads in this file that send `type`/location keys: remove those keys.

- [ ] **Step 6: Run affected files**

Run: `php artisan test --compact tests/Feature/CohortSessionTest.php tests/Feature/CohortManagementTest.php tests/Feature/AttendanceRecordingTest.php tests/Feature/EnrollmentManagementTest.php`
Expected: PASS (attendance/enrollment tests confirm nothing else depended on the auto-seeded session — if one did, it creates its session explicitly with `CohortSession::factory()`).

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: session venue API, cohorts stop auto-seeding their single session"
```

---

### Task 3: Admin dialogs — slim CohortFormDialog, new SessionFormDialog

**Files:**
- Modify: `resources/js/admin/components/CohortFormDialog.vue`
- Create: `resources/js/admin/components/SessionFormDialog.vue`

**Interfaces:**
- Consumes: `sessions.create(cohortId, payload)` / `sessions.update(id, payload)` from `resources/js/admin/api.js` (already exist).
- Produces: `<SessionFormDialog v-model:open :cohort-id :session @saved>` — `session` prop is the API session row (null = create mode); emits `saved` with the saved row.

- [ ] **Step 1: Slim `CohortFormDialog.vue`** — remove: the `TYPE_OPTIONS` const, `setType()`, the `LocationPicker` import, the `type`/`location`/`meeting_url` keys from the form seed and payload (KEEP `materials_url`), and the whole "Tipe kelas" + location/meeting template blocks (the `div` with the Tipe kelas ToggleGroup, the `v-if="form.type === 'offline'"` block, and its `v-else` meeting-url block). Keep the "Link materi" block.

- [ ] **Step 2: Create `SessionFormDialog.vue`**:

```vue
<script setup>
import { ref, computed, watch } from 'vue';
import { sessions as sessionsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { DateTimePicker } from '@/components/ui/date-picker';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { Alert } from '@/components/ui/alert';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import LocationPicker from '@/components/LocationPicker.vue';
import { toDatetimeLocal } from '@/lib/format';

const TYPE_OPTIONS = [
    { value: 'offline', label: 'Offline (tatap muka)' },
    { value: 'online', label: 'Online' },
];

const props = defineProps({
    cohortId: { type: [String, Number], required: true },
    /** Session row (API shape) to edit; null opens the dialog in create mode. */
    session: { type: Object, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['saved']);

const isEditing = computed(() => props.session !== null);

const form = ref({});
const formErrors = ref({});
const saving = ref(false);

/** ToggleGroup can deselect to empty; class type is mandatory, so ignore that. */
function setType(value) {
    if (value) form.value.type = value;
}

// Every open re-seeds the form so a reopened dialog never shows stale values.
watch(open, (isOpen) => {
    if (!isOpen) return;
    form.value = {
        title: props.session?.title ?? '',
        scheduled_at: toDatetimeLocal(props.session?.scheduled_at),
        type: props.session?.type ?? 'offline',
        location: {
            name: props.session?.location_name ?? '',
            address: props.session?.location_address ?? '',
            lat: props.session?.location_lat ?? null,
            lng: props.session?.location_lng ?? null,
        },
        meeting_url: props.session?.meeting_url ?? '',
    };
    formErrors.value = {};
});

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = {
            title: form.value.title,
            scheduled_at: form.value.scheduled_at || null,
            type: form.value.type,
            location_name: form.value.location.name || null,
            location_address: form.value.location.address || null,
            location_lat: form.value.location.lat === '' ? null : form.value.location.lat ?? null,
            location_lng: form.value.location.lng === '' ? null : form.value.location.lng ?? null,
            meeting_url: form.value.meeting_url || null,
        };
        const res = isEditing.value
            ? await sessionsApi.update(props.session.id, payload)
            : await sessionsApi.create(props.cohortId, payload);
        open.value = false;
        emit('saved', res.session);
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
    <Dialog v-model:open="open" :title="isEditing ? 'Ubah Kelas' : 'Tambah Kelas'">
        <form @submit.prevent="save" class="space-y-3">
            <div>
                <label class="text-xs text-muted-foreground">Judul kelas</label>
                <Input v-model="form.title" placeholder="Contoh: Kelas 1: Riset Produk" class="mt-1.5" />
                <p v-if="formErrors.title" class="mt-1 text-xs text-destructive">{{ formErrors.title[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Jadwal</label>
                <DateTimePicker v-model="form.scheduled_at" clearable placeholder="Pilih tanggal & jam" class="mt-1.5" />
                <p v-if="formErrors.scheduled_at" class="mt-1 text-xs text-destructive">{{ formErrors.scheduled_at[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Tipe kelas</label>
                <ToggleGroup type="single" variant="outline" class="mt-1.5 w-full" :model-value="form.type" @update:model-value="setType">
                    <ToggleGroupItem v-for="option in TYPE_OPTIONS" :key="option.value" :value="option.value" class="flex-1">
                        {{ option.label }}
                    </ToggleGroupItem>
                </ToggleGroup>
                <p v-if="formErrors.type" class="mt-1 text-xs text-destructive">{{ formErrors.type[0] }}</p>
            </div>
            <div v-if="form.type === 'offline'">
                <label class="text-xs text-muted-foreground">Lokasi kelas</label>
                <LocationPicker v-model="form.location" class="mt-1.5" />
                <p v-if="formErrors.location_address" class="mt-1 text-xs text-destructive">{{ formErrors.location_address[0] }}</p>
                <p v-if="formErrors.location_lat" class="mt-1 text-xs text-destructive">{{ formErrors.location_lat[0] }}</p>
                <p v-if="formErrors.location_lng" class="mt-1 text-xs text-destructive">{{ formErrors.location_lng[0] }}</p>
            </div>
            <div v-else>
                <label class="text-xs text-muted-foreground">Link meeting (Google Meet / Zoom)</label>
                <Input v-model="form.meeting_url" placeholder="https://meet.google.com/…" class="mt-1.5" />
                <p class="mt-1 text-xs text-muted-foreground">Opsional. Bisa kamu isi atau ubah kapan saja.</p>
                <p v-if="formErrors.meeting_url" class="mt-1 text-xs text-destructive">{{ formErrors.meeting_url[0] }}</p>
            </div>
            <div class="mt-4 flex justify-end gap-2 border-t border-border pt-4">
                <Button type="button" variant="outline" size="sm" @click="open = false">Batal</Button>
                <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
            </div>
        </form>
    </Dialog>
</template>
```

- [ ] **Step 3: Build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat: class dialog carries the venue; cohort form slims to batch fields"
```

---

### Task 4: Admin `CohortDetail.vue` — class blocks + per-class attendance

**Files:**
- Modify: `resources/js/admin/views/CohortDetail.vue`

**Interfaces:**
- Consumes: session rows with venue from Task 2, `SessionFormDialog` from Task 3, existing `sessions.remove(id)` / `sessions.setAttendance(id, ids)`.
- Produces: the class-block layout Spec 1 Phase 2 will later mount assignment cards into.

- [ ] **Step 1: Rework the script** — replace the `mainSession` concept with a selected-class model. In the `<script setup>` section:

1. Replace the `mainSession` computed and its comment with:

```js
// Spec 2026-07-18: cohort = batch, each session = one class. Attendance and
// the info-copy toolset operate on the selected class.
const selectedSessionId = ref(null);
const selectedSession = computed(
    () => sessionList.value.find((s) => s.id === selectedSessionId.value) ?? null
);

/** First upcoming class, else the last one — the most actionable default. */
function defaultSessionId() {
    const now = new Date();
    const upcoming = sessionList.value.find((s) => s.scheduled_at && new Date(s.scheduled_at) >= now);
    return (upcoming ?? sessionList.value[sessionList.value.length - 1])?.id ?? null;
}

// Class dialog state
const sessionFormOpen = ref(false);
const sessionBeingEdited = ref(null);
const deleteTarget = ref(null);
const deleteError = ref('');

function openCreateSession() {
    sessionBeingEdited.value = null;
    sessionFormOpen.value = true;
}

function openEditSession(session) {
    sessionBeingEdited.value = session;
    sessionFormOpen.value = true;
}

async function confirmDeleteSession() {
    deleteError.value = '';
    try {
        await sessionsApi.remove(deleteTarget.value.id);
        deleteTarget.value = null;
        await load();
    } catch (e) {
        if (!e.sessionExpired) deleteError.value = e.message ?? 'Gagal menghapus kelas.';
    }
}
```

2. In `load()`, after `roster.value = res.roster;` add:

```js
if (!sessionList.value.some((s) => s.id === selectedSessionId.value)) {
    selectedSessionId.value = defaultSessionId();
}
```

3. Update `hadirCount` to use `selectedSession`:

```js
const hadirCount = computed(() =>
    selectedSession.value ? roster.value.filter((r) => isHadir(r, selectedSession.value)).length : 0
);
```

4. Rework `copyClassInfo` to read the selected class (schedule + venue per class, materials from the cohort):

```js
async function copyClassInfo() {
    const c = cohort.value;
    const s = selectedSession.value;
    const lines = [
        [`${c.program?.name ?? ''} · ${c.name}`.replace(/^ · /, ''), s?.title].filter(Boolean).join(' — '),
    ];
    if (s?.scheduled_at) lines.push(`Jadwal: ${fmtDateTime(s.scheduled_at)} WIB`);
    if (s?.type === 'offline') {
        const place = [s.location_name, s.location_address].filter(Boolean).join(', ');
        if (place) lines.push(`Lokasi: ${place}`);
        if (s.maps_url) lines.push(`Peta: ${s.maps_url}`);
    } else if (s?.meeting_url) {
        lines.push(`Link meeting: ${s.meeting_url}`);
    }
    if (c.materials_url) lines.push(`Materi: ${c.materials_url}`);

    if (await copyText(lines.join('\n'))) {
        copiedInfo.value = true;
        setTimeout(() => (copiedInfo.value = false), 1800);
    }
}
```

5. Add imports: `Plus` to the lucide import list, and `import SessionFormDialog from '@/components/SessionFormDialog.vue';`.

- [ ] **Step 2: Rework the template** — the middle segment of the "Tiket kelas" card (the `div` with the Video/MapPin tile) stops showing venue (that now lives per class) and becomes a "Materi & info" segment, so the materials link and the copy button keep their home and the `md:grid-cols-[1fr_1.35fr_1fr]` grid stays intact. Replace that middle segment's inner content with:

```html
<span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-100 via-sand-50 to-orange-200/70 ring-1 ring-inset ring-teal-900/10">
    <FileText class="size-5 text-teal-700" />
</span>
<div class="min-w-0 flex-1 text-sm">
    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Materi & info</p>
    <a
        v-if="cohort.materials_url"
        :href="cohort.materials_url"
        target="_blank"
        rel="noopener"
        class="mt-0.5 inline-flex items-center gap-1 font-semibold text-teal-700 hover:underline"
    >
        <FileText class="size-3.5" /> Materi kelas
    </a>
    <p v-else class="mt-0.5 text-muted-foreground/60 italic">Materi belum diisi.</p>
    <div class="mt-2">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-full border border-border px-3 py-1 text-xs font-semibold text-teal-700 transition hover:border-teal-600/50 hover:bg-accent"
            @click="copyClassInfo"
        >
            <Check v-if="copiedInfo" class="size-3.5" />
            <Copy v-else class="size-3.5" />
            {{ copiedInfo ? 'Tersalin!' : 'Salin info kelas terpilih' }}
        </button>
    </div>
</div>
```

(`Video`/`MapPin`/`ExternalLink` imports drop from the lucide list if now unused.) Then insert the class list between the ticket card and the roster card:

```html
<!-- Daftar kelas: satu blok per pertemuan; blok terpilih memegang absensi. -->
<div class="mt-6">
    <div class="flex items-center justify-between">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kelas</p>
        <Button v-if="auth.can('cohorts.manage')" variant="outline" size="sm" @click="openCreateSession">
            <Plus class="mr-1 h-3.5 w-3.5" /> Tambah kelas
        </Button>
    </div>

    <div v-if="!sessionList.length" class="mt-3 rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
        Belum ada kelas. Tambahkan kelas pertama untuk mulai mencatat kehadiran.
    </div>

    <div v-else class="mt-3 grid gap-2 md:grid-cols-2">
        <button
            v-for="session in sessionList"
            :key="session.id"
            type="button"
            class="rounded-xl border px-4 py-3 text-left transition"
            :class="session.id === selectedSessionId
                ? 'border-teal-600 bg-teal-600/5 ring-1 ring-teal-600'
                : 'border-border bg-card hover:border-teal-600/50'"
            @click="selectedSessionId = session.id"
        >
            <div class="flex items-start justify-between gap-2">
                <p class="min-w-0 truncate text-sm font-semibold text-foreground">{{ session.title }}</p>
                <Badge variant="secondary" class="shrink-0">
                    {{ session.type === 'online' ? 'Online' : 'Offline' }}
                </Badge>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ session.scheduled_at ? fmtDateTime(session.scheduled_at) + ' WIB' : 'Jadwal belum diatur' }}
                · {{ session.attendances_count }} hadir
            </p>
            <p v-if="session.type === 'offline' && session.location_name" class="mt-0.5 truncate text-xs text-muted-foreground">
                {{ session.location_name }}
            </p>
            <div v-if="auth.can('cohorts.manage')" class="mt-2 flex gap-1.5">
                <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click.stop="openEditSession(session)">
                    <Pencil class="mr-1 h-3 w-3" /> Ubah
                </Button>
                <Button variant="ghost" size="sm" class="h-7 px-2 text-xs text-destructive" @click.stop="deleteTarget = session; deleteError = ''">
                    <Trash2 class="mr-1 h-3 w-3" /> Hapus
                </Button>
            </div>
        </button>
    </div>
</div>
```

In the roster card, replace every `mainSession` reference with `selectedSession` (header `v-if`, the hadir button `v-if`/`:class`/`@click`), and change the "Daftar hadir" header text to include the class: `Daftar hadir — {{ selectedSession.title }}`.

- [ ] **Step 3: Add the dialogs at the bottom of the template** (next to the existing dialogs):

```html
<SessionFormDialog
    v-model:open="sessionFormOpen"
    :cohort-id="cohort.id"
    :session="sessionBeingEdited"
    @saved="load"
/>

<Dialog :open="deleteTarget !== null" title="Hapus kelas ini?" @update:open="deleteTarget = null">
    <p class="text-sm text-muted-foreground">
        Menghapus "{{ deleteTarget?.title }}" juga menghapus {{ deleteTarget?.attendances_count ?? 0 }}
        catatan kehadiran kelas ini. Tindakan ini tidak bisa dibatalkan.
    </p>
    <Alert v-if="deleteError" class="mt-3">{{ deleteError }}</Alert>
    <div class="mt-4 flex justify-end gap-2">
        <Button variant="outline" size="sm" @click="deleteTarget = null">Batal</Button>
        <Button variant="destructive" size="sm" @click="confirmDeleteSession">Hapus</Button>
    </div>
</Dialog>
```

- [ ] **Step 4: Build + smoke**

Run: `npm run build`
Expected: build succeeds. Optional smoke via the project's `verify` skill recipe if the executor wants visual confirmation.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: cohort detail becomes a batch cockpit with per-class blocks"
```

---

### Task 5: Member area — per-class timeline in /akun

**Files:**
- Modify: `app/Http/Controllers/MemberAreaController.php:78` (eager-load), `resources/views/member/akun.blade.php:114-232` (venue region)
- Test: `tests/Feature/MemberAreaTest.php`

**Interfaces:**
- Consumes: `CohortSession` venue helpers from Task 1 (`isOnline()`, `mapsEmbedUrl()`, `mapsUrl()`, `mapsDirectionsUrl()`, `googleCalendarUrl()`, `scheduledLabel()`, `startsWithinHours()`).

- [ ] **Step 1: Adapt the member tests first** — in `tests/Feature/MemberAreaTest.php`, the venue-dependent tests (`test_countdown_and_auto_open_map_near_class_day`, `test_enrolled_member_sees_kelasmu_with_location_and_maps_link`, `test_enrolled_member_sees_meeting_link_for_online_cohort`, `test_enrolled_online_cohort_without_meeting_url_shows_placeholder`) currently build venue on the cohort factory (`Cohort::factory()->atLocation()` / `->online()`). Change each to a plain `Cohort::factory()` plus an explicit session, e.g.:

Example — `test_enrolled_member_sees_kelasmu_with_location_and_maps_link` becomes:

```php
[$user, $person] = $this->member();
$program = Program::factory()->active()->create(['name' => 'Kelas Offline Uji']);
$cohort = Cohort::factory()->create([
    'program_id' => $program->id,
    'name' => 'Angkatan Offline 1',
    'start_date' => '2026-08-01 09:00:00',
]);
$session = CohortSession::factory()->for($cohort)->atLocation()->create([
    'title' => 'Kelas 1: Riset Produk',
    'scheduled_at' => '2026-08-01 09:00:00',
]);
$enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);
```

and its venue assertions switch source: `$cohort->location_name` → `$session->location_name`,
`$cohort->mapsEmbedUrl()` → `$session->mapsEmbedUrl()` (same for directions/maps URLs); the
schedule assertion `'1 Agustus 2026 pukul 09.00 WIB'` now comes from the session's
`scheduled_at`. Apply the same source-swap to the online/placeholder/countdown tests
(`->online()` state; placeholder test uses `->create(['type' => 'online', 'meeting_url' => null])`).
In the countdown/auto-open test, the session's `scheduled_at` (set it to `now()->addDay()`)
now drives BOTH the "Besok" chip (`countdownLabel()`) and the auto-open map
(`startsWithinHours(48)`) — the assertions themselves stay.
Add `use App\Models\CohortSession;`. Every assertion string stays — only the model carrying
the venue changes. Add one new test:

```php
public function test_enrolled_member_sees_each_class_of_the_batch(): void
{
    [$user, $person] = $this->member();
    $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
    CohortSession::factory()->for($cohort)->atLocation()->create(['title' => 'Kelas 1: Riset Produk']);
    CohortSession::factory()->for($cohort)->online()->create(['title' => 'Kelas 2: Konten']);
    Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

    $this->actingAs($user)->get('/akun?bagian=kelas')
        ->assertOk()
        ->assertSee('Kelas 1: Riset Produk')
        ->assertSee('Kelas 2: Konten');
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact tests/Feature/MemberAreaTest.php`
Expected: FAIL — factories/states no longer exist on Cohort; new test finds no class titles.

- [ ] **Step 3: Eager-load sessions** — in `MemberAreaController` change the enrolled-classes `with([...])` to:

```php
->with(['cohort.program', 'cohort.mentor:id,name', 'cohort.sessions', 'latestStatusEvent'])
```

- [ ] **Step 4: Rework `akun.blade.php`** — inside the enrolled-class card, delete the cohort-level schedule row (`Dimulai {{ $cohort->startLabel() }}` block, lines ~130-146) and the whole venue region (the `@elseif ($cohort->isOnline())` block through the offline `details`/fallback blocks, lines ~159-221), keeping the ended-state block and the materials block. In their place, after the mentor line, insert the class timeline:

```blade
@if ($cohort->sessions->isEmpty())
    <p class="mt-3 text-sm text-teal-800/70">Jadwal kelas akan diumumkan.</p>
@endif

<div class="mt-4 space-y-3">
    @foreach ($cohort->sessions as $session)
        <div class="rounded-2xl border border-teal-900/10 bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <p class="font-semibold text-teal-900">{{ $session->title }}</p>
                <span @class([
                    'shrink-0 rounded-full px-3 py-1 text-xs font-semibold',
                    'bg-teal-100 text-teal-700' => ! $session->isOnline(),
                    'bg-sand-100 text-teal-800/70' => $session->isOnline(),
                ])>{{ $session->isOnline() ? 'Online' : 'Tatap muka' }}</span>
            </div>

            @if ($session->scheduledLabel())
                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-teal-800/80">
                    <span>{{ $session->scheduledLabel() }}</span>
                    @if ($session->countdownLabel())
                        <span class="rounded-full bg-orange-500/15 px-2.5 py-0.5 text-xs font-bold text-orange-700">{{ $session->countdownLabel() }}</span>
                    @endif
                    @if ($session->googleCalendarUrl())
                        <a href="{{ $session->googleCalendarUrl() }}" target="_blank" rel="noopener"
                           class="text-xs font-semibold text-teal-700 underline-offset-4 transition hover:text-orange-600 hover:underline">
                            + Tambahkan ke Google Calendar
                        </a>
                    @endif
                </div>
            @endif

            @if ($session->isOnline())
                @if ($session->meeting_url)
                    <a href="{{ $session->meeting_url }}" target="_blank" rel="noopener"
                       class="mt-3 inline-flex items-center gap-2 rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-900">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="6" width="13" height="12" rx="2.5"/><path d="m15.5 10 5-3v10l-5-3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Gabung meeting
                    </a>
                @else
                    <p class="mt-2 text-sm text-teal-800/70">Link meeting akan dibagikan sebelum kelas dimulai.</p>
                @endif
            @else
                @if ($session->mapsEmbedUrl())
                    <details {{ $session->startsWithinHours(48) ? 'open' : '' }} class="group mt-3 overflow-hidden rounded-2xl border border-teal-900/10 bg-white">
                        <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 transition hover:bg-sand-50 [&::-webkit-details-marker]:hidden">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-100 via-sand-50 to-orange-200/70 ring-1 ring-inset ring-teal-900/10">
                                <svg class="h-5 w-5 text-orange-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-6-5.1-6-9.9a6 6 0 1 1 12 0C18 15.9 12 21 12 21Z"/><circle cx="12" cy="11" r="2.3"/></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-teal-900">{{ $session->location_name ?: 'Lokasi kelas' }}</span>
                                <span class="block text-xs text-teal-800/70">{{ $session->location_address ?: 'Lihat lokasi di peta' }}</span>
                            </span>
                            <svg class="h-4 w-4 shrink-0 text-teal-700 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 8 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </summary>
                        <div class="border-t border-teal-900/10">
                            <iframe src="{{ $session->mapsEmbedUrl() }}" title="Peta lokasi kelas" class="h-56 w-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                            <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                                <a href="{{ $session->mapsDirectionsUrl() }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-xs font-semibold text-white transition hover:bg-orange-600">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M21.7 11.3 12.7 2.3a1 1 0 0 0-1.4 0l-9 9a1 1 0 0 0 0 1.4l9 9a1 1 0 0 0 1.4 0l9-9a1 1 0 0 0 0-1.4ZM13 14.5V12h-3v3H8v-4a1 1 0 0 1 1-1h4V7.5l3.5 3.5-3.5 3.5Z"/></svg>
                                    Petunjuk arah
                                </a>
                                <a href="{{ $session->mapsUrl() }}" target="_blank" rel="noopener"
                                   class="text-xs font-semibold text-teal-700 underline-offset-4 transition hover:text-orange-600 hover:underline">
                                    Buka di Google Maps
                                </a>
                            </div>
                        </div>
                    </details>
                @elseif ($session->location_name || $session->location_address)
                    @if ($session->location_name)<p class="mt-2 font-semibold text-teal-900">{{ $session->location_name }}</p>@endif
                    @if ($session->location_address)<p class="text-sm text-teal-800/70">{{ $session->location_address }}</p>@endif
                @else
                    <p class="mt-2 text-sm text-teal-800/70">Lokasi kelas akan diumumkan.</p>
                @endif
            @endif
        </div>
    @endforeach
</div>
```

Guard the whole timeline with the existing ended-state branch: keep the `@if ($cohort->status === 'ended')` personal-record block first and put the timeline in its `@else`.

- [ ] **Step 5: Run the member tests**

Run: `php artisan test --compact tests/Feature/MemberAreaTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: member kelas tab becomes a per-class timeline with class-level venue"
```

---

### Task 6: Part 1 closure — full suite + pint

- [ ] **Step 1:** Run `vendor/bin/pint --dirty --format agent` (fixes style in place).
- [ ] **Step 2:** Run `php artisan test --compact` — expected: ALL PASS. Fix any straggler (most likely a test still building venue on `Cohort::factory()` — move it to a session factory as in Task 5 Step 1).
- [ ] **Step 3:** Run `npm run build` — expected: success.
- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "chore: green suite after class revival (spec 2 phase A complete)"
```

---

# Part 2 — Scoring foundation (Spec 1 Phase 1)

### Task 7: Assignments schema + models + factories

**Files:**
- Create: migrations `create_assignments_table`, `create_assignment_submissions_table`, `add_min_average_score_to_programs_table`
- Create: `app/Models/Assignment.php`, `app/Models/AssignmentSubmission.php`, `database/factories/AssignmentFactory.php`, `database/factories/AssignmentSubmissionFactory.php`
- Modify: `app/Models/CohortSession.php` (+`assignment()`), `app/Models/Enrollment.php` (+`assignmentSubmissions()`), `app/Models/Program.php` (+fillable/cast)
- Test: create `tests/Feature/AssignmentModelTest.php`

**Interfaces:**
- Produces: `Assignment{cohort_session_id unique, title, body, created_by, updated_by}` with `session()`, `submissions()`; `AssignmentSubmission{assignment_id, enrollment_id, url, note, score, feedback, graded_by, graded_at}` with `assignment()`, `enrollment()`; `CohortSession::assignment(): HasOne`; `Enrollment::assignmentSubmissions(): HasMany`; `Program.min_average_score` (int, nullable).

- [ ] **Step 1: Write the failing model test**

Run: `php artisan make:test --phpunit --no-interaction AssignmentModelTest`

```php
<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_relations_round_trip(): void
    {
        $session = CohortSession::factory()->for(Cohort::factory())->create();
        $assignment = Assignment::factory()->for($session, 'session')->create();
        // No Person/Enrollment factories exist in this project — tests build
        // them with create(), same as ProgramEligibilityTest.
        $person = Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $session->cohort_id]);
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->assertTrue($session->assignment->is($assignment));
        $this->assertTrue($assignment->session->is($session));
        $this->assertTrue($submission->assignment->is($assignment));
        $this->assertTrue($enrollment->assignmentSubmissions->first()->is($submission));
    }

    public function test_one_assignment_per_session(): void
    {
        $session = CohortSession::factory()->for(Cohort::factory())->create();
        Assignment::factory()->for($session, 'session')->create();

        $this->expectException(\Illuminate\Database\QueryException::class);
        Assignment::factory()->for($session, 'session')->create();
    }
}
```

(This project deliberately has no Person/Enrollment/StatusEvent factories — always `create()` them as above.)

- [ ] **Step 2: Run to verify failure** — `php artisan test --compact tests/Feature/AssignmentModelTest.php` → FAIL (classes missing).

- [ ] **Step 3: Create migrations**

Run:
```bash
php artisan make:migration create_assignments_table --no-interaction
php artisan make:migration create_assignment_submissions_table --no-interaction
php artisan make:migration add_min_average_score_to_programs_table --no-interaction
```

```php
// create_assignments_table
Schema::create('assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cohort_session_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('body');
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});

// create_assignment_submissions_table
Schema::create('assignment_submissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
    $table->string('url', 500);
    $table->text('note')->nullable();
    $table->unsignedTinyInteger('score')->nullable();
    $table->text('feedback')->nullable();
    $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('graded_at')->nullable();
    $table->timestamps();
    $table->index(['assignment_id', 'enrollment_id']);
});

// add_min_average_score_to_programs_table
Schema::table('programs', function (Blueprint $table) {
    $table->unsignedTinyInteger('min_average_score')->nullable();
});
```

Each `down()` drops what `up()` created (`dropIfExists` / `dropColumn`).

- [ ] **Step 4: Create the models**

`app/Models/Assignment.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'cohort_session_id',
        'title',
        'body',
        'created_by',
        'updated_by',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CohortSession::class, 'cohort_session_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
```

`app/Models/AssignmentSubmission.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'enrollment_id',
        'url',
        'note',
        'score',
        'feedback',
        'graded_by',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'graded_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
```

Relations on existing models — `CohortSession`:

```php
public function assignment(): HasOne
{
    return $this->hasOne(Assignment::class);
}
```

`Enrollment`:

```php
public function assignmentSubmissions(): HasMany
{
    return $this->hasMany(AssignmentSubmission::class);
}
```

`Program`: add `'min_average_score'` to `$fillable` and a `casts()` entry `'min_average_score' => 'integer'`.

- [ ] **Step 5: Factories**

`database/factories/AssignmentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Assignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Tugas '.fake()->unique()->numberBetween(1, 999),
            'body' => fake()->paragraph(),
        ];
    }
}
```

`database/factories/AssignmentSubmissionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\AssignmentSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'url' => 'https://drive.google.com/'.fake()->uuid(),
        ];
    }

    /** Graded by an unspecified admin; pass score via create() to control it. */
    public function graded(int $score): static
    {
        return $this->state(fn () => [
            'score' => $score,
            'graded_at' => now(),
        ]);
    }
}
```

- [ ] **Step 6: Migrate + run** — `php artisan migrate --no-interaction && php artisan test --compact tests/Feature/AssignmentModelTest.php` → PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: assignments, submissions, and program score threshold schema"
```

---

### Task 8: `AssignmentScoring` rules class (TDD)

**Files:**
- Create: `app/Support/AssignmentScoring.php`
- Test: create `tests/Feature/AssignmentScoringTest.php`

**Interfaces:**
- Produces (Spec 1 "Derived rules", consumed by Task 9 and by Spec 1 Phase 2/3 UIs):

```php
class AssignmentScoring
{
    /** Score of the latest GRADED submission; null when never graded. */
    public function effectiveScore(Assignment $assignment, Enrollment $enrollment): ?int;

    /** 'belum_dikerjakan' | 'menunggu_dinilai' | 'dinilai' */
    public function submissionState(Assignment $assignment, Enrollment $enrollment): string;

    /**
     * Half-up 1-decimal mean of effective scores (missing = 0) over ALL
     * assignments in the program's cohorts where the person holds an active
     * enrollment. Null when no such assignment exists (fallback signal).
     */
    public function averageFor(Person $person, Program $program): ?float;

    /** Threshold set AND average non-null AND average >= threshold (same rounded value). */
    public function passes(Person $person, Program $program): bool;
}
```

- [ ] **Step 1: Write the failing tests**

Run: `php artisan make:test --phpunit --no-interaction AssignmentScoringTest`

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
use App\Support\AssignmentScoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentScoringTest extends TestCase
{
    use RefreshDatabase;

    private AssignmentScoring $scoring;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoring = new AssignmentScoring;
    }

    private function makePerson(): Person
    {
        return Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    /** @return array{Program, Cohort, Person, Enrollment} */
    private function programWithEnrollment(?int $threshold = 75): array
    {
        $program = Program::factory()->create(['min_average_score' => $threshold]);
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $person = $this->makePerson();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        return [$program, $cohort, $person, $enrollment];
    }

    private function assignmentIn(Cohort $cohort): Assignment
    {
        return Assignment::factory()
            ->for(CohortSession::factory()->for($cohort)->create(), 'session')
            ->create();
    }

    public function test_effective_score_is_the_latest_graded_submission(): void
    {
        [, $cohort, , $enrollment] = $this->programWithEnrollment();
        $assignment = $this->assignmentIn($cohort);

        AssignmentSubmission::factory()->graded(60)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        AssignmentSubmission::factory()->graded(85)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        // A newer, not-yet-graded retake must NOT erase the standing grade.
        AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);

        $this->assertSame(85, $this->scoring->effectiveScore($assignment, $enrollment));
    }

    public function test_submission_state_derivation(): void
    {
        [, $cohort, , $enrollment] = $this->programWithEnrollment();
        $assignment = $this->assignmentIn($cohort);

        $this->assertSame('belum_dikerjakan', $this->scoring->submissionState($assignment, $enrollment));

        AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        $this->assertSame('menunggu_dinilai', $this->scoring->submissionState($assignment, $enrollment));

        AssignmentSubmission::factory()->graded(70)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        $this->assertSame('dinilai', $this->scoring->submissionState($assignment, $enrollment));
    }

    public function test_average_counts_missing_assignments_as_zero(): void
    {
        [$program, $cohort, $person, $enrollment] = $this->programWithEnrollment();
        $graded = $this->assignmentIn($cohort);
        $this->assignmentIn($cohort); // never submitted -> 0

        AssignmentSubmission::factory()->graded(80)->create(['assignment_id' => $graded->id, 'enrollment_id' => $enrollment->id]);

        $this->assertSame(40.0, $this->scoring->averageFor($person, $program));
    }

    public function test_average_spans_multiple_enrollments_of_the_program(): void
    {
        // Legacy shape: two single-class cohorts in one program.
        [$program, $cohortA, $person, $enrollmentA] = $this->programWithEnrollment();
        $cohortB = Cohort::factory()->create(['program_id' => $program->id]);
        $enrollmentB = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohortB->id]);

        $a = $this->assignmentIn($cohortA);
        $b = $this->assignmentIn($cohortB);
        AssignmentSubmission::factory()->graded(70)->create(['assignment_id' => $a->id, 'enrollment_id' => $enrollmentA->id]);
        AssignmentSubmission::factory()->graded(90)->create(['assignment_id' => $b->id, 'enrollment_id' => $enrollmentB->id]);

        $this->assertSame(80.0, $this->scoring->averageFor($person, $program));
    }

    public function test_average_rounds_half_up_to_one_decimal(): void
    {
        [$program, $cohort, $person, $enrollment] = $this->programWithEnrollment();
        foreach ([74, 75, 76] as $score) {
            $assignment = $this->assignmentIn($cohort);
            AssignmentSubmission::factory()->graded($score)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        }

        // 225 / 3 = 75.0 exactly; and 74+75 = 149/2 = 74.5 stays 74.5.
        $this->assertSame(75.0, $this->scoring->averageFor($person, $program));
        $this->assertTrue($this->scoring->passes($person, $program));
    }

    public function test_dropped_enrollment_is_excluded(): void
    {
        [$program, $cohort, $person, $enrollment] = $this->programWithEnrollment();
        $assignment = $this->assignmentIn($cohort);
        AssignmentSubmission::factory()->graded(90)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'dropped', 'occurred_at' => now()]);

        $this->assertNull($this->scoring->averageFor($person, $program));
        $this->assertFalse($this->scoring->passes($person, $program));
    }

    public function test_passes_requires_threshold_and_assignments(): void
    {
        // No threshold -> never passes by score.
        [$programNoBar, $cohortNoBar, $personA, $enrollmentA] = $this->programWithEnrollment(threshold: null);
        $assignment = $this->assignmentIn($cohortNoBar);
        AssignmentSubmission::factory()->graded(100)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollmentA->id]);
        $this->assertFalse($this->scoring->passes($personA, $programNoBar));

        // Threshold but zero assignments -> average null -> no pass (fallback signal).
        [$programBar, , $personB] = $this->programWithEnrollment(threshold: 75);
        $this->assertNull($this->scoring->averageFor($personB, $programBar));
        $this->assertFalse($this->scoring->passes($personB, $programBar));
    }
}
```

(If `StatusEvent`/`Person`/`Enrollment` factories are missing states used here, follow the existing patterns in `ProgramEligibilityTest` which already fabricates enrollments and status events.)

- [ ] **Step 2: Run to verify failure** — `php artisan test --compact tests/Feature/AssignmentScoringTest.php` → FAIL (class missing).

- [ ] **Step 3: Implement `app/Support/AssignmentScoring.php`**

```php
<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;

/**
 * Derived scoring rules (spec 2026-07-17): nothing here is stored. The
 * effective score is the latest GRADED submission; the average is per person
 * per program with missing assignments counted as zero; display and gate use
 * the SAME half-up 1-decimal rounding so they can never disagree.
 */
class AssignmentScoring
{
    public function effectiveScore(Assignment $assignment, Enrollment $enrollment): ?int
    {
        return $assignment->submissions()
            ->where('enrollment_id', $enrollment->id)
            ->whereNotNull('score')
            ->latest('id')
            ->value('score');
    }

    public function submissionState(Assignment $assignment, Enrollment $enrollment): string
    {
        $latest = $assignment->submissions()
            ->where('enrollment_id', $enrollment->id)
            ->latest('id')
            ->first();

        if ($latest === null) {
            return 'belum_dikerjakan';
        }

        return $latest->score === null ? 'menunggu_dinilai' : 'dinilai';
    }

    public function averageFor(Person $person, Program $program): ?float
    {
        $enrollments = $person->enrollments()
            ->whereHas('cohort', fn ($q) => $q->where('program_id', $program->id))
            ->with(['latestStatusEvent', 'cohort.sessions.assignment'])
            ->get()
            ->filter(fn (Enrollment $e) => $e->isActive());

        $scores = [];
        foreach ($enrollments as $enrollment) {
            foreach ($enrollment->cohort->sessions as $session) {
                if ($session->assignment !== null) {
                    $scores[] = $this->effectiveScore($session->assignment, $enrollment) ?? 0;
                }
            }
        }

        if ($scores === []) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 1);
    }

    public function passes(Person $person, Program $program): bool
    {
        if ($program->min_average_score === null) {
            return false;
        }

        $average = $this->averageFor($person, $program);

        return $average !== null && $average >= $program->min_average_score;
    }
}
```

- [ ] **Step 4: Run to verify pass** — `php artisan test --compact tests/Feature/AssignmentScoringTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: AssignmentScoring derives effective scores, averages, and passes"
```

---

### Task 9: `ProgramEligibility` gates on score with attendance fallback (TDD)

**Files:**
- Modify: `app/Support/ProgramEligibility.php`
- Test: `tests/Feature/ProgramEligibilityTest.php` (add tests; existing attendance tests must keep passing — they prove the fallback)

**Interfaces:**
- Consumes: `AssignmentScoring::averageFor` / `passes` from Task 8.
- Produces: unchanged public API — `canAccess(?Person, Program): bool`, `lockReason(?Person, Program): ?string` with the same keys `guest | needs_general | needs_previous_level` (chooser/member UI untouched).

- [ ] **Step 1: Add failing tests** — append to `tests/Feature/ProgramEligibilityTest.php`, following its existing helper style (it already builds general/affiliate programs, enrollments, and attendances):

```php
public function test_meeting_the_score_bar_unlocks_level_1(): void
{
    $general = Program::factory()->active()->create(['min_average_score' => 75]);
    $level1 = Program::factory()->affiliate(1)->active()->create();
    $cohort = Cohort::factory()->create(['program_id' => $general->id]);
    $person = $this->makePerson();
    $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
    $assignment = Assignment::factory()->for(CohortSession::factory()->for($cohort)->create(), 'session')->create();
    AssignmentSubmission::factory()->graded(80)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);

    $this->assertNull(app(ProgramEligibility::class)->lockReason($person, $level1));
}

public function test_below_the_score_bar_stays_locked_even_with_attendance(): void
{
    $general = Program::factory()->active()->create(['min_average_score' => 75]);
    $level1 = Program::factory()->affiliate(1)->active()->create();
    $cohort = Cohort::factory()->create(['program_id' => $general->id]);
    $person = $this->makePerson();
    $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
    $session = CohortSession::factory()->for($cohort)->create();
    $assignment = Assignment::factory()->for($session, 'session')->create();
    AssignmentSubmission::factory()->graded(60)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
    Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

    $this->assertSame('needs_general', app(ProgramEligibility::class)->lockReason($person, $level1));
}

public function test_threshold_without_assignments_falls_back_to_attendance(): void
{
    $general = Program::factory()->active()->create(['min_average_score' => 75]);
    $level1 = Program::factory()->affiliate(1)->active()->create();
    $cohort = Cohort::factory()->create(['program_id' => $general->id]);
    $person = $this->makePerson();
    $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
    $session = CohortSession::factory()->for($cohort)->create();
    Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

    // Misconfiguration guard: bar set but no soal written yet must not lock everyone out.
    $this->assertNull(app(ProgramEligibility::class)->lockReason($person, $level1));
}

public function test_score_gate_applies_between_community_levels(): void
{
    $level1 = Program::factory()->affiliate(1)->active()->create(['min_average_score' => 70]);
    $level2 = Program::factory()->affiliate(2)->active()->create();
    $cohort = Cohort::factory()->create(['program_id' => $level1->id]);
    $person = $this->makePerson();
    $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
    $assignment = Assignment::factory()->for(CohortSession::factory()->for($cohort)->create(), 'session')->create();
    AssignmentSubmission::factory()->graded(72)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);

    $this->assertNull(app(ProgramEligibility::class)->lockReason($person, $level2));
}
```

Add the needed `use` imports (`Assignment`, `AssignmentSubmission`, `Attendance`, `CohortSession`).

- [ ] **Step 2: Run to verify failure** — `php artisan test --compact tests/Feature/ProgramEligibilityTest.php` → the new tests FAIL, old ones PASS.

- [ ] **Step 3: Rework `app/Support/ProgramEligibility.php`**

```php
<?php

namespace App\Support;

use App\Models\Person;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for who may access a program (spec 2026-07-08,
 * re-amended 2026-07-17: the measure is the assignment-score average when the
 * prerequisite program carries a threshold AND has assignments; otherwise the
 * legacy attendance rule keeps governing — both the null-threshold case and
 * the "threshold set but no soal written yet" misconfiguration guard).
 */
class ProgramEligibility
{
    public function __construct(private readonly AssignmentScoring $scoring) {}

    public function canAccess(?Person $person, Program $program): bool
    {
        return $this->lockReason($person, $program) === null;
    }

    /** null when accessible; otherwise guest | needs_general | needs_previous_level. */
    public function lockReason(?Person $person, Program $program): ?string
    {
        if (! $program->isAffiliate()) {
            return null;
        }

        if ($person === null) {
            return 'guest';
        }

        $level = $program->level ?? 1;

        if ($level <= 1) {
            return $this->passesAny($person, fn (Builder $q) => $q->where('type', 'general'))
                ? null
                : 'needs_general';
        }

        return $this->passesAny($person, fn (Builder $q) => $q->where('type', 'affiliate_community')->where('level', $level - 1))
            ? null
            : 'needs_previous_level';
    }

    /**
     * The person passes ANY prerequisite program matching the scope: by score
     * when that program gates on score and has assignments, else by attendance.
     *
     * @param  callable(Builder): Builder  $programScope
     */
    private function passesAny(Person $person, callable $programScope): bool
    {
        $programs = Program::query()
            ->tap(fn (Builder $q) => $programScope($q))
            ->whereHas('cohorts.enrollments', fn (Builder $q) => $q->where('people_id', $person->id))
            ->get();

        foreach ($programs as $prerequisite) {
            if ($prerequisite->min_average_score !== null
                && $this->scoring->averageFor($person, $prerequisite) !== null) {
                if ($this->scoring->passes($person, $prerequisite)) {
                    return true;
                }

                continue; // Score rule governs this program; the bar was not met.
            }

            if ($this->hasAttended($person, $prerequisite)) {
                return true;
            }
        }

        return false;
    }

    private function hasAttended(Person $person, Program $program): bool
    {
        return $person->enrollments()
            ->whereHas('attendances')
            ->whereHas('cohort', fn (Builder $q) => $q->where('program_id', $program->id))
            ->exists();
    }
}
```

- [ ] **Step 4: Run the file + its consumers** — `php artisan test --compact tests/Feature/ProgramEligibilityTest.php tests/Feature/PublicCatalogTest.php tests/Feature/MemberAreaTest.php` → PASS (constructor injection resolves via the container; `ProgramPageController` already injects `ProgramEligibility`).

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: eligibility gates on score average with attendance fallback"
```

---

### Task 10: Permissions + closure

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`
- Test: `tests/Feature/PermissionSeederTest.php`

- [ ] **Step 1: Failing test** — add to `PermissionSeederTest` (follow its existing assertion style):

```php
public function test_assignment_permissions_exist_for_admin_and_mentor(): void
{
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    foreach (['admin', 'mentor'] as $role) {
        $roleModel = \Spatie\Permission\Models\Role::findByName($role, 'web');
        $this->assertTrue($roleModel->hasPermissionTo('assignments.manage'));
        $this->assertTrue($roleModel->hasPermissionTo('assignments.grade'));
    }
}
```

Run: `php artisan test --compact tests/Feature/PermissionSeederTest.php` → new test FAILS.

- [ ] **Step 2: Seed the permissions** — in `PermissionSeeder::run()` add `'assignments.manage'`, `'assignments.grade'` to the `$permissions` array, and add both to the mentor role's `syncPermissions([...])` list (admin gets all automatically).

- [ ] **Step 3: Run** — `php artisan test --compact tests/Feature/PermissionSeederTest.php` → PASS.

- [ ] **Step 4: Full suite + pint + build**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run build
```

Expected: ALL PASS, build OK.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: assignment permissions; scoring foundation complete (spec 1 phase 1)"
```

---

## Out of scope for this plan (next plans)

- Spec 1 Phase 2/3: assignment admin UI (editor, grading panel, roster Nilai column, recap, program form field) and member submit/progress UI.
- Spec 2 Phase B/C: `session_confirmations` (RSVP), public class list, offline benefit copy.
- Production data reshaping (existing July-batch cohorts stay single-class; no data change needed).
- Deploy steps (memory `kheedma-phase2-resume` holds the checklist; deploy note: run `php artisan migrate` — the venue move migration is data-bearing, back up first).
