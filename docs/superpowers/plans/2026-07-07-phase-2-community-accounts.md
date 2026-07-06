# Phase 2 — Community + Member Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Open the second funnel door: `/komunitas` join creates a Person-linked participant account with instant login, a minimal member area at `/akun`, member login at `/masuk`, an email password-reset flow (SMTP-ready; credentials land later), and an admin Community members list.

**Architecture:** Public side stays Blade (join/login/akun/reset pages on the existing `x-layouts.public` style). Accounts reuse the `users` table + `web` guard + `participant` role; identity follows the golden rule — find-or-create Person by normalized phone, then link `people.user_id`. Password reset uses Laravel's built-in broker with a custom Indonesian notification; local mail driver is `log`, production switches via `.env` only. Admin side follows the Tahap C pattern (permission-gated API + Vue screen). Spec: `docs/superpowers/specs/2026-07-06-program-community-products-concept.md` (Phase 2 scope).

**Tech Stack:** PHP 8.4, Laravel 13 (Password broker, Notifications), spatie/laravel-permission v8, Blade + Tailwind v4 (public), Vue 3 (admin), PHPUnit 12.

## Global Constraints

- PHP: curly braces always; explicit return types + param type hints. `vendor/bin/pint --dirty --format agent` before each backend commit.
- Tests: PHPUnit; feature tests seed `RoleSeeder` + `PermissionSeeder` in `setUp` when roles/permissions are exercised.
- No new composer/npm dependencies. Public forms reuse the `apply.blade.php` conventions (`$field` class string, honeypot `website` field, `old()` handling, Indonesian copy, no em-dashes, no internal entity terms).
- Identity rule (verbatim from spec): find-or-create Person by normalized phone (`App\Support\Phone::normalize`); joining creates the `participant` User and links the existing `people.user_id` column.
- Community membership columns (spec §4): `people_id` (unique), `referral_source`; `created_at` records the join time (no redundant `joined_at` column — noted deviation, `created_at` IS the join timestamp).
- Member URLs: login `GET/POST /masuk`, logout `POST /keluar`, area `GET /akun`, join `GET/POST /komunitas`, reset request `GET/POST /lupa-password`, reset form `GET /reset-password/{token}` + `POST /reset-password`. The reset-form GET route MUST be named `password.reset` (Laravel's notification URL generator requires it).
- Email/password reset: full flow built now; local `MAIL_MAILER=log` works as-is; production only needs `.env` SMTP values (no code change). Reset responses are enumeration-safe (same generic message whether or not the email exists).
- Deactivated accounts (`users.is_active = false`) cannot log in at `/masuk` (same message pattern as the admin gate).
- Staff (admin/mentor) who log in via `/masuk` or visit `/akun` are redirected to `/admin` — the member area is for participants.
- New permission: `community.view` (admin only; mentor does NOT get it).
- Public POST routes carry `throttle:10,1` (join/login) or `throttle:6,1` (reset request/update).

---

## File Structure

**Backend — create:**
- `database/migrations/2026_07_07_100001_create_community_memberships_table.php`
- `app/Models/CommunityMembership.php`
- `app/Http/Requests/CommunityJoinRequest.php`
- `app/Http/Controllers/CommunityController.php` (join door)
- `app/Http/Controllers/MemberAuthController.php` (login/logout)
- `app/Http/Controllers/MemberAreaController.php` (/akun)
- `app/Http/Controllers/MemberPasswordController.php` (reset flow)
- `app/Notifications/ResetPasswordNotification.php` (Indonesian email)
- `app/Http/Controllers/Api/Admin/CommunityMemberController.php`
- Tests: `tests/Feature/CommunityJoinTest.php`, `MemberAuthTest.php`, `PasswordResetTest.php`, `CommunityAdminTest.php`

**Backend — modify:**
- `database/seeders/PermissionSeeder.php` (+`community.view`)
- `app/Models/Person.php` (+`communityMembership()` HasOne)
- `app/Models/User.php` (+`sendPasswordResetNotification()` override)
- `bootstrap/app.php` (`redirectGuestsTo` → member login for web)
- `routes/web.php` (member/community routes), `routes/api.php` (admin community route)

**Blade — create:** `resources/views/funnel/community.blade.php`, `member/login.blade.php`, `member/akun.blade.php`, `member/forgot-password.blade.php`, `member/reset-password.blade.php`
**Blade — modify:** `resources/views/components/layouts/public.blade.php` (nav gains Masuk/Akun link)

**Frontend (admin) — create:** `resources/js/admin/views/Community.vue`
**Frontend (admin) — modify:** `resources/js/admin/api.js`, `router.js`, `components/AppShell.vue`

---

### Task 1: CommunityMembership foundation

**Files:**
- Create: `database/migrations/2026_07_07_100001_create_community_memberships_table.php`
- Create: `app/Models/CommunityMembership.php`
- Modify: `app/Models/Person.php`, `database/seeders/PermissionSeeder.php`
- Test: `tests/Feature/CommunityMembershipModelTest.php`

**Interfaces:**
- Produces: `CommunityMembership` model (`people_id`, `referral_source` fillable; `person()` BelongsTo); `Person::communityMembership()` HasOne; permission `community.view` (admin only).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CommunityMembershipModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CommunityMembership;
use App\Models\Person;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunityMembershipModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_has_one_membership(): void
    {
        $person = Person::create([
            'name' => 'Uji Komunitas', 'phone' => '+628123450001', 'email' => 'uji.komunitas@example.test',
        ]);
        $membership = CommunityMembership::create([
            'people_id' => $person->id, 'referral_source' => 'instagram',
        ]);

        $this->assertTrue($person->communityMembership->is($membership));
        $this->assertTrue($membership->person->is($person));
    }

    public function test_membership_is_unique_per_person(): void
    {
        $person = Person::create([
            'name' => 'Uji Unik', 'phone' => '+628123450002', 'email' => 'uji.unik@example.test',
        ]);
        CommunityMembership::create(['people_id' => $person->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        CommunityMembership::create(['people_id' => $person->id]);
    }

    public function test_admin_gets_community_view_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('community.view'));
        $this->assertFalse(Role::findByName('mentor', 'web')->hasPermissionTo('community.view'));
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=CommunityMembershipModelTest`
Expected: FAIL — model/table missing.

- [ ] **Step 3: Create migration + model + relation + permission**

Create `database/migrations/2026_07_07_100001_create_community_memberships_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Community membership — the unselective second door. One row per
        // Person, ever; created_at is the join timestamp. The login account
        // itself lives on users (linked via people.user_id).
        Schema::create('community_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('people_id')->unique()->constrained('people')->cascadeOnDelete();
            $table->string('referral_source')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_memberships');
    }
};
```

Create `app/Models/CommunityMembership.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'people_id',
        'referral_source',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'people_id');
    }
}
```

In `app/Models/Person.php`: add `use Illuminate\Database\Eloquent\Relations\HasOne;` (already imported? check — Person currently imports BelongsTo/HasMany; add HasOne if absent) and the relation:

```php
    /** Community membership (the unselective second funnel door), if joined. */
    public function communityMembership(): HasOne
    {
        return $this->hasOne(CommunityMembership::class, 'people_id');
    }
```

In `database/seeders/PermissionSeeder.php`: add `'community.view',` to the `$permissions` array (after `'programs.manage',`). Mentor mapping unchanged.

- [ ] **Step 4: Migrate + verify pass**

Run: `php artisan migrate` then `php artisan test --compact --filter=CommunityMembershipModelTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_07_100001_create_community_memberships_table.php app/Models/CommunityMembership.php app/Models/Person.php database/seeders/PermissionSeeder.php tests/Feature/CommunityMembershipModelTest.php
git commit -m "feat: community membership entity and community.view permission"
```

---

### Task 2: Join door — /komunitas creates account + membership + auto-login

**Files:**
- Create: `app/Http/Requests/CommunityJoinRequest.php`
- Create: `app/Http/Controllers/CommunityController.php`
- Create: `resources/views/funnel/community.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CommunityJoinTest.php`

**Interfaces:**
- Consumes: `CommunityMembership` (Task 1), `Phone::normalize`, `Application::REFERRAL_SOURCES`, `participant` role.
- Produces: routes `komunitas` (GET) / `komunitas.join` (POST, throttle:10,1). Join creates/reuses Person by phone, creates User (role `participant`, `is_active` true), links `people.user_id`, creates membership, logs in, redirects to `/akun`. Route name `member.area` is registered in Task 3 — THIS task registers a temporary `/akun` placeholder route named `member.area` returning the join success target (see Step 3) so redirects resolve; Task 3 replaces it.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CommunityJoinTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CommunityMembership;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CommunityJoinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'name' => 'Siti Aminah',
            'phone' => '081298765432',
            'email' => 'siti@example.test',
            'password' => 'rahasia-kuat',
            'referral_source' => 'tiktok',
        ];
    }

    public function test_join_creates_person_account_membership_and_logs_in(): void
    {
        $this->post('/komunitas', $this->validPayload())
            ->assertRedirect('/akun');

        $person = Person::sole();
        $this->assertSame('+6281298765432', $person->phone);
        $this->assertNotNull($person->user_id);
        $this->assertTrue($person->user->hasRole('participant'));
        $this->assertSame('tiktok', $person->communityMembership->referral_source);
        $this->assertTrue(Auth::check());
        $this->assertTrue(Auth::user()->is($person->user));
    }

    public function test_join_reuses_existing_person_by_phone(): void
    {
        $existing = Person::create([
            'name' => 'Siti Lama', 'phone' => '+6281298765432', 'email' => 'siti.lama@example.test',
        ]);

        $this->post('/komunitas', $this->validPayload())->assertRedirect('/akun');

        $this->assertSame(1, Person::count());
        $this->assertNotNull($existing->fresh()->user_id);
    }

    public function test_phone_that_already_has_an_account_is_rejected(): void
    {
        $this->post('/komunitas', $this->validPayload());
        Auth::logout();

        $this->from('/komunitas')
            ->post('/komunitas', [...$this->validPayload(), 'email' => 'lain@example.test'])
            ->assertRedirect('/komunitas')
            ->assertSessionHasErrors('phone');

        $this->assertSame(1, User::role('participant')->count());
        $this->assertSame(1, CommunityMembership::count());
    }

    public function test_email_already_used_by_another_account_is_rejected(): void
    {
        User::factory()->create(['email' => 'siti@example.test']);

        $this->from('/komunitas')
            ->post('/komunitas', $this->validPayload())
            ->assertRedirect('/komunitas')
            ->assertSessionHasErrors('email');
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->from('/komunitas')
            ->post('/komunitas', [...$this->validPayload(), 'website' => 'spam'])
            ->assertRedirect('/komunitas')
            ->assertSessionHasErrors('website');
    }

    public function test_join_page_renders(): void
    {
        $this->get('/komunitas')->assertOk()->assertSee('Komunitas');
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=CommunityJoinTest`
Expected: FAIL — 404 (routes missing).

- [ ] **Step 3: Create request, controller, routes**

Create `app/Http/Requests/CommunityJoinRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunityJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Canonicalise the phone before validation so format + matching use +62 form. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::normalize($this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+62\d{8,13}$/'],
            'email' => ['required', 'email:rfc', 'max:160', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'referral_source' => ['required', Rule::in(Application::REFERRAL_SOURCES)],
            // Honeypot: real users never see or fill this; bots do.
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.regex' => 'Format nomor HP tidak valid. Contoh: 0812xxxxxxx.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'referral_source.required' => 'Beritahu kami dari mana kamu tahu komunitas ini.',
            'referral_source.in' => 'Pilihan sumber tidak valid.',
            'website.prohibited' => 'Pengiriman ditolak.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'phone' => 'nomor HP',
            'email' => 'email',
            'password' => 'kata sandi',
            'referral_source' => 'sumber informasi',
        ];
    }
}
```

Create `app/Http/Controllers/CommunityController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommunityJoinRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommunityController extends Controller
{
    /** Public join form for the affiliator community. */
    public function show(): View
    {
        return view('funnel.community');
    }

    /**
     * Join: find-or-create the Person by phone (the identity anchor), create
     * their participant account, record the membership, and sign them in.
     */
    public function join(CommunityJoinRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $person = Person::firstOrNew(['phone' => $data['phone']]);

        // The phone anchor already carries a login: this human has an account.
        if ($person->exists && $person->user_id !== null) {
            throw ValidationException::withMessages([
                'phone' => 'Nomor ini sudah punya akun. Silakan masuk.',
            ]);
        }

        $user = DB::transaction(function () use ($person, $data): User {
            $person->fill([
                'name' => $data['name'],
                'email' => $data['email'],
            ])->save();

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);
            $user->assignRole('participant');

            $person->user_id = $user->id;
            $person->save();

            $person->communityMembership()->firstOrCreate(
                [],
                ['referral_source' => $data['referral_source']]
            );

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('member.area')->with('joined', true);
    }
}
```

In `routes/web.php`, add import `use App\Http\Controllers\CommunityController;` and routes (after the funnel block):

```php
/*
 | Community door — join creates a participant account and signs in.
 */
Route::get('/komunitas', [CommunityController::class, 'show'])->name('komunitas');
Route::post('/komunitas', [CommunityController::class, 'join'])->middleware('throttle:10,1')->name('komunitas.join');

// Temporary target until Task 3 builds the real member area.
Route::get('/akun', fn () => redirect()->route('home'))->middleware('auth')->name('member.area');
```

- [ ] **Step 4: Create the join view**

Create `resources/views/funnel/community.blade.php`:

```blade
<x-layouts.public title="Gabung Komunitas"
    description="Gabung komunitas affiliator Kheedma Academy: materi, kabar terbaru, dan teman seperjalanan.">

    @php
        $field = 'mt-1.5 w-full rounded-lg bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';
    @endphp

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-2xl px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Komunitas</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900 sm:text-4xl">Gabung Komunitas Affiliator.</h1>
                <p class="mx-auto mt-4 max-w-lg text-base leading-relaxed text-teal-800/80">
                    Gratis. Buat akunmu, dapatkan kabar terbaru, materi pilihan, dan jadi yang
                    pertama tahu saat kelas baru dibuka.
                </p>
            </div>

            @if ($errors->any())
                <div class="mt-8 rounded-xl border border-orange-600/30 bg-orange-50 px-5 py-4 text-sm text-orange-700">
                    Ada beberapa isian yang perlu diperbaiki. Silakan cek kembali field di bawah.
                </div>
            @endif

            <form method="POST" action="{{ route('komunitas.join') }}" class="mt-10 space-y-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                @csrf

                {{-- Honeypot: hidden from humans, tempting to bots --}}
                <div aria-hidden="true" style="position:absolute; left:-9999px; top:-9999px; width:1px; height:1px; overflow:hidden;">
                    <label>Website
                        <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
                    </label>
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-teal-800">Nama lengkap</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name"
                           class="{{ $field }} @error('name') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="Nama sesuai identitas">
                    @error('name') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-teal-800">Nomor HP <span class="text-teal-800/50">(WhatsApp aktif)</span></label>
                    <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" autocomplete="tel"
                           class="{{ $field }} @error('phone') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="0812xxxxxxx">
                    @error('phone') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-teal-800">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email"
                           class="{{ $field }} @error('email') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="kamu@email.com">
                    @error('email') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-teal-800">Kata sandi <span class="text-teal-800/50">(minimal 8 karakter)</span></label>
                    <input id="password" name="password" type="password" autocomplete="new-password"
                           class="{{ $field }} @error('password') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="••••••••">
                    @error('password') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="referral_source" class="block text-sm font-medium text-teal-800">Tahu komunitas ini dari mana?</label>
                    <select id="referral_source" name="referral_source"
                            class="{{ $field }} @error('referral_source') border border-red-400 @else border border-teal-900/15 @enderror">
                        <option value="">Pilih salah satu…</option>
                        @foreach ([
                            'instagram' => 'Instagram',
                            'tiktok' => 'TikTok',
                            'whatsapp' => 'WhatsApp',
                            'teman' => 'Teman atau keluarga',
                            'google' => 'Pencarian Google',
                            'lainnya' => 'Lainnya',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('referral_source') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('referral_source') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-orange-500 px-7 py-3.5 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600 hover:shadow-lg sm:w-auto">
                        Gabung Sekarang
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </form>

            <p class="mt-6 text-center text-sm text-teal-800/60">
                Sudah punya akun? <a href="{{ route('member.login') ?? url('/masuk') }}" class="font-semibold text-teal-700 hover:text-orange-600">Masuk di sini</a>
            </p>
        </div>
    </section>

</x-layouts.public>
```

NOTE for the implementer: `route('member.login')` only exists after Task 3. In THIS task, write the link as `{{ url('/masuk') }}` (plain) — Task 3 upgrades it to the named route. Do not use the `??` fallback shown above; use the plain `url('/masuk')` form.

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter=CommunityJoinTest` then the full suite.
Expected: all PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/CommunityJoinRequest.php app/Http/Controllers/CommunityController.php resources/views/funnel/community.blade.php routes/web.php tests/Feature/CommunityJoinTest.php
git commit -m "feat: community join door with instant participant account"
```

---

### Task 3: Member login, logout, and the /akun area

**Files:**
- Create: `app/Http/Controllers/MemberAuthController.php`, `app/Http/Controllers/MemberAreaController.php`
- Create: `resources/views/member/login.blade.php`, `resources/views/member/akun.blade.php`
- Modify: `routes/web.php` (replace the temporary `/akun` route), `bootstrap/app.php` (`redirectGuestsTo`), `resources/views/funnel/community.blade.php` (login link → named route), `resources/views/components/layouts/public.blade.php` (nav link)
- Test: `tests/Feature/MemberAuthTest.php`

**Interfaces:**
- Consumes: participant accounts (Task 2), `users.is_active`.
- Produces: routes `member.login` (GET /masuk), `member.login.store` (POST /masuk, throttle:10,1), `member.logout` (POST /keluar), `member.area` (GET /akun, auth). Guests hitting protected web routes redirect to `member.login`. Staff at /masuk or /akun → redirect `/admin`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MemberAuthTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function participant(array $overrides = []): User
    {
        $user = User::factory()->create([...$overrides, 'password' => Hash::make('rahasia-kuat')]);
        $user->assignRole('participant');
        Person::create([
            'name' => $user->name, 'phone' => '+62812'.random_int(10000000, 99999999),
            'email' => $user->email, 'user_id' => $user->id,
        ]);

        return $user;
    }

    public function test_participant_can_login_and_reach_akun(): void
    {
        $user = $this->participant();

        $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia-kuat'])
            ->assertRedirect('/akun');

        $this->actingAs($user)->get('/akun')->assertOk()->assertSee($user->name);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->participant();

        $this->from('/masuk')
            ->post('/masuk', ['email' => $user->email, 'password' => 'salah-total'])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors('email');
    }

    public function test_deactivated_participant_cannot_login(): void
    {
        $user = $this->participant(['is_active' => false]);

        $this->from('/masuk')
            ->post('/masuk', ['email' => $user->email, 'password' => 'rahasia-kuat'])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors('email');
    }

    public function test_staff_is_redirected_to_admin_panel(): void
    {
        $admin = User::factory()->admin()->create(['password' => Hash::make('rahasia-kuat')]);

        $this->post('/masuk', ['email' => $admin->email, 'password' => 'rahasia-kuat'])
            ->assertRedirect('/admin');

        $this->actingAs($admin)->get('/akun')->assertRedirect('/admin');
    }

    public function test_guest_is_redirected_to_member_login(): void
    {
        $this->get('/akun')->assertRedirect('/masuk');
    }

    public function test_logout_ends_the_session(): void
    {
        $user = $this->participant();

        $this->actingAs($user)->post('/keluar')->assertRedirect('/');
        $this->assertGuest();
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=MemberAuthTest`
Expected: FAIL — routes missing / guest redirect exception.

- [ ] **Step 3: Create the controllers**

Create `app/Http/Controllers/MemberAuthController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberAuthController extends Controller
{
    /** Member login page (public site — the admin SPA has its own). */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->home(Auth::user());
        }

        return view('member.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Akun ini dinonaktifkan. Hubungi admin.',
            ]);
        }

        $request->session()->regenerate();

        return $this->home($user);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /** Staff belong in the admin panel; members in the member area. */
    private function home($user): RedirectResponse
    {
        if ($user->hasAnyRole(['admin', 'mentor'])) {
            return redirect('/admin');
        }

        return redirect()->intended(route('member.area'));
    }
}
```

Create `app/Http/Controllers/MemberAreaController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberAreaController extends Controller
{
    /** Minimal member home: identity + membership; products/announcements land later. */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasAnyRole(['admin', 'mentor'])) {
            return redirect('/admin');
        }

        $person = $user->person()->with('communityMembership')->first();

        return view('member.akun', [
            'user' => $user,
            'person' => $person,
            'membership' => $person?->communityMembership,
        ]);
    }
}
```

NOTE: `User::person()` exists as `HasOne` (check `app/Models/User.php` — it is `person(): HasOne`). Use it as above.

- [ ] **Step 4: Routes + guest redirect + nav**

In `routes/web.php`: add imports `use App\Http\Controllers\MemberAuthController; use App\Http\Controllers\MemberAreaController;`, DELETE the temporary `/akun` closure route from Task 2, and add:

```php
/*
 | Member area (participants). Staff are redirected to /admin.
 */
Route::get('/masuk', [MemberAuthController::class, 'showLogin'])->name('member.login');
Route::post('/masuk', [MemberAuthController::class, 'login'])->middleware('throttle:10,1')->name('member.login.store');
Route::post('/keluar', [MemberAuthController::class, 'logout'])->name('member.logout');
Route::get('/akun', [MemberAreaController::class, 'index'])->middleware('auth')->name('member.area');
```

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware): void {` add:

```php
        // Web guests land on the member login; API guests keep getting 401 JSON.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('member.login'));
```

(`Illuminate\Http\Request` is already imported in bootstrap/app.php.)

In `resources/views/funnel/community.blade.php`: change the login link to `{{ route('member.login') }}`.

In `resources/views/components/layouts/public.blade.php`: in the desktop `<nav>`, after the "Nilai" link add:

```blade
                @auth
                    <a href="{{ route('member.area') }}" class="transition hover:text-orange-600">Akun Saya</a>
                @else
                    <a href="{{ route('member.login') }}" class="transition hover:text-orange-600">Masuk</a>
                @endauth
```

- [ ] **Step 5: Create the views**

Create `resources/views/member/login.blade.php`:

```blade
<x-layouts.public title="Masuk"
    description="Masuk ke akun Kheedma Academy kamu.">

    @php
        $field = 'mt-1.5 w-full rounded-lg bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';
    @endphp

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-md px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Akun</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900">Masuk.</h1>
            </div>

            @if (session('reset'))
                <div class="mt-8 rounded-xl border border-teal-600/30 bg-teal-50 px-5 py-4 text-sm text-teal-800">
                    {{ session('reset') }}
                </div>
            @endif

            <form method="POST" action="{{ route('member.login.store') }}" class="mt-10 space-y-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-teal-800">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required
                           class="{{ $field }} @error('email') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="kamu@email.com">
                    @error('email') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-teal-800">Kata sandi</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="{{ $field }} border border-teal-900/15" placeholder="••••••••">
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-teal-800/70">
                        <input type="checkbox" name="remember" value="1" class="rounded border-teal-900/20 text-teal-700 focus:ring-teal-600/30">
                        Ingat saya
                    </label>
                    <a href="{{ route('member.password.request') }}" class="font-medium text-teal-700 hover:text-orange-600">Lupa kata sandi?</a>
                </div>

                <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-full bg-teal-700 px-7 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-teal-800">
                    Masuk
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-teal-800/60">
                Belum punya akun? <a href="{{ route('komunitas') }}" class="font-semibold text-teal-700 hover:text-orange-600">Gabung komunitas</a>
            </p>
        </div>
    </section>

</x-layouts.public>
```

NOTE for the implementer: `route('member.password.request')` only exists after Task 4. In THIS task write that link as `{{ url('/lupa-password') }}`; Task 4 upgrades it to the named route.

Create `resources/views/member/akun.blade.php`:

```blade
<x-layouts.public title="Akun Saya"
    description="Area member Kheedma Academy.">

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-2xl px-6 py-16 sm:py-20">
            @if (session('joined'))
                <div class="mb-8 rounded-xl border border-teal-600/30 bg-teal-50 px-5 py-4 text-sm text-teal-800">
                    Selamat datang di komunitas! Akunmu sudah aktif.
                </div>
            @endif

            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Akun Saya</p>
                    <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900">Halo, {{ $user->name }}.</h1>
                </div>
                <form method="POST" action="{{ route('member.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-teal-900/15 px-5 py-2 text-sm font-medium text-teal-800 transition hover:border-teal-600/40 hover:text-orange-600">
                        Keluar
                    </button>
                </form>
            </div>

            <div class="mt-8 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-teal-800/60">Profil</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-teal-800/60">Nama</dt>
                        <dd class="font-medium text-teal-900">{{ $user->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-teal-800/60">Email</dt>
                        <dd class="font-medium text-teal-900">{{ $user->email }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-teal-800/60">Nomor HP</dt>
                        <dd class="font-medium text-teal-900">{{ $person?->phone ?? '—' }}</dd>
                    </div>
                    @if ($membership)
                        <div class="flex justify-between gap-4">
                            <dt class="text-teal-800/60">Anggota sejak</dt>
                            <dd class="font-medium text-teal-900">{{ $membership->created_at->translatedFormat('d F Y') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="mt-6 rounded-3xl border border-dashed border-teal-900/15 bg-white/40 p-6 text-center sm:p-8">
                <p class="text-sm leading-relaxed text-teal-800/60">
                    Materi pilihan dan pengumuman komunitas akan tampil di sini. Nantikan ya!
                </p>
            </div>
        </div>
    </section>

</x-layouts.public>
```

- [ ] **Step 6: Run tests + full suite**

Run: `php artisan test --compact --filter=MemberAuthTest` then `php artisan test --compact`
Expected: all PASS (existing suites unaffected; the API 401 behavior is preserved by the `api/*` null branch in redirectGuestsTo).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/MemberAuthController.php app/Http/Controllers/MemberAreaController.php resources/views/member/ resources/views/funnel/community.blade.php resources/views/components/layouts/public.blade.php routes/web.php bootstrap/app.php tests/Feature/MemberAuthTest.php
git commit -m "feat: member login and minimal /akun area"
```

---

### Task 4: Password reset via email (SMTP-ready; credentials later)

**Files:**
- Create: `app/Notifications/ResetPasswordNotification.php`
- Create: `app/Http/Controllers/MemberPasswordController.php`
- Create: `resources/views/member/forgot-password.blade.php`, `resources/views/member/reset-password.blade.php`
- Modify: `app/Models/User.php` (notification override), `routes/web.php`, `resources/views/member/login.blade.php` (named route link)
- Test: `tests/Feature/PasswordResetTest.php`

**Interfaces:**
- Consumes: Laravel Password broker (users table + password_reset_tokens already exist; `Illuminate\Foundation\Auth\User` already includes `CanResetPassword`).
- Produces: routes `member.password.request` (GET /lupa-password), `member.password.email` (POST /lupa-password, throttle:6,1), `password.reset` (GET /reset-password/{token} — name REQUIRED by the broker's URL generator), `member.password.update` (POST /reset-password, throttle:6,1). Indonesian reset email. Enumeration-safe responses.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PasswordResetTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_to_a_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/lupa-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_unknown_email_gets_the_same_generic_response(): void
    {
        Notification::fake();

        $this->post('/lupa-password', ['email' => 'tidak-ada@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'sandi-baru-aman',
            'password_confirmation' => 'sandi-baru-aman',
        ])->assertRedirect('/masuk');

        $this->assertTrue(Hash::check('sandi-baru-aman', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->from('/reset-password/token-palsu')
            ->post('/reset-password', [
                'token' => 'token-palsu',
                'email' => $user->email,
                'password' => 'sandi-baru-aman',
                'password_confirmation' => 'sandi-baru-aman',
            ])->assertSessionHasErrors('email');
    }

    public function test_reset_form_renders(): void
    {
        $this->get('/reset-password/abc123?email=uji@example.test')
            ->assertOk()
            ->assertSee('Atur ulang');
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=PasswordResetTest`
Expected: FAIL — routes/notification missing.

- [ ] **Step 3: Create the notification + User override**

Create `app/Notifications/ResetPasswordNotification.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi · Kheedma Academy')
            ->greeting('Assalamu\'alaikum, '.$notifiable->name.'.')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akunmu.')
            ->action('Atur Ulang Kata Sandi', $url)
            ->line('Tautan ini berlaku 60 menit. Kalau kamu tidak meminta pengaturan ulang, abaikan email ini.')
            ->salutation('Salam hangat, Tim Kheedma Academy');
    }
}
```

In `app/Models/User.php`: add import `use App\Notifications\ResetPasswordNotification;` and the override method:

```php
    /** Send the Indonesian reset email pointing at the member reset page. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
```

- [ ] **Step 4: Create the controller + routes**

Create `app/Http/Controllers/MemberPasswordController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberPasswordController extends Controller
{
    public function requestForm(): View
    {
        return view('member.forgot-password');
    }

    /**
     * Always answer with the same message so account emails cannot be
     * enumerated from this endpoint.
     */
    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Jika email terdaftar, tautan atur ulang sudah kami kirim. Cek kotak masukmu.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('member.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $status = Password::reset(
            $data,
            function ($user, string $password): void {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Tautan atur ulang tidak valid atau sudah kedaluwarsa. Minta tautan baru.',
            ]);
        }

        return redirect()->route('member.login')->with('reset', 'Kata sandi berhasil diubah. Silakan masuk.');
    }
}
```

In `routes/web.php`: add import `use App\Http\Controllers\MemberPasswordController;` and routes (after the member block):

```php
/*
 | Member password reset. The GET reset route MUST be named password.reset —
 | Laravel's reset notification builds its URL from that name.
 */
Route::get('/lupa-password', [MemberPasswordController::class, 'requestForm'])->name('member.password.request');
Route::post('/lupa-password', [MemberPasswordController::class, 'sendLink'])->middleware('throttle:6,1')->name('member.password.email');
Route::get('/reset-password/{token}', [MemberPasswordController::class, 'resetForm'])->name('password.reset');
Route::post('/reset-password', [MemberPasswordController::class, 'update'])->middleware('throttle:6,1')->name('member.password.update');
```

In `resources/views/member/login.blade.php`: change the lupa-password link to `{{ route('member.password.request') }}`.

- [ ] **Step 5: Create the two views**

Create `resources/views/member/forgot-password.blade.php`:

```blade
<x-layouts.public title="Lupa Kata Sandi"
    description="Atur ulang kata sandi akun Kheedma Academy kamu.">

    @php
        $field = 'mt-1.5 w-full rounded-lg bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';
    @endphp

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-md px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Akun</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900">Lupa kata sandi?</h1>
                <p class="mx-auto mt-4 text-sm leading-relaxed text-teal-800/70">
                    Masukkan emailmu. Kami kirimkan tautan untuk mengatur ulang kata sandi.
                </p>
            </div>

            @if (session('status'))
                <div class="mt-8 rounded-xl border border-teal-600/30 bg-teal-50 px-5 py-4 text-sm text-teal-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('member.password.email') }}" class="mt-10 space-y-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-teal-800">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required
                           class="{{ $field }} @error('email') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="kamu@email.com">
                    @error('email') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-full bg-teal-700 px-7 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-teal-800">
                    Kirim Tautan
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-teal-800/60">
                Ingat kata sandimu? <a href="{{ route('member.login') }}" class="font-semibold text-teal-700 hover:text-orange-600">Masuk</a>
            </p>
        </div>
    </section>

</x-layouts.public>
```

Create `resources/views/member/reset-password.blade.php`:

```blade
<x-layouts.public title="Atur Ulang Kata Sandi"
    description="Atur ulang kata sandi akun Kheedma Academy kamu.">

    @php
        $field = 'mt-1.5 w-full rounded-lg bg-white px-3.5 py-2.5 text-sm text-teal-900 outline-none transition placeholder:text-teal-900/30 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';
    @endphp

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-md px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Akun</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900">Atur ulang kata sandi.</h1>
            </div>

            <form method="POST" action="{{ route('member.password.update') }}" class="mt-10 space-y-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="block text-sm font-medium text-teal-800">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required
                           class="{{ $field }} @error('email') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="kamu@email.com">
                    @error('email') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-teal-800">Kata sandi baru <span class="text-teal-800/50">(minimal 8 karakter)</span></label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required
                           class="{{ $field }} @error('password') border border-red-400 @else border border-teal-900/15 @enderror"
                           placeholder="••••••••">
                    @error('password') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-teal-800">Ulangi kata sandi baru</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                           class="{{ $field }} border border-teal-900/15" placeholder="••••••••">
                </div>

                <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-full bg-teal-700 px-7 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-teal-800">
                    Simpan Kata Sandi
                </button>
            </form>
        </div>
    </section>

</x-layouts.public>
```

- [ ] **Step 6: Run tests + full suite**

Run: `php artisan test --compact --filter=PasswordResetTest` then `php artisan test --compact`
Expected: all PASS. (Local mail driver is `log`; Notification::fake covers the send assertions.)

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/ResetPasswordNotification.php app/Http/Controllers/MemberPasswordController.php app/Models/User.php resources/views/member/forgot-password.blade.php resources/views/member/reset-password.blade.php resources/views/member/login.blade.php routes/web.php tests/Feature/PasswordResetTest.php
git commit -m "feat: member password reset via email (SMTP-ready)"
```

---

### Task 5: Admin community members list

**Files:**
- Create: `app/Http/Controllers/Api/Admin/CommunityMemberController.php`
- Modify: `routes/api.php`
- Modify: `resources/js/admin/api.js`, `router.js`, `components/AppShell.vue`
- Create: `resources/js/admin/views/Community.vue`
- Test: `tests/Feature/CommunityAdminTest.php`

**Interfaces:**
- Consumes: `CommunityMembership` + `community.view` (Task 1).
- Produces: `GET /api/admin/community-members` (paginated, `q` search on person name/phone/email) behind `permission:community.view`. Row: `{ id, joined_at, referral_source, person: {id,name,phone,email} }`. Admin screen `/admin/community` (route name `community`, nav "Komunitas", icon `HeartHandshake`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CommunityAdminTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CommunityMembership;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function member(string $name, string $phone): CommunityMembership
    {
        $person = Person::create([
            'name' => $name, 'phone' => $phone, 'email' => str_replace('+', '', $phone).'@example.test',
        ]);

        return CommunityMembership::create(['people_id' => $person->id, 'referral_source' => 'teman']);
    }

    public function test_admin_can_list_and_search_members(): void
    {
        $this->member('Ahmad Fauzi', '+628111111100');
        $this->member('Budi Santoso', '+628222222200');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/community-members')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin)
            ->getJson('/api/admin/community-members?q=Ahmad')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.person.name', 'Ahmad Fauzi')
            ->assertJsonPath('data.0.referral_source', 'teman');
    }

    public function test_mentor_is_forbidden(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($mentor)->getJson('/api/admin/community-members')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=CommunityAdminTest`
Expected: FAIL — 404.

- [ ] **Step 3: Controller + route**

Create `app/Http/Controllers/Api/Admin/CommunityMemberController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityMemberController extends Controller
{
    /** Paginated, searchable list of community members (newest join first). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $members = CommunityMembership::query()
            ->with('person:id,name,phone,email')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->whereHas('person', function ($p) use ($term) {
                    $p->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CommunityMembership $m) => [
                'id' => $m->id,
                'joined_at' => $m->created_at?->toIso8601String(),
                'referral_source' => $m->referral_source,
                'person' => [
                    'id' => $m->person->id,
                    'name' => $m->person->name,
                    'phone' => $m->person->phone,
                    'email' => $m->person->email,
                ],
            ]);

        return response()->json($members);
    }
}
```

In `routes/api.php`: add import `use App\Http\Controllers\Api\Admin\CommunityMemberController;` and inside the admin prefix group:

```php
        Route::get('/community-members', [CommunityMemberController::class, 'index'])->middleware('permission:community.view');
```

- [ ] **Step 4: Admin screen**

In `resources/js/admin/api.js`, add after the `programs` export:

```js
export const communityMembers = {
    list(query = '') {
        return api(`/admin/community-members${query}`);
    },
};
```

In `resources/js/admin/router.js` children (after `programs`):

```js
            {
                path: 'community',
                name: 'community',
                component: () => import('./views/Community.vue'),
                meta: { permission: 'community.view' },
            },
```

In `resources/js/admin/components/AppShell.vue`: add `HeartHandshake` to the lucide import and a nav item after "Program":

```js
        { to: { name: 'community' }, label: 'Komunitas', icon: HeartHandshake, show: auth.can('community.view') },
```

Create `resources/js/admin/views/Community.vue`:

```vue
<script setup>
import { ref, watch, onMounted } from 'vue';
import { communityMembers as communityApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const items = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const q = ref('');
const loading = ref(false);
const error = ref('');

let debounce;

const REFERRAL_LABELS = {
    instagram: 'Instagram',
    tiktok: 'TikTok',
    whatsapp: 'WhatsApp',
    teman: 'Teman/keluarga',
    google: 'Google',
    lainnya: 'Lainnya',
};

async function fetchPage(page = 1) {
    loading.value = true;
    error.value = '';
    const params = new URLSearchParams();
    if (q.value) params.set('q', q.value);
    params.set('page', page);
    try {
        const res = await communityApi.list(`?${params.toString()}`);
        items.value = res.data;
        meta.value = { current_page: res.current_page, last_page: res.last_page, total: res.total };
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal memuat data.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => fetchPage());
watch(q, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => fetchPage(1), 300);
});

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <div class="mx-auto max-w-5xl">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Komunitas</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Anggota Komunitas</h1>
            </div>
            <span class="text-sm text-muted-foreground">{{ meta.total }} anggota</span>
        </div>

        <div class="mt-6">
            <Input v-model="q" placeholder="Cari nama, HP, atau email…" class="sm:max-w-xs" />
        </div>

        <div v-if="error" class="mt-4 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>

        <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold">Kontak</th>
                        <th class="px-4 py-3 font-semibold">Sumber</th>
                        <th class="px-4 py-3 font-semibold">Bergabung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="4" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td></tr>
                    <tr v-else-if="!items.length"><td colspan="4" class="px-4 py-10 text-center text-muted-foreground">Belum ada anggota.</td></tr>
                    <tr v-for="member in items" :key="member.id" class="border-b border-border last:border-0">
                        <td class="px-4 py-3 font-medium text-foreground">{{ member.person.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <div>{{ member.person.phone }}</div>
                            <div class="text-xs">{{ member.person.email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <Badge variant="secondary">{{ REFERRAL_LABELS[member.referral_source] ?? member.referral_source ?? '—' }}</Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ fmtDate(member.joined_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex items-center justify-between text-sm text-muted-foreground">
            <span>Halaman {{ meta.current_page }} dari {{ meta.last_page }}</span>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="meta.current_page <= 1" @click="fetchPage(meta.current_page - 1)">Sebelumnya</Button>
                <Button variant="outline" size="sm" :disabled="meta.current_page >= meta.last_page" @click="fetchPage(meta.current_page + 1)">Berikutnya</Button>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 5: Run tests + build + full suite**

Run: `php artisan test --compact --filter=CommunityAdminTest`, `npm run build`, then `php artisan test --compact`
Expected: all PASS, build green.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/CommunityMemberController.php routes/api.php resources/js/admin/api.js resources/js/admin/router.js resources/js/admin/components/AppShell.vue resources/js/admin/views/Community.vue tests/Feature/CommunityAdminTest.php
git commit -m "feat: admin community members list"
```

---

## Self-Review

**Spec coverage (Phase 2 scope):** CommunityMembership entity → Task 1. Participant account at join + identity rule (Person by phone, `people.user_id` link) → Task 2. Member login + minimal area → Task 3. Guest redirect (web → member login, API stays 401 JSON) → Task 3. Password reset (email, Indonesian, enumeration-safe, SMTP-ready) → Task 4. `community.view` + admin list → Tasks 1, 5. Public layout Masuk/Akun nav → Task 3. `/komunitas` links from Phase 1 chooser/landing become live → Task 2 (no changes needed there).

**Placeholder scan:** none; two forward-reference notes (member.login route in Task 2's view, member.password.request in Task 3's view) carry explicit implementer instructions (plain `url()` first, named route upgraded by the later task).

**Type consistency:** `member.area`/`member.login`/`password.reset` route names consistent across Tasks 2-4; `CommunityMembership` fillable matches controller usage; `communityMembers.list(query)` (Task 5 api.js) matches Community.vue usage; row shape `{id, joined_at, referral_source, person{...}}` consistent between controller and Vue template; `User::person()` HasOne consumed in Task 3 (verified to exist in the model).

**Deploy notes (carry to PR):** re-run `PermissionSeeder` (`community.view`); production `.env` needs real SMTP (`MAIL_MAILER=smtp`, host/port/credentials, proper `MAIL_FROM_ADDRESS`) — until then reset emails go to the log; Phase 1+2 deploy together (the `/komunitas` links).
