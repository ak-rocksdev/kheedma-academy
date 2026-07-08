# Program Segmentation & Eligibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Segment the program catalog into `general` and tiered `affiliate_community` classes, with real eligibility rules (completion-based), locked-teaser UI, and server-side gating.

**Architecture:** Three columns on `programs` + one stateless `ProgramEligibility` service consumed by the public funnel (chooser, landing, apply guard), the member area, and rendered by a shared lock-modal Blade partial with a vanilla-JS toggle. Admin Programs module gains type/level/locked-message fields. No new tables, no new dependencies.

**Tech Stack:** PHP 8.4, Laravel 13, Blade + vanilla JS (public), Vue 3 admin SPA, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-07-08-program-segmentation-design.md` — the eligibility contract there is binding.

## Global Constraints

- Program types (verbatim values): `general`, `affiliate_community`. Existing rows stay `general` via column default.
- Eligibility contract: a Person has completed a program when they have an `Enrollment` in any `Cohort` of that program that has a `StatusEvent` with `status = 'completed'`.
- Rules: `general` → always accessible. Affiliate level 1 → logged-in person with ≥1 completed `general` program. Affiliate level N>1 → completed `affiliate_community` program of level N-1. Guests never eligible for affiliate.
- Lock reasons (verbatim strings): `guest`, `needs_general`, `needs_previous_level`.
- Default locked message (verbatim, in `config/kheedma.php` key `default_locked_message`): "Kelas ini khusus member yang sudah menyelesaikan program sebelumnya. Selesaikan program yang sedang dibuka dulu, lalu kelas ini terbuka untukmu."
- UI copy Indonesian, no em-dashes. PHP: curly braces always, explicit return types + param type hints.
- Run `vendor/bin/pint --dirty --format agent` before each backend commit. Tests are PHPUnit classes; run focused filters, full suite at the end.
- Surgical diffs (karpathy): touch only what the task names; no drive-by refactors; every changed line traces to the spec.

---

## File Structure

**Create:**
- `database/migrations/2026_07_08_000001_add_segmentation_to_programs_table.php`
- `config/kheedma.php`
- `app/Support/ProgramEligibility.php`
- `resources/views/funnel/partials/lock-modal.blade.php`
- Tests: `tests/Feature/ProgramEligibilityTest.php`

**Modify:**
- `app/Models/Program.php` (fillable + `isAffiliate()` helper)
- `database/factories/ProgramFactory.php` (`affiliate(int $level)` state)
- `app/Http/Controllers/ApplicationController.php` (eligibility guard in `create`/`store`)
- `app/Http/Controllers/ProgramPageController.php` (chooser sections + landing eligibility)
- `resources/views/funnel/chooser.blade.php` (two sections + locked cards)
- `resources/views/funnel/program.blade.php` (locked CTA state)
- `resources/views/components/layouts/public.blade.php` (include lock modal partial)
- `resources/js/app.js` (lock-modal toggle)
- `app/Http/Controllers/MemberAreaController.php` + `resources/views/member/akun.blade.php` ("Program untuk Anda")
- `app/Http/Controllers/Api/Admin/ProgramController.php` (validation + row)
- `resources/js/admin/views/Programs.vue` (form fields + list badge)
- Tests: `tests/Feature/PublicApplyTest.php`, `tests/Feature/PublicCatalogTest.php`, `tests/Feature/ProgramManagementTest.php` (additions only)

---

## Task 1: Schema, config, model, factory

**Files:**
- Create: `database/migrations/2026_07_08_000001_add_segmentation_to_programs_table.php`
- Create: `config/kheedma.php`
- Modify: `app/Models/Program.php`
- Modify: `database/factories/ProgramFactory.php`
- Test: `tests/Feature/ProgramEligibilityTest.php` (first test only)

**Interfaces:**
- Produces: `programs.type` (string, default `'general'`), `programs.level` (unsignedTinyInteger nullable), `programs.locked_message` (text nullable); `Program::isAffiliate(): bool`; `ProgramFactory::affiliate(int $level = 1)` state (active by default is NOT implied — chain `->active()` as needed); `config('kheedma.default_locked_message')`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ProgramEligibilityTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_segmentation_fields_round_trip(): void
    {
        $general = Program::factory()->create();
        $affiliate = Program::factory()->affiliate(2)->create(['locked_message' => 'Khusus lulusan Level 1.']);

        $this->assertSame('general', $general->fresh()->type);
        $this->assertFalse($general->isAffiliate());

        $fresh = $affiliate->fresh();
        $this->assertSame('affiliate_community', $fresh->type);
        $this->assertSame(2, (int) $fresh->level);
        $this->assertSame('Khusus lulusan Level 1.', $fresh->locked_message);
        $this->assertTrue($fresh->isAffiliate());

        $this->assertNotSame('', (string) config('kheedma.default_locked_message'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ProgramEligibilityTest`
Expected: FAIL (unknown column `type` / missing `affiliate` factory state).

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_08_000001_add_segmentation_to_programs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalog segmentation (spec 2026-07-08): general classes vs tiered
        // affiliate-community classes. Existing rows are general by default.
        Schema::table('programs', function (Blueprint $table) {
            $table->string('type')->default('general')->after('description');   // general | affiliate_community
            $table->unsignedTinyInteger('level')->nullable()->after('type');    // affiliate tier; null for general
            $table->text('locked_message')->nullable()->after('level');         // per-class lock popup copy
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['type', 'level', 'locked_message']);
        });
    }
};
```

- [ ] **Step 4: Create the config**

Create `config/kheedma.php`:

```php
<?php

return [

    /*
     | Shown when a locked affiliate class has no locked_message of its own.
     | Lives in config so copy edits never touch logic code.
     */
    'default_locked_message' => 'Kelas ini khusus member yang sudah menyelesaikan program sebelumnya. Selesaikan program yang sedang dibuka dulu, lalu kelas ini terbuka untukmu.',

];
```

- [ ] **Step 5: Update the model and factory**

In `app/Models/Program.php`, extend `$fillable` with the three new fields:

```php
    protected $fillable = [
        'slug',
        'name',
        'tagline',
        'description',
        'type',
        'level',
        'locked_message',
        'status',
        'selection_mode',
    ];
```

Add this method after `getRouteKeyName()`:

```php
    public function isAffiliate(): bool
    {
        return $this->type === 'affiliate_community';
    }
```

In `database/factories/ProgramFactory.php`, add `'type' => 'general',` to the `definition()` return array (after `'description'`), and add this state after `draft()`:

```php
    public function affiliate(int $level = 1): static
    {
        return $this->state(fn () => ['type' => 'affiliate_community', 'level' => $level]);
    }
```

- [ ] **Step 6: Migrate and run the test**

Run: `php artisan migrate` then `php artisan test --compact --filter=ProgramEligibilityTest`
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_08_000001_add_segmentation_to_programs_table.php config/kheedma.php app/Models/Program.php database/factories/ProgramFactory.php tests/Feature/ProgramEligibilityTest.php
git commit -m "feat: program segmentation fields (type, level, locked_message)"
```

---

## Task 2: ProgramEligibility service

**Files:**
- Create: `app/Support/ProgramEligibility.php`
- Test: `tests/Feature/ProgramEligibilityTest.php` (extend)

**Interfaces:**
- Consumes: Task 1 fields; existing `Person::enrollments()`, `Enrollment::statusEvents()`, `Enrollment::cohort()` → `Cohort::program()` relations.
- Produces: `ProgramEligibility::canAccess(?Person $person, Program $program): bool` and `ProgramEligibility::lockReason(?Person $person, Program $program): ?string` returning `null | 'guest' | 'needs_general' | 'needs_previous_level'`. Later tasks resolve it from the container (`app(ProgramEligibility::class)`) or method injection.

- [ ] **Step 1: Check the Cohort→Program relation exists**

`app/Models/Cohort.php` must have a `program(): BelongsTo` relation (added in the programs feature). Verify with: `grep -n "function program" app/Models/Cohort.php`. If missing, add:

```php
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
```

- [ ] **Step 2: Write the failing tests (eligibility matrix)**

Append to `tests/Feature/ProgramEligibilityTest.php` (inside the class). The completion helper builds the exact contract: enrollment in a cohort of the program + a `completed` status event.

```php
    private function makePerson(): \App\Models\Person
    {
        return \App\Models\Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    private function completeProgram(\App\Models\Person $person, Program $program): void
    {
        $cohort = \App\Models\Cohort::factory()->create(['program_id' => $program->id]);
        $enrollment = \App\Models\Enrollment::create([
            'people_id' => $person->id,
            'cohort_id' => $cohort->id,
        ]);
        \Illuminate\Support\Facades\DB::table('status_events')->insert([
            'enrollment_id' => $enrollment->id,
            'status' => 'completed',
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_general_program_is_open_to_everyone(): void
    {
        $eligibility = app(\App\Support\ProgramEligibility::class);
        $general = Program::factory()->active()->create();

        $this->assertTrue($eligibility->canAccess(null, $general));
        $this->assertNull($eligibility->lockReason(null, $general));
        $this->assertTrue($eligibility->canAccess($this->makePerson(), $general));
    }

    public function test_guest_is_locked_out_of_affiliate(): void
    {
        $eligibility = app(\App\Support\ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();

        $this->assertFalse($eligibility->canAccess(null, $level1));
        $this->assertSame('guest', $eligibility->lockReason(null, $level1));
    }

    public function test_member_without_completion_needs_general(): void
    {
        $eligibility = app(\App\Support\ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $person = $this->makePerson();

        $this->assertFalse($eligibility->canAccess($person, $level1));
        $this->assertSame('needs_general', $eligibility->lockReason($person, $level1));
    }

    public function test_completed_general_unlocks_level_1_but_not_level_2(): void
    {
        $eligibility = app(\App\Support\ProgramEligibility::class);
        $general = Program::factory()->active()->create();
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $level2 = Program::factory()->affiliate(2)->active()->create();

        $person = $this->makePerson();
        $this->completeProgram($person, $general);

        $this->assertTrue($eligibility->canAccess($person, $level1));
        $this->assertNull($eligibility->lockReason($person, $level1));
        $this->assertFalse($eligibility->canAccess($person, $level2));
        $this->assertSame('needs_previous_level', $eligibility->lockReason($person, $level2));
    }

    public function test_completed_level_1_unlocks_level_2(): void
    {
        $eligibility = app(\App\Support\ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $level2 = Program::factory()->affiliate(2)->active()->create();

        $person = $this->makePerson();
        $this->completeProgram($person, $level1);

        $this->assertTrue($eligibility->canAccess($person, $level2));
    }

    public function test_incomplete_enrollment_does_not_count(): void
    {
        $eligibility = app(\App\Support\ProgramEligibility::class);
        $general = Program::factory()->active()->create();
        $level1 = Program::factory()->affiliate(1)->active()->create();

        $person = $this->makePerson();
        // Enrollment exists but no 'completed' status event.
        $cohort = \App\Models\Cohort::factory()->create(['program_id' => $general->id]);
        \App\Models\Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->assertFalse($eligibility->canAccess($person, $level1));
        $this->assertSame('needs_general', $eligibility->lockReason($person, $level1));
    }
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ProgramEligibilityTest`
Expected: FAIL — class `App\Support\ProgramEligibility` not found (round-trip test from Task 1 still passes).

- [ ] **Step 4: Implement the service**

Create `app/Support/ProgramEligibility.php`:

```php
<?php

namespace App\Support;

use App\Models\Person;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for who may access a program (spec 2026-07-08).
 *
 * Completion contract: a Person has completed a program when they have an
 * Enrollment in any Cohort of that program carrying a StatusEvent with
 * status 'completed'. Spec 2 (enrollment + attendance) writes that data;
 * this service only reads it.
 */
class ProgramEligibility
{
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
            return $this->hasCompleted($person, fn (Builder $q) => $q->where('type', 'general'))
                ? null
                : 'needs_general';
        }

        return $this->hasCompleted($person, fn (Builder $q) => $q->where('type', 'affiliate_community')->where('level', $level - 1))
            ? null
            : 'needs_previous_level';
    }

    /** @param  callable(Builder): Builder  $programScope */
    private function hasCompleted(Person $person, callable $programScope): bool
    {
        return $person->enrollments()
            ->whereHas('statusEvents', fn (Builder $q) => $q->where('status', 'completed'))
            ->whereHas('cohort.program', $programScope)
            ->exists();
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ProgramEligibilityTest`
Expected: PASS (all 7).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/ProgramEligibility.php tests/Feature/ProgramEligibilityTest.php app/Models/Cohort.php
git commit -m "feat: ProgramEligibility service (completion-based access rules)"
```

(Include `app/Models/Cohort.php` only if Step 1 added the relation.)

---

## Task 3: Server-side guard on the apply funnel

**Files:**
- Modify: `app/Http/Controllers/ApplicationController.php` (`create` and `store`)
- Test: `tests/Feature/PublicApplyTest.php` (append tests)

**Interfaces:**
- Consumes: `ProgramEligibility::canAccess(?Person, Program)` (Task 2); existing guard block pattern `if (! $program->isOpen()) { return redirect()->route('program.show', $program); }`.
- Produces: ineligible GET/POST on `/program/{slug}/daftar` redirects to `route('program.show', $program)`. No behavior change for general programs.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PublicApplyTest.php` (match the file's existing conventions for creating an open program — it already has helpers/tests that build a program with an open cohort; reuse the same pattern for the affiliate program and give it an open registration window so only eligibility blocks it):

```php
    public function test_guest_cannot_open_affiliate_apply_form(): void
    {
        $program = \App\Models\Program::factory()->affiliate(1)->active()->create();
        \App\Models\Cohort::factory()->create([
            'program_id' => $program->id,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addWeek(),
        ]);

        $this->get(route('program.apply', $program))
            ->assertRedirect(route('program.show', $program));
    }

    public function test_guest_post_to_affiliate_apply_is_rejected(): void
    {
        $program = \App\Models\Program::factory()->affiliate(1)->active()->create();
        \App\Models\Cohort::factory()->create([
            'program_id' => $program->id,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addWeek(),
        ]);

        $this->post(route('program.apply.store', $program), [])
            ->assertRedirect(route('program.show', $program));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=PublicApplyTest`
Expected: the two new tests FAIL (form renders / validation errors instead of redirect); existing tests still pass.

- [ ] **Step 3: Add the guard to both actions**

In `app/Http/Controllers/ApplicationController.php`, add the import:

```php
use App\Support\ProgramEligibility;
```

In `create(...)`, directly after the `if (! $program->isOpen()) { ... }` block, add:

```php
        if (! app(ProgramEligibility::class)->canAccess(Auth::user()?->person, $program)) {
            return redirect()->route('program.show', $program);
        }
```

In `store(...)`, add the identical block in the same position (after its `isOpen()` guard). UI state is never the only gate; this enforces eligibility on the real submit.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=PublicApplyTest`
Expected: PASS (new + existing).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ApplicationController.php tests/Feature/PublicApplyTest.php
git commit -m "feat: server-side eligibility guard on the apply funnel"
```

---

## Task 4: Public chooser sections + lock modal + landing CTA

**Files:**
- Modify: `app/Http/Controllers/ProgramPageController.php`
- Modify: `resources/views/funnel/chooser.blade.php`
- Modify: `resources/views/funnel/program.blade.php`
- Create: `resources/views/funnel/partials/lock-modal.blade.php`
- Modify: `resources/views/components/layouts/public.blade.php` (include the partial)
- Modify: `resources/js/app.js` (toggle)
- Test: `tests/Feature/PublicCatalogTest.php` (append)

**Interfaces:**
- Consumes: `ProgramEligibility` (Task 2), `config('kheedma.default_locked_message')` (Task 1).
- Produces: chooser view receives `$programs` (general, open) and `$affiliate` (collection of `['program' => Program, 'locked' => bool, 'reason' => ?string, 'message' => string]`); landing view receives `$locked` (bool) and `$lockedMessage` (string). Lock trigger markup contract for JS: `data-lock-trigger`, `data-lock-message`, `data-lock-reason` attributes; modal element id `lock-modal`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PublicCatalogTest.php`:

```php
    public function test_chooser_shows_affiliate_section_with_locked_teaser(): void
    {
        \App\Models\Program::factory()->affiliate(1)->active()->create(['name' => 'Affiliate Kelas Satu']);

        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Kheedma Affiliate Community')
            ->assertSee('Affiliate Kelas Satu')
            ->assertSee('Terkunci')
            ->assertSee('data-lock-trigger', false);
    }

    public function test_chooser_hides_inactive_affiliate_classes(): void
    {
        \App\Models\Program::factory()->affiliate(1)->draft()->create(['name' => 'Affiliate Rahasia']);

        $this->get('/daftar')
            ->assertOk()
            ->assertDontSee('Affiliate Rahasia');
    }

    public function test_affiliate_landing_shows_locked_state_not_apply_cta(): void
    {
        $program = \App\Models\Program::factory()->affiliate(1)->active()->create();
        \App\Models\Cohort::factory()->create([
            'program_id' => $program->id,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addWeek(),
        ]);

        $this->get(route('program.show', $program))
            ->assertOk()
            ->assertSee('Terkunci')
            ->assertDontSee(route('program.apply', $program), false);
    }

    public function test_locked_message_falls_back_to_config_default(): void
    {
        \App\Models\Program::factory()->affiliate(1)->active()->create(['locked_message' => null]);

        $this->get('/daftar')
            ->assertOk()
            ->assertSee(e(config('kheedma.default_locked_message')), false);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=PublicCatalogTest`
Expected: new tests FAIL; existing pass.

- [ ] **Step 3: Update ProgramPageController**

Replace the two methods in `app/Http/Controllers/ProgramPageController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Support\ProgramEligibility;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProgramPageController extends Controller
{
    public function __construct(private readonly ProgramEligibility $eligibility)
    {
    }

    /** Two-section chooser: open general programs, then the affiliate ladder. */
    public function chooser(): View
    {
        $person = Auth::user()?->person;

        $programs = Program::openForRegistration()->where('type', 'general')->latest()->get();

        // Affiliate classes are ALWAYS listed while active (teaser value),
        // locked or not, ordered by level.
        $affiliate = Program::query()
            ->where('status', 'active')
            ->where('type', 'affiliate_community')
            ->orderBy('level')
            ->get()
            ->map(fn (Program $program) => [
                'program' => $program,
                'locked' => ! $this->eligibility->canAccess($person, $program),
                'reason' => $this->eligibility->lockReason($person, $program),
                'message' => $program->locked_message ?? config('kheedma.default_locked_message'),
            ]);

        return view('funnel.chooser', compact('programs', 'affiliate'));
    }

    /** Program promo landing. Locked affiliate classes render as a teaser. */
    public function show(Program $program): View
    {
        abort_if($program->status === 'draft', 404);

        $isOpen = $program->isOpen();
        $locked = ! $this->eligibility->canAccess(Auth::user()?->person, $program);

        return view('funnel.program', [
            'program' => $program,
            'isOpen' => $isOpen,
            'openCohort' => $isOpen ? $program->openCohort() : null,
            'locked' => $locked,
            'lockedMessage' => $program->locked_message ?? config('kheedma.default_locked_message'),
            'lockReason' => $this->eligibility->lockReason(Auth::user()?->person, $program),
        ]);
    }
}
```

- [ ] **Step 4: Create the lock modal partial**

Create `resources/views/funnel/partials/lock-modal.blade.php`. One hidden modal per page; JS fills the message and shows reason-appropriate CTAs. Styling follows the chooser's card idiom (teal, rounded-3xl).

```blade
{{-- Lock explainer modal. Filled and toggled by initLockModal() in app.js. --}}
<div id="lock-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="lock-modal-title">
    <div class="absolute inset-0 bg-teal-950/70 backdrop-blur-sm" data-lock-close></div>
    <div class="relative z-10 w-full max-w-md rounded-3xl border border-teal-900/10 bg-white p-7 shadow-xl sm:p-8">
        <span class="inline-block rounded-full bg-teal-100 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-teal-800">Terkunci</span>
        <h2 id="lock-modal-title" class="mt-3 text-xl font-bold text-teal-900">Kelas ini belum terbuka untukmu</h2>
        <p id="lock-modal-message" class="mt-2 text-sm leading-relaxed text-teal-800/80"></p>

        {{-- Guest CTAs: route into the funnel. Hidden for logged-in members. --}}
        <div id="lock-modal-guest-actions" class="mt-6 hidden flex-col gap-2 sm:flex-row">
            <a href="{{ route('daftar') }}" class="inline-flex flex-1 items-center justify-center rounded-full bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600">Lihat program yang dibuka</a>
            <a href="{{ url('/komunitas') }}" class="inline-flex flex-1 items-center justify-center rounded-full border border-teal-900/15 px-5 py-2.5 text-sm font-semibold text-teal-900 transition hover:bg-teal-50">Gabung Komunitas</a>
        </div>

        <div class="mt-4 text-center">
            <button type="button" data-lock-close class="text-sm font-medium text-teal-700 underline-offset-4 hover:underline">Tutup</button>
        </div>
    </div>
</div>
```

In `resources/views/components/layouts/public.blade.php`, include the partial just before the closing `</body>` tag (one line):

```blade
    @include('funnel.partials.lock-modal')
```

- [ ] **Step 5: Add the toggle to app.js**

Append to `resources/js/app.js` (and call it from the file's existing DOM-ready bootstrap alongside the other `init*()` calls — match how `initRegionSelects()` is invoked):

```js
/**
 * Locked-class explainer. Any element with data-lock-trigger opens the shared
 * #lock-modal, filling the message and showing guest CTAs when the visitor
 * is not logged in (reason 'guest').
 */
function initLockModal() {
    const modal = document.getElementById('lock-modal');
    if (!modal) {
        return;
    }

    const message = modal.querySelector('#lock-modal-message');
    const guestActions = modal.querySelector('#lock-modal-guest-actions');

    function open(trigger) {
        message.textContent = trigger.dataset.lockMessage || '';
        guestActions.classList.toggle('hidden', trigger.dataset.lockReason !== 'guest');
        guestActions.classList.toggle('flex', trigger.dataset.lockReason === 'guest');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function close() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.querySelectorAll('[data-lock-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            open(trigger);
        });
    });
    modal.querySelectorAll('[data-lock-close]').forEach((el) => el.addEventListener('click', close));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });
}
```

- [ ] **Step 6: Update the chooser view**

In `resources/views/funnel/chooser.blade.php`, wrap the existing general-programs `@foreach` under a section heading, and add the affiliate section between it and the community card. Replace the `<div class="mt-10 space-y-4">…</div>` block content as follows (the general card markup inside the first `@foreach` stays byte-identical to what is there now):

```blade
            <div class="mt-10 space-y-4">
                @if ($programs->isNotEmpty())
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-teal-700">Program</p>
                @endif
                @foreach ($programs as $program)
                    {{-- (existing general program card markup, unchanged) --}}
                @endforeach

                @if ($affiliate->isNotEmpty())
                    <p class="pt-4 font-display text-xs uppercase tracking-[0.3em] text-teal-700">Kheedma Affiliate Community</p>
                    @foreach ($affiliate as $entry)
                        @if ($entry['locked'])
                            <button
                                type="button"
                                data-lock-trigger
                                data-lock-message="{{ $entry['message'] }}"
                                data-lock-reason="{{ $entry['reason'] }}"
                                class="block w-full rounded-3xl border border-teal-900/10 bg-white/40 p-6 text-left opacity-75 shadow-sm transition hover:opacity-100 sm:p-8"
                            >
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <span class="inline-block rounded-full bg-teal-100 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-teal-800">Terkunci · Level {{ $entry['program']->level }}</span>
                                        <h2 class="mt-3 text-xl font-bold text-teal-900/70">{{ $entry['program']->name }}</h2>
                                        @if ($entry['program']->tagline)
                                            <p class="mt-1.5 text-sm leading-relaxed text-teal-800/50">{{ $entry['program']->tagline }}</p>
                                        @endif
                                    </div>
                                    <svg class="h-5 w-5 shrink-0 text-teal-700/50" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="5" y="9" width="10" height="7" rx="1.5"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9" stroke-linecap="round"/>
                                    </svg>
                                </div>
                            </button>
                        @else
                            <a href="{{ route('program.show', $entry['program']) }}"
                               class="block rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur transition hover:border-teal-600/40 hover:shadow-md sm:p-8">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <span class="inline-block rounded-full bg-orange-100 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-700">Level {{ $entry['program']->level }}{{ $entry['program']->isOpen() ? '' : ' · Pendaftaran ditutup' }}</span>
                                        <h2 class="mt-3 text-xl font-bold text-teal-900">{{ $entry['program']->name }}</h2>
                                        @if ($entry['program']->tagline)
                                            <p class="mt-1.5 text-sm leading-relaxed text-teal-800/70">{{ $entry['program']->tagline }}</p>
                                        @endif
                                    </div>
                                    <svg class="h-5 w-5 shrink-0 text-teal-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                            </a>
                        @endif
                    @endforeach
                @endif

                {{-- (existing community door card, unchanged) --}}
            </div>
```

- [ ] **Step 7: Update the program landing CTA**

In `resources/views/funnel/program.blade.php`, the CTA block currently reads (line ~24):

```blade
                @if ($isOpen)
                    <x-cta :href="route('program.apply', $program)" label="Daftar Sekarang" />
```

Change the condition so a locked visitor sees the lock trigger instead of the apply link:

```blade
                @if ($isOpen && ! $locked)
                    <x-cta :href="route('program.apply', $program)" label="Daftar Sekarang" />
                @elseif ($locked)
                    <button
                        type="button"
                        data-lock-trigger
                        data-lock-message="{{ $lockedMessage }}"
                        data-lock-reason="{{ $lockReason }}"
                        class="inline-flex items-center gap-2 rounded-full bg-teal-900/10 px-6 py-3 text-sm font-semibold text-teal-900/60"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="9" width="10" height="7" rx="1.5"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9" stroke-linecap="round"/></svg>
                        Terkunci
                    </button>
```

Keep whatever `@else` branch follows (closed-state invite) untouched.

- [ ] **Step 8: Build + run tests**

Run: `npm run build` (expect clean) then `php artisan test --compact --filter=PublicCatalogTest`
Expected: PASS (new + existing). Also run `php artisan test --compact --filter=PublicApplyTest` (no regression).

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ProgramPageController.php resources/views/funnel/ resources/views/components/layouts/public.blade.php resources/js/app.js tests/Feature/PublicCatalogTest.php
git commit -m "feat: segmented chooser with locked affiliate teasers + lock modal"
```

---

## Task 5: Member area "Program untuk Anda"

**Files:**
- Modify: `app/Http/Controllers/MemberAreaController.php`
- Modify: `resources/views/member/akun.blade.php`
- Test: `tests/Feature/MemberAuthTest.php` — do NOT touch; add the new test to `tests/Feature/PublicCatalogTest.php` instead (it owns catalog-visibility assertions).

**Interfaces:**
- Consumes: `ProgramEligibility` (Task 2); lock trigger markup contract (Task 4 — the modal partial is already in the public layout).
- Produces: `member.akun` view receives `$affiliate` with the same entry shape as the chooser (`program`, `locked`, `reason`, `message`).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/PublicCatalogTest.php`. Build the member the same way `MemberAuthTest` does (User with `participant` role linked to a Person via `people.user_id`) — copy that file's setup pattern; the essential shape:

```php
    public function test_member_area_lists_affiliate_ladder_with_lock_state(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        \App\Models\Program::factory()->affiliate(1)->active()->create(['name' => 'Affiliate Kelas Satu']);

        $user = \App\Models\User::factory()->create();
        $user->assignRole('participant');
        \App\Models\Person::create([
            'name' => 'Member Uji',
            'phone' => '+628111111111',
            'email' => 'member.uji@example.test',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/akun')
            ->assertOk()
            ->assertSee('Program untuk Anda')
            ->assertSee('Affiliate Kelas Satu')
            ->assertSee('data-lock-trigger', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_member_area_lists_affiliate_ladder_with_lock_state`
Expected: FAIL ("Program untuk Anda" not present).

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/MemberAreaController.php`, add imports:

```php
use App\Models\Program;
use App\Support\ProgramEligibility;
```

In `index(...)`, after `$person = ...` is resolved, add:

```php
        $eligibility = app(ProgramEligibility::class);
        $affiliate = Program::query()
            ->where('status', 'active')
            ->where('type', 'affiliate_community')
            ->orderBy('level')
            ->get()
            ->map(fn (Program $program) => [
                'program' => $program,
                'locked' => ! $eligibility->canAccess($person, $program),
                'reason' => $eligibility->lockReason($person, $program),
                'message' => $program->locked_message ?? config('kheedma.default_locked_message'),
            ]);
```

And pass `'affiliate' => $affiliate,` in the `view('member.akun', [...])` array.

- [ ] **Step 4: Add the view section**

In `resources/views/member/akun.blade.php`, after the "Status Pendaftaran" block (`@if ($applications->isNotEmpty()) ... @endif`, ends near line 75), add:

```blade
            @if ($affiliate->isNotEmpty())
                <div class="mt-10">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/60">Program untuk Anda</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($affiliate as $entry)
                            @if ($entry['locked'])
                                <button
                                    type="button"
                                    data-lock-trigger
                                    data-lock-message="{{ $entry['message'] }}"
                                    data-lock-reason="{{ $entry['reason'] }}"
                                    class="flex w-full items-center justify-between gap-4 rounded-2xl border border-teal-900/10 bg-white/50 px-5 py-4 text-left opacity-75 transition hover:opacity-100"
                                >
                                    <div>
                                        <p class="font-semibold text-teal-900/70">{{ $entry['program']->name }}</p>
                                        <p class="text-xs text-teal-800/50">Level {{ $entry['program']->level }} · Terkunci</p>
                                    </div>
                                    <svg class="h-4 w-4 shrink-0 text-teal-700/50" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="9" width="10" height="7" rx="1.5"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9" stroke-linecap="round"/></svg>
                                </button>
                            @else
                                <a href="{{ route('program.show', $entry['program']) }}"
                                   class="flex items-center justify-between gap-4 rounded-2xl border border-teal-900/10 bg-white px-5 py-4 transition hover:border-teal-600/40">
                                    <div>
                                        <p class="font-semibold text-teal-900">{{ $entry['program']->name }}</p>
                                        <p class="text-xs text-teal-800/60">Level {{ $entry['program']->level }} · Terbuka untukmu</p>
                                    </div>
                                    <svg class="h-4 w-4 shrink-0 text-teal-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter=PublicCatalogTest`
Expected: PASS. Also `php artisan test --compact --filter=MemberAuthTest` (no regression).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/MemberAreaController.php resources/views/member/akun.blade.php tests/Feature/PublicCatalogTest.php
git commit -m "feat: affiliate ladder with lock state in the member area"
```

---

## Task 6: Admin Programs — type, level, locked message

**Files:**
- Modify: `app/Http/Controllers/Api/Admin/ProgramController.php`
- Modify: `resources/js/admin/views/Programs.vue`
- Test: `tests/Feature/ProgramManagementTest.php` (append)

**Interfaces:**
- Consumes: Task 1 fields.
- Produces: API accepts/returns `type`, `level`, `locked_message`; `row()` gains those three keys. Vue form sends them (full payload, as the existing form does).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/ProgramManagementTest.php` (reuse the file's existing acting-as-admin helper/pattern):

```php
    public function test_affiliate_program_requires_level(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'Affiliate Tanpa Level',
                'slug' => 'affiliate-tanpa-level',
                'status' => 'draft',
                'selection_mode' => 'selective',
                'type' => 'affiliate_community',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('level');
    }

    public function test_general_program_rejects_level(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'Umum Berlevel',
                'slug' => 'umum-berlevel',
                'status' => 'draft',
                'selection_mode' => 'selective',
                'type' => 'general',
                'level' => 2,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('level');
    }

    public function test_segmentation_fields_round_trip_through_the_api(): void
    {
        $res = $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'Affiliate Level Dua',
                'slug' => 'affiliate-level-dua',
                'status' => 'active',
                'selection_mode' => 'selective',
                'type' => 'affiliate_community',
                'level' => 2,
                'locked_message' => 'Selesaikan Level 1 dulu.',
            ])
            ->assertCreated();

        $res->assertJsonPath('program.type', 'affiliate_community')
            ->assertJsonPath('program.level', 2)
            ->assertJsonPath('program.locked_message', 'Selesaikan Level 1 dulu.');
    }

    public function test_switching_type_to_general_clears_level(): void
    {
        $program = \App\Models\Program::factory()->affiliate(2)->create();

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/programs/{$program->slug}", ['type' => 'general'])
            ->assertOk()
            ->assertJsonPath('program.type', 'general')
            ->assertJsonPath('program.level', null);
    }
```

Note: the admin programs routes bind `{program:id}` (`Route::patch('/programs/{program:id}', ...)`) — if the PATCH above 404s, use `$program->id` instead of `$program->slug` in the URL. Match the file's existing update tests.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ProgramManagementTest`
Expected: new tests FAIL (unknown fields ignored / no validation); existing pass.

- [ ] **Step 3: Extend validation and row**

In `app/Http/Controllers/Api/Admin/ProgramController.php` `validated(...)`, resolve the effective type first, then add the three rules. Replace the method body's `return $request->validate([...]);` with:

```php
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
                ? ['required', 'integer', 'min:1', 'max:255']
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
```

In `row(...)`, add after `'selection_mode' => ...`:

```php
            'type' => $p->type,
            'level' => $p->level !== null ? (int) $p->level : null,
            'locked_message' => $p->locked_message,
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ProgramManagementTest`
Expected: PASS (new + existing). If `'level' => ['prohibited']` fails an existing test that sends `level: null`, relax to `['prohibited_unless:type,affiliate_community', 'nullable']` and note it in the report.

- [ ] **Step 5: Extend the Vue form + list**

In `resources/js/admin/views/Programs.vue`:

a) Extend both form initializers (create in `openCreate()` at ~line 62 and the `const form = ref({...})` at ~line 16, and the `openEdit()` mapping at ~line 69) with:

```js
type: 'general', level: '', locked_message: ''
```

(in `openEdit`: `type: program.type, level: program.level ?? '', locked_message: program.locked_message ?? ''`).

b) Add a `TYPE_OPTIONS` constant beside the existing `STATUS_OPTIONS`:

```js
const TYPE_OPTIONS = [
    { value: 'general', label: 'Program Umum' },
    { value: 'affiliate_community', label: 'Affiliate Community' },
];
```

c) In the dialog form (after the "Mode seleksi" ToggleGroup block), add — following the exact ToggleGroup idiom already used for status/selection_mode, including a `setType` function mirroring `setStatus`:

```vue
                <div>
                    <label class="text-xs text-muted-foreground">Tipe</label>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        class="mt-1.5 w-full"
                        :model-value="form.type"
                        @update:model-value="setType"
                    >
                        <ToggleGroupItem v-for="option in TYPE_OPTIONS" :key="option.value" :value="option.value" class="flex-1">
                            {{ option.label }}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
                <div v-if="form.type === 'affiliate_community'">
                    <label class="text-xs text-muted-foreground">Level</label>
                    <Input v-model="form.level" type="number" min="1" placeholder="1" class="mt-1.5" />
                    <p v-if="formErrors.level" class="mt-1 text-xs text-destructive">{{ formErrors.level[0] }}</p>
                </div>
                <div v-if="form.type === 'affiliate_community'">
                    <label class="text-xs text-muted-foreground">Pesan terkunci (opsional)</label>
                    <textarea
                        v-model="form.locked_message"
                        rows="3"
                        placeholder="Kosongkan untuk memakai pesan default."
                        class="mt-1.5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    ></textarea>
                </div>
```

With `setType` in the script, beside `setStatus`, mirroring the existing setters' style (they assign `form.value.<field> = value` when value is truthy):

```js
function setType(value) {
    if (value) {
        form.value.type = value;
        if (value === 'general') {
            form.value.level = '';
            form.value.locked_message = '';
        }
    }
}
```

d) In `save()`'s payload construction, ensure the three fields are sent, with level coerced: `level: form.value.type === 'affiliate_community' ? Number(form.value.level) : undefined` and `locked_message: form.value.locked_message || null`, `type: form.value.type`. Omit `level` entirely (undefined) for general so the `prohibited` rule passes. Match how the existing payload object is built in that function.

e) In the list table, in the name/status column area add a type badge (find the row cells and add one `<Badge>`): `<Badge variant="secondary">{{ program.type === 'affiliate_community' ? 'Affiliate L' + program.level : 'Umum' }}</Badge>`.

- [ ] **Step 6: Build + verify**

Run: `npm run build`
Expected: clean build. Then run the full backend suite: `php artisan test --compact` — everything green.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/ProgramController.php resources/js/admin/views/Programs.vue tests/Feature/ProgramManagementTest.php
git commit -m "feat: admin manages program type, level, and locked message"
```

---

## Self-Review

**Spec coverage:** §1 data → Task 1; §2 service → Task 2; §3 chooser (3 card states, sections, modal, guest CTA) → Task 4; §4 landing + server guard → Tasks 3-4; §5 member area → Task 5; §6 admin → Task 6; §7 testing matrix → Tasks 2-6 tests. Eligibility contract stated verbatim in Global Constraints. ✓

**Placeholder scan:** clean — no TBDs, every code step carries complete code, verify commands runnable. ✓

**Type consistency:** entry shape `{program, locked, reason, message}` identical in Tasks 4 and 5; lock trigger attributes (`data-lock-trigger`, `data-lock-message`, `data-lock-reason`) and `#lock-modal` id consistent across Tasks 4-5 and app.js; `lockReason` strings match Global Constraints everywhere; `affiliate(int $level)` factory signature used consistently. ✓

**Karpathy check:** no speculative fields (`required_program_id` dropped per spec decision); chooser/general card markup preserved byte-identical; guards reuse the existing redirect idiom; admin validation follows the file's own `creating/sometimes` pattern; every task's steps carry runnable verify commands. ✓
