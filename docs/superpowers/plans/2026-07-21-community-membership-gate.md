# Community Membership Gate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make community membership the gate to the affiliate ladder — only Program-Umum graduates may JOIN the community, and only community members may take the leveled community classes.

**Architecture:** `ProgramEligibility` splits into two named checks: `canJoinCommunity` (join gate = completed a general intake + score bar when set) and a membership-based `canAccess`/`lockReason` for `affiliate_community` programs (member → Level 1; member + previous level → Level N). `CommunityController::join` enforces the join gate; the `/komunitas` and `/daftar` surfaces render the locked "Khusus" teaser. The "completed an intake" primitive (`hasCompletedACohort`) is shared by both the join gate and level progression.

**Tech Stack:** Laravel 13 / PHP 8.4, PHPUnit 12, Blade + Tailwind v4. No migrations. No new dependencies.

**Spec:** `docs/superpowers/specs/2026-07-21-community-membership-gate.md`.

**Per-domain skills to apply while implementing:** backend/Eloquent/controllers/tests → `laravel-best-practices` (esp. §5 Eloquent, §3 Security, §8 Testing, §10 Routing); Blade UI → `frontend-design` for the "Khusus" teaser card; self-review → `karpathy-guidelines`.

## Global Constraints

- Code/identifiers/comments 100% English; UI copy 100% Indonesian, warm "kamu" register, NO em-dashes.
- "Khusus" = qualification-limited, NOT paid; joining stays free of charge.
- Two ordered steps: join the community first, THEN register for a Level class.
- After joining, Level 1 opens immediately (no extra condition).
- Level N>1 additionally requires the previous level completed (existing progression).
- Grandfather existing members: the join gate applies to NEW joins only; a person with a `CommunityMembership` stays a member.
- Membership is never revoked (e.g. on being dropped from a general cohort).
- Guests and non-graduates still SEE the community (join card + leveled cards) as a locked teaser.
- "Completed an intake" = active enrollment in a cohort whose EVERY session the person attended (a 0-session cohort cannot be completed).
- View logic computed in the controller, not in Blade (house rule).
- After PHP changes run `vendor/bin/pint --dirty --format agent`. Never migrate:fresh/refresh/db:wipe. Never `git add -A` (repo-root `test.md` stays untracked).
- `ProgramEligibility::hasCompletedACohort` and `passesAny` already exist (added 2026-07-21) — reuse, do not duplicate.

---

### Task 1: `Person::isCommunityMember()` helper

**Files:**
- Modify: `app/Models/Person.php` (next to the existing `communityMembership(): HasOne` relation, ~line 91)
- Test: `tests/Feature/PersonAccountTest.php` (extend; reuse its existing Person creation style)

**Interfaces:**
- Consumes: existing `Person::communityMembership(): HasOne` relation.
- Produces: `Person::isCommunityMember(): bool`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/PersonAccountTest.php` (import `App\Models\CommunityMembership` if not present):

```php
    public function test_is_community_member_reflects_membership(): void
    {
        $person = Person::create([
            'name' => 'Uji Member',
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);

        $this->assertFalse($person->isCommunityMember());

        CommunityMembership::create(['people_id' => $person->id]);

        $this->assertTrue($person->fresh()->isCommunityMember());
    }
```

Verify `CommunityMembership` fillable allows `people_id` (check `app/Models/CommunityMembership.php`); if the model needs more required columns, set them here to match its migration.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_is_community_member_reflects_membership`
Expected: FAIL (method `isCommunityMember` not defined).

- [ ] **Step 3: Implement the helper**

In `app/Models/Person.php`, directly after the `communityMembership()` relation method:

```php
    /** Community membership is the gate to the affiliate ladder. */
    public function isCommunityMember(): bool
    {
        // Honor an eager-loaded relation (the chooser/member-area load it and
        // call this per affiliate program) instead of re-querying per call.
        return $this->relationLoaded('communityMembership')
            ? $this->communityMembership !== null
            : $this->communityMembership()->exists();
    }
```

Rationale (verified 2026-07-21): `MemberAreaController` eager-loads `communityMembership`, and `ProgramEligibility::lockReason` runs once per affiliate program in the `/daftar` map — a bare `exists()` would issue one COUNT per program. The `relationLoaded` guard also avoids a lazy-load exception if `preventLazyLoading` is on.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=test_is_community_member_reflects_membership`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Person.php tests/Feature/PersonAccountTest.php
git commit -m "feat: Person::isCommunityMember reports community membership"
```

---

### Task 2: `ProgramEligibility` join gate (`canJoinCommunity` / `joinLockReason`)

**Files:**
- Modify: `app/Support/ProgramEligibility.php` (add two public methods; reuse the private `passesAny`)
- Test: `tests/Feature/ProgramEligibilityTest.php` (extend)

**Interfaces:**
- Consumes: existing private `ProgramEligibility::passesAny(Person, callable): bool` and `hasCompletedACohort`.
- Produces: `ProgramEligibility::joinLockReason(?Person): ?string` (null | `guest` | `needs_general`) and `ProgramEligibility::canJoinCommunity(?Person): bool`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/ProgramEligibilityTest.php` (it already imports Assignment, AssignmentSubmission, Attendance, Cohort, CohortSession, Enrollment, Person, Program, ProgramEligibility). Its `attendProgram($person, $program)` helper creates a 1-session cohort with one attendance = a completed intake:

```php
    public function test_guest_cannot_join_community(): void
    {
        $this->assertFalse(app(ProgramEligibility::class)->canJoinCommunity(null));
        $this->assertSame('guest', app(ProgramEligibility::class)->joinLockReason(null));
    }

    public function test_graduate_of_general_can_join_community(): void
    {
        $general = Program::factory()->active()->create();
        $person = $this->makePerson();
        $this->attendProgram($person, $general);

        $this->assertTrue(app(ProgramEligibility::class)->canJoinCommunity($person));
        $this->assertNull(app(ProgramEligibility::class)->joinLockReason($person));
    }

    public function test_partial_general_attendance_cannot_join_community(): void
    {
        $general = Program::factory()->active()->create();
        $person = $this->makePerson();
        $cohort = Cohort::factory()->create(['program_id' => $general->id]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        $sessions = CohortSession::factory()->count(3)->for($cohort)->create();
        Attendance::create(['cohort_session_id' => $sessions[0]->id, 'enrollment_id' => $enrollment->id]);

        $this->assertSame('needs_general', app(ProgramEligibility::class)->joinLockReason($person));
    }

    public function test_below_the_score_bar_cannot_join_community(): void
    {
        $general = Program::factory()->active()->create(['min_average_score' => 75]);
        $person = $this->makePerson();
        $cohort = Cohort::factory()->create(['program_id' => $general->id]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        $session = CohortSession::factory()->for($cohort)->create();
        $assignment = Assignment::factory()->for($session, 'session')->create();
        AssignmentSubmission::factory()->graded(60)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->assertSame('needs_general', app(ProgramEligibility::class)->joinLockReason($person));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=join_community tests/Feature/ProgramEligibilityTest.php`
Expected: FAIL (method `canJoinCommunity`/`joinLockReason` not defined).

- [ ] **Step 3: Implement the join gate**

In `app/Support/ProgramEligibility.php`, add after `lockReason()`:

```php
    /** Whether the person may join the affiliate community (become a member). */
    public function canJoinCommunity(?Person $person): bool
    {
        return $this->joinLockReason($person) === null;
    }

    /**
     * null when the person may join; otherwise guest | needs_general.
     * Joining requires completing a Program Umum intake (attending every class
     * of one cohort) and, where that program sets a score bar with soal,
     * clearing it - the same measure `passesAny` applies over general programs.
     */
    public function joinLockReason(?Person $person): ?string
    {
        if ($person === null) {
            return 'guest';
        }

        return $this->passesAny($person, fn (Builder $q) => $q->where('type', 'general'))
            ? null
            : 'needs_general';
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=join_community tests/Feature/ProgramEligibilityTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/ProgramEligibility.php tests/Feature/ProgramEligibilityTest.php
git commit -m "feat: ProgramEligibility gates joining the community on general completion"
```

---

### Task 3: Membership-based access to leveled community classes

**Files:**
- Modify: `app/Support/ProgramEligibility.php` (`lockReason` for affiliate programs)
- Test: `tests/Feature/ProgramEligibilityTest.php` (rework affiliate-access tests)

**Interfaces:**
- Consumes: `Person::isCommunityMember()` (Task 1), existing `passesAny`.
- Produces: updated `ProgramEligibility::lockReason(?Person, Program)` for `affiliate_community` — reasons `guest` | `needs_membership` | `needs_previous_level`.

- [ ] **Step 1: Rework the affiliate-access tests**

In `tests/Feature/ProgramEligibilityTest.php`, replace the bodies of the affiliate-access tests so membership is the Level-1 gate. Add `use App\Models\CommunityMembership;` to the imports. A helper keeps setup DRY — add it near `attendProgram`:

```php
    private function makeMember(): Person
    {
        $person = $this->makePerson();
        CommunityMembership::create(['people_id' => $person->id]);

        return $person;
    }
```

Replace `test_member_without_attendance_needs_general` → now a NON-member needs membership:

```php
    public function test_non_member_needs_membership_for_level_1(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $person = $this->makePerson();

        $this->assertFalse($eligibility->canAccess($person, $level1));
        $this->assertSame('needs_membership', $eligibility->lockReason($person, $level1));
    }
```

Replace `test_attending_general_unlocks_level_1_but_not_level_2` → membership unlocks Level 1, not Level 2:

```php
    public function test_membership_unlocks_level_1_but_not_level_2(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $level2 = Program::factory()->affiliate(2)->active()->create();
        $person = $this->makeMember();

        $this->assertNull($eligibility->lockReason($person, $level1));
        $this->assertSame('needs_previous_level', $eligibility->lockReason($person, $level2));
    }
```

Replace `test_attending_level_1_unlocks_level_2` → member who completed Level 1 unlocks Level 2:

```php
    public function test_completing_level_1_unlocks_level_2_for_a_member(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $level2 = Program::factory()->affiliate(2)->active()->create();
        $person = $this->makeMember();
        $this->attendProgram($person, $level1); // completes a Level-1 intake

        $this->assertNull($eligibility->lockReason($person, $level2));
    }
```

Update the score-gate-between-levels test so the person is a member (it currently sets a graded submission on level 1 but no membership) — replace `test_score_gate_applies_between_community_levels`:

```php
    public function test_score_gate_applies_between_community_levels(): void
    {
        $level1 = Program::factory()->affiliate(1)->active()->create(['min_average_score' => 70]);
        $level2 = Program::factory()->affiliate(2)->active()->create();
        $person = $this->makeMember();
        $cohort = Cohort::factory()->create(['program_id' => $level1->id]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        $session = CohortSession::factory()->for($cohort)->create();
        $assignment = Assignment::factory()->for($session, 'session')->create();
        AssignmentSubmission::factory()->graded(72)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->assertNull(app(ProgramEligibility::class)->lockReason($person, $level2));
    }
```

Delete the now-obsolete tests whose meaning moved to the join gate (Task 2 covers these at the join scope): `test_meeting_the_score_bar_unlocks_level_1`, `test_below_the_score_bar_stays_locked_even_with_attendance`, `test_threshold_without_assignments_falls_back_to_attendance`, `test_enrollment_without_attendance_does_not_count`, `test_partial_attendance_of_a_cohort_does_not_unlock`. (These asserted the OLD "general completion unlocks Level 1" rule, now replaced by membership.) Keep `test_program_segmentation_fields_round_trip`, `test_general_program_is_open_to_everyone`, `test_guest_is_locked_out_of_affiliate`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/ProgramEligibilityTest.php`
Expected: FAIL (Level 1 still unlocks via general completion, not membership).

- [ ] **Step 3: Implement membership-based access**

In `app/Support/ProgramEligibility.php`, replace the WHOLE `lockReason` method with the version below (the guest/isAffiliate guards are unchanged; the membership check replaces the general-completion check for Level 1):

```php
    public function lockReason(?Person $person, Program $program): ?string
    {
        if (! $program->isAffiliate()) {
            return null;
        }

        if ($person === null) {
            return 'guest';
        }

        // Community classes are members-only. Membership already implies a
        // finished Program Umum intake, so Level 1 needs nothing further.
        if (! $person->isCommunityMember()) {
            return 'needs_membership';
        }

        $level = $program->level ?? 1;

        if ($level <= 1) {
            return null;
        }

        return $this->passesAny($person, fn (Builder $q) => $q->where('type', 'affiliate_community')->where('level', $level - 1))
            ? null
            : 'needs_previous_level';
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/ProgramEligibilityTest.php`
Expected: PASS.

- [ ] **Step 5: Full suite (eligibility is wired into member area + funnel)**

Run: `php artisan test --compact`
Expected: PASS. If `MemberAreaTest`/`PublicCatalogTest`/`ProgramDetailTest` assert the OLD "attendance unlocks community" copy, fix those assertions to the new membership rule as part of this task (they render `ProgramEligibility` output).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/ProgramEligibility.php tests/Feature/ProgramEligibilityTest.php
git commit -m "feat: leveled community classes are gated on membership, not general completion"
```

---

### Task 4: Enforce the join gate in `CommunityController::join`

**Files:**
- Modify: `app/Http/Controllers/CommunityController.php` (`join`, before `communityMembership()->firstOrCreate(...)` ~line 105)
- Test: `tests/Feature/CommunityJoinTest.php` (extend; follow its existing member/guest setup)

**Interfaces:**
- Consumes: `ProgramEligibility::canJoinCommunity(?Person)` (Task 2).
- Produces: `join` rejects a person who cannot yet join; existing members and eligible graduates are unaffected.

- [ ] **Step 1: Write the failing tests**

VERIFIED conventions (2026-07-21): `CommunityJoinTest` already has `private function validPayload(): array` (the full join payload, no arg) and `private function participantWithProfile(array $personOverrides = []): User` (a logged-in participant whose Person carries the intake profile). The join route is `POST komunitas` → name `komunitas.join`. Reuse those; do NOT invent `member()`/`validJoinPayload($person)`. Add ONE new helper `completeGeneralIntake(Person): void` mirroring `ProgramEligibilityTest::attendProgram` (general program + cohort + enrollment + one session + attendance). Tests:

```php
    public function test_non_graduate_cannot_join_community(): void
    {
        $user = $this->participantWithProfile();
        // No completed general intake.

        $this->actingAs($user)
            ->post(route('komunitas.join'), $this->validPayload())
            ->assertRedirect();

        $this->assertNull($user->person->fresh()->communityMembership);
    }

    public function test_graduate_can_join_community(): void
    {
        $user = $this->participantWithProfile();
        $this->completeGeneralIntake($user->person);

        $this->actingAs($user)
            ->post(route('komunitas.join'), $this->validPayload())
            ->assertRedirect();

        $this->assertNotNull($user->person->fresh()->communityMembership);
    }

    public function test_existing_member_join_is_idempotent(): void
    {
        $user = $this->participantWithProfile();
        CommunityMembership::create(['people_id' => $user->person->id]);

        $this->actingAs($user)
            ->post(route('komunitas.join'), $this->validPayload())
            ->assertRedirect();

        $this->assertSame(1, $user->person->fresh()->communityMembership()->count());
    }
```

Add `use App\Models\CommunityMembership;` and whatever Cohort/Session/Enrollment/Attendance imports `completeGeneralIntake` needs. `validPayload()` posts the profile of a NEW person; since `participantWithProfile()` already logged one in, confirm whether the existing join tests post `validPayload()` as the same logged-in person or a fresh one, and match that (the guard runs on the resolved `$person`, so the logged-in participant is what matters).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/CommunityJoinTest.php`
Expected: FAIL (non-graduate currently joins successfully).

- [ ] **Step 3: Add the gate**

In `app/Http/Controllers/CommunityController.php::join`, inject the eligibility service and guard right before the membership is created. Resolve `$person` first (the method already resolves/provisions `$person`), then:

```php
        if (! app(ProgramEligibility::class)->canJoinCommunity($person)) {
            return redirect()
                ->route('komunitas')
                ->with('community_notice', 'Komunitas khusus untuk lulusan program. Selesaikan dulu semua kelas di satu angkatan.');
        }

        $person->communityMembership()->firstOrCreate(
            // ...existing arguments unchanged
        );
```

Add `use App\Support\ProgramEligibility;` at the top. Confirm the redirect route name via `php artisan route:list --path=komunitas` (use whatever the GET `/komunitas` route is named; the spec calls it `komunitas`). Place the guard AFTER the existing `$alreadyMember` short-circuit (line ~29) so an existing member is never bounced.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/CommunityJoinTest.php`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CommunityController.php tests/Feature/CommunityJoinTest.php
git commit -m "feat: joining the community requires finishing a general intake"
```

---

### Task 5: Locked "Khusus" state on the `/komunitas` join page

**Files:**
- Modify: `app/Http/Controllers/CommunityController.php` (`show` — pass a `joinLockReason`/`canJoin`)
- Modify: `resources/views/funnel/community.blade.php` (render the locked explainer for ineligible viewers)
- Test: `tests/Feature/CommunityJoinTest.php` (extend)

**Interfaces:**
- Consumes: `ProgramEligibility::canJoinCommunity` / `joinLockReason` (Task 2).
- Produces: `show` view data `canJoin` (bool) and `joinLockReason` (?string); a locked explainer replaces the form when `! $canJoin` and the viewer is not already a member.

- [ ] **Step 1: Write the failing tests**

```php
    public function test_ineligible_viewer_sees_locked_community_notice(): void
    {
        [$user, $person] = $this->member();

        $this->actingAs($user)->get(route('komunitas'))
            ->assertOk()
            ->assertSee('Khusus untuk lulusan program')
            ->assertDontSee('name="motivation"', false); // the join form is not rendered
    }

    public function test_graduate_sees_the_join_form(): void
    {
        [$user, $person] = $this->member();
        $this->completeGeneralIntake($person);

        $this->actingAs($user)->get(route('komunitas'))
            ->assertOk()
            ->assertSee('name="phone"', false); // form present
    }
```

Adjust the two `assertSee`/`assertDontSee` selectors to match a stable field actually present in `community.blade.php`'s join form (inspect the file; pick a `name="..."` that only the form renders).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=community_notice tests/Feature/CommunityJoinTest.php`
Expected: FAIL (form still shows for the ineligible viewer).

- [ ] **Step 3: Pass state from the controller**

VERIFIED (2026-07-21): `CommunityController::show` already computes `$person` and `$alreadyMember = (bool) $person?->communityMembership;` and passes `compact('person', 'alreadyMember', 'confirming', 'focusedEdit', 'sections')`. Add ONE variable next to `$alreadyMember`:

```php
        $canJoin = app(ProgramEligibility::class)->canJoinCommunity($person);
```

Add `use App\Support\ProgramEligibility;`. Extend the compact to `compact('person', 'alreadyMember', 'confirming', 'focusedEdit', 'sections', 'canJoin')`.

- [ ] **Step 4: Blade locked state**

VERIFIED structure of `resources/views/funnel/community.blade.php`: after the intro (`@unless ($focusedEdit) ... @endunless`), the body is `@if ($alreadyMember) [member view] @elseif ($confirming && ! $errors->any()) [confirm form] @else [full join form] @endif`. The locked state is a NEW branch inserted BETWEEN the already-member branch and the confirming branch, so an existing member is never shown the lock and neither form renders for an ineligible viewer:

```blade
            @if ($alreadyMember)
                {{-- existing already-member markup, unchanged --}}
            @elseif (! $canJoin)
                <div class="mt-10 rounded-3xl border border-teal-900/10 bg-white/70 p-6 text-center shadow-sm backdrop-blur sm:p-8">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-sand-100 text-teal-700">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4" stroke-linecap="round"/></svg>
                    </span>
                    <h2 class="mt-4 text-lg font-bold text-teal-900">Khusus untuk lulusan program</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-teal-800/70">
                        Komunitas ini untuk kamu yang sudah menyelesaikan semua kelas di satu angkatan program. Selesaikan dulu programmu, lalu kamu bisa gabung.
                    </p>
                    <a href="{{ route('daftar') }}" class="mt-5 inline-flex items-center rounded-full bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-900">Lihat program</a>
                </div>
            @elseif ($confirming && ! $errors->any())
                {{-- existing confirm-form markup, unchanged --}}
            @else
                {{-- existing full join-form markup, unchanged --}}
            @endif
```

The intro block above (`@unless ($focusedEdit)`) is unrelated and stays. Match the page's card styling; apply `frontend-design` sensibilities to the teaser.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/CommunityJoinTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CommunityController.php resources/views/funnel/community.blade.php tests/Feature/CommunityJoinTest.php
git commit -m "feat: the community page shows a Khusus lock until you finish a program"
```

---

### Task 6: `/daftar` chooser — "Khusus" join card + membership-gated ladder

**Files:**
- Modify: `app/Http/Controllers/ProgramPageController.php` (`chooser` — pass `canJoinCommunity`; eager-load membership on `$person`)
- Modify: `resources/views/funnel/chooser.blade.php` (the "Gabung Komunitas" card ~lines 55-70; the affiliate ladder ~76+)
- Test: `tests/Feature/PublicCatalogTest.php` (extend)

**Interfaces:**
- Consumes: `ProgramEligibility::canJoinCommunity` (Task 2), membership-based `lockReason` (Task 3).
- Produces: `chooser` view gains `$canJoinCommunity` (bool); the join card renders "Khusus" and locks when `! $canJoinCommunity`; leveled cards already read `$entry['locked']` from `lockReason`.

- [ ] **Step 1: Write the failing tests**

VERIFIED (2026-07-21): `PublicCatalogTest` has NO generic logged-in-participant helper — it builds users inline with `$user->assignRole('participant')` (see `memberWithPendingApplication`). It has `openProgram(): Program`. Add two small private helpers to the class: `participant(): array` returning `[User, Person]` (User factory + `assignRole('participant')` + a Person associated to the user, mirroring `MemberAreaTest::member()`), and `completeGeneralIntake(Person): void` (mirror `ProgramEligibilityTest::attendProgram`). Add `use App\Models\CommunityMembership;` and Cohort/Session/Enrollment/Attendance imports as needed. Tests:

```php
    public function test_join_card_is_khusus_and_locked_for_a_non_graduate(): void
    {
        [$user] = $this->participant();
        // No completed general intake, not a member.

        $this->actingAs($user)->get('/daftar')
            ->assertOk()
            ->assertSee('Khusus')
            ->assertDontSee('Gratis');
    }

    public function test_join_card_invites_a_graduate(): void
    {
        [$user, $person] = $this->participant();
        $this->completeGeneralIntake($person);

        $this->actingAs($user)->get('/daftar')
            ->assertOk()
            ->assertSee('Gabung Komunitas');
    }
```

Before removing the "Gratis" badge, grep the test suite for any assertion on the literal "Gratis" (e.g. an existing `assertSee('Gratis')`) and update it — a guest with no person will hit the `! $canJoinCommunity` branch, so the guest catalog now shows "Khusus", not "Gratis".

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=join_card tests/Feature/PublicCatalogTest.php`
Expected: FAIL ("Khusus" not rendered; "Gratis" still present).

- [ ] **Step 3: Controller passes the state**

In `ProgramPageController::chooser`, change the person resolution to eager-load membership and compute the flag:

```php
        $person = Auth::user()?->load('person.communityMembership')->person;
        // ...existing $programs and $affiliate build...
        $canJoinCommunity = $this->eligibility->canJoinCommunity($person);

        return view('funnel.chooser', compact('programs', 'affiliate', 'canJoinCommunity'));
```

(If `Auth::user()?->load(...)` reads awkwardly against the file's style, resolve `$person = Auth::user()?->person` then `$person?->loadMissing('communityMembership')` — follow the file's existing idiom.)

- [ ] **Step 4: Blade — the join card**

In `resources/views/funnel/chooser.blade.php`, replace the `<a href="{{ url('/komunitas') }}"> ... </a>` join-card block (~lines 55-70) with a version that reads `$canJoinCommunity`:

```blade
                @if ($canJoinCommunity)
                    <a href="{{ url('/komunitas') }}"
                       class="block rounded-3xl border border-teal-900/10 bg-teal-900 p-6 shadow-sm transition hover:bg-teal-800 hover:shadow-md sm:p-8">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <span class="inline-block rounded-full bg-white/10 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-300">Khusus lulusan</span>
                                <h2 class="mt-3 text-xl font-bold text-white">Gabung Komunitas Affiliator</h2>
                                <p class="mt-1.5 text-sm leading-relaxed text-white/70">
                                    Kamu sudah menyelesaikan program. Lanjutkan seriusmu di komunitas: kelas berjenjang, pendampingan, dan jalur karier affiliator.
                                </p>
                            </div>
                            <svg class="h-5 w-5 shrink-0 text-white" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </a>
                @else
                    <div class="block rounded-3xl border border-teal-900/10 bg-teal-900/90 p-6 shadow-sm sm:p-8">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-300">
                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="9" width="10" height="7" rx="1.5"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9" stroke-linecap="round"/></svg>
                                    Khusus lulusan
                                </span>
                                <h2 class="mt-3 text-xl font-bold text-white">Komunitas Affiliator</h2>
                                <p class="mt-1.5 text-sm leading-relaxed text-white/70">
                                    Terbuka setelah kamu menyelesaikan semua kelas di satu angkatan program. Di sinilah kelas berjenjang dan jalur karier affiliator berlanjut.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
```

The leveled ladder below (`@if ($affiliate->isNotEmpty())`) is unchanged: its cards already render `$entry['locked']` from `lockReason`, which now means "not a member". Review the section blurb copy "Terbuka bertahap setelah kamu menyelesaikan program" → "Terbuka setelah kamu gabung komunitas" so it matches the two-step model (the ladder opens on membership, not on general completion).

- [ ] **Step 5: Run tests to verify they pass + full file**

Run: `php artisan test --compact tests/Feature/PublicCatalogTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ProgramPageController.php resources/views/funnel/chooser.blade.php tests/Feature/PublicCatalogTest.php
git commit -m "feat: the /daftar join card reads Khusus and locks until you finish a program"
```

---

### Task 7: Member-area affiliate section + join signpost

**Files:**
- Modify: `app/Http/Controllers/MemberAreaController.php` (affiliate section already uses `lockReason`; add a `canJoinCommunity` signpost when not a member)
- Modify: `resources/views/member/akun.blade.php` (the "PROGRAM UNTUKMU" section)
- Test: `tests/Feature/MemberAreaTest.php` (extend)

**Interfaces:**
- Consumes: membership-based `lockReason` (Task 3), `canJoinCommunity` (Task 2).
- Produces: the member's affiliate section reflects membership; a non-member graduate sees a "gabung komunitas" invite, a non-graduate sees the locked teaser.

- [ ] **Step 1: Write the failing tests**

```php
    public function test_member_area_shows_join_invite_to_a_graduate_non_member(): void
    {
        [$user, $person] = $this->member();
        $this->completeGeneralIntake($person); // graduate, not yet a member

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Gabung komunitas'); // invite copy
    }

    public function test_member_area_unlocks_level_1_for_a_member(): void
    {
        [$user, $person] = $this->member();
        \App\Models\CommunityMembership::create(['people_id' => $person->id]);
        $level1 = Program::factory()->affiliate(1)->active()->create(['name' => 'Kelas Komunitas L1']);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kelas Komunitas L1')
            ->assertDontSee('Terkunci');
    }
```

Use the file's existing `member()` helper and add `completeGeneralIntake(Person)` if not already present (mirror `attendProgram`). Confirm the exact locked-label text the akun affiliate section renders (grep `akun.blade.php` for the affiliate/`reason` block) and assert against the real string.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="join_invite|unlocks_level_1" tests/Feature/MemberAreaTest.php`
Expected: FAIL.

- [ ] **Step 3: Controller**

In `MemberAreaController::index`, alongside the existing `$affiliate` mapping (which already sets `locked`/`reason` from `$eligibility`), add:

```php
        $canJoinCommunity = $eligibility->canJoinCommunity($person);
```

Pass `'canJoinCommunity' => $canJoinCommunity` to the view. Ensure `$person` is loaded with `communityMembership` (the index already eager-loads `communityMembership` — verify, it does per line ~37).

- [ ] **Step 4: Blade signpost**

In `resources/views/member/akun.blade.php`, in the "PROGRAM UNTUKMU" affiliate section, add — above or within the affiliate list — a join signpost driven by state (non-member graduate → invite to /komunitas; non-graduate → the existing locked teaser already renders per-card). Insert where the section renders (grep for `affiliate` / "PROGRAM UNTUKMU"):

The akun view already receives `$membership` (`'membership' => $person?->communityMembership`), so reuse it instead of calling a method in Blade (house rule: view logic in the controller):

```blade
@if ($canJoinCommunity && ! $membership)
    <a href="{{ url('/komunitas') }}" class="mt-4 flex items-center justify-between gap-3 rounded-2xl border border-teal-600/30 bg-teal-50 px-4 py-3.5 transition hover:border-teal-600/50">
        <span class="text-sm font-semibold text-teal-800">Gabung komunitas untuk membuka kelas berjenjang.</span>
        <svg class="h-4 w-4 shrink-0 text-teal-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 6 4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
@endif
```

Keep the existing per-card locked/unlocked rendering (it now reflects membership via `lockReason`).

- [ ] **Step 5: Run tests + full MemberAreaTest**

Run: `php artisan test --compact tests/Feature/MemberAreaTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/MemberAreaController.php resources/views/member/akun.blade.php tests/Feature/MemberAreaTest.php
git commit -m "feat: member area invites graduates to join and gates classes on membership"
```

---

### Task 8: Full-suite pass + visual verification + copy sweep

**Files:** none new — verification only (main session, not a subagent).

- [ ] **Step 1: Full suite**

Run: `php artisan test --compact`
Expected: PASS (all).

- [ ] **Step 2: Copy sweep + lock-reason wording**

Grep the touched surfaces (`chooser.blade.php`, `community.blade.php`, `akun.blade.php`) for: em-dashes in UI copy (forbidden), "Gratis" leftovers on the community entry, and the old "mulai dari komunitas / titik awal" framing. Fix inline; the community is now the graduates' NEXT step, not a starting point. Also verify the lock-modal copy for the NEW reason `needs_membership`: the akun/program-page locked cards pass `data-lock-reason="{{ ... }}"` to `initLockModal()` in `resources/js/app.js` — check whether that JS maps reasons to messages; if it does, add a `needs_membership` case ("Gabung komunitas dulu untuk membuka kelas ini."), otherwise the program's `locked_message` field covers it and no code change is needed. Confirm which by reading `initLockModal`.

- [ ] **Step 3: Playwright walkthrough (three personas)**

- Non-graduate (fresh participant): `/daftar` join card reads "Khusus lulusan" and is locked; `/komunitas` shows the locked explainer; `/akun` affiliate cards show locked.
- Graduate non-member (attended all sessions of one general cohort): `/daftar` join card invites; `/komunitas` shows the join form; `/akun` shows the "Gabung komunitas" signpost.
- Member: `/akun` Level 1 card unlocked; Level 2 locked with "needs_previous_level" copy.
- Screenshots of each.

- [ ] **Step 4: Ledger + wrap-up**

Append the summary to `.superpowers/sdd/progress.md`; note the grandfathering decision and that `fix/enrollment-intake-clarity` (the enrolled-intake KELAS DIBUKA fix) is a separate branch to merge.
