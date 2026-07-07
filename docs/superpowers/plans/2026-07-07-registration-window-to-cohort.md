# Model Correction — Registration Window Moves to Angkatan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move `registration_opens_at`/`registration_closes_at` from `programs` to `cohorts` (the intake window belongs to the batch), derive "program is open" from its open-window Angkatan, and surface "Kelas dimulai {date}" on the public landing.

**Architecture:** Pure model correction, no new features. `Cohort` gains the window + `isOpenForRegistration()` + scope; `Program::isOpen()`/`openForRegistration()` become derivations over cohorts; admin forms swap the two DatePickers between screens; the public landing gains the class-start line from the open Angkatan. Approved in the concept spec's "Model correction (2026-07-07)" note — this supersedes the planned `accepting_enrollments` flag (Phase 3 will target the open-window Angkatan).

**Tech Stack:** PHP 8.4, Laravel 13, Vue 3, existing `ui/date-picker` component, PHPUnit 12.

## Global Constraints

- PHP: braces always; explicit return types + param hints. `vendor/bin/pint --dirty --format agent` before each backend commit.
- Tests PHPUnit; keep the whole suite green at every task boundary (`php artisan test --compact`).
- Program keeps: slug/name/tagline/description/`status` (catalog visibility)/`selection_mode`. Program NO LONGER stores window columns.
- Open rules (verbatim): Cohort window open = `registration_opens_at` null-or-past AND `registration_closes_at` null-or-future. **Program open = `status === 'active'` AND at least one cohort whose window is open.** A cohort with BOTH window fields null is NOT open (an explicit opens/closes value is required to open intake — prevents every legacy batch from silently opening its program; recommend setting at least `registration_opens_at`).
- Public behavior unchanged in shape: chooser lists open programs; landing shows open (CTA) vs closed states; apply routes guard via `Program::isOpen()`. NEW: when open, the landing shows "Kelas dimulai {d F Y}" from the open Angkatan's `start_date` (line omitted when start_date null).
- Admin: Programs form loses the two DatePickers; Angkatan form gains them (+ effective-pair validation: closes must be after opens, payload-or-stored). Programs table keeps the "Pendaftaran" Buka/Tutup badge (now derived); Angkatan table gains a "Pendaftaran" Buka/Tutup badge.
- Migration moves the columns (add to cohorts, drop from programs) with a reversible `down()`. No production data exists; dev values on `programs` are dropped without copying (admin re-enters on the Angkatan — noted, acceptable).
- Admin copy Indonesian ("Angkatan"); public copy promotional Indonesian, no em-dashes, no internal terms.

---

## File Structure

**Modify (backend):**
- `database/migrations/2026_07_07_200001_move_registration_window_to_cohorts.php` (create)
- `app/Models/Cohort.php` (window fillable/casts, `isOpenForRegistration()`, `scopeOpenForRegistration`)
- `app/Models/Program.php` (drop window fields, derive `isOpen()` + scope, `openCohort()` helper)
- `app/Http/Controllers/Api/Admin/ProgramController.php` (drop window validation/row fields)
- `app/Http/Controllers/Api/Admin/CohortController.php` (window validation incl. effective pair + row fields)
- `app/Http/Controllers/ProgramPageController.php` (pass open cohort to the landing)
- `resources/views/funnel/program.blade.php` (class-start line)

**Modify (frontend):** `resources/js/admin/views/Programs.vue` (remove DatePickers), `resources/js/admin/views/Cohorts.vue` (add DatePickers + badge column)

**Tests:** adjust `ProgramModelTest`, `ProgramManagementTest`, `CohortManagementTest`, `PublicCatalogTest`, `PublicApplyTest`; `ProgramFactory` window states move to `CohortFactory`.

---

### Task 1: Schema + model derivations + factories

**Files:**
- Create: `database/migrations/2026_07_07_200001_move_registration_window_to_cohorts.php`
- Modify: `app/Models/Cohort.php`, `app/Models/Program.php`, `database/factories/CohortFactory.php`, `database/factories/ProgramFactory.php`
- Test: rewrite `tests/Feature/ProgramModelTest.php`

**Interfaces:**
- Produces: `Cohort::isOpenForRegistration(): bool`, `Cohort::scopeOpenForRegistration()`, window in `$fillable`+`casts`; `Program::isOpen(): bool` (active + any open cohort), `Program::scopeOpenForRegistration()` (whereHas), `Program::openCohort(): ?Cohort` (the open-window Angkatan, earliest `registration_closes_at` first, nulls last); `CohortFactory::openWindow()` / `closedWindow()` states; `ProgramFactory` loses `windowClosed()` (replaced by cohort states), keeps `active()/inactive()/draft()`.

- [ ] **Step 1: Write the failing test**

Rewrite `tests/Feature/ProgramModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Program;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_open_state_derives_from_cohort_windows(): void
    {
        $open = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $open->id]);

        $activeNoCohort = Program::factory()->active()->create();

        $activeClosedWindow = Program::factory()->active()->create();
        Cohort::factory()->closedWindow()->create(['program_id' => $activeClosedWindow->id]);

        $activeWindowlessCohort = Program::factory()->active()->create();
        Cohort::factory()->create(['program_id' => $activeWindowlessCohort->id]);

        $inactive = Program::factory()->inactive()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $inactive->id]);

        $this->assertTrue($open->isOpen());
        $this->assertFalse($activeNoCohort->isOpen());
        $this->assertFalse($activeClosedWindow->isOpen());
        $this->assertFalse($activeWindowlessCohort->isOpen());
        $this->assertFalse($inactive->isOpen());

        $this->assertSame([$open->id], Program::openForRegistration()->pluck('id')->all());
    }

    public function test_open_cohort_returns_the_open_window_batch(): void
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->closedWindow()->create(['program_id' => $program->id]);
        $openBatch = Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        $this->assertTrue($program->openCohort()->is($openBatch));
        $this->assertNull(Program::factory()->active()->create()->openCohort());
    }

    public function test_cohort_window_open_logic(): void
    {
        $this->assertTrue(Cohort::factory()->openWindow()->create()->isOpenForRegistration());
        $this->assertFalse(Cohort::factory()->closedWindow()->create()->isOpenForRegistration());
        $this->assertFalse(Cohort::factory()->create()->isOpenForRegistration()); // both nulls = not open

        $openEnded = Cohort::factory()->create(['registration_opens_at' => now()->subDay()]);
        $this->assertTrue($openEnded->isOpenForRegistration()); // opens set, no close = open

        $future = Cohort::factory()->create(['registration_opens_at' => now()->addWeek()]);
        $this->assertFalse($future->isOpenForRegistration());
    }

    public function test_admin_role_gets_programs_manage_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('programs.manage'));
        $this->assertFalse(Role::findByName('mentor', 'web')->hasPermissionTo('programs.manage'));
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=ProgramModelTest`
Expected: FAIL — factory states/columns missing.

- [ ] **Step 3: Migration**

Create `database/migrations/2026_07_07_200001_move_registration_window_to_cohorts.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Model correction: the registration window belongs to the intake
        // (Angkatan), not the catalog item. "Program open" is derived from its
        // cohorts. Dev-only values on programs are intentionally dropped.
        Schema::table('cohorts', function (Blueprint $table) {
            $table->timestamp('registration_opens_at')->nullable()->after('end_date');
            $table->timestamp('registration_closes_at')->nullable()->after('registration_opens_at');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['registration_opens_at', 'registration_closes_at']);
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->timestamp('registration_opens_at')->nullable()->after('status');
            $table->timestamp('registration_closes_at')->nullable()->after('registration_opens_at');
        });

        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropColumn(['registration_opens_at', 'registration_closes_at']);
        });
    }
};
```

- [ ] **Step 4: Models**

`app/Models/Cohort.php` — add to `$fillable` after `'end_date',`: `'registration_opens_at', 'registration_closes_at',`; add casts `'registration_opens_at' => 'datetime', 'registration_closes_at' => 'datetime',`; add import `use Illuminate\Database\Eloquent\Builder;` and methods:

```php
    /**
     * Intake open: opens_at set-and-past (or null WITH closes_at set) is not
     * enough — a window only opens when at least one bound is set and now sits
     * inside it. Both nulls = intake not open.
     */
    public function isOpenForRegistration(): bool
    {
        if ($this->registration_opens_at === null && $this->registration_closes_at === null) {
            return false;
        }
        if ($this->registration_opens_at && $this->registration_opens_at->isFuture()) {
            return false;
        }
        if ($this->registration_closes_at && $this->registration_closes_at->isPast()) {
            return false;
        }

        return true;
    }

    /** Query counterpart of isOpenForRegistration(). */
    public function scopeOpenForRegistration(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNotNull('registration_opens_at')->orWhereNotNull('registration_closes_at'))
            ->where(fn (Builder $q) => $q->whereNull('registration_opens_at')->orWhere('registration_opens_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('registration_closes_at')->orWhere('registration_closes_at', '>=', now()));
    }
```

`app/Models/Program.php` — REMOVE `'registration_opens_at', 'registration_closes_at',` from `$fillable` and both entries from `casts()` (leave `casts()` returning an empty array removal-safe: if nothing remains, drop the whole `casts()` method); replace `isOpen()` and the scope; add `openCohort()`:

```php
    /** Open for registration: catalog-active AND an Angkatan's intake window is open. */
    public function isOpen(): bool
    {
        return $this->status === 'active' && $this->cohorts()->openForRegistration()->exists();
    }

    /** Query counterpart of isOpen(), for the public chooser. */
    public function scopeOpenForRegistration(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereHas('cohorts', fn (Builder $q) => $q->openForRegistration());
    }

    /** The Angkatan currently accepting registrations (soonest-closing first). */
    public function openCohort(): ?Cohort
    {
        return $this->cohorts()
            ->openForRegistration()
            ->orderByRaw('registration_closes_at IS NULL, registration_closes_at ASC')
            ->first();
    }
```

- [ ] **Step 5: Factories**

`database/factories/CohortFactory.php` — add states:

```php
    /** Intake window currently open (registration accepted right now). */
    public function openWindow(): static
    {
        return $this->state(fn () => [
            'registration_opens_at' => now()->subWeek(),
            'registration_closes_at' => now()->addWeek(),
        ]);
    }

    /** Intake window already closed. */
    public function closedWindow(): static
    {
        return $this->state(fn () => [
            'registration_opens_at' => now()->subMonth(),
            'registration_closes_at' => now()->subDay(),
        ]);
    }
```

`database/factories/ProgramFactory.php` — remove `registration_opens_at`/`registration_closes_at` from `definition()` and DELETE the `windowClosed()` state.

- [ ] **Step 6: Migrate + run**

Run: `php artisan migrate` then `php artisan test --compact --filter=ProgramModelTest`
Expected: PASS. (Other suites will fail until Tasks 2-3 — do NOT run the full suite yet.)

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_07_200001_move_registration_window_to_cohorts.php app/Models/Cohort.php app/Models/Program.php database/factories/CohortFactory.php database/factories/ProgramFactory.php tests/Feature/ProgramModelTest.php
git commit -m "refactor: registration window lives on the Angkatan; program open state derived"
```

---

### Task 2: Admin API — Program loses the window, Angkatan gains it

**Files:**
- Modify: `app/Http/Controllers/Api/Admin/ProgramController.php`, `app/Http/Controllers/Api/Admin/CohortController.php`
- Test: adjust `tests/Feature/ProgramManagementTest.php`, extend `tests/Feature/CohortManagementTest.php`

**Interfaces:**
- Produces: Program row keeps `is_open` (derived) but DROPS `registration_opens_at`/`registration_closes_at`; Program validation drops both fields (and the stored-pair check — delete the Carbon block). Cohort validation gains `registration_opens_at` (`sometimes|nullable|date`) + `registration_closes_at` (`sometimes|nullable|date`) + the effective-pair check (payload-or-stored, closes strictly after opens, 422 on `registration_closes_at`); Cohort row gains both fields (ISO strings) + `registration_open` boolean.

- [ ] **Step 1: Adjust tests (RED)**

In `tests/Feature/ProgramManagementTest.php`: DELETE `test_partial_update_cannot_close_registration_before_stored_open_date` (moves to cohorts) and remove window fields from any payloads/assertions. In `test_admin_can_create_a_program`, change the `is_open` assertion: creating an active program with no cohorts asserts `assertJsonPath('program.is_open', false)`.

In `tests/Feature/CohortManagementTest.php`, add:

```php
    public function test_angkatan_carries_the_registration_window(): void
    {
        $program = Program::factory()->active()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', [
                'name' => 'Angkatan 1',
                'program_id' => $program->id,
                'registration_opens_at' => now()->subDay()->toDateTimeString(),
                'registration_closes_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertCreated()
            ->assertJsonPath('cohort.registration_open', true);

        $this->assertTrue($program->fresh()->isOpen());
    }

    public function test_partial_update_cannot_close_registration_before_stored_open_date(): void
    {
        $cohort = Cohort::factory()->create([
            'registration_opens_at' => now()->addDays(10),
            'registration_closes_at' => now()->addDays(30),
        ]);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/cohorts/{$cohort->id}", [
                'registration_closes_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('registration_closes_at');
    }
```

Run both filters — expect the new/changed tests to FAIL.

- [ ] **Step 2: ProgramController**

In `validated()`: delete the `registration_opens_at`/`registration_closes_at` rules AND the whole effective-pair Carbon block (restore `return $request->validate([...]);` directly); remove the `Carbon` import if now unused. In `row()`: delete both window lines (keep `is_open`).

- [ ] **Step 3: CohortController**

In `validated()`: assign `$data = $request->validate([...])` adding after `end_date`:

```php
            'registration_opens_at' => ['sometimes', 'nullable', 'date'],
            'registration_closes_at' => ['sometimes', 'nullable', 'date'],
```

then before `return $data;` add the effective-pair check (mirror the pattern removed from ProgramController):

```php
        $opensAt = array_key_exists('registration_opens_at', $data) ? $data['registration_opens_at'] : $cohort?->registration_opens_at;
        $closesAt = array_key_exists('registration_closes_at', $data) ? $data['registration_closes_at'] : $cohort?->registration_closes_at;

        if ($opensAt && $closesAt && ! Carbon::parse($closesAt)->gt(Carbon::parse($opensAt))) {
            throw ValidationException::withMessages([
                'registration_closes_at' => 'Tanggal tutup pendaftaran harus setelah tanggal buka.',
            ]);
        }
```

(`validated()` signature becomes `validated(Request $request, ?Cohort $cohort = null)`; `store()` passes nothing extra, `update()` passes `$cohort`; adjust the existing `bool $creating` param — replace it with `$cohort === null` logic to avoid two overlapping params: `$creating = $cohort === null;` inside, `store()` calls `validated($request)`, `update()` calls `validated($request, $cohort)`. Import `Illuminate\Support\Carbon`.)

In `row()` add:

```php
            'registration_opens_at' => $c->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $c->registration_closes_at?->toIso8601String(),
            'registration_open' => $c->isOpenForRegistration(),
```

- [ ] **Step 4: Run + commit**

Run: `php artisan test --compact --filter=ProgramManagementTest` and `--filter=CohortManagementTest` → PASS.

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/ProgramController.php app/Http/Controllers/Api/Admin/CohortController.php tests/Feature/ProgramManagementTest.php tests/Feature/CohortManagementTest.php
git commit -m "refactor: admin API moves registration window from program to angkatan"
```

---

### Task 3: Public funnel — landing shows class start; fixtures updated

**Files:**
- Modify: `app/Http/Controllers/ProgramPageController.php`, `resources/views/funnel/program.blade.php`
- Test: adjust `tests/Feature/PublicCatalogTest.php`, `tests/Feature/PublicApplyTest.php`

**Interfaces:**
- Produces: `show()` passes `openCohort` (`$program->openCohort()`, null when closed); the landing renders, when open AND `openCohort?->start_date`, the line `Kelas dimulai {start_date d F Y}` (Indonesian, promotional — no internal terms). Test fixtures: "open program" = `Program::factory()->active()` + `Cohort::factory()->openWindow()` child; "closed"/"windowless" variants accordingly.

- [ ] **Step 1: Adjust tests (RED)**

`PublicCatalogTest`: replace program fixtures — open = active + openWindow cohort; the old `windowClosed()` program becomes active + `closedWindow()` cohort; `inactive()` keeps an openWindow cohort (still hidden). Add:

```php
    public function test_landing_shows_class_start_date_when_open(): void
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create([
            'program_id' => $program->id,
            'start_date' => '2026-08-01',
        ]);

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('Kelas dimulai')
            ->assertSee('1 Agustus 2026');
    }
```

`PublicApplyTest`: every `Program::factory()->active()->create()` used for OPEN flows gains a `Cohort::factory()->openWindow()->create(['program_id' => …])`; `windowClosed()` program fixture becomes active + closedWindow cohort. Import `App\Models\Cohort`. (A private helper `openProgram(): Program` in each test class keeps this tidy.)

Run both filters — expect FAIL (fixtures now describe the new model).

- [ ] **Step 2: Controller + view**

`ProgramPageController::show()`:

```php
    public function show(Program $program): View
    {
        abort_if($program->status === 'draft', 404);

        $isOpen = $program->isOpen();

        return view('funnel.program', [
            'program' => $program,
            'isOpen' => $isOpen,
            'openCohort' => $isOpen ? $program->openCohort() : null,
        ]);
    }
```

`resources/views/funnel/program.blade.php` — inside the `@if ($isOpen)` branch, replace the static caption line with:

```blade
                    <x-cta :href="route('program.apply', $program)" label="Daftar Sekarang" />
                    <p class="mt-4 text-xs text-teal-800/50">
                        Pendaftaran sedang dibuka. Tempat terbatas.
                        @if ($openCohort?->start_date)
                            Kelas dimulai {{ $openCohort->start_date->translatedFormat('j F Y') }}.
                        @endif
                    </p>
```

- [ ] **Step 3: Run + full suite + commit**

Run: `php artisan test --compact --filter=PublicCatalogTest`, `--filter=PublicApplyTest`, then the FULL suite `php artisan test --compact` → all green.

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ProgramPageController.php resources/views/funnel/program.blade.php tests/Feature/PublicCatalogTest.php tests/Feature/PublicApplyTest.php
git commit -m "refactor: public landing derives openness from angkatan and shows class start"
```

---

### Task 4: Admin UI — DatePickers swap screens

**Files:**
- Modify: `resources/js/admin/views/Programs.vue`, `resources/js/admin/views/Cohorts.vue`

**Interfaces:**
- Consumes: cohort row `registration_opens_at`/`registration_closes_at`/`registration_open` (Task 2); `ui/date-picker`.
- Produces: Programs dialog without the window row (script drops the two form fields + payload lines; table unchanged — `is_open` badge still works). Cohorts dialog gains the two labeled DatePickers below the class-date row; form state/payload gains both fields (date-only slice on edit: `?.slice(0, 10)`); table gains a "Pendaftaran" column with Buka/Tutup badge from `registration_open` (bump colspans 7→8).

- [ ] **Step 1: Programs.vue**

Remove from the form template the whole "Pendaftaran dibuka / ditutup" `flex gap-3` block and the `formErrors.registration_closes_at` line; remove `DatePicker` import; remove `registration_opens_at`/`registration_closes_at` from the `form` ref initializations (both `openCreate` and the initial declaration), from `openEdit`, and from the `save()` payload.

- [ ] **Step 2: Cohorts.vue**

Import `DatePicker`; extend the `form` ref (+`registration_opens_at: ''`, `registration_closes_at: ''` in declaration/openCreate; in `openEdit`: `cohort.registration_opens_at?.slice(0, 10) ?? ''` etc.); extend the `save()` payload (`|| null` pattern); in the dialog below the Mulai/Selesai date row add:

```vue
                <div class="flex gap-3">
                    <div class="min-w-0 flex-1">
                        <label class="text-xs text-muted-foreground">Pendaftaran dibuka</label>
                        <DatePicker v-model="form.registration_opens_at" class="mt-1.5" placeholder="Pilih tanggal" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <label class="text-xs text-muted-foreground">Pendaftaran ditutup</label>
                        <DatePicker v-model="form.registration_closes_at" class="mt-1.5" placeholder="Pilih tanggal" />
                    </div>
                </div>
                <p v-if="formErrors.registration_closes_at" class="text-xs text-destructive">{{ formErrors.registration_closes_at[0] }}</p>
```

Table: add header `Pendaftaran` after `Periode` and cell:

```vue
                        <td class="px-4 py-3">
                            <Badge :variant="cohort.registration_open ? 'success' : 'secondary'">
                                {{ cohort.registration_open ? 'Buka' : 'Tutup' }}
                            </Badge>
                        </td>
```

Bump loading/empty colspans 7→8.

- [ ] **Step 3: Build + full suite + commit**

Run: `npm run build` (green) and `php artisan test --compact` (all green).

```bash
git add resources/js/admin/views/Programs.vue resources/js/admin/views/Cohorts.vue
git commit -m "refactor: registration window datepickers move to the angkatan form"
```

---

## Self-Review

**Coverage vs the approved correction:** columns move (T1); derived `isOpen`/scope/`openCohort` (T1); admin API swap incl. effective-pair validation relocation (T2); public landing class-start line + fixture updates (T3); admin UI swap + Buka/Tutup badge on Angkatan (T4). `accepting_enrollments` is nowhere introduced — superseded per the amended concept spec.

**Placeholder scan:** none. **Type consistency:** `registration_open` (cohort row, T2) consumed by Cohorts.vue badge (T4); `openCohort` (T1) consumed by controller/view (T3); factory states (T1) consumed by tests (T1-T3); `validated(Request, ?Cohort)` signature change is self-contained in T2.

**Known suite churn:** Task 1 leaves ProgramManagement/PublicCatalog/PublicApply suites red until T2/T3 — task ordering is deliberate; only the FULL suite gates at T3/T4 ends.
