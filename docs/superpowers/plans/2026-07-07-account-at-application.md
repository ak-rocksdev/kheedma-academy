# Account at Application Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Applying to a program also creates the participant account (auto-login), logged-in applicants get a pre-filled form + honest status notice + self-service edits, and `/akun` shows application statuses. Concept decision #9 (spec §2).

**Architecture:** A shared `ProvisionParticipantAccount` action provisions Person+User for BOTH doors (community join refactors onto it). `ApplicationController` branches: guest (password field → provision → login) vs authenticated participant (pre-filled, no password, honest pending notice) vs staff (redirect `/admin`). The pending-dedup backstop stays. `/akun` lists the person's applications with status badges.

**Tech Stack:** PHP 8.4, Laravel 13, Blade public views, PHPUnit 12. No new dependencies.

## Global Constraints

- PHP: braces always; explicit return types + param hints. `vendor/bin/pint --dirty --format agent` before each backend commit.
- Tests PHPUnit; full suite green at every task boundary except where a task explicitly states otherwise.
- Identity rule unchanged: Person by normalized phone; `people.user_id` links the account; participant role; `is_active` true.
- Privacy rules: a guest whose PHONE already has an account gets a validation error on `phone`: "Nomor ini sudah punya akun. Masuk untuk melanjutkan." (account-existence disclosure OK; never echo stored name/email pre-auth). Email collisions reuse the Phase-2 message: "Email ini sudah terpakai. Gunakan email lain atau masuk jika sudah punya akun."
- The silent pending-dedup backstop (one pending application per person per program) remains for ALL submit paths; the HONEST notice is only for authenticated users (their own data).
- Public copy Indonesian, no em-dashes, no internal terms. Password field: `autocomplete="new-password"`, min 8, single field (no confirmation — reset flow exists).
- Community *membership* is NOT created by applying (account ≠ membership).
- Staff (admin/mentor) visiting the apply form is redirected to `/admin` (same rule as `/akun`).

---

## File Structure

**Create:** `app/Actions/ProvisionParticipantAccount.php`; test `tests/Feature/AccountAtApplicationTest.php`
**Modify:** `app/Http/Controllers/CommunityController.php` (use the action), `app/Http/Controllers/ApplicationController.php` (branching flows), `app/Http/Requests/StoreApplicationRequest.php` (conditional rules), `resources/views/funnel/apply.blade.php` (password block, prefill, notice, login hint), `resources/views/terima-kasih.blade.php` (CTA to /akun), `app/Http/Controllers/MemberAreaController.php` + `resources/views/member/akun.blade.php` (application statuses), `tests/Feature/PublicApplyTest.php` (rework guest-double-submit tests to the new reality).

---

### Task 1: ProvisionParticipantAccount action (community join refactors onto it)

**Files:**
- Create: `app/Actions/ProvisionParticipantAccount.php`
- Modify: `app/Http/Controllers/CommunityController.php`
- Test: existing `tests/Feature/CommunityJoinTest.php` must stay green unchanged (behavioral refactor).

**Interfaces:**
- Produces: `ProvisionParticipantAccount::provision(array $identity): array{0: Person, 1: User}` where `$identity = ['phone' => normalized, 'name' => ..., 'email' => ..., 'password' => plain]`. Finds-or-news the Person by phone, throws `ValidationException` on `phone` ("Nomor ini sudah punya akun. Silakan masuk.") when the person already carries `user_id`, then inside `DB::transaction`: saves person (name/email refreshed), creates the participant User (`is_active` true, role `participant`), links `people.user_id`. Does NOT log in and does NOT create membership — callers do their own extras.

- [ ] **Step 1: Create the action**

`app/Actions/ProvisionParticipantAccount.php`:

```php
<?php

namespace App\Actions;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProvisionParticipantAccount
{
    /**
     * Find-or-create the Person by phone (the identity anchor) and give them a
     * participant login. Shared by the community door and the program
     * application form. Callers handle login/session and any extras
     * (membership row, application row).
     *
     * @param  array{phone: string, name: string, email: string, password: string}  $identity
     * @return array{0: Person, 1: User}
     */
    public function provision(array $identity): array
    {
        $person = Person::firstOrNew(['phone' => $identity['phone']]);

        // The phone anchor already carries a login: this human has an account.
        if ($person->exists && $person->user_id !== null) {
            throw ValidationException::withMessages([
                'phone' => 'Nomor ini sudah punya akun. Silakan masuk.',
            ]);
        }

        $user = DB::transaction(function () use ($person, $identity): User {
            $person->fill([
                'name' => $identity['name'],
                'email' => $identity['email'],
            ])->save();

            $user = User::create([
                'name' => $identity['name'],
                'email' => $identity['email'],
                'password' => Hash::make($identity['password']),
                'is_active' => true,
            ]);
            $user->assignRole('participant');

            $person->user_id = $user->id;
            $person->save();

            return $user;
        });

        return [$person->fresh(), $user];
    }
}
```

- [ ] **Step 2: Refactor CommunityController::join onto it**

Replace the person-guard + transaction block in `CommunityController::join` with:

```php
        $data = $request->validated();

        [$person, $user] = app(\App\Actions\ProvisionParticipantAccount::class)->provision([
            'phone' => $data['phone'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $person->communityMembership()->firstOrCreate(
            [],
            ['referral_source' => $data['referral_source']]
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('member.area')->with('joined', true);
```

(Remove now-unused imports: `DB`, `Hash`, `User`, `ValidationException` if no longer referenced; keep `Person` only if still used. Add the action import or use `app()` as shown — prefer constructor injection per house architecture rules: inject `public function join(CommunityJoinRequest $request, ProvisionParticipantAccount $provisioner)` and call `$provisioner->provision(...)`.)

NOTE: the membership `firstOrCreate([])` must run on the FRESH person returned by the action (it holds the id).

- [ ] **Step 3: Run the community suite + full suite**

Run: `php artisan test --compact --filter=CommunityJoinTest` (expect 7/7 — behavior identical) then `php artisan test --compact` (all green).

- [ ] **Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/ProvisionParticipantAccount.php app/Http/Controllers/CommunityController.php
git commit -m "refactor: shared participant account provisioning action"
```

---

### Task 2: Apply flow — guest provisions an account, participants get prefilled honesty

**Files:**
- Modify: `app/Http/Requests/StoreApplicationRequest.php`, `app/Http/Controllers/ApplicationController.php`, `resources/views/funnel/apply.blade.php`, `resources/views/terima-kasih.blade.php`
- Test: create `tests/Feature/AccountAtApplicationTest.php`; rework `tests/Feature/PublicApplyTest.php`

**Interfaces:**
- Consumes: `ProvisionParticipantAccount` (Task 1), pending-dedup backstop (existing), `member.area`/`member.login` routes.
- Produces: guest submit = Person+User+Application+auto-login → thank-you (with /akun CTA); guest with account-carrying phone = validation error on `phone`; authenticated participant = prefilled form (no password), self-updates person+user identity, honest notice when a pending application for THIS program exists; staff = redirect `/admin`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AccountAtApplicationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountAtApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        DB::table('indonesia_provinces')->insert([
            'code' => '32', 'name' => 'JAWA BARAT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('indonesia_cities')->insert([
            'code' => '3273', 'province_code' => '32', 'name' => 'KOTA BANDUNG', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function openProgram(): Program
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        return $program;
    }

    /** @return array<string, string> */
    private function guestPayload(): array
    {
        return [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.test',
            'password' => 'rahasia-kuat',
            'province_code' => '32',
            'city_code' => '3273',
            'referral_source' => 'instagram',
        ];
    }

    public function test_guest_application_creates_account_and_logs_in(): void
    {
        $program = $this->openProgram();

        $this->post("/program/{$program->slug}/daftar", $this->guestPayload())
            ->assertRedirect(route('daftar.thankyou'));

        $person = Person::sole();
        $this->assertNotNull($person->user_id);
        $this->assertTrue($person->user->hasRole('participant'));
        $this->assertSame(1, Application::count());
        $this->assertTrue(Auth::check());
        $this->assertSame(0, \App\Models\CommunityMembership::count());
    }

    public function test_guest_password_is_required(): void
    {
        $program = $this->openProgram();

        $this->from("/program/{$program->slug}/daftar")
            ->post("/program/{$program->slug}/daftar", [...$this->guestPayload(), 'password' => ''])
            ->assertSessionHasErrors('password');
    }

    public function test_guest_with_account_carrying_phone_is_told_to_login(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());
        Auth::logout();

        $this->from("/program/{$program->slug}/daftar")
            ->post("/program/{$program->slug}/daftar", [...$this->guestPayload(), 'email' => 'lain@example.test'])
            ->assertSessionHasErrors('phone');

        $this->assertSame(1, User::role('participant')->count());
        $this->assertSame(1, Application::count());
    }

    public function test_authenticated_participant_applies_without_password_and_updates_identity(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());
        $user = Auth::user();

        $second = $this->openProgram();

        $this->actingAs($user)
            ->post("/program/{$second->slug}/daftar", [
                'name' => 'Budi Santoso Baru',
                'phone' => '081234567890',
                'email' => 'budi@example.test',
                'province_code' => '32',
                'city_code' => '3273',
                'referral_source' => 'teman',
            ])
            ->assertRedirect(route('daftar.thankyou'));

        $this->assertSame(2, Application::count());
        $this->assertSame('Budi Santoso Baru', Person::sole()->name);
        $this->assertSame('Budi Santoso Baru', $user->fresh()->name);
    }

    public function test_authenticated_participant_sees_pending_notice_instead_of_form(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());

        $this->get("/program/{$program->slug}/daftar")
            ->assertOk()
            ->assertSee('sudah mendaftar')
            ->assertDontSee('Kirim Pendaftaran');
    }

    public function test_authenticated_resubmit_to_same_program_stays_deduplicated(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());

        $this->post("/program/{$program->slug}/daftar", [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.test',
            'province_code' => '32',
            'city_code' => '3273',
            'referral_source' => 'instagram',
        ])->assertRedirect(route('daftar.thankyou'));

        $this->assertSame(1, Application::count());
    }

    public function test_staff_is_redirected_to_admin(): void
    {
        $program = $this->openProgram();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get("/program/{$program->slug}/daftar")->assertRedirect('/admin');
    }

    public function test_prefilled_form_shows_identity_for_participant(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->guestPayload());
        $second = $this->openProgram();

        $this->get("/program/{$second->slug}/daftar")
            ->assertOk()
            ->assertSee('value="Budi Santoso"', false)
            ->assertSee('Masuk sebagai');
    }
}
```

Rework `tests/Feature/PublicApplyTest.php`: `validPayload()` gains `'password' => 'rahasia-kuat',` (guest flow now requires it); `test_duplicate_pending_submission_does_not_create_a_second_application` keeps posting twice WITHOUT logging out — after the first post the client IS the logged-in participant, so the second post exercises the authenticated dedup (assert 1 application, thank-you redirect). `test_rejected_applicant_can_reapply` likewise stays logged in. `test_pending_application_elsewhere_does_not_block_another_program` stays logged in across the two posts. `test_submission_links_program_and_referral_source` unchanged except payload.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=AccountAtApplicationTest` → FAIL (password not required, no account created). `--filter=PublicApplyTest` → FAIL on password additions.

- [ ] **Step 3: Request — conditional rules**

`StoreApplicationRequest`: add import `use Illuminate\Support\Facades\Auth;` and adjust `rules()`:

```php
    public function rules(): array
    {
        $person = Auth::user()?->person;

        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required', 'string', 'regex:/^\+62\d{8,13}$/',
                Rule::unique('people', 'phone')->ignore($person?->id)->whereNull('deleted_at'),
            ],
            'email' => [
                'required', 'email:rfc', 'max:160',
                Rule::unique('users', 'email')->ignore(Auth::id()),
                Rule::unique('people', 'email')
                    ->where(fn ($q) => $q->where('phone', '!=', $this->input('phone')))
                    ->whereNull('deleted_at'),
            ],
            'password' => Auth::check() ? ['prohibited'] : ['required', 'string', 'min:8'],
            'province_code' => ['required', 'string', 'size:2', 'exists:indonesia_provinces,code'],
            'city_code' => [
                'required', 'string', 'size:4',
                Rule::exists('indonesia_cities', 'code')->where(
                    fn ($q) => $q->where('province_code', $this->input('province_code'))
                ),
            ],
            'tiktok_username' => ['nullable', 'string', 'max:64'],
            'instagram_username' => ['nullable', 'string', 'max:64'],
            'referral_source' => ['required', Rule::in(Application::REFERRAL_SOURCES)],
            // Honeypot: real users never see or fill this; bots do.
            'website' => ['prohibited'],
        ];
    }
```

Messages: add

```php
            'phone.unique' => 'Nomor ini sudah terpakai pendaftar lain.',
            'email.unique' => 'Email ini sudah terpakai. Gunakan email lain atau masuk jika sudah punya akun.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.prohibited' => 'Kamu sudah masuk; kata sandi tidak diperlukan.',
```

and attribute `'password' => 'kata sandi',`.

NOTE the guest phone rule: `unique:people` ignoring nobody — but a guest legitimately re-using a phone of an account-LESS person must pass (that person gets the account). `Rule::unique('people','phone')` would BLOCK that. Fix: the guest phone-uniqueness question is really "does this phone belong to an account-carrying person" and that is enforced in the ACTION (ValidationException on phone), not here. So for guests the phone rule is ONLY `required+regex` (drop the unique). For authenticated users, the unique-ignoring-self applies (they may change their phone, but not onto someone else's). Final phone rule:

```php
            'phone' => array_filter([
                'required', 'string', 'regex:/^\+62\d{8,13}$/',
                Auth::check() ? Rule::unique('people', 'phone')->ignore($person?->id)->whereNull('deleted_at') : null,
            ]),
```

- [ ] **Step 4: Controller — three flows**

`ApplicationController`: add imports (`App\Actions\ProvisionParticipantAccount`, `Illuminate\Support\Facades\Auth`). Replace `create()` and `store()`:

```php
    /** Show the application form (prefilled + honest for logged-in participants). */
    public function create(Program $program): View|RedirectResponse
    {
        abort_if($program->status === 'draft', 404);

        if (! $program->isOpen()) {
            return redirect()->route('program.show', $program);
        }

        $user = Auth::user();

        if ($user && $user->hasAnyRole(['admin', 'mentor'])) {
            return redirect('/admin');
        }

        $person = $user?->person;
        $pendingApplication = $person
            ? $person->applications()->where('program_id', $program->id)->where('status', 'pending')->exists()
            : false;

        $provinces = Provinsi::orderBy('name')->get(['code', 'name']);

        return view('funnel.apply', compact('program', 'provinces', 'person', 'pendingApplication'));
    }
```

```php
    public function store(StoreApplicationRequest $request, Program $program, ProvisionParticipantAccount $provisioner): RedirectResponse
    {
        abort_if($program->status === 'draft', 404);

        if (! $program->isOpen()) {
            return redirect()->route('program.show', $program);
        }

        $user = Auth::user();

        if ($user && $user->hasAnyRole(['admin', 'mentor'])) {
            return redirect('/admin');
        }

        $data = $request->validated();

        if ($user) {
            // Logged-in participant: their Person is authoritative; refresh it.
            $person = $user->person;
            $person->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'province_code' => $data['province_code'],
                'city_code' => $data['city_code'],
                'tiktok_username' => $data['tiktok_username'] ?? null,
                'instagram_username' => $data['instagram_username'] ?? null,
            ]);
            $user->update(['name' => $data['name'], 'email' => $data['email']]);
        } else {
            // Guest: provision the account (throws on an account-carrying phone).
            [$person, $account] = $provisioner->provision([
                'phone' => $data['phone'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $person->update([
                'province_code' => $data['province_code'],
                'city_code' => $data['city_code'],
                'tiktok_username' => $data['tiktok_username'] ?? null,
                'instagram_username' => $data['instagram_username'] ?? null,
            ]);

            Auth::login($account);
            $request->session()->regenerate();
        }

        // One pending application per person per program (silent backstop).
        $alreadyPending = $person->applications()
            ->where('program_id', $program->id)
            ->where('status', 'pending')
            ->exists();

        if (! $alreadyPending) {
            $person->applications()->create([
                'status' => 'pending',
                'program_id' => $program->id,
                'referral_source' => $data['referral_source'],
            ]);
        }

        return redirect()
            ->route('daftar.thankyou')
            ->with('applicant_name', $person->name)
            ->with('has_account', true);
    }
```

Edge note for the implementer: `$user->person` can be null for a participant account created without a Person (should not exist in practice) — guard with `abort_unless($person, 403)` in the logged-in branch of `store()` and treat null-person users as guests in `create()`'s prefill (i.e. `$person` stays null, form renders empty with no password field? NO — a participant without person still must not submit `password` (prohibited). Simplest honest handling: if `$user && ! $user->person`, log them out of the flow? Overthinking — assert with `abort_unless($user->person, 403)` in both branches when `$user` is a participant).

- [ ] **Step 5: Views**

`resources/views/funnel/apply.blade.php`:

1. Top of the card (before the form), the honest notice replaces the form when `$pendingApplication`:

```blade
            @if ($pendingApplication)
                <div class="mt-10 rounded-3xl border border-teal-900/10 bg-white/70 p-6 text-center shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-lg font-bold text-teal-900">Kamu sudah mendaftar program ini.</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-teal-800/70">
                        Pendaftaranmu sedang kami tinjau. Pantau statusnya di halaman akunmu.
                    </p>
                    <div class="mt-5">
                        <x-cta :href="route('member.area')" label="Lihat Status" />
                    </div>
                </div>
            @else
                <form ...existing...>
```

(close the `@else` after the form's `</form>` with `@endif`).

2. Prefill inputs (guest sees empty, participant sees their data): change each `value="{{ old('x') }}"` to `value="{{ old('x', $person?->x) }}"` for name/phone/email/tiktok_username/instagram_username; province option selected: `@selected(old('province_code', $person?->province_code) === $province->code)`; city select `data-old="{{ old('city_code', $person?->city_code) }}"`.

3. Password block — insert AFTER the email field, guests only:

```blade
                @guest
                    <div>
                        <label for="password" class="block text-sm font-medium text-teal-800">Buat kata sandi <span class="text-teal-800/50">(minimal 8 karakter)</span></label>
                        <input id="password" name="password" type="password" autocomplete="new-password"
                               class="{{ $field }} @error('password') border border-red-400 @else border border-teal-900/15 @enderror"
                               placeholder="••••••••">
                        <p class="mt-1.5 text-xs text-teal-800/50">Akunmu dibuat otomatis untuk memantau status pendaftaran dan mengubah datamu nanti.</p>
                        @error('password') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endguest
```

4. Identity hint above the submit button for participants, login hint for guests:

```blade
                @auth
                    <p class="text-xs text-teal-800/60">Masuk sebagai <span class="font-semibold">{{ auth()->user()->name }}</span>. Perubahan data di atas akan tersimpan di akunmu.</p>
                @else
                    <p class="text-xs text-teal-800/60">Sudah punya akun? <a href="{{ route('member.login') }}" class="font-semibold text-teal-700 hover:text-orange-600">Masuk dulu</a> supaya datamu terisi otomatis.</p>
                @endauth
```

`resources/views/terima-kasih.blade.php`: after the existing content, add a CTA when the session carries the account flag:

```blade
        @if (session('has_account'))
            <div class="mt-6">
                <x-cta :href="route('member.area')" label="Lihat Status Pendaftaran" />
            </div>
        @endif
```

(Adapt placement to the existing layout of the page — read it first; keep its style.)

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact --filter=AccountAtApplicationTest` and `--filter=PublicApplyTest` → PASS; then the FULL suite (CommunityJoinTest email rule interplay: the apply email people-rule mirrors the join door) → all green.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/StoreApplicationRequest.php app/Http/Controllers/ApplicationController.php resources/views/funnel/apply.blade.php resources/views/terima-kasih.blade.php tests/Feature/AccountAtApplicationTest.php tests/Feature/PublicApplyTest.php
git commit -m "feat: applying to a program creates the participant account"
```

---

### Task 3: /akun shows application statuses

**Files:**
- Modify: `app/Http/Controllers/MemberAreaController.php`, `resources/views/member/akun.blade.php`
- Test: extend `tests/Feature/MemberAuthTest.php`

**Interfaces:**
- Consumes: `Person::applications` + `Application::program`.
- Produces: `/akun` lists the person's applications (newest first): program name, submitted date, status badge (Menunggu/Diterima/Ditolak in promo-safe Indonesian).

- [ ] **Step 1: Failing test**

Add to `MemberAuthTest` (import `App\Models\Application`, `App\Models\Program`):

```php
    public function test_akun_lists_application_statuses(): void
    {
        $user = $this->participant();
        $program = Program::factory()->active()->create(['name' => 'Program Status']);
        Application::create([
            'people_id' => $user->person->id, 'status' => 'pending',
            'program_id' => $program->id, 'referral_source' => 'teman',
        ]);

        $this->actingAs($user)->get('/akun')
            ->assertOk()
            ->assertSee('Program Status')
            ->assertSee('Menunggu');
    }
```

Run → FAIL.

- [ ] **Step 2: Controller + view**

`MemberAreaController::index`: extend the person load to `$user->person()->with(['communityMembership', 'applications' => fn ($q) => $q->latest(), 'applications.program:id,name'])->first();` and pass `'applications' => $person?->applications ?? collect(),` to the view.

`member/akun.blade.php`: after the Profil card, add:

```blade
            @if ($applications->isNotEmpty())
                <div class="mt-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/60">Status Pendaftaran</h2>
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach ($applications as $application)
                            <li class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-teal-900">{{ $application->program?->name ?? 'Program' }}</p>
                                    <p class="text-xs text-teal-800/60">Daftar {{ $application->created_at->translatedFormat('j F Y') }}</p>
                                </div>
                                @php($statusLabel = ['pending' => 'Menunggu', 'accepted' => 'Diterima', 'rejected' => 'Belum lolos'][$application->status] ?? $application->status)
                                @php($statusClass = ['pending' => 'bg-orange-100 text-orange-700', 'accepted' => 'bg-teal-100 text-teal-700', 'rejected' => 'bg-red-50 text-red-600'][$application->status] ?? 'bg-sand-100 text-teal-800/70')
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
```

- [ ] **Step 3: Run + full suite + commit**

Run: `php artisan test --compact --filter=MemberAuthTest` then full suite → all green.

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/MemberAreaController.php resources/views/member/akun.blade.php tests/Feature/MemberAuthTest.php
git commit -m "feat: member area lists application statuses"
```

---

## Self-Review

**Decision #9 coverage:** password on form + provision + auto-login (T2); login prompt for account-carrying phone, no pre-auth data echo (action message, T1/T2); prefilled form + no password + self-service identity update (T2); honest pending notice for authed only, silent backstop for everyone (T2); `/akun` statuses (T3); membership NOT created by applying (T2 test asserts 0); staff redirect (T2); approval never creates users (nothing to do — no code path does).

**Placeholder scan:** none; view snippets carry full markup; the one "read it first" (terima-kasih placement) is a legitimate site-specific adaptation instruction.

**Type consistency:** `provision(array): array{0: Person, 1: User}` produced T1, consumed T2; view vars `person`/`pendingApplication` produced in `create()` consumed in blade; `has_account` flash produced in `store()` consumed in terima-kasih.

**Known interplay:** PublicApplyTest rework (T2) redefines the dedup tests as authenticated flows — deliberate: after the first guest submit the client session IS logged in, which is exactly the new real-world path.
