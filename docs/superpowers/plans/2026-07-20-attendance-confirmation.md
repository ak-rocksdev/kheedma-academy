# Attendance Confirmation (Konfirmasi Kehadiran) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Members declare per upcoming class whether they can attend (attending / cannot attend + optional note), changeable until the class starts; mentors read a per-class recap.

**Architecture:** One mutable `session_confirmations` row per (class, enrollment) — intent, never attendance. A member web endpoint upserts the row with ownership + freeze-at-start guards; the member Kelas timeline gains a prompt/chip block per class; the admin cohort detail API adds per-session recap counts + name list rendered in `CohortDetail.vue`.

**Tech Stack:** Laravel 13 / PHP 8.4, PHPUnit 12, Blade + Tailwind v4 (member), Vue 3 + shadcn-vue (admin SPA).

**Spec:** `docs/superpowers/specs/2026-07-18-batch-cohorts-class-revival-rsvp-design.md` (Phase B section).

## Global Constraints

- Code 100% English (identifiers, tables, routes, comments); UI copy 100% Indonesian, "kamu" register, NO em-dashes.
- Confirmation is intent: it must NEVER write to `attendances`.
- One row per (class, student), mutable; `UNIQUE (cohort_session_id, enrollment_id)`; no history.
- Editable until `scheduled_at` passes; a class with `scheduled_at = null` stays editable.
- Authorization mirrors submissions: only one's own **active** enrollment in the class's cohort (404 otherwise).
- Admin recap is read-only, visible with `cohorts.view`.
- Derived states per (class, student): `belum konfirmasi` (no row) / `hadir` (attending) / `berhalangan` (cannot_attend).
- Out of scope: notifications, history/audit, editable offline-benefits copy, rescheduling workflow.
- After PHP changes run `vendor/bin/pint --dirty --format agent`. Never run `migrate:fresh`/`migrate:refresh`/`db:wipe` on the local MySQL.
- Never `git add -A`; stage files explicitly (untracked personal `test.md` must stay untracked).

---

### Task 1: `session_confirmations` table, model, relations, factory

**Files:**
- Create: `database/migrations/2026_07_20_000001_create_session_confirmations_table.php` (via `php artisan make:migration create_session_confirmations_table --no-interaction`, then edit)
- Create: `app/Models/SessionConfirmation.php`
- Create: `database/factories/SessionConfirmationFactory.php`
- Modify: `app/Models/CohortSession.php` (add `confirmations()` HasMany)
- Modify: `app/Models/Enrollment.php` (add `sessionConfirmations()` HasMany)
- Test: `tests/Feature/SessionConfirmationModelTest.php`

**Interfaces:**
- Produces: `SessionConfirmation` model — fillable `cohort_session_id`, `enrollment_id`, `status`, `note`; relations `session(): BelongsTo` (FK `cohort_session_id`), `enrollment(): BelongsTo`. `CohortSession::confirmations(): HasMany`, `Enrollment::sessionConfirmations(): HasMany`. Status strings are exactly `'attending'` and `'cannot_attend'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\SessionConfirmation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionConfirmationModelTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: CohortSession, 1: Enrollment} */
    private function sessionAndEnrollment(): array
    {
        $cohort = Cohort::factory()->create();
        $session = CohortSession::factory()->for($cohort)->create();
        $person = Person::create([
            'name' => 'Peserta Uji',
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        return [$session, $enrollment];
    }

    public function test_confirmation_belongs_to_session_and_enrollment(): void
    {
        [$session, $enrollment] = $this->sessionAndEnrollment();
        $confirmation = SessionConfirmation::factory()->create([
            'cohort_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->assertTrue($confirmation->session->is($session));
        $this->assertTrue($confirmation->enrollment->is($enrollment));
        $this->assertTrue($session->confirmations()->first()->is($confirmation));
        $this->assertTrue($enrollment->sessionConfirmations()->first()->is($confirmation));
    }

    public function test_one_row_per_class_and_enrollment(): void
    {
        [$session, $enrollment] = $this->sessionAndEnrollment();
        SessionConfirmation::factory()->create([
            'cohort_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->expectException(QueryException::class);
        SessionConfirmation::factory()->create([
            'cohort_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/SessionConfirmationModelTest.php`
Expected: FAIL (class `SessionConfirmation` not found).

- [ ] **Step 3: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['cohort_session_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_confirmations');
    }
};
```

- [ ] **Step 4: Model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's declared intent for one class: attending or cannot_attend.
 * One mutable row per (class, enrollment) — intent, never attendance;
 * the mentor still records actual presence in `attendances`.
 */
class SessionConfirmation extends Model
{
    use HasFactory;

    protected $fillable = [
        'cohort_session_id',
        'enrollment_id',
        'status',
        'note',
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

- [ ] **Step 5: Relations on existing models**

In `app/Models/CohortSession.php`, next to `attendances()`:

```php
    public function confirmations(): HasMany
    {
        return $this->hasMany(SessionConfirmation::class);
    }
```

In `app/Models/Enrollment.php`, next to `attendances()`:

```php
    public function sessionConfirmations(): HasMany
    {
        return $this->hasMany(SessionConfirmation::class);
    }
```

- [ ] **Step 6: Factory**

House convention (see `AssignmentSubmissionFactory`): factories do NOT invent FK defaults — tests always pass `cohort_session_id`/`enrollment_id` explicitly.

```php
<?php

namespace Database\Factories;

use App\Models\SessionConfirmation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionConfirmation>
 */
class SessionConfirmationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status' => 'attending',
            'note' => null,
        ];
    }

    public function cannotAttend(?string $note = null): static
    {
        return $this->state(fn () => ['status' => 'cannot_attend', 'note' => $note]);
    }
}
```

- [ ] **Step 7: Migrate, run test to verify it passes**

Run: `php artisan migrate --no-interaction` then `php artisan test --compact tests/Feature/SessionConfirmationModelTest.php`
Expected: PASS (2 tests).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/*session_confirmations* app/Models/SessionConfirmation.php database/factories/SessionConfirmationFactory.php app/Models/CohortSession.php app/Models/Enrollment.php tests/Feature/SessionConfirmationModelTest.php
git commit -m "feat: session_confirmations rows carry per-class attendance intent"
```

---

### Task 2: member set/change endpoint

**Files:**
- Create: `app/Http/Controllers/MemberSessionConfirmationController.php`
- Modify: `routes/web.php` (member block, next to `member.assignment.submit`)
- Test: `tests/Feature/MemberSessionConfirmationTest.php`

**Interfaces:**
- Consumes: `SessionConfirmation` (Task 1), `Enrollment::isActive()`, `CohortSession->cohort_id`, `CohortSession->scheduled_at` (datetime cast).
- Produces: route `POST /akun/kelas/{session}/konfirmasi` named `member.session.confirm` (middleware `auth`, `throttle:10,1`), accepting `status` (`attending`|`cannot_attend`) and `note` (nullable, max 500). Redirects back with session flash `konfirmasi_tersimpan` = session id. Route-model binding parameter name is `session` typed `CohortSession`.

- [ ] **Step 1: Write the failing tests**

Follow `MemberSubmissionTest`'s structure (member() helper with participant role + Person). Tests:

```php
<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\SessionConfirmation;
use App\Models\StatusEvent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSessionConfirmationTest extends TestCase
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

    private function upcomingSession(): CohortSession
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);

        return CohortSession::factory()->for($cohort)->create(['scheduled_at' => now()->addDays(2)]);
    }

    public function test_member_sets_then_changes_confirmation_in_one_row(): void
    {
        [$user, $person] = $this->member();
        $session = $this->upcomingSession();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $session->cohort_id]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertRedirect()
            ->assertSessionHas('konfirmasi_tersimpan', $session->id);

        $this->assertSame(1, SessionConfirmation::count());
        $this->assertSame('attending', SessionConfirmation::sole()->status);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), [
                'status' => 'cannot_attend',
                'note' => 'Ada acara keluarga.',
            ])
            ->assertRedirect();

        $this->assertSame(1, SessionConfirmation::count());
        $row = SessionConfirmation::sole();
        $this->assertSame('cannot_attend', $row->status);
        $this->assertSame('Ada acara keluarga.', $row->note);
        $this->assertSame($enrollment->id, $row->enrollment_id);
    }

    public function test_switching_back_to_attending_clears_the_note(): void
    {
        [$user, $person] = $this->member();
        $session = $this->upcomingSession();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $session->cohort_id]);
        SessionConfirmation::factory()->cannotAttend('Bentrok kerja.')->create([
            'cohort_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertRedirect();

        $row = SessionConfirmation::sole();
        $this->assertSame('attending', $row->status);
        $this->assertNull($row->note);
    }

    public function test_confirmation_freezes_once_the_class_started(): void
    {
        [$user, $person] = $this->member();
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->for($cohort)->create(['scheduled_at' => now()->subHour()]);
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, SessionConfirmation::count());
    }

    public function test_unscheduled_class_stays_confirmable(): void
    {
        [$user, $person] = $this->member();
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->for($cohort)->create(['scheduled_at' => null]);
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertRedirect()
            ->assertSessionHas('konfirmasi_tersimpan', $session->id);

        $this->assertSame(1, SessionConfirmation::count());
    }

    public function test_member_without_enrollment_gets_404(): void
    {
        [$user] = $this->member();
        $session = $this->upcomingSession();

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertNotFound();
    }

    public function test_dropped_enrollment_cannot_confirm(): void
    {
        [$user, $person] = $this->member();
        $session = $this->upcomingSession();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $session->cohort_id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'dropped', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertNotFound();
    }

    public function test_status_must_be_a_known_value(): void
    {
        [$user, $person] = $this->member();
        $session = $this->upcomingSession();
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $session->cohort_id]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'maybe'])
            ->assertSessionHasErrors('status');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $session = $this->upcomingSession();

        $this->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertRedirect(route('member.login'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/MemberSessionConfirmationTest.php`
Expected: FAIL (route `member.session.confirm` not defined).

- [ ] **Step 3: Route**

In `routes/web.php`, inside the member block next to `member.assignment.submit` (keep the same middleware shape):

```php
Route::post('/akun/kelas/{session}/konfirmasi', [MemberSessionConfirmationController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('member.session.confirm');
```

Add the `use App\Http\Controllers\MemberSessionConfirmationController;` import. Match exactly how the neighboring member routes declare middleware (check whether they chain `->middleware('auth')` individually or sit in a group; follow the file's existing pattern).

- [ ] **Step 4: Controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\CohortSession;
use App\Models\SessionConfirmation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberSessionConfirmationController extends Controller
{
    /**
     * A member declares intent for one class: attending or cannot_attend
     * (+ optional note). One mutable row per (class, enrollment); editable
     * until the class starts, then attendance takes over as the fact.
     * Never writes `attendances`.
     */
    public function store(Request $request, CohortSession $session): RedirectResponse
    {
        $person = $request->user()->person;

        $enrollment = $person?->enrollments()
            ->where('cohort_id', $session->cohort_id)
            ->with('latestStatusEvent')
            ->first();

        abort_unless($enrollment !== null && $enrollment->isActive(), 404);

        // Freeze at start: a class without a schedule has nothing to freeze
        // against and stays editable.
        if ($session->scheduled_at !== null && $session->scheduled_at->isPast()) {
            throw ValidationException::withMessages([
                'status' => 'Kelas sudah dimulai. Konfirmasi ditutup, kehadiranmu dicatat mentor di kelas.',
            ]);
        }

        $data = $request->validate([
            'status' => ['required', 'in:attending,cannot_attend'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'status.required' => 'Pilih salah satu ya.',
            'status.in' => 'Pilihan tidak dikenal.',
            'note.max' => 'Catatan terlalu panjang (maksimal 500 karakter).',
        ]);

        SessionConfirmation::updateOrCreate(
            ['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id],
            [
                'status' => $data['status'],
                // The note belongs to "berhalangan"; switching back wipes it.
                'note' => $data['status'] === 'cannot_attend' ? ($data['note'] ?? null) : null,
            ],
        );

        return back()->with('konfirmasi_tersimpan', $session->id);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/MemberSessionConfirmationTest.php`
Expected: PASS (8 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/MemberSessionConfirmationController.php routes/web.php tests/Feature/MemberSessionConfirmationTest.php
git commit -m "feat: members confirm per-class attendance intent until the class starts"
```

---

### Task 3: member prompt UI on the class timeline

**Files:**
- Modify: `app/Http/Controllers/MemberAreaController.php` (eager-load + per-session confirmation state)
- Modify: `resources/views/member/akun.blade.php` (Kelas tab, inside the per-session card after the venue block, `resources/views/member/akun.blade.php:243` area)
- Test: extend `tests/Feature/MemberAreaTest.php`

**Interfaces:**
- Consumes: `Enrollment::sessionConfirmations` (Task 1), route `member.session.confirm` (Task 2), reusable `<x-modal>` component (`resources/views/components/modal.blade.php`, props `id`/`title`/`autoopen`), `initModals()` behavior via `data-modal-open`/`data-modal-close`.
- Produces: `$confirmationCards` view data keyed by session id: `['status' => 'attending'|'cannot_attend'|null, 'note' => ?string, 'editable' => bool]`.

- [ ] **Step 1: Write the failing tests** (append to `MemberAreaTest`; reuse its `member()` helper)

```php
    public function test_upcoming_class_shows_confirmation_prompt(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->create(['scheduled_at' => now()->addDays(3)]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Bisa hadir di kelas ini?')
            ->assertSee('Insya Allah hadir')
            ->assertSee('Berhalangan');
    }

    public function test_confirmed_class_shows_choice_as_changeable_chip(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $session = CohortSession::factory()->for($cohort)->create(['scheduled_at' => now()->addDays(3)]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);
        SessionConfirmation::factory()->cannotAttend('Ada acara keluarga.')->create([
            'cohort_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertSee('Kamu berhalangan hadir')
            ->assertSee('Ada acara keluarga.')
            ->assertSee('Ubah konfirmasi')
            ->assertDontSee('Bisa hadir di kelas ini?');
    }

    public function test_started_class_shows_no_confirmation_prompt(): void
    {
        [$user, $person] = $this->member();
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        CohortSession::factory()->for($cohort)->create(['scheduled_at' => now()->subHours(3)]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $this->actingAs($user)->get('/akun?bagian=kelas')
            ->assertOk()
            ->assertDontSee('Bisa hadir di kelas ini?')
            ->assertDontSee('Insya Allah hadir');
    }
```

Add `use App\Models\SessionConfirmation;` to the test imports.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=confirmation tests/Feature/MemberAreaTest.php`
Expected: FAIL (copy not rendered).

- [ ] **Step 3: Controller data**

In `MemberAreaController::index`, add `'sessionConfirmations'` to the enrollments eager-load list, then after the `$assignmentCards` loop build:

```php
        // Confirmation state per class, keyed by session id. Editable until
        // the class starts; unscheduled classes stay editable (spec Phase B).
        $confirmationCards = [];
        foreach ($enrolledClasses as $enrollment) {
            foreach ($enrollment->cohort->sessions as $session) {
                $row = $enrollment->sessionConfirmations->firstWhere('cohort_session_id', $session->id);
                $confirmationCards[$session->id] = [
                    'status' => $row?->status,
                    'note' => $row?->note,
                    'editable' => $session->scheduled_at === null || $session->scheduled_at->isFuture(),
                ];
            }
        }
```

Pass `'confirmationCards' => $confirmationCards` to the view.

- [ ] **Step 4: Blade prompt block**

In the per-session card in the Kelas tab, directly after the venue `@endif` (the online/offline block) and before the assignment signpost `@if (isset($assignmentCards[$session->id]))`, insert (only for non-ended cohorts — the block already sits inside the `@else` of `$cohort->status === 'ended'`):

```blade
@php($konfirmasi = $confirmationCards[$session->id] ?? null)
@if ($konfirmasi && $konfirmasi['editable'])
    <div class="mt-3 rounded-2xl border border-teal-900/10 bg-sand-50/60 px-4 py-3.5">
        @if (session('konfirmasi_tersimpan') === $session->id)
            <p class="mb-2 text-xs font-semibold text-teal-700">Konfirmasimu tersimpan. Syukron!</p>
        @endif
        @if ($konfirmasi['status'] === null)
            <p class="text-sm font-semibold text-teal-900">Bisa hadir di kelas ini?</p>
            <p class="mt-0.5 text-xs text-teal-800/60">Konfirmasimu membantu mentor menyiapkan kelas.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('member.session.confirm', $session) }}" data-submit-once>
                    @csrf
                    <input type="hidden" name="status" value="attending">
                    <button type="submit" class="rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-900">Insya Allah hadir</button>
                </form>
                <button type="button" data-modal-open="modal-berhalangan-{{ $session->id }}"
                        class="rounded-full border border-teal-900/15 px-4 py-2 text-sm font-semibold text-teal-800 transition hover:border-orange-400 hover:text-orange-600">Berhalangan</button>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-2">
                @if ($konfirmasi['status'] === 'attending')
                    <p class="inline-flex items-center gap-1.5 text-sm font-semibold text-teal-700">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10.5l4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Kamu konfirmasi hadir
                    </p>
                @else
                    <p class="text-sm font-semibold text-orange-700">Kamu berhalangan hadir</p>
                @endif
                <div class="flex flex-wrap gap-2">
                    @if ($konfirmasi['status'] === 'cannot_attend')
                        <form method="POST" action="{{ route('member.session.confirm', $session) }}" data-submit-once>
                            @csrf
                            <input type="hidden" name="status" value="attending">
                            <button type="submit" class="rounded-full border border-teal-900/15 px-3.5 py-1.5 text-xs font-semibold text-teal-700 transition hover:border-teal-600/40">Ubah konfirmasi: jadi hadir</button>
                        </form>
                    @else
                        <button type="button" data-modal-open="modal-berhalangan-{{ $session->id }}"
                                class="rounded-full border border-teal-900/15 px-3.5 py-1.5 text-xs font-semibold text-teal-700 transition hover:border-orange-400 hover:text-orange-600">Ubah konfirmasi</button>
                    @endif
                </div>
            </div>
            @if ($konfirmasi['status'] === 'cannot_attend' && $konfirmasi['note'])
                <p class="mt-2 border-l-2 border-orange-300 pl-3 text-xs italic text-teal-800/70">{{ $konfirmasi['note'] }}</p>
            @endif
        @endif
    </div>

    @php($confirmFailedHere = $errors->hasAny(['status', 'note']) && (int) old('_session_id') === $session->id)
    <x-modal id="modal-berhalangan-{{ $session->id }}" title="Berhalangan hadir?" :autoopen="$confirmFailedHere">
        <p class="rounded-xl bg-sand-50 px-4 py-3 text-sm text-teal-800/80">
            Kabari kendalamu, biar mentor carikan solusi. Kamu masih bisa mengubah konfirmasi ini sampai kelas dimulai.
        </p>
        <form method="POST" action="{{ route('member.session.confirm', $session) }}" data-submit-once class="mt-3 space-y-2.5">
            @csrf
            <input type="hidden" name="status" value="cannot_attend">
            <input type="hidden" name="_session_id" value="{{ $session->id }}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-teal-800/60">Kendalamu (opsional)</label>
            <textarea name="note" rows="3" placeholder="Contoh: bentrok jam kerja, ada acara keluarga."
                      class="w-full rounded-lg border border-teal-900/15 bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">{{ $confirmFailedHere ? old('note') : $konfirmasi['note'] }}</textarea>
            @if ($confirmFailedHere) <p class="text-xs text-red-600">{{ $errors->first('status') ?: $errors->first('note') }}</p> @endif
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-modal-close class="rounded-full border border-teal-900/15 px-5 py-2 text-sm font-semibold text-teal-800 transition hover:border-teal-600/40">Batal</button>
                <button type="submit" class="rounded-full bg-orange-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-orange-600">Kirim konfirmasi</button>
            </div>
        </form>
    </x-modal>
@endif
```

Note: `session('konfirmasi_tersimpan')` and `$session->id` are both ints; the strict `===` comparison is intentional and mirrors the tugas flash pattern.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/MemberAreaTest.php`
Expected: PASS (all, including the 3 new).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/MemberAreaController.php resources/views/member/akun.blade.php tests/Feature/MemberAreaTest.php
git commit -m "feat: class timeline asks 'Bisa hadir di kelas ini?' until the class starts"
```

---

### Task 4: admin recap per class

**Files:**
- Modify: `app/Http/Controllers/Api/Admin/CohortController.php:35` (`show()` — load confirmations, extend session rows)
- Modify: `resources/js/admin/views/CohortDetail.vue` (recap line + expandable names in the class block / selected-class area)
- Test: extend `tests/Feature/CohortSessionTest.php`

**Interfaces:**
- Consumes: `CohortSession::confirmations` (Task 1) with `enrollment.person:id,name`.
- Produces: each row in the `sessions` array of `GET /api/admin/cohorts/{cohort}` gains:
  `'confirmations' => ['attending' => int, 'cannot_attend' => int, 'entries' => [['name' => string, 'status' => string, 'note' => ?string], ...]]`.
  "Belum konfirmasi" is derived client-side: active roster count minus responders.

- [ ] **Step 1: Write the failing test** (append to `CohortSessionTest`; follow its existing admin-auth helper pattern — read the file first and reuse exactly how other tests build an admin user and cohort)

```php
    public function test_cohort_detail_carries_confirmation_recap_per_session(): void
    {
        $admin = $this->admin(); // reuse the file's existing helper/pattern
        $cohort = Cohort::factory()->create();
        $session = CohortSession::factory()->for($cohort)->create();
        $personA = Person::create(['name' => 'Aisyah Uji', 'phone' => '+62811111111']);
        $personB = Person::create(['name' => 'Budi Uji', 'phone' => '+62822222222']);
        $enrollA = Enrollment::create(['people_id' => $personA->id, 'cohort_id' => $cohort->id]);
        $enrollB = Enrollment::create(['people_id' => $personB->id, 'cohort_id' => $cohort->id]);
        SessionConfirmation::factory()->create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollA->id]);
        SessionConfirmation::factory()->cannotAttend('Bentrok kerja.')->create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollB->id]);

        $res = $this->actingAs($admin)->getJson("/api/admin/cohorts/{$cohort->id}")->assertOk();

        $row = collect($res->json('sessions'))->firstWhere('id', $session->id);
        $this->assertSame(1, $row['confirmations']['attending']);
        $this->assertSame(1, $row['confirmations']['cannot_attend']);
        $names = collect($row['confirmations']['entries'])->pluck('name');
        $this->assertTrue($names->contains('Aisyah Uji'));
        $this->assertSame('Bentrok kerja.', collect($row['confirmations']['entries'])->firstWhere('name', 'Budi Uji')['note']);
    }
```

Adjust imports/helpers to match the file's existing conventions (read them before writing).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_cohort_detail_carries_confirmation_recap_per_session`
Expected: FAIL (undefined index `confirmations`).

- [ ] **Step 3: API change**

In `CohortController::show()`, extend the sessions query eager-load:

```php
$sessions = $cohort->sessions()
    ->withCount('attendances')
    ->with(['assignment.updater:id,name', 'confirmations.enrollment.person:id,name'])
    ->get();
```

And in the sessions map, add:

```php
'confirmations' => [
    'attending' => $s->confirmations->where('status', 'attending')->count(),
    'cannot_attend' => $s->confirmations->where('status', 'cannot_attend')->count(),
    'entries' => $s->confirmations->map(fn ($c) => [
        'name' => $c->enrollment->person?->name ?? '-',
        'status' => $c->status,
        'note' => $c->note,
    ])->values(),
],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/CohortSessionTest.php`
Expected: PASS.

- [ ] **Step 5: Vue recap UI**

In `CohortDetail.vue`, inside the selected-class area (near the attendance summary "Hadir n/m"), add a confirmation recap for the selected class. Data comes from `selectedSession.confirmations`. Computed:

```js
// Konfirmasi kehadiran recap: intent signal for the mentor, read-only here.
const confirmationRecap = computed(() => {
    const c = selectedSession.value?.confirmations;
    if (!c) return null;
    const responded = c.attending + c.cannot_attend;
    return { ...c, belum: Math.max(0, activeRosterCount.value - responded) };
});
```

Template (below the DAFTAR HADIR header area, styled like the existing quiet meta rows):

```html
<div v-if="confirmationRecap" class="border-b border-border px-4 py-2.5 text-xs sm:px-6">
    <details class="group">
        <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground [&::-webkit-details-marker]:hidden">
            <span class="font-semibold uppercase tracking-wide">Konfirmasi kehadiran</span>
            <span class="font-semibold text-teal-700">{{ confirmationRecap.attending }} hadir</span>
            <span class="font-semibold text-orange-600">{{ confirmationRecap.cannot_attend }} berhalangan</span>
            <span>{{ confirmationRecap.belum }} belum konfirmasi</span>
            <span class="ml-auto text-[0.7rem] underline-offset-2 group-open:hidden">Lihat nama</span>
            <span class="ml-auto hidden text-[0.7rem] group-open:inline">Tutup</span>
        </summary>
        <ul class="mt-2 space-y-1">
            <li v-for="entry in selectedSession.confirmations.entries" :key="entry.name + entry.status" class="flex flex-wrap items-baseline gap-x-2">
                <span class="font-medium text-foreground">{{ entry.name }}</span>
                <span :class="entry.status === 'attending' ? 'text-teal-700' : 'text-orange-600'">
                    {{ entry.status === 'attending' ? 'hadir' : 'berhalangan' }}
                </span>
                <span v-if="entry.note" class="text-muted-foreground">· {{ entry.note }}</span>
            </li>
            <li v-if="!selectedSession.confirmations.entries.length" class="text-muted-foreground">Belum ada yang konfirmasi.</li>
        </ul>
    </details>
</div>
```

Place it where the roster card renders for the selected class; match surrounding markup/indentation (verify with sed before editing — do not guess indentation).

- [ ] **Step 6: Build + verify**

Run: `npm run build`
Expected: build succeeds. Visual check happens in the final review pass (Playwright at `/admin/cohorts/1`).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/CohortController.php resources/js/admin/views/CohortDetail.vue tests/Feature/CohortSessionTest.php
git commit -m "feat: class blocks show the confirmation recap mentors act on"
```

---

### Task 5: full-suite pass + visual verification

**Files:** none new — verification only.

- [ ] **Step 1: Full suite**

Run: `php artisan test --compact`
Expected: PASS (all).

- [ ] **Step 2: Playwright walkthrough (main session, not a subagent)**

- Member (rina@gmail.com / 112233) at `/akun?bagian=kelas`: prompt visible on the upcoming class, confirm "Berhalangan" with a note via the modal, chip + note render, switch to "jadi hadir".
- Admin (admin@kheedma.id / 112233) at `/admin/cohorts/1`: recap line shows the change, names expand.
- Screenshots of both.

- [ ] **Step 3: Ledger + wrap-up**

Append the Phase B summary to `.superpowers/sdd/progress.md`.
