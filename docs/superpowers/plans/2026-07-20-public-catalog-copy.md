# Public Catalog & Copy (Spec 2 Phase C) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Guests see the batch's class list (title + date + type only) on the program landing page and a "N kelas" meta on chooser cards; offline classes in the member timeline get the fixed "Kenapa hadir offline?" benefit block.

**Architecture:** Read-only presentation work. `ProgramPageController::show` passes a guest-safe class list mapped in the controller (no venue/link fields ever reach the view data); `chooser()` adds a class count per open program; the member timeline gains a static Blade copy block on offline session cards.

**Tech Stack:** Laravel 13 / PHP 8.4, Blade + Tailwind v4, PHPUnit 12. No JS/Vue changes, no migrations.

**Spec:** `docs/superpowers/specs/2026-07-18-batch-cohorts-class-revival-rsvp-design.md` — "Public catalog" section, Governing decision 7, Member UI item 4, Phase C.

## Global Constraints

- Code 100% English; UI copy 100% Indonesian, warm "kamu" register, NO em-dashes.
- Public catalog shows per class ONLY: title, date, type. Location details, addresses, coordinates, and meeting links must never leak to guests (assert `assertDontSee` in tests).
- Offline benefits are a fixed copy block (spec: pemantauan langsung mentor, review editing video oleh mentor, praktik posting sosmed langsung) — static Indonesian copy, NOT a CMS feature.
- View logic computed in the controller, not in Blade (house rule).
- Reuse the open cohort's session list; no new query concepts.
- After PHP changes run `vendor/bin/pint --dirty --format agent`. Never migrate:fresh/refresh/db:wipe on local MySQL. Never `git add -A` (repo-root `test.md` stays untracked).

---

### Task 1: public class list on the program landing page

**Files:**
- Modify: `app/Http/Controllers/ProgramPageController.php` (`show()`)
- Modify: `resources/views/funnel/program.blade.php` (insert the class-list block after the sections/description block, before the CTA `<div class="mt-10 text-center">`)
- Test: `tests/Feature/ProgramDetailTest.php` (extend; read its existing setup helpers first and reuse them)

**Interfaces:**
- Consumes: `Program::openCohort()`, `CohortSession::scheduledLabel(): ?string`, `CohortSession::isOnline(): bool`.
- Produces: view data `openClasses` — array of `['title' => string, 'schedule' => ?string, 'is_online' => bool]`, ordered by `scheduled_at` (nulls last). Empty array when the program is closed or the cohort has no classes.

- [ ] **Step 1: Write the failing tests** (append to `ProgramDetailTest`, matching its existing style of building an open program+cohort; add a session factory import if missing)

```php
    public function test_guest_sees_class_list_titles_dates_and_type_only(): void
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->openWindow()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->create([
            'title' => 'Kelas 1: Riset Produk',
            'scheduled_at' => now()->addDays(5)->setTime(9, 0),
            'type' => 'offline',
            'location_name' => 'Kantor Rahasia',
            'location_address' => 'Jl. Tersembunyi 1',
        ]);
        CohortSession::factory()->for($cohort)->create([
            'title' => 'Kelas 2: Praktik Posting',
            'scheduled_at' => now()->addDays(12)->setTime(9, 0),
            'type' => 'online',
            'meeting_url' => 'https://meet.google.com/rahasia',
        ]);

        $this->get(route('program.show', $program))
            ->assertOk()
            ->assertSee('Kelas 1: Riset Produk')
            ->assertSee('Kelas 2: Praktik Posting')
            ->assertSee('Tatap muka')
            ->assertSee('Online')
            ->assertDontSee('Kantor Rahasia')
            ->assertDontSee('Jl. Tersembunyi 1')
            ->assertDontSee('meet.google.com', false);
    }

    public function test_closed_program_shows_no_class_list(): void
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]); // no open window
        CohortSession::factory()->for($cohort)->create(['title' => 'Kelas Tersembunyi Uji']);

        $this->get(route('program.show', $program))
            ->assertOk()
            ->assertDontSee('Kelas Tersembunyi Uji');
    }
```

Note: check `CohortFactory` for the correct open-window state name (`openWindow()` is used in `MemberAreaTest`); reuse whatever `ProgramDetailTest` already uses to build an open program, and only add what is missing.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=class_list tests/Feature/ProgramDetailTest.php`
Expected: FAIL (titles not rendered).

- [ ] **Step 3: Controller**

In `ProgramPageController::show()`, before the `return view(...)`:

```php
        // Public class list: the visitor learns the batch contains many
        // classes before registering. Guest-safe by construction — only
        // title/schedule/type are mapped; venue and links never leave here.
        $openCohort = $isOpen ? $program->openCohort() : null;
        $openClasses = $openCohort
            ? $openCohort->sessions()
                ->orderByRaw('scheduled_at IS NULL, scheduled_at')
                ->get()
                ->map(fn ($session) => [
                    'title' => $session->title,
                    'schedule' => $session->scheduledLabel(),
                    'is_online' => $session->isOnline(),
                ])->all()
            : [];
```

Reuse the `$openCohort` variable for the existing `'openCohort' => ...` view key (drop the duplicate `$program->openCohort()` call), and add `'openClasses' => $openClasses` to the view data.

- [ ] **Step 4: Blade block**

In `resources/views/funnel/program.blade.php`, after the sections/description `@endif` (line ~25) and before `<div class="mt-10 text-center">`:

```blade
    @if (count($openClasses))
        <div class="mt-10 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
            <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Jadwal Kelas</p>
            <h2 class="mt-2 text-lg font-bold text-teal-900">Apa saja yang akan kamu ikuti?</h2>
            <ul class="mt-4 divide-y divide-teal-900/5">
                @foreach ($openClasses as $i => $kelas)
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sand-100 text-xs font-bold text-teal-800/70">{{ $i + 1 }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-teal-900">{{ $kelas['title'] }}</span>
                            @if ($kelas['schedule'])
                                <span class="block text-xs text-teal-800/60">{{ $kelas['schedule'] }}</span>
                            @endif
                        </span>
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wide',
                            'bg-teal-100 text-teal-700' => ! $kelas['is_online'],
                            'bg-sand-100 text-teal-800/70' => $kelas['is_online'],
                        ])>{{ $kelas['is_online'] ? 'Online' : 'Tatap muka' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
```

(The numbered marker is honest structure here: classes are a real weekly sequence.)

- [ ] **Step 5: Run the full test file**

Run: `php artisan test --compact tests/Feature/ProgramDetailTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ProgramPageController.php resources/views/funnel/program.blade.php tests/Feature/ProgramDetailTest.php
git commit -m "feat: program page lists the batch's classes for guests"
```

---

### Task 2: "N kelas" meta on chooser cards

**Files:**
- Modify: `app/Http/Controllers/ProgramPageController.php` (`chooser()`)
- Modify: `resources/views/funnel/chooser.blade.php` (open-program card body, under the tagline around line 37-39)
- Test: `tests/Feature/PublicCatalogTest.php` (extend, matching its existing conventions)

**Interfaces:**
- Consumes: `Program::openCohort()`.
- Produces: each `$programs` entry gains `'class_count' => int` (0 when no open cohort or no classes).

- [ ] **Step 1: Write the failing test** (append to `PublicCatalogTest`; read its helpers first and mirror how it builds open programs)

```php
    public function test_chooser_card_shows_class_count(): void
    {
        $program = Program::factory()->active()->create(['name' => 'Program Hitung Kelas']);
        $cohort = Cohort::factory()->openWindow()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->count(4)->create();

        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Program Hitung Kelas')
            ->assertSee('4 kelas');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_chooser_card_shows_class_count`
Expected: FAIL.

- [ ] **Step 3: Controller**

In `chooser()`, extend the general-programs map:

```php
        $programs = Program::openForRegistration()->where('type', 'general')->latest()->get()
            ->map(fn (Program $program) => [
                'program' => $program,
                'chip' => $this->stateChip($person, $program),
                // "N kelas" tells the visitor one registration covers a series.
                'class_count' => $program->openCohort()?->sessions()->count() ?? 0,
            ]);
```

- [ ] **Step 4: Blade**

In the open-program card in `chooser.blade.php`, right after the tagline `@endif` (~line 39):

```blade
                                    @if ($entry['class_count'] > 0)
                                        <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-teal-700">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 9.5h17M8 3v4M16 3v4" stroke-linecap="round"/></svg>
                                            {{ $entry['class_count'] }} kelas dalam satu pendaftaran
                                        </p>
                                    @endif
```

- [ ] **Step 5: Run the full test file**

Run: `php artisan test --compact tests/Feature/PublicCatalogTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ProgramPageController.php resources/views/funnel/chooser.blade.php tests/Feature/PublicCatalogTest.php
git commit -m "feat: chooser cards say how many classes one registration covers"
```

---

### Task 3: offline benefit block on the member class timeline

**Files:**
- Modify: `resources/views/member/akun.blade.php` (offline branch of the per-session card, after the location collapsible/plain-text block, before the confirmation prompt)
- Test: `tests/Feature/MemberAreaTest.php` (extend)

**Interfaces:**
- Consumes: the existing `$session->isOnline()` branch structure in the Kelas tab (the offline branch ends right before `@php($konfirmasi = ...)`).
- Produces: static copy block, no new view data (fixed copy per spec Governing decision 7 — deliberately NOT CMS-editable).

- [ ] **Step 1: Write the failing tests** (append to `MemberAreaTest`)

```php
    public function test_offline_class_shows_benefit_block(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->create([
            'scheduled_at' => now()->addDays(3),
            'type' => 'offline',
            'location_name' => 'Kantor Kheedma',
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kenapa hadir offline?')
            ->assertSee('Pemantauan langsung dari mentor')
            ->assertSee('Review editing videomu oleh mentor')
            ->assertSee('Praktik posting sosmed langsung di kelas');
    }

    public function test_online_class_shows_no_offline_benefits(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->create([
            'scheduled_at' => now()->addDays(3),
            'type' => 'online',
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertDontSee('Kenapa hadir offline?');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=offline tests/Feature/MemberAreaTest.php`
Expected: FAIL.

- [ ] **Step 3: Blade block**

In `akun.blade.php`, inside the offline (`@else` of `$session->isOnline()`) branch, after the location collapsible / plain-text / "Lokasi kelas akan diumumkan." chain closes (its `@endif`) and still inside the offline branch, add:

```blade
                                                        {{-- Fixed offline-benefits copy (spec: deliberately not CMS-editable). --}}
                                                        <div class="mt-3 rounded-2xl bg-teal-900/[0.04] px-4 py-3.5">
                                                            <p class="text-xs font-bold uppercase tracking-wide text-teal-800/70">Kenapa hadir offline?</p>
                                                            <ul class="mt-2 space-y-1.5 text-sm text-teal-800/80">
                                                                <li class="flex items-start gap-2">
                                                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-teal-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10.5l4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                                    Pemantauan langsung dari mentor
                                                                </li>
                                                                <li class="flex items-start gap-2">
                                                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-teal-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10.5l4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                                    Review editing videomu oleh mentor
                                                                </li>
                                                                <li class="flex items-start gap-2">
                                                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-teal-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10.5l4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                                    Praktik posting sosmed langsung di kelas
                                                                </li>
                                                            </ul>
                                                        </div>
```

Match the file's real indentation (sed the region first; the snippet above assumes the current nesting depth of the venue blocks). Verify Blade directive balance after the edit (`grep -c '@if' / '@endif'` and foreach counts).

- [ ] **Step 4: Run the full MemberAreaTest file**

Run: `php artisan test --compact tests/Feature/MemberAreaTest.php`
Expected: PASS (all).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/member/akun.blade.php tests/Feature/MemberAreaTest.php
git commit -m "feat: offline classes state why showing up in person pays"
```

---

### Task 4: verification pass

**Files:** none — verification only (main session, not a subagent).

- [ ] **Step 1:** `php artisan test --compact` — full suite green.
- [ ] **Step 2:** Playwright: program landing page as guest (class list, no venue leak), /daftar chooser ("N kelas" meta), member timeline offline card (benefit block). Screenshots.
- [ ] **Step 3:** Ledger entry in `.superpowers/sdd/progress.md`.
