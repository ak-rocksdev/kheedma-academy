# Class Logistics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Class start becomes a datetime that auto-closes registration; cohorts get a type (offline with Google-Places-picked location / online with editable meeting link) and a single materials URL, surfaced to enrolled members.

**Architecture:** Minimal-change per PO decision: `cohorts.start_date` column converts date→datetime keeping its name; the start moment becomes a hard ceiling in `Cohort::isOpenForRegistration()` and its scope while `registration_closes_at` stays as the optional earlier cutoff. New nullable cohort columns carry type/location/meeting/materials. Google Places autocomplete is ADMIN-ONLY (lazy-loaded on the cohort form); members see address text + a Google Maps link built from lat/lng (zero API calls).

**Tech Stack:** Laravel 13 (PHPUnit 12), Vue 3 plain-JS `<script setup>`, shadcn-vue, Google Maps JS API (places library, admin only).

**Confirmed decisions (2026-07-17):** start ceiling + manual close coexist; data lives on Cohort; Places admin-only with user's API key; `materials_url` on cohort, visible only to enrolled members.

## Global Constraints

- Code 100% English; UI copy 100% Indonesian ("kamu" register, no em-dashes).
- REQUIRED skills per task: PHP → `laravel-best-practices`; Vue/UI → `vue-best-practices` + `frontend-design:frontend-design`; karpathy-guidelines always.
- API key: already in `.env` as `GOOGLE_MAPS_API_KEY` (never commit `.env`; `.env.example` carries the empty name). Backend reads it ONLY via `config('services.google_maps.key')`.
- No new composer/npm dependencies. Google Maps JS loads at runtime via script tag on demand (admin cohort form only).
- After PHP changes: `vendor/bin/pint --dirty --format agent`. TDD everywhere tests are named.
- Branch: `feat/class-logistics` (stacked on feat/content-sections — do not merge/rebase anything).
- `start_date` KEEPS its column name (minimal churn); only its type/cast/inputs change. `end_date` stays a plain date.
- Datetime inputs use native `<input type="datetime-local">` (the semantically right element) styled like the existing `Input` component.

---

### Task 1: Schema + model — datetime start ceiling, logistics columns

**Files:**
- Create: migration `add_logistics_to_cohorts_table` (also converts start_date)
- Modify: `app/Models/Cohort.php`, `database/factories/CohortFactory.php`
- Test: `tests/Feature/CohortModelTest.php` (new; sibling style from ProgramModelTest)

**Interfaces:**
- Produces columns on `cohorts`: `start_date` DATETIME (was date); `type` string default `'offline'`; `location_name` string nullable; `location_address` string nullable; `location_lat` decimal(10,7) nullable; `location_lng` decimal(10,7) nullable; `meeting_url` string nullable; `materials_url` string nullable.
- Model: casts `start_date => 'datetime'`, `location_lat`/`location_lng` => 'float'; all new columns fillable; `isOnline(): bool`; `mapsUrl(): ?string` returning `https://www.google.com/maps/search/?api=1&query={lat},{lng}` when both coords set, else null.
- Registration ceiling: `isOpenForRegistration()` returns false when `start_date` is set and past; `scopeOpenForRegistration` gains the matching `where(start_date null OR > now())`.
- `status` attribute: compare `start_date` against `now()` (not `startOfDay`) since it now carries a time; `end_date` comparison unchanged.
- Factory: default stays offline with Jakarta-ish coords state `locatedAt()`? NO — keep default minimal (type offline, no location); add states `online()` (type online + meeting_url) and `atLocation()` (name/address/lat/lng filled).

- [ ] **Step 1: Write the failing tests**

```bash
php artisan make:test --phpunit CohortModelTest --no-interaction
```

```php
<?php

namespace Tests\Feature;

use App\Models\Cohort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CohortModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_closes_when_class_start_time_passes(): void
    {
        $cohort = Cohort::factory()->create([
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'start_date' => now()->subMinute(),
        ]);

        $this->assertFalse($cohort->isOpenForRegistration());
        $this->assertSame(0, Cohort::openForRegistration()->count());
    }

    public function test_registration_open_before_class_starts(): void
    {
        $cohort = Cohort::factory()->create([
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDays(2),
            'start_date' => now()->addHour(),
        ]);

        $this->assertTrue($cohort->isOpenForRegistration());
        $this->assertSame(1, Cohort::openForRegistration()->count());
    }

    public function test_manual_close_still_cuts_earlier_than_start(): void
    {
        $cohort = Cohort::factory()->create([
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->subMinute(),
            'start_date' => now()->addDay(),
        ]);

        $this->assertFalse($cohort->isOpenForRegistration());
    }

    public function test_start_date_keeps_its_time(): void
    {
        $cohort = Cohort::factory()->create(['start_date' => '2026-08-01 09:30:00']);

        $this->assertSame('09:30', $cohort->fresh()->start_date->format('H:i'));
    }

    public function test_maps_url_requires_both_coordinates(): void
    {
        $located = Cohort::factory()->atLocation()->create();
        $bare = Cohort::factory()->create();

        $this->assertStringContainsString('google.com/maps', $located->mapsUrl());
        $this->assertNull($bare->mapsUrl());
    }

    public function test_is_online_by_type(): void
    {
        $this->assertTrue(Cohort::factory()->online()->create()->isOnline());
        $this->assertFalse(Cohort::factory()->create()->isOnline());
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact tests/Feature/CohortModelTest.php`
Expected: FAIL (missing columns/factory states/methods).

- [ ] **Step 3: Implement**

```bash
php artisan make:migration add_logistics_to_cohorts_table --no-interaction
```

Migration `up()` (one concern: cohort logistics; the start_date type change is part of it):

```php
Schema::table('cohorts', function (Blueprint $table) {
    $table->dateTime('start_date')->nullable()->change();
    $table->string('type')->default('offline'); // 'offline' | 'online'
    $table->string('location_name')->nullable();
    $table->string('location_address')->nullable();
    $table->decimal('location_lat', 10, 7)->nullable();
    $table->decimal('location_lng', 10, 7)->nullable();
    $table->string('meeting_url')->nullable();
    $table->string('materials_url')->nullable();
});
```

`down()`: reverse (`date(...)->change()`, dropColumn the six).

`Cohort.php` changes:
- fillable += `type, location_name, location_address, location_lat, location_lng, meeting_url, materials_url`.
- casts: `'start_date' => 'datetime'`, `'location_lat' => 'float'`, `'location_lng' => 'float'`.
- `isOpenForRegistration()` gains, after the closes_at check:

```php
if ($this->start_date && $this->start_date->isPast()) {
    return false;
}
```

(PHPDoc: "The class start is a hard ceiling: once the class begins, registration is closed no matter the manual window.")
- `scopeOpenForRegistration` gains:

```php
->where(fn (Builder $q) => $q->whereNull('start_date')->orWhere('start_date', '>', now()))
```

- `status()` attribute: replace `$today = now()->startOfDay();` comparison for start with plain `now()` (`$this->start_date->isFuture()` → upcoming), keep end_date logic with startOfDay semantics:

```php
protected function status(): Attribute
{
    return Attribute::make(get: function (): string {
        if ($this->start_date && $this->start_date->isFuture()) {
            return 'upcoming';
        }
        if ($this->end_date && $this->end_date->lt(now()->startOfDay())) {
            return 'ended';
        }

        return $this->start_date ? 'active' : 'upcoming';
    });
}
```

`CohortFactory` — add states (check existing definition first, keep its defaults):

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

`isOnline()` / `mapsUrl()` on the model:

```php
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
```

- [ ] **Step 4: Run tests** — the new file, then `php artisan test --compact` full (status-attribute change touches existing cohort tests; fix regressions ONLY by aligning test data with datetime semantics, never by weakening the plafon).

- [ ] **Step 5: Pint + commit** — `feat: cohort logistics columns + start-time registration ceiling`

---

### Task 2: Cohort admin API — validation + payload + maps key plumbing

**Files:**
- Modify: `app/Http/Controllers/Api/Admin/CohortController.php`, `config/services.php`, `resources/views/admin.blade.php`
- Test: `tests/Feature/CohortManagementTest.php` (extend, follow existing style)

**Interfaces:**
- Consumes: Task 1 columns/casts.
- Produces validation in `CohortController::validated()` (extend the existing array):

```php
'type' => ['sometimes', 'required', Rule::in(['offline', 'online'])],
'location_name' => ['nullable', 'string', 'max:255'],
'location_address' => ['required_if:type,offline', 'nullable', 'string', 'max:500'],
'location_lat' => ['required_if:type,offline', 'nullable', 'numeric', 'between:-90,90'],
'location_lng' => ['required_if:type,offline', 'nullable', 'numeric', 'between:-180,180'],
'meeting_url' => ['nullable', 'url:https', 'max:500'],
'materials_url' => ['nullable', 'url:https', 'max:500'],
```

with Indonesian `required_if` messages (add a messages array to the validate call following the file's existing custom-message pattern): 'location_address.required_if' => 'Kelas offline butuh alamat lokasi.', 'location_lat.required_if' / 'location_lng.required_if' => 'Pilih titik lokasi dari pencarian tempat.'.
- Payload gains: `type, location_name, location_address, location_lat, location_lng, meeting_url, materials_url, maps_url` (from `$c->mapsUrl()`), and `start_date` switches `toDateString()` → `toIso8601String()`.
- `config/services.php` gains:

```php
'google_maps' => [
    'key' => env('GOOGLE_MAPS_API_KEY'),
],
```

- `resources/views/admin.blade.php` head gains (before @vite):

```blade
<meta name="google-maps-key" content="{{ config('services.google_maps.key') }}">
```

- Keep the existing date-only `23:59:59` append hack for `registration_closes_at` (legacy tolerance) — do not remove.

**Tests to add** (failing first): offline create without address/lat/lng → 422 with the three errors; online create with only meeting_url → 201; `meeting_url` `http://` (non-https) and garbage → 422; update can change `meeting_url` later → 200 and persisted; payload includes `type` + `maps_url` for a located cohort; `start_date` accepts `2026-08-01T09:30` (datetime-local format) and round-trips the time.

- [ ] Steps: failing tests → implement → file passes → full suite → pint → commit `feat: cohort logistics API validation + maps key plumbing`.

---

### Task 3: Admin SPA — cohort form logistics + datetime inputs + Places (admin-only)

**REQUIRED before coding:** `vue-best-practices` (core refs) + `frontend-design:frontend-design`.

**Files:**
- Create: `resources/js/admin/composables/useGooglePlaces.js`, `resources/js/admin/components/LocationPicker.vue`
- Modify: `resources/js/admin/components/CohortFormDialog.vue`, `resources/js/admin/views/CohortDetail.vue` (and `Cohorts.vue` list if it renders dates)

**Interfaces:**
- Consumes: payload/validation from Task 2; `<meta name="google-maps-key">`.
- `useGooglePlaces()` composable: lazy-loads the Maps JS bootstrap once per session (`key` read from the meta tag; `v=weekly`, `libraries=places` via `importLibrary('places')`), exposes `{ ready, error, attachAutocomplete }`. If the key meta is empty, `error` explains the manual-input fallback. VERIFY the current Google API surface in the browser: prefer `google.maps.places.PlaceAutocompleteElement` (new element); adapt if the installed API version differs. Selected place maps to `{ name: displayName, address: formattedAddress, lat, lng }`.
- `LocationPicker.vue`: props `{ name, address, lat, lng }` via one `v-model` object (`defineModel`), renders the autocomplete input (or plain address input fallback when no key), read-only resolved summary (name, address, coords), and a "Ubah manual" toggle exposing the raw address/lat/lng inputs for keyless environments.
- `CohortFormDialog.vue`:
  - `start_date` and both registration fields become `<input type="datetime-local">` (reuse `Input` with `type` attr if it forwards attrs — check; else native input with the Input's classes). Seed values from ISO payload → `datetime-local` format (`YYYY-MM-DDTHH:mm`) with a small helper; submit as-is (backend accepts it).
  - New "Tipe kelas" ToggleGroup: `offline` → "Offline (tatap muka)", `online` → "Online".
  - offline → `<LocationPicker v-model="form.location" />`; online → `meeting_url` Input (label "Link meeting (Google Meet / Zoom)", hint "Opsional. Bisa kamu isi atau ubah kapan saja."); always → `materials_url` Input (label "Link materi (Google Drive)", hint "Opsional. Hanya terlihat oleh peserta yang terdaftar.").
  - Switching type does NOT clear the hidden side's values (server ignores what validation allows; user can toggle back without losing input).
- `CohortDetail.vue`: logistics card — type badge, location (name, address, "Lihat di Google Maps" link from `maps_url`) or meeting link, materials link; datetime display via the existing `fmtDate`-style helpers extended for time (check `lib/format.js` for an existing datetime formatter first).

**Verification:** `npm run build` clean; adaptation points named in the report (Input attr forwarding, Places element used, format helper reuse).

- [ ] Steps: implement → build → commit `feat: cohort form logistics with admin-only places autocomplete`.

---

### Task 4: Member + public surfaces

**Files:**
- Modify: `app/Http/Controllers/MemberAreaController.php`, `resources/views/member/akun.blade.php`, `resources/views/funnel/program.blade.php` (time display)
- Test: `tests/Feature/MemberAreaTest.php` (extend), `tests/Feature/ProgramDetailTest.php` or `ContentSectionPublicTest.php` sibling (time display assert)

**Interfaces:**
- Consumes: Task 1 model methods.
- Public program page: `Kelas dimulai {{ $openCohort->start_date->locale('id')->translatedFormat('j F Y') }}` gains time when present: `translatedFormat('j F Y · H.i') . ' WIB'` (check the exact existing blade string first; same change in `member/akun.blade.php` line ~96).
- Member area: where an ENROLLED member's cohort is rendered (explore `MemberAreaController` + `akun.blade.php` enrollment section first — follow its data shape), add a logistics block:
  - offline: "Lokasi kelas:" + location_name (bold) + location_address + link "Lihat di Google Maps" (`$cohort->mapsUrl()`, target _blank rel noopener) — only when location data present.
  - online: "Kelas online" + link "Gabung meeting" when `meeting_url` set.
  - materials: "Materi kelas" link when `materials_url` set.
  - Copy warm Indonesian; nothing renders for members who are not enrolled in that cohort (the enrollment scoping already exists — reuse it, do not widen queries).
- Eager-load additions in MemberAreaController must include the new columns (it currently selects `cohort:id,name,start_date` — extend the column list as needed).

**Tests:** enrolled member sees location name + maps link (offline cohort) / meeting link (online cohort) / materials link; a member NOT enrolled in that cohort sees none of them; program page shows "pukul"-style time (assert the formatted time string).

- [ ] Steps: failing tests → implement → file passes → full suite → pint → commit `feat: class logistics for enrolled members + start time on public page`.

---

### Task 5: Full verification

- [ ] `php artisan test --compact` (zero failures), `vendor/bin/pint --dirty --format agent`, `npm run build`.
- [ ] E2E walk on Herd (`http://kheedma-academy.test`, NOT artisan serve): admin edits a cohort → set start datetime (datetime-local picker appears), pick type offline → Places autocomplete resolves a real place (key present) → save; cohort detail shows location + maps link; set a start time in the past on a second cohort → its program page shows registration closed; member enrolled sees location/materials in /akun; switch cohort to online + meeting link → member sees "Gabung meeting". Screenshots as evidence.
- [ ] Deploy notes for the ledger: migrate (1 migration); GOOGLE_MAPS_API_KEY must be added to the VPS .env; advise referrer-restricting the key.
