# Registration Refinement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enrich both registration doors (program + community) with the legacy Google-Form intake fields (birth date, TikTok affiliate profile with conditional chaining, motivation), simplify the admin application review to a Status-only selector, and remove the prefilter/verdict surface AND columns entirely (returns later as a richer task feature).

**Architecture:** Profile-grade facts live on `Person` (refreshed on every touch): `birth_date`, `tiktok_followers`, `has_started_affiliate`, `affiliate_level`, `affiliate_gmv_range`. Per-registration intent lives on the registration record: `motivation` on BOTH `applications` and `community_memberships`. Conditional chain (user decision): TikTok username is OPTIONAL and gates the affiliate block — no username → followers/has-started/level/GMV cannot be filled (hidden, stored null); username present → followers + has-started required; has-started = "Sudah" → level + GMV required. `prefilter_*` columns are DROPPED (user decision). Admin review card keeps only Status as a ToggleGroup selector.

**Tech Stack:** PHP 8.4, Laravel 13, Blade + vanilla JS (public), Vue 3 (admin), PHPUnit 12. No new dependencies.

## Global Constraints

- PHP braces/return types/param hints; `vendor/bin/pint --dirty --format agent` before each backend commit. Tests PHPUnit; suites intentionally red between Tasks 1-2 are called out explicitly; FULL suite green from Task 2 on.
- Public copy: Indonesian promo language, no em-dashes, no internal terms. Admin copy Indonesian.
- Birth date: native `<input type="date">` on public Blade (the correct element for DOB; datepicker calendars are hostile for far-past dates), `max` = today, required both doors. DB `date` nullable (legacy rows), required by validation for new submissions. Admin shows derived age ("{n} tahun").
- Conditional chain exactly: `tiktok_username` optional → gates (`tiktok_followers` integer ≥0 + `has_started_affiliate` Sudah/Belum, both required_with) → `has_started_affiliate` true gates (`affiliate_level` 0-8 + `affiliate_gmv_range` in ['0-50','50-100','100+'], both required). When a gate is off, dependents are normalized to NULL server-side regardless of payload.
- `motivation`: required, string, max 1000, on both doors ("Kenapa kamu ingin ikut program ini?" / "Apa alasanmu ingin gabung komunitas?"). DB text nullable (legacy rows).
- Prefilter removal is TOTAL: migration drops `prefilter_submitted/link/verdict/note` from applications; every reference removed from models, controllers, requests, API rows, Vue, `lib/status.js`, and tests.
- Status selector: ToggleGroup `Menunggu | Diterima | Ditolak` on the PersonDetail review card (house pattern from Programs.vue; deselect-to-empty ignored).
- The logged-in confirmation card (apply flow) must DISPLAY the new profile facts and carry them as hidden inputs; "Ubah data dulu" opens the editable form including the new fields.

---

## File Structure

**Migrations (create):** `2026_07_07_300001_add_intake_profile_to_people_table.php` (5 columns), `2026_07_07_300002_add_motivation_to_registrations.php` (applications.motivation + community_memberships.motivation), `2026_07_07_300003_drop_prefilter_columns_from_applications.php`.
**Models:** `Person` (fillable/casts + `GMV_RANGES` const + `age` accessor), `Application` (drop prefilter fillable/casts; + motivation), `CommunityMembership` (+ motivation).
**Backend:** `StoreApplicationRequest`, `CommunityJoinRequest` (new rules + conditional normalization), `ApplicationController::store`, `CommunityController::join` (persist new fields), `ApplicantController` (strip prefilter from update/rows), `PersonController::show` (strip prefilter; add profile facts + application motivation).
**Public:** `funnel/apply.blade.php` (new fields + confirmation-card additions), `funnel/community.blade.php`, `resources/js/app.js` (conditional show/hide chain for both forms).
**Admin:** `views/PersonDetail.vue` (profile facts, Status ToggleGroup, motivation display, prefilter removal), `views/Applicants.vue` + `lib/status.js` (prefilter cleanup).
**Tests:** rework `PublicApplyTest`, `CommunityJoinTest`, `AccountAtApplicationTest`, `ApplicantProgramFilterTest`; extend for conditional-chain validation.

---

### Task 1: Schema + models (suites go red until Task 2 — expected)

**Files:**
- Create: the 3 migrations above
- Modify: `app/Models/Person.php`, `app/Models/Application.php`, `app/Models/CommunityMembership.php`
- Test: `tests/Feature/IntakeProfileModelTest.php` (new; the only green gate for this task)

**Interfaces:**
- Produces: `people.birth_date` (date null), `people.tiktok_followers` (unsignedInteger null), `people.has_started_affiliate` (boolean null), `people.affiliate_level` (unsignedTinyInteger null), `people.affiliate_gmv_range` (string null); `applications.motivation` + `community_memberships.motivation` (text null); applications loses the 4 prefilter columns. `Person::GMV_RANGES = ['0-50', '50-100', '100+']`; `Person::age` accessor (int|null, from birth_date); casts `birth_date => date`, `has_started_affiliate => boolean`.

- [ ] **Step 1: Failing test**

`tests/Feature/IntakeProfileModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CommunityMembership;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IntakeProfileModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_carries_the_intake_profile(): void
    {
        $person = Person::create([
            'name' => 'Uji Profil', 'phone' => '+628123450010', 'email' => 'uji.profil@example.test',
            'birth_date' => '2000-01-15', 'tiktok_username' => 'ujiprofil',
            'tiktok_followers' => 1500, 'has_started_affiliate' => true,
            'affiliate_level' => 3, 'affiliate_gmv_range' => '0-50',
        ]);

        $fresh = $person->fresh();
        $this->assertSame('2000-01-15', $fresh->birth_date->toDateString());
        $this->assertIsInt($fresh->age);
        $this->assertTrue($fresh->has_started_affiliate);
        $this->assertSame(3, (int) $fresh->affiliate_level);
        $this->assertContains($fresh->affiliate_gmv_range, Person::GMV_RANGES);
    }

    public function test_registration_records_carry_motivation_and_prefilter_is_gone(): void
    {
        $person = Person::create([
            'name' => 'Uji Motivasi', 'phone' => '+628123450011', 'email' => 'uji.motivasi@example.test',
        ]);
        $application = Application::create([
            'people_id' => $person->id, 'status' => 'pending', 'motivation' => 'Ingin belajar dari nol.',
        ]);
        $membership = CommunityMembership::create([
            'people_id' => $person->id, 'motivation' => 'Cari teman seperjalanan.',
        ]);

        $this->assertSame('Ingin belajar dari nol.', $application->fresh()->motivation);
        $this->assertSame('Cari teman seperjalanan.', $membership->fresh()->motivation);
        $this->assertFalse(Schema::hasColumn('applications', 'prefilter_verdict'));
        $this->assertFalse(Schema::hasColumn('applications', 'prefilter_link'));
    }
}
```

- [ ] **Step 2: RED** — `php artisan test --compact --filter=IntakeProfileModelTest` fails (columns missing).

- [ ] **Step 3: Migrations**

`2026_07_07_300001_add_intake_profile_to_people_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Intake profile (from the legacy Google Form): refreshed on every
        // registration touch. The affiliate block is gated by tiktok_username
        // at the validation layer; columns are nullable for legacy rows.
        Schema::table('people', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('email');
            $table->unsignedInteger('tiktok_followers')->nullable()->after('tiktok_username');
            $table->boolean('has_started_affiliate')->nullable()->after('tiktok_followers');
            $table->unsignedTinyInteger('affiliate_level')->nullable()->after('has_started_affiliate');
            $table->string('affiliate_gmv_range', 10)->nullable()->after('affiliate_level');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'tiktok_followers', 'has_started_affiliate', 'affiliate_level', 'affiliate_gmv_range']);
        });
    }
};
```

`2026_07_07_300002_add_motivation_to_registrations.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-registration intent ("kenapa ingin ikut/gabung") — belongs to the
        // registration record, not the Person. Nullable for legacy rows.
        Schema::table('applications', function (Blueprint $table) {
            $table->text('motivation')->nullable()->after('referral_source');
        });
        Schema::table('community_memberships', function (Blueprint $table) {
            $table->text('motivation')->nullable()->after('referral_source');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('motivation');
        });
        Schema::table('community_memberships', function (Blueprint $table) {
            $table->dropColumn('motivation');
        });
    }
};
```

`2026_07_07_300003_drop_prefilter_columns_from_applications.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The pre-filter task verdict returns later as a richer feature; the
        // simple columns are removed to keep the reviewed structure honest.
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['prefilter_submitted', 'prefilter_link', 'prefilter_verdict', 'prefilter_note']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->boolean('prefilter_submitted')->default(false);
            $table->string('prefilter_link')->nullable();
            $table->string('prefilter_verdict')->nullable();
            $table->text('prefilter_note')->nullable();
        });
    }
};
```

- [ ] **Step 4: Models**

`Person`: fillable += `'birth_date', 'tiktok_followers', 'has_started_affiliate', 'affiliate_level', 'affiliate_gmv_range'` (place birth_date after email, the affiliate group after instagram_username); add:

```php
    /** Fixed choices for the GMV range selector. */
    public const GMV_RANGES = ['0-50', '50-100', '100+'];
```

casts (add a `casts()` method if none):

```php
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'has_started_affiliate' => 'boolean',
        ];
    }
```

and the accessor:

```php
    /** Derived age in years (null when birth_date is unknown). */
    protected function age(): Attribute
    {
        return Attribute::make(get: fn (): ?int => $this->birth_date?->age);
    }
```

(import `Illuminate\Database\Eloquent\Casts\Attribute`).

`Application`: REMOVE `'prefilter_submitted', 'prefilter_link', 'prefilter_verdict', 'prefilter_note'` from `$fillable` and the `prefilter_submitted` cast; ADD `'motivation',` after `'referral_source',`.

`CommunityMembership`: fillable += `'motivation',`.

- [ ] **Step 5: Migrate + focused GREEN**

`php artisan migrate` then `php artisan test --compact --filter=IntakeProfileModelTest` → PASS. (PublicApply/CommunityJoin/AccountAtApplication/ApplicantProgramFilter now RED — expected until Task 2. Do NOT run the full suite as a gate here.)

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_07_3000*.php app/Models/Person.php app/Models/Application.php app/Models/CommunityMembership.php tests/Feature/IntakeProfileModelTest.php
git commit -m "feat: intake profile schema (birth date, affiliate chain, motivation); drop prefilter columns"
```

---

### Task 2: Backend — validation chain, persistence, prefilter strip (full suite green again)

**Files:**
- Modify: `app/Http/Requests/StoreApplicationRequest.php`, `app/Http/Requests/CommunityJoinRequest.php`, `app/Http/Controllers/ApplicationController.php`, `app/Http/Controllers/CommunityController.php`, `app/Http/Controllers/Api/Admin/ApplicantController.php`, `app/Http/Controllers/Api/Admin/PersonController.php`
- Test: extend/rework `PublicApplyTest`, `CommunityJoinTest`, `AccountAtApplicationTest`, `ApplicantProgramFilterTest`

**Interfaces:**
- Produces the SHARED rule set (identical on both requests) — extract nothing yet, duplicate consciously (two files, one shape):

```php
            'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'motivation' => ['required', 'string', 'max:1000'],
            'tiktok_username' => ['nullable', 'string', 'max:64'],
            'tiktok_followers' => ['nullable', 'required_with:tiktok_username', 'integer', 'min:0', 'max:1000000000'],
            'has_started_affiliate' => ['nullable', 'required_with:tiktok_username', 'boolean'],
            'affiliate_level' => ['nullable', 'required_if:has_started_affiliate,1', 'integer', 'min:0', 'max:8'],
            'affiliate_gmv_range' => ['nullable', 'required_if:has_started_affiliate,1', Rule::in(Person::GMV_RANGES)],
```

plus a `prepareForValidation` normalization in BOTH requests (after the existing phone merge): when `tiktok_username` empty → force `tiktok_followers`, `has_started_affiliate`, `affiliate_level`, `affiliate_gmv_range` to null; when `has_started_affiliate` falsy → force `affiliate_level`, `affiliate_gmv_range` to null. Indonesian messages for every new rule (birth_date.required "Tanggal lahir wajib diisi.", birth_date.before "Tanggal lahir tidak valid.", motivation.required per-door wording, tiktok_followers.required_with "Isi jumlah followers TikTok-mu.", has_started_affiliate.required_with "Beritahu kami apakah kamu sudah memulai affiliate.", affiliate_level.required_if "Pilih level affiliate-mu.", affiliate_gmv_range.required_if "Pilih rentang GMV-mu."). Attributes map accordingly.

- Controllers persist the new person fields in the SAME update/create blocks that already write tiktok/instagram (`birth_date`, `tiktok_followers`, `has_started_affiliate`, `affiliate_level`, `affiliate_gmv_range` — read from `$data` with `?? null`) and add `'motivation' => $data['motivation']` to the application create / membership firstOrCreate payloads.
- `ApplicantController::update` validation drops the 4 prefilter rules (leaving ONLY `status`); `row()` drops `prefilter_submitted`/`prefilter_verdict`.
- `PersonController::show` application rows drop the 4 prefilter fields, gain `'motivation' => $a->motivation`; the person payload gains `birth_date` (ISO date), `age`, `tiktok_followers`, `has_started_affiliate`, `affiliate_level`, `affiliate_gmv_range`.

**Test rework (the bulk):** every `validPayload()`/`guestPayload()` gains `'birth_date' => '2000-01-15', 'motivation' => 'Ingin serius belajar affiliate.'`. New validation tests (in PublicApplyTest AND CommunityJoinTest): birth_date required; followers required when tiktok filled; level+gmv required when has_started=1; dependents NULLED server-side when tiktok empty despite payload (assert stored null). AccountAtApplicationTest: payloads + the confirmation-card test asserts the new facts appear (after Task 3 adds them — keep assertions to fields, run order note: Task 3 finishes the card; in THIS task only payload fixes + API-level asserts). ApplicantProgramFilterTest: remove any prefilter assertions; person-detail test gains `assertJsonPath('person.applications.0.motivation', ...)`.

Gate: `php artisan test --compact` FULL suite green. Pint. Commit `feat: intake validation chain and persistence on both doors; prefilter API removed`.

---

### Task 3: Public forms + conditional JS + confirmation card

**Files:**
- Modify: `resources/views/funnel/apply.blade.php`, `resources/views/funnel/community.blade.php`, `resources/js/app.js`

**Blade additions (both forms, after the email field, house `$field` style):**
1. `Tanggal lahir` — `<input type="date" id="birth_date" name="birth_date" max="{{ now()->toDateString() }}" value="{{ old('birth_date', $person?->birth_date?->toDateString()) }}">` (community form has no $person — plain old()). Label + error line.
2. TikTok block: keep existing `tiktok_username` input, labeled "Akun TikTok (opsional)". Wrap the dependents in `<div data-tiktok-dependents class="hidden">`:
   - `Jumlah followers TikTok` — number input min 0.
   - `Sudah mulai affiliate TikTok?` — two-option radio-pills (native radios styled as pills, values 1/0, house style) with `data-affiliate-started`.
   - `<div data-affiliate-dependents class="hidden">`: `Level affiliate` native select 0-8 + `GMV affiliate TikTok` select (`0-50 Juta`, `50-100 Juta`, `Di atas 100 Juta` → values `0-50`,`50-100`,`100+`).
3. `motivation` textarea (rows 3, per-door label: program "Kenapa kamu ingin ikut program ini?" / community "Apa alasanmu ingin gabung komunitas ini?"), required, error line.
4. Apply confirmation card (`$confirming` branch): add hidden inputs for all new fields (from $person; `has_started_affiliate` as `{{ $person->has_started_affiliate ? 1 : 0 }}` only when not null, else empty) + display rows in the `<dl>`: Tanggal lahir (formatted `->locale('id')->translatedFormat('j F Y')` + "({{ $person->age }} tahun)"), Followers TikTok, Sudah affiliate (Sudah/Belum), Level, GMV — each row only when the value is not null. Motivation stays a VISIBLE textarea on the confirmation card (per-registration answer, asked fresh every time) below the referral select.

**app.js — `initAffiliateChain()`** (runs on both forms):

```js
function initAffiliateChain() {
    const tiktok = document.getElementById('tiktok_username');
    const dependents = document.querySelector('[data-tiktok-dependents]');
    if (!tiktok || !dependents) return;
    const affiliateDependents = document.querySelector('[data-affiliate-dependents]');

    function syncTiktok() {
        const has = tiktok.value.trim() !== '';
        dependents.classList.toggle('hidden', !has);
        if (!has && affiliateDependents) affiliateDependents.classList.add('hidden');
    }
    function syncStarted() {
        if (!affiliateDependents) return;
        const started = document.querySelector('input[name="has_started_affiliate"]:checked')?.value === '1';
        affiliateDependents.classList.toggle('hidden', !started);
    }
    tiktok.addEventListener('input', syncTiktok);
    document.querySelectorAll('input[name="has_started_affiliate"]').forEach((r) => r.addEventListener('change', syncStarted));
    syncTiktok();
    syncStarted();
}
```

(server normalization already nulls hidden dependents, so stale values are harmless; `hidden` fields still submit — acceptable because of that normalization). Call it alongside the other inits.

Gate: `npm run build` + full suite (Blade renders exercised by feature tests) + the AccountAtApplicationTest confirmation assertions from Task 2 now pass. Commit `feat: intake fields on both public forms with conditional affiliate chain`.

---

### Task 4: Admin — profile facts, Status selector, prefilter cleanup

**Files:**
- Modify: `resources/js/admin/views/PersonDetail.vue`, `resources/js/admin/views/Applicants.vue`, `resources/js/admin/lib/status.js`

**PersonDetail.vue:**
1. Profile card gains rows (each `—` when null): `Usia` ("{{ person.age }} tahun"), `Followers TikTok`, `Affiliate` ("Sudah · Level {{ level }} · GMV {{ gmv }} Juta-range label" or "Belum mulai"). Map GMV values to labels `{'0-50':'0-50 Juta','50-100':'50-100 Juta','100+':'Di atas 100 Juta'}`.
2. Riwayat card: REMOVE the verdict select + Simpan-only-on-verdict logic and every `prefilter` reference; ADD the motivation display (`<p class="text-sm italic ...">"{{ app.motivation }}"</p>` when present); REPLACE the status dropdown with a ToggleGroup selector (import ToggleGroup/ToggleGroupItem; options from `APPLICATION_STATUSES`; deselect-guard like Programs.vue `setStatus`) + keep the existing Simpan button posting `{ status: app.status }` only.
3. `save(app)` payload drops `prefilter_verdict`.

**Applicants.vue + status.js:** remove `PREFILTER_VERDICTS` export and any import/usage of prefilter fields (grep `prefilter` across `resources/js` must return empty).

Gate: `npm run build`, FULL suite, grep `prefilter` in `resources/js` and `app/` returns nothing (except migrations). Commit `feat: admin review simplified to status selector; intake profile shown on person detail`.

---

## Self-Review

**Decisions honored:** birth date (not age) with native date element; TikTok optional GATING followers/has-started, which gates level/GMV; server-side null normalization (chain cannot be bypassed); motivation per registration record on both doors; prefilter columns DROPPED (Task 1 migration) and every surface reference removed (Task 4 grep gate); Status becomes a ToggleGroup selector.
**Sequencing:** suites red between Task 1 and Task 2 called out; full-suite gates at Tasks 2-4.
**Type consistency:** `Person::GMV_RANGES` (T1) used by requests (T2) and mapped to labels in Vue (T4) and Blade selects (T3); `person.age` accessor (T1) consumed by PersonController payload (T2), confirmation card (T3), PersonDetail (T4); `data-tiktok-dependents`/`data-affiliate-dependents` contract between Blade (T3) and app.js (T3).
**Deploy notes:** three migrations (one DESTRUCTIVE: prefilter columns — dev-only data, approved); no seeder/permission changes.
