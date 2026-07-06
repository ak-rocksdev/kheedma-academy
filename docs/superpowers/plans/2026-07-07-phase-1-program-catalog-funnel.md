# Phase 1 — Program Catalog + Funnel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce the Program entity (catalog item with slug + status), an admin Programs module, and rebuild the public funnel so every application targets a program via `/program/{slug}` landing pages and a `/daftar` chooser.

**Architecture:** Laravel 13; admin side follows the Tahap C pattern (permission-gated API controllers + Vue SPA screens). Public side stays server-rendered Blade: a chooser at `/daftar`, a promo landing per program, and the existing application form moved under `/program/{slug}/daftar` with the program pre-bound. Spec: `docs/superpowers/specs/2026-07-06-program-community-products-concept.md` (Phase 1 scope).

**Tech Stack:** PHP 8.4, Laravel 13, spatie/laravel-permission v8, Vue 3 `<script setup>`, Tailwind v4, Blade (public), PHPUnit 12.

## Global Constraints

- PHP: curly braces always; explicit return types + param type hints. Run `vendor/bin/pint --dirty --format agent` before each backend commit.
- Tests: PHPUnit only, `php artisan make:test`-style classes; feature tests seed `RoleSeeder` + `PermissionSeeder` in `setUp` (Spatie cache is flushed by the seeder).
- No new composer/npm dependencies. Admin UI reuses existing components (`Input`, `Badge`, `Button`, `Dialog`, native `<select>` + `selectClass`).
- Public copy: promotional Indonesian, no em-dashes, never the internal terms "Angkatan"/"cohort"/entity names. Admin UI copy: Indonesian, "Angkatan" (never "cohort").
- Program public URL scheme (verbatim from spec): `/program/{slug}` landing, `/program/{slug}/daftar` form. NOT `/daftar/{slug}` (collides with `/daftar/terima-kasih`, `/daftar/cities/{province}`).
- `applications.program_id`: nullable at DB level, required by validation for new submissions. `applications.referral_source`: new required form field (closes a Layer 1 gap).
- `cohorts.program_id`: nullable column; required on Angkatan create going forward; existing dev rows get fixed via UI (no code backfill).
- Program fields (verbatim from spec): `slug` (unique), `name`, `tagline`, `description`, `status` (`draft`/`active`/`inactive`), `registration_opens_at`/`registration_closes_at` (nullable), `selection_mode` (`selective`|`instant`; behavior lands in Phase 3 — Phase 1 only stores it).
- Public visibility rules: `draft` → 404 everywhere; `active` + within window (or no window) → open; `active` outside window or `inactive` → landing shows closed state + community invite, form routes redirect to landing, POST rejected.
- New permission: `programs.manage` (admin only; mentor gets nothing new).

---

## File Structure

**Backend — create:**
- `database/migrations/2026_07_07_000001_create_programs_table.php`
- `database/migrations/2026_07_07_000002_add_program_fields_to_applications_table.php` (program_id + referral_source)
- `database/migrations/2026_07_07_000003_add_program_id_to_cohorts_table.php`
- `app/Models/Program.php`, `database/factories/ProgramFactory.php`
- `app/Http/Controllers/Api/Admin/ProgramController.php` (admin CRUD)
- `app/Http/Controllers/ProgramPageController.php` (public: chooser + landing)
- Tests: `tests/Feature/ProgramManagementTest.php`, `PublicApplyTest.php`, `PublicCatalogTest.php`

**Backend — modify:**
- `database/seeders/PermissionSeeder.php` (+`programs.manage`)
- `app/Models/Application.php` (fillable, casts, `program()`, `REFERRAL_SOURCES`)
- `app/Models/Cohort.php` (fillable, `program()`)
- `app/Http/Controllers/ApplicationController.php` (program threading)
- `app/Http/Requests/StoreApplicationRequest.php` (+referral_source rule)
- `app/Http/Controllers/Api/Admin/CohortController.php` (program validation + row)
- `app/Http/Controllers/Api/Admin/ApplicantController.php` (program column + filter)
- `routes/web.php` (funnel routes), `routes/api.php` (admin programs routes)

**Frontend — create:** `resources/js/admin/views/Programs.vue`
**Frontend — modify:** `resources/js/admin/api.js`, `router.js`, `components/AppShell.vue`, `views/Cohorts.vue`, `views/Applicants.vue`

**Blade — create:** `resources/views/funnel/chooser.blade.php`, `resources/views/funnel/program.blade.php`
**Blade — move+edit:** `resources/views/daftar.blade.php` → `resources/views/funnel/apply.blade.php`

---

### Task 1: Program model, migration, factory, permission

**Files:**
- Create: `database/migrations/2026_07_07_000001_create_programs_table.php`
- Create: `app/Models/Program.php`
- Create: `database/factories/ProgramFactory.php`
- Modify: `database/seeders/PermissionSeeder.php`
- Test: `tests/Feature/ProgramModelTest.php`

**Interfaces:**
- Produces: `Program` model with `isOpen(): bool`, scope `openForRegistration()`, `cohorts()`/`applications()` HasMany; `ProgramFactory` with states `active()`, `inactive()`, `draft()`, `windowClosed()`; permission `programs.manage` seeded to admin.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ProgramModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Program;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_state_follows_status_and_window(): void
    {
        $open = Program::factory()->active()->create();
        $inactive = Program::factory()->inactive()->create();
        $draft = Program::factory()->draft()->create();
        $windowClosed = Program::factory()->windowClosed()->create();

        $this->assertTrue($open->isOpen());
        $this->assertFalse($inactive->isOpen());
        $this->assertFalse($draft->isOpen());
        $this->assertFalse($windowClosed->isOpen());

        $this->assertSame([$open->id], Program::openForRegistration()->pluck('id')->all());
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

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ProgramModelTest`
Expected: FAIL — class `App\Models\Program` not found.

- [ ] **Step 3: Create migration, model, factory; extend seeder**

Create `database/migrations/2026_07_07_000001_create_programs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Program — a sellable catalog item. The slug is the stable public URL
        // (/program/{slug}); batches (Angkatan/cohorts) and applications hang
        // off it. selection_mode is stored now, enforced in Phase 3.
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');          // draft | active | inactive
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->string('selection_mode')->default('selective'); // selective | instant
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
```

Create `app/Models/Program.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'tagline',
        'description',
        'status',
        'registration_opens_at',
        'registration_closes_at',
        'selection_mode',
    ];

    protected function casts(): array
    {
        return [
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
        ];
    }

    /** Route-model binding uses the slug (public URLs never expose ids). */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** Open for registration: active AND inside the window (when one is set). */
    public function isOpen(): bool
    {
        if ($this->status !== 'active') {
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

    /** Query counterpart of isOpen(), for the public chooser. */
    public function scopeOpenForRegistration(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where(fn (Builder $q) => $q->whereNull('registration_opens_at')->orWhere('registration_opens_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('registration_closes_at')->orWhere('registration_closes_at', '>=', now()));
    }
}
```

Create `database/factories/ProgramFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Program '.fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'tagline' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => 'draft',
            'registration_opens_at' => null,
            'registration_closes_at' => null,
            'selection_mode' => 'selective',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    /** Active but its registration window has already closed. */
    public function windowClosed(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'registration_opens_at' => now()->subMonth(),
            'registration_closes_at' => now()->subDay(),
        ]);
    }
}
```

In `database/seeders/PermissionSeeder.php`, add `'programs.manage',` to the `$permissions` array (after `'users.manage',`). Admin already syncs the whole array; mentor mapping unchanged.

- [ ] **Step 4: Run migration + test to verify pass**

Run: `php artisan migrate` then `php artisan test --compact --filter=ProgramModelTest`
Expected: PASS (both tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_07_000001_create_programs_table.php app/Models/Program.php database/factories/ProgramFactory.php database/seeders/PermissionSeeder.php tests/Feature/ProgramModelTest.php
git commit -m "feat: Program entity with open-state logic and programs.manage permission"
```

---

### Task 2: Admin Program CRUD API

**Files:**
- Create: `app/Http/Controllers/Api/Admin/ProgramController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ProgramManagementTest.php`

**Interfaces:**
- Consumes: `Program` model + `programs.manage` (Task 1); `UserFactory::admin()/mentor()` states.
- Produces: `GET/POST /api/admin/programs`, `PATCH/DELETE /api/admin/programs/{program}` (id binding on the API — slug binding is public-only, so admin routes bind `{program}` by id via explicit `Route::model` NOT needed; use `{program:id}`). Row shape: `{ id, slug, name, tagline, status, selection_mode, registration_opens_at, registration_closes_at, is_open, cohorts_count, applications_count }`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ProgramManagementTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_create_a_program(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'Program Affiliate Pemula',
                'slug' => 'affiliate-pemula',
                'tagline' => 'Dari nol jadi affiliator amanah.',
                'status' => 'active',
                'selection_mode' => 'selective',
            ])
            ->assertCreated()
            ->assertJsonPath('program.slug', 'affiliate-pemula')
            ->assertJsonPath('program.is_open', true);
    }

    public function test_slug_must_be_unique_and_kebab(): void
    {
        Program::factory()->create(['slug' => 'affiliate-pemula']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'X', 'slug' => 'affiliate-pemula', 'status' => 'draft', 'selection_mode' => 'selective',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/programs', [
                'name' => 'X', 'slug' => 'Bukan Slug!', 'status' => 'draft', 'selection_mode' => 'selective',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_mentor_is_forbidden(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($mentor)->getJson('/api/admin/programs')->assertForbidden();
    }

    public function test_program_with_applications_cannot_be_deleted(): void
    {
        $program = Program::factory()->create();
        $person = Person::create([
            'name' => 'Peserta Uji', 'phone' => '+628123456700', 'email' => 'uji@example.test',
        ]);
        Application::create(['people_id' => $person->id, 'status' => 'pending', 'program_id' => $program->id]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/programs/{$program->id}")
            ->assertStatus(422);
    }

    public function test_empty_program_can_be_deleted(): void
    {
        $program = Program::factory()->create();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/programs/{$program->id}")
            ->assertNoContent();
    }
}
```

Note: `test_program_with_applications_cannot_be_deleted` needs `applications.program_id`, which Task 4 adds. Mark it `$this->markTestSkipped('program_id lands in Task 4');` for now IF Task 4 has not run — but in this plan Task 4 runs later, so instead write the guard against BOTH relations and test only the cohorts guard here:

Replace that test with:

```php
    public function test_program_with_cohorts_cannot_be_deleted(): void
    {
        $program = Program::factory()->create();
        \Illuminate\Support\Facades\DB::table('cohorts')->insert([
            'name' => 'Angkatan 1', 'program_id' => $program->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/programs/{$program->id}")
            ->assertStatus(422);
    }
```

…and note this test also depends on `cohorts.program_id` (Task 4). Therefore: **Task 2 ships with the delete guard implemented for both relations but tests only `test_empty_program_can_be_deleted`; Task 4's test file extends coverage.** Remove both blocked tests from this task; Task 4 Step 6 adds them back.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ProgramManagementTest`
Expected: FAIL — 404 (no route).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Api/Admin/ProgramController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramController extends Controller
{
    /** Full catalog, newest first, with funnel counters. */
    public function index(): JsonResponse
    {
        $programs = Program::query()
            ->withCount(['cohorts', 'applications'])
            ->latest()
            ->get()
            ->map(fn (Program $p) => $this->row($p));

        return response()->json(['data' => $programs]);
    }

    public function store(Request $request): JsonResponse
    {
        $program = Program::create($this->validated($request));

        return response()->json([
            'program' => $this->row($program->loadCount(['cohorts', 'applications'])),
        ], 201);
    }

    public function update(Request $request, Program $program): JsonResponse
    {
        $program->update($this->validated($request, $program));

        return response()->json([
            'program' => $this->row($program->fresh()->loadCount(['cohorts', 'applications'])),
        ]);
    }

    /** Delete only when nothing hangs off the program yet. */
    public function destroy(Program $program): JsonResponse
    {
        if ($program->cohorts()->exists() || $program->applications()->exists()) {
            throw ValidationException::withMessages([
                'program' => 'Program dengan angkatan atau pendaftar tidak bisa dihapus. Nonaktifkan saja.',
            ]);
        }

        $program->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Program $program = null): array
    {
        $creating = $program === null;

        return $request->validate([
            'name' => $creating ? ['required', 'string', 'max:255'] : ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                $creating ? 'required' : 'sometimes',
                'required', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('programs', 'slug')->ignore($program?->id),
                Rule::notIn(['daftar', 'komunitas']),   // reserved public prefixes
            ],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => [$creating ? 'required' : 'sometimes', 'in:draft,active,inactive'],
            'registration_opens_at' => ['sometimes', 'nullable', 'date'],
            'registration_closes_at' => ['sometimes', 'nullable', 'date', 'after:registration_opens_at'],
            'selection_mode' => [$creating ? 'required' : 'sometimes', 'in:selective,instant'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Program $p): array
    {
        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'name' => $p->name,
            'tagline' => $p->tagline,
            'description' => $p->description,
            'status' => $p->status,
            'selection_mode' => $p->selection_mode,
            'registration_opens_at' => $p->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $p->registration_closes_at?->toIso8601String(),
            'is_open' => $p->isOpen(),
            'cohorts_count' => (int) ($p->cohorts_count ?? 0),
            'applications_count' => (int) ($p->applications_count ?? 0),
        ];
    }
}
```

- [ ] **Step 4: Register routes**

In `routes/api.php`, add the import `use App\Http\Controllers\Api\Admin\ProgramController;` and, inside the `Route::prefix('admin')` group, add:

```php
        Route::middleware('permission:programs.manage')->group(function () {
            Route::get('/programs', [ProgramController::class, 'index']);
            Route::post('/programs', [ProgramController::class, 'store']);
            Route::patch('/programs/{program:id}', [ProgramController::class, 'update']);
            Route::delete('/programs/{program:id}', [ProgramController::class, 'destroy']);
        });
```

(`{program:id}` — explicit id binding because the model's route key is `slug` for public URLs.)

- [ ] **Step 5: Run test to verify pass**

Run: `php artisan test --compact --filter=ProgramManagementTest`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/ProgramController.php routes/api.php tests/Feature/ProgramManagementTest.php
git commit -m "feat: admin program CRUD API"
```

---

### Task 3: Admin Programs screen

**Files:**
- Modify: `resources/js/admin/api.js` (add `programs` group after `cohorts`)
- Modify: `resources/js/admin/router.js`, `resources/js/admin/components/AppShell.vue`
- Create: `resources/js/admin/views/Programs.vue`

**Interfaces:**
- Consumes: `/api/admin/programs` endpoints (Task 2), `Dialog`, `auth.can`.
- Produces: `/admin/programs` screen; route name `programs` with `meta.permission: 'programs.manage'`; nav item "Program".

- [ ] **Step 1: api.js group**

Add to `resources/js/admin/api.js` after the `cohorts` export:

```js
export const programs = {
    list() {
        return api('/admin/programs');
    },
    create(payload) {
        return api('/admin/programs', { method: 'POST', body: payload });
    },
    update(id, payload) {
        return api(`/admin/programs/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/programs/${id}`, { method: 'DELETE' });
    },
};
```

- [ ] **Step 2: Route + nav**

In `resources/js/admin/router.js` children (before the `cohorts` entry):

```js
            {
                path: 'programs',
                name: 'programs',
                component: () => import('./views/Programs.vue'),
                meta: { permission: 'programs.manage' },
            },
```

In `resources/js/admin/components/AppShell.vue`: import `BookOpen` from `lucide-vue-next` (add to the existing import list) and insert a nav item between Pelamar and Angkatan:

```js
        { to: { name: 'programs' }, label: 'Program', icon: BookOpen, show: auth.can('programs.manage') },
```

- [ ] **Step 3: Create the view**

Create `resources/js/admin/views/Programs.vue`:

```vue
<script setup>
import { ref, onMounted } from 'vue';
import { programs as programsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

const items = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editing = ref(null);
const form = ref({ name: '', slug: '', tagline: '', description: '', status: 'draft', selection_mode: 'selective', registration_opens_at: '', registration_closes_at: '' });
const formErrors = ref({});
const saving = ref(false);

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const STATUS = {
    draft: { label: 'Draf', variant: 'secondary' },
    active: { label: 'Aktif', variant: 'success' },
    inactive: { label: 'Nonaktif', variant: 'destructive' },
};

function slugify(text) {
    return text.toLowerCase().trim().replace(/[^a-z0-9\s-]/g, '').replace(/[\s-]+/g, '-').replace(/^-|-$/g, '');
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await programsApi.list();
        items.value = res.data;
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal memuat data.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function openCreate() {
    editing.value = null;
    form.value = { name: '', slug: '', tagline: '', description: '', status: 'draft', selection_mode: 'selective', registration_opens_at: '', registration_closes_at: '' };
    formErrors.value = {};
    dialogOpen.value = true;
}

function openEdit(program) {
    editing.value = program;
    form.value = {
        name: program.name,
        slug: program.slug,
        tagline: program.tagline ?? '',
        description: program.description ?? '',
        status: program.status,
        selection_mode: program.selection_mode,
        registration_opens_at: program.registration_opens_at?.slice(0, 10) ?? '',
        registration_closes_at: program.registration_closes_at?.slice(0, 10) ?? '',
    };
    formErrors.value = {};
    dialogOpen.value = true;
}

function onNameInput() {
    // Auto-suggest the slug only while creating and before manual edits diverge.
    if (!editing.value && (!form.value.slug || form.value.slug === slugify(form.value.name.slice(0, -1)))) {
        form.value.slug = slugify(form.value.name);
    }
}

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = {
            name: form.value.name,
            slug: form.value.slug,
            tagline: form.value.tagline || null,
            description: form.value.description || null,
            status: form.value.status,
            selection_mode: form.value.selection_mode,
            registration_opens_at: form.value.registration_opens_at || null,
            registration_closes_at: form.value.registration_closes_at || null,
        };
        if (editing.value) {
            await programsApi.update(editing.value.id, payload);
        } else {
            await programsApi.create(payload);
        }
        dialogOpen.value = false;
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function remove(program) {
    error.value = '';
    try {
        await programsApi.remove(program.id);
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal menghapus program.';
    }
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Program</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Katalog Program</h1>
            </div>
            <Button variant="accent" size="sm" @click="openCreate">Tambah Program</Button>
        </div>

        <div v-if="error" class="mt-4 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>

        <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold">Slug</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Pendaftaran</th>
                        <th class="px-4 py-3 font-semibold">Angkatan</th>
                        <th class="px-4 py-3 font-semibold">Pendaftar</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td></tr>
                    <tr v-else-if="!items.length"><td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Belum ada program.</td></tr>
                    <tr v-for="program in items" :key="program.id" class="border-b border-border last:border-0">
                        <td class="px-4 py-3 font-medium text-foreground">{{ program.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground"><code class="text-xs">/program/{{ program.slug }}</code></td>
                        <td class="px-4 py-3">
                            <Badge :variant="STATUS[program.status]?.variant ?? 'secondary'">
                                {{ STATUS[program.status]?.label ?? program.status }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="program.is_open ? 'success' : 'secondary'">
                                {{ program.is_open ? 'Buka' : 'Tutup' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ program.cohorts_count }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ program.applications_count }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Button variant="ghost" size="sm" @click="openEdit(program)">Ubah</Button>
                            <Button variant="ghost" size="sm" @click="remove(program)">Hapus</Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="dialogOpen" :title="editing ? 'Ubah Program' : 'Tambah Program'">
            <form class="space-y-3" @submit.prevent="save">
                <div>
                    <Input v-model="form.name" placeholder="Nama program" @input="onNameInput" />
                    <p v-if="formErrors.name" class="mt-1 text-xs text-destructive">{{ formErrors.name[0] }}</p>
                </div>
                <div>
                    <Input v-model="form.slug" placeholder="slug-url" />
                    <p class="mt-1 text-xs text-muted-foreground">/program/{{ form.slug || '…' }}</p>
                    <p v-if="formErrors.slug" class="mt-1 text-xs text-destructive">{{ formErrors.slug[0] }}</p>
                </div>
                <Input v-model="form.tagline" placeholder="Tagline singkat (opsional)" />
                <div>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        placeholder="Deskripsi program (opsional)"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    ></textarea>
                    <p v-if="formErrors.description" class="mt-1 text-xs text-destructive">{{ formErrors.description[0] }}</p>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Status</label>
                        <select v-model="form.status" :class="selectClass">
                            <option value="draft">Draf</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Mode seleksi</label>
                        <select v-model="form.selection_mode" :class="selectClass">
                            <option value="selective">Selektif (dinilai admin)</option>
                            <option value="instant">Langsung masuk</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Pendaftaran dibuka</label>
                        <Input v-model="form.registration_opens_at" type="date" />
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Pendaftaran ditutup</label>
                        <Input v-model="form.registration_closes_at" type="date" />
                    </div>
                </div>
                <p v-if="formErrors.registration_closes_at" class="text-xs text-destructive">{{ formErrors.registration_closes_at[0] }}</p>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" size="sm" @click="dialogOpen = false">Batal</Button>
                    <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
```

- [ ] **Step 4: Build**

Run: `npm run build`
Expected: success, no Vue errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/admin/api.js resources/js/admin/router.js resources/js/admin/components/AppShell.vue resources/js/admin/views/Programs.vue
git commit -m "feat: admin program catalog screen"
```

---

### Task 4: Schema wiring — applications & cohorts gain program

**Files:**
- Create: `database/migrations/2026_07_07_000002_add_program_fields_to_applications_table.php`
- Create: `database/migrations/2026_07_07_000003_add_program_id_to_cohorts_table.php`
- Modify: `app/Models/Application.php`, `app/Models/Cohort.php`
- Test: extend `tests/Feature/ProgramManagementTest.php` (the two delete-guard tests deferred from Task 2)

**Interfaces:**
- Produces: `applications.program_id` (nullable FK, nullOnDelete) + `applications.referral_source` (nullable string); `cohorts.program_id` (nullable FK, nullOnDelete); `Application::program()` BelongsTo, `Application::REFERRAL_SOURCES` const; `Cohort::program()` BelongsTo. Both models' fillable extended.

- [ ] **Step 1: Create the migrations**

`database/migrations/2026_07_07_000002_add_program_fields_to_applications_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // program_id: nullable at the DB level (legacy rows predate programs)
        // but required by validation for every new submission.
        // referral_source: closes a Layer 1 gap — the v1 concept mandates
        // capturing how the applicant heard about the Academy.
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('people_id')->constrained('programs')->nullOnDelete();
            $table->string('referral_source')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn('referral_source');
        });
    }
};
```

`database/migrations/2026_07_07_000003_add_program_id_to_cohorts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable: pre-program Angkatan rows exist in dev. New Angkatan are
        // validated to carry a program; old rows get repointed via the UI.
        Schema::table('cohorts', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('id')->constrained('programs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });
    }
};
```

- [ ] **Step 2: Update the models**

`app/Models/Application.php` — add to `$fillable`: `'program_id', 'referral_source',` (after `'people_id',`); add the const and relation:

```php
    /** Fixed choices for the public form's "tahu dari mana" select. */
    public const REFERRAL_SOURCES = ['instagram', 'tiktok', 'whatsapp', 'teman', 'google', 'lainnya'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
```

`app/Models/Cohort.php` — add `'program_id',` to `$fillable` (before `'name',`) and:

```php
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
```

(`BelongsTo` is already imported in Cohort; check Application's imports and add `use Illuminate\Database\Eloquent\Relations\BelongsTo;` if missing — it is already there.)

- [ ] **Step 3: Extend the delete-guard tests**

Add to `tests/Feature/ProgramManagementTest.php`:

```php
    public function test_program_with_applications_cannot_be_deleted(): void
    {
        $program = Program::factory()->create();
        $person = Person::create([
            'name' => 'Peserta Uji', 'phone' => '+628123456700', 'email' => 'uji@example.test',
        ]);
        Application::create(['people_id' => $person->id, 'status' => 'pending', 'program_id' => $program->id]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/programs/{$program->id}")
            ->assertStatus(422);
    }

    public function test_program_with_cohorts_cannot_be_deleted(): void
    {
        $program = Program::factory()->create();
        Cohort::factory()->create(['program_id' => $program->id]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/programs/{$program->id}")
            ->assertStatus(422);
    }
```

Add imports `use App\Models\Application; use App\Models\Cohort; use App\Models\Person;` to the test file.

- [ ] **Step 4: Migrate + run tests**

Run: `php artisan migrate` then `php artisan test --compact --filter=ProgramManagementTest`
Expected: PASS (all, including the two new guards).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_07_000002_add_program_fields_to_applications_table.php database/migrations/2026_07_07_000003_add_program_id_to_cohorts_table.php app/Models/Application.php app/Models/Cohort.php tests/Feature/ProgramManagementTest.php
git commit -m "feat: applications and cohorts reference their program"
```

---

### Task 5: Public application form under /program/{slug}/daftar

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/ApplicationController.php`
- Modify: `app/Http/Requests/StoreApplicationRequest.php`
- Move+edit: `resources/views/daftar.blade.php` → `resources/views/funnel/apply.blade.php`
- Test: `tests/Feature/PublicApplyTest.php`

**Interfaces:**
- Consumes: `Program` (slug binding, `isOpen()`), `Application::REFERRAL_SOURCES`.
- Produces: routes `program.apply` (GET `/program/{program:slug}/daftar`), `program.apply.store` (POST same URL); `ApplicationController::create(Program $program)` / `store(StoreApplicationRequest $request, Program $program)`. Old `GET /daftar` route stays temporarily pointing at a stub until Task 6 replaces it with the chooser — to keep home CTAs working, keep `Route::view` removed only in Task 6; in THIS task `GET /daftar` redirects to home… **Simplification: this task immediately points `/daftar`'s closure to a redirect to the first open program or home; Task 6 replaces it with the real chooser.** Keep `daftar.thankyou` and `daftar.cities` routes unchanged.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PublicApplyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicApplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Minimal region fixtures (laravolt tables) for the address validation.
        DB::table('indonesia_provinces')->insert([
            'code' => '32', 'name' => 'JAWA BARAT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('indonesia_cities')->insert([
            'code' => '3273', 'province_code' => '32', 'name' => 'KOTA BANDUNG', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.test',
            'province_code' => '32',
            'city_code' => '3273',
            'referral_source' => 'instagram',
        ];
    }

    public function test_form_renders_for_open_program(): void
    {
        $program = Program::factory()->active()->create();

        $this->get("/program/{$program->slug}/daftar")
            ->assertOk()
            ->assertSee($program->name);
    }

    public function test_form_redirects_to_landing_when_closed(): void
    {
        $program = Program::factory()->inactive()->create();

        $this->get("/program/{$program->slug}/daftar")
            ->assertRedirect("/program/{$program->slug}");
    }

    public function test_draft_program_is_not_found(): void
    {
        $program = Program::factory()->draft()->create();

        $this->get("/program/{$program->slug}/daftar")->assertNotFound();
        $this->get("/program/{$program->slug}")->assertNotFound();
    }

    public function test_submission_links_program_and_referral_source(): void
    {
        $program = Program::factory()->active()->create();

        $this->post("/program/{$program->slug}/daftar", $this->validPayload())
            ->assertRedirect(route('daftar.thankyou'));

        $application = Application::sole();
        $this->assertSame($program->id, $application->program_id);
        $this->assertSame('instagram', $application->referral_source);
        $this->assertSame('pending', $application->status);
    }

    public function test_referral_source_is_required_and_validated(): void
    {
        $program = Program::factory()->active()->create();

        $this->from("/program/{$program->slug}/daftar")
            ->post("/program/{$program->slug}/daftar", [...$this->validPayload(), 'referral_source' => 'radio'])
            ->assertSessionHasErrors('referral_source');
    }

    public function test_submission_rejected_when_program_closed(): void
    {
        $program = Program::factory()->windowClosed()->create();

        $this->post("/program/{$program->slug}/daftar", $this->validPayload())
            ->assertRedirect("/program/{$program->slug}");

        $this->assertSame(0, Application::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PublicApplyTest`
Expected: FAIL — 404s (routes missing).

- [ ] **Step 3: Rewrite the routes**

In `routes/web.php`, replace the Layer 1 block with:

```php
/*
 | Public funnel. /daftar is the two-door chooser (Task 6); each program has
 | its own landing + application form under a stable slug URL.
 */
Route::controller(ApplicationController::class)->group(function () {
    Route::get('/daftar/terima-kasih', 'thankYou')->name('daftar.thankyou');
    Route::get('/daftar/cities/{province}', 'cities')->where('province', '[0-9]{2}')->name('daftar.cities');
    Route::get('/program/{program:slug}/daftar', 'create')->name('program.apply');
    Route::post('/program/{program:slug}/daftar', 'store')->middleware('throttle:10,1')->name('program.apply.store');
});

// Temporary until the chooser lands (Task 6): send /daftar to the first open
// program, or home when none is open.
Route::get('/daftar', function () {
    $program = \App\Models\Program::openForRegistration()->first();

    return $program
        ? redirect()->route('program.apply', $program)
        : redirect()->route('home');
})->name('daftar');
```

- [ ] **Step 4: Thread the program through the controller + request**

In `app/Http/Controllers/ApplicationController.php`:

Add import `use App\Models\Program;` and replace `create()` and `store()`:

```php
    /** Show the application form for one program (draft programs 404 via guard). */
    public function create(Program $program): View|RedirectResponse
    {
        abort_if($program->status === 'draft', 404);

        if (! $program->isOpen()) {
            return redirect()->route('program.show', $program);
        }

        $provinces = Provinsi::orderBy('name')->get(['code', 'name']);

        return view('funnel.apply', compact('program', 'provinces'));
    }
```

```php
    public function store(StoreApplicationRequest $request, Program $program): RedirectResponse
    {
        abort_if($program->status === 'draft', 404);

        if (! $program->isOpen()) {
            return redirect()->route('program.show', $program);
        }

        $data = $request->validated();

        $person = Person::updateOrCreate(
            ['phone' => $data['phone']],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'province_code' => $data['province_code'],
                'city_code' => $data['city_code'],
                'tiktok_username' => $data['tiktok_username'] ?? null,
                'instagram_username' => $data['instagram_username'] ?? null,
            ]
        );

        $person->applications()->create([
            'status' => 'pending',
            'program_id' => $program->id,
            'referral_source' => $data['referral_source'],
        ]);

        return redirect()
            ->route('daftar.thankyou')
            ->with('applicant_name', $person->name);
    }
```

NOTE: `route('program.show', ...)` is registered in Task 6. To keep THIS task green, register the landing route now as part of Step 3's block (it will get its controller in Task 6):

```php
    Route::get('/program/{program:slug}', [\App\Http\Controllers\ProgramPageController::class, 'show'])->name('program.show');
```

…and create a minimal `app/Http/Controllers/ProgramPageController.php` stub in this task:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\View\View;

class ProgramPageController extends Controller
{
    /** Program promo landing; Task 6 gives it its real view. */
    public function show(Program $program): View
    {
        abort_if($program->status === 'draft', 404);

        return view('funnel.program', ['program' => $program]);
    }
}
```

(The `funnel.program` view is created in Task 6; the redirect tests in this task only assert the redirect target URL, never render it.)

In `app/Http/Requests/StoreApplicationRequest.php`: add import `use App\Models\Application;`, then add to `rules()` after `instagram_username`:

```php
            'referral_source' => ['required', Rule::in(Application::REFERRAL_SOURCES)],
```

to `messages()`:

```php
            'referral_source.required' => 'Beritahu kami dari mana kamu tahu program ini.',
            'referral_source.in' => 'Pilihan sumber tidak valid.',
```

and to `attributes()`:

```php
            'referral_source' => 'sumber informasi',
```

- [ ] **Step 5: Move + edit the form view**

```bash
git mv resources/views/daftar.blade.php resources/views/funnel/apply.blade.php
```

Then edit `resources/views/funnel/apply.blade.php` — four surgical changes:

1. Layout header (replace the opening component tag + heading copy):

```blade
<x-layouts.public :title="'Daftar ' . $program->name"
    :description="'Formulir pendaftaran ' . $program->name . ' Kheedma Academy.'">
```

and inside the header block replace the eyebrow + h1 + intro paragraph:

```blade
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Pendaftaran</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900 sm:text-4xl">{{ $program->name }}</h1>
                <p class="mx-auto mt-4 max-w-lg text-base leading-relaxed text-teal-800/80">
                    {{ $program->tagline ?: 'Isi data di bawah ini. Setelah mendaftar, kamu akan menerima tugas pra-seleksi sebagai langkah menunjukkan kesungguhan.' }}
                </p>
```

2. Form action:

```blade
            <form method="POST" action="{{ route('program.apply.store', $program) }}" class="mt-10 space-y-6 rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
```

3. New referral field — insert AFTER the TikTok/Instagram grid `</div>` and BEFORE the submit `<div class="pt-2">`:

```blade
                <div>
                    <label for="referral_source" class="block text-sm font-medium text-teal-800">Tahu program ini dari mana?</label>
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
```

4. The old hard-coded sentence “Cohort 1 gratis, tempat terbatas.” must not survive (internal-term rule): covered by change 1's new intro copy.

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact --filter=PublicApplyTest` then the full suite `php artisan test --compact`
Expected: PublicApplyTest PASS; full suite green.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A routes/web.php app/Http/Controllers/ApplicationController.php app/Http/Controllers/ProgramPageController.php app/Http/Requests/StoreApplicationRequest.php resources/views/funnel/ tests/Feature/PublicApplyTest.php
git commit -m "feat: program-scoped public application form with referral source"
```

---

### Task 6: Chooser + program landing pages

**Files:**
- Modify: `app/Http/Controllers/ProgramPageController.php` (add `chooser()`, finish `show()`)
- Modify: `routes/web.php` (replace the temporary `/daftar` redirect)
- Create: `resources/views/funnel/chooser.blade.php`, `resources/views/funnel/program.blade.php`
- Test: `tests/Feature/PublicCatalogTest.php`

**Interfaces:**
- Consumes: `Program::openForRegistration()`, `isOpen()`; routes `program.show`, `program.apply` (Task 5).
- Produces: `GET /daftar` renders the chooser (name `daftar` kept — home CTAs untouched); `GET /program/{slug}` renders the landing with open/closed states. Community card links to `url('/komunitas')` (Phase 2 route; pages launch together).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PublicCatalogTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_chooser_lists_only_open_programs(): void
    {
        $open = Program::factory()->active()->create(['name' => 'Program Terbuka']);
        Program::factory()->inactive()->create(['name' => 'Program Tertutup']);
        Program::factory()->draft()->create(['name' => 'Program Draf']);
        Program::factory()->windowClosed()->create(['name' => 'Program Kedaluwarsa']);

        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Program Terbuka')
            ->assertDontSee('Program Tertutup')
            ->assertDontSee('Program Draf')
            ->assertDontSee('Program Kedaluwarsa')
            ->assertSee('Komunitas');
    }

    public function test_chooser_without_programs_still_offers_community(): void
    {
        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Komunitas');
    }

    public function test_landing_shows_cta_when_open(): void
    {
        $program = Program::factory()->active()->create();

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee($program->name)
            ->assertSee(route('program.apply', $program), false);
    }

    public function test_landing_shows_closed_state_when_inactive(): void
    {
        $program = Program::factory()->inactive()->create();

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('Pendaftaran ditutup')
            ->assertDontSee(route('program.apply', $program), false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PublicCatalogTest`
Expected: FAIL — `/daftar` currently redirects (302), landing view missing.

- [ ] **Step 3: Finish the controller + routes**

Replace `app/Http/Controllers/ProgramPageController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\View\View;

class ProgramPageController extends Controller
{
    /** Two-door chooser: open programs + the community invitation. */
    public function chooser(): View
    {
        $programs = Program::openForRegistration()->latest()->get();

        return view('funnel.chooser', compact('programs'));
    }

    /** Program promo landing. Open: CTA to the form. Closed: invite to community. */
    public function show(Program $program): View
    {
        abort_if($program->status === 'draft', 404);

        return view('funnel.program', ['program' => $program, 'isOpen' => $program->isOpen()]);
    }
}
```

In `routes/web.php`, delete the temporary `/daftar` closure route and register:

```php
Route::get('/daftar', [ProgramPageController::class, 'chooser'])->name('daftar');
```

(add import `use App\Http\Controllers\ProgramPageController;` and change the Task 5 `program.show` line to reference the imported class).

- [ ] **Step 4: Create the chooser view**

Create `resources/views/funnel/chooser.blade.php`:

```blade
<x-layouts.public title="Pendaftaran"
    description="Pilih jalurmu di Kheedma Academy: daftar program yang sedang dibuka atau gabung komunitas affiliator.">

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-3xl px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Pendaftaran</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900 sm:text-4xl">Pilih jalurmu.</h1>
                <p class="mx-auto mt-4 max-w-lg text-base leading-relaxed text-teal-800/80">
                    Ikuti program yang sedang dibuka, atau mulai lebih dulu dari komunitas.
                </p>
            </div>

            <div class="mt-10 space-y-4">
                @foreach ($programs as $program)
                    <a href="{{ route('program.show', $program) }}"
                       class="block rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur transition hover:border-teal-600/40 hover:shadow-md sm:p-8">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <span class="inline-block rounded-full bg-orange-100 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-700">Dibuka</span>
                                <h2 class="mt-3 text-xl font-bold text-teal-900">{{ $program->name }}</h2>
                                @if ($program->tagline)
                                    <p class="mt-1.5 text-sm leading-relaxed text-teal-800/70">{{ $program->tagline }}</p>
                                @endif
                            </div>
                            <svg class="h-5 w-5 shrink-0 text-teal-700" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </a>
                @endforeach

                <a href="{{ url('/komunitas') }}"
                   class="block rounded-3xl border border-teal-900/10 bg-teal-900 p-6 shadow-sm transition hover:bg-teal-800 hover:shadow-md sm:p-8">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <span class="inline-block rounded-full bg-white/10 px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-orange-300">Gratis</span>
                            <h2 class="mt-3 text-xl font-bold text-white">Gabung Komunitas Affiliator</h2>
                            <p class="mt-1.5 text-sm leading-relaxed text-white/70">
                                Belum siap ikut program? Mulai dari komunitas: materi, kabar terbaru, dan teman seperjalanan.
                            </p>
                        </div>
                        <svg class="h-5 w-5 shrink-0 text-white" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </a>
            </div>
        </div>
    </section>

</x-layouts.public>
```

- [ ] **Step 5: Create the landing view**

Create `resources/views/funnel/program.blade.php`:

```blade
<x-layouts.public :title="$program->name"
    :description="$program->tagline ?: 'Program Kheedma Academy: ' . $program->name">

    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-100 blur-3xl"></div>

        <div class="relative mx-auto max-w-2xl px-6 py-16 sm:py-20">
            <div class="text-center">
                <x-logo variant="stacked" class="mx-auto h-20" />
                <p class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Program</p>
                <h1 class="mt-3 text-3xl font-bold leading-tight text-teal-900 sm:text-4xl">{{ $program->name }}</h1>
                @if ($program->tagline)
                    <p class="mx-auto mt-4 max-w-lg text-base leading-relaxed text-teal-800/80">{{ $program->tagline }}</p>
                @endif
            </div>

            @if ($program->description)
                <div class="mt-10 rounded-3xl border border-teal-900/10 bg-white/70 p-6 text-sm leading-relaxed text-teal-800/90 shadow-sm backdrop-blur sm:p-8">
                    {!! nl2br(e($program->description)) !!}
                </div>
            @endif

            <div class="mt-10 text-center">
                @if ($isOpen)
                    <x-cta :href="route('program.apply', $program)" label="Daftar Sekarang" />
                    <p class="mt-4 text-xs text-teal-800/50">Pendaftaran sedang dibuka. Tempat terbatas.</p>
                @else
                    <div class="rounded-3xl border border-teal-900/10 bg-white/70 p-6 shadow-sm backdrop-blur sm:p-8">
                        <h2 class="text-lg font-bold text-teal-900">Pendaftaran ditutup</h2>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-teal-800/70">
                            Pendaftaran program ini sedang tidak dibuka. Gabung komunitas dulu supaya
                            kamu jadi yang pertama tahu saat kelas baru dibuka.
                        </p>
                        <div class="mt-5">
                            <x-cta :href="url('/komunitas')" label="Gabung Komunitas" />
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

</x-layouts.public>
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact --filter=PublicCatalogTest` then `php artisan test --compact --filter=PublicApplyTest` (redirect targets now render) then the full suite.
Expected: all PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ProgramPageController.php routes/web.php resources/views/funnel/chooser.blade.php resources/views/funnel/program.blade.php tests/Feature/PublicCatalogTest.php
git commit -m "feat: two-door chooser and program landing pages"
```

---

### Task 7: Angkatan admin gains its program parent

**Files:**
- Modify: `app/Http/Controllers/Api/Admin/CohortController.php`
- Modify: `resources/js/admin/views/Cohorts.vue`
- Test: extend `tests/Feature/CohortManagementTest.php`

**Interfaces:**
- Consumes: `Cohort::program()` (Task 4), `/api/admin/programs` (Task 2).
- Produces: cohort row shape gains `program: {id,name}|null`; `program_id` required on create, `sometimes|required` on update; Cohorts.vue gains a Program column + dropdown in the form.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/CohortManagementTest.php` (import `App\Models\Program`):

```php
    public function test_cohort_requires_a_program_on_create(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', ['name' => 'Angkatan 1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('program_id');
    }

    public function test_cohort_row_includes_its_program(): void
    {
        $program = Program::factory()->active()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', ['name' => 'Angkatan 1', 'program_id' => $program->id])
            ->assertCreated()
            ->assertJsonPath('cohort.program.id', $program->id);
    }
```

Existing cohort tests create cohorts without `program_id` via the factory (allowed: column nullable, factory unaffected); the two `postJson` create tests in the existing file (`test_admin_can_create_a_cohort_with_a_mentor`, `test_mentor_id_must_reference_a_mentor`) must be updated to include `'program_id' => Program::factory()->create()->id` in their payloads.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=CohortManagementTest`
Expected: the two new tests FAIL (validation/shape missing).

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/Api/Admin/CohortController.php`:

- `index()`: change eager load to `->with(['mentor:id,name', 'program:id,name'])`.
- `store()`/`update()` reload: change `->load('mentor:id,name')` / `->fresh(['mentor:id,name'])` to include `'program:id,name'`.
- `validated()`: add after the `name` rule:

```php
            'program_id' => [
                $creating ? 'required' : 'sometimes',
                'required',
                'exists:programs,id',
            ],
```

- `row()`: add after `'name' => $c->name,`:

```php
            'program' => $c->program ? ['id' => $c->program->id, 'name' => $c->program->name] : null,
```

- [ ] **Step 4: Update Cohorts.vue**

In `resources/js/admin/views/Cohorts.vue`:

- Import programs api: change the api import line to `import { cohorts as cohortsApi, users as usersApi, programs as programsApi } from '@/api';`
- Add `const programs = ref([]);` next to `mentors`.
- In `load()`, extend the parallel fetch:

```js
        const [cRes, mRes, pRes] = await Promise.all([cohortsApi.list(), usersApi.list('?role=mentor'), programsApi.list()]);
        items.value = cRes.data;
        mentors.value = mRes.data;
        programs.value = pRes.data;
```

- Form state gains `program_id: ''` (in `openCreate`, `openEdit` uses `cohort.program?.id ?? ''`), and `save()` payload gains `program_id: form.value.program_id || null`.
- Table: add a "Program" header column after "Nama" and a cell `{{ cohort.program?.name ?? '—' }}` (bump the loading/empty `colspan` from 6 to 7).
- Dialog form: add ABOVE the mentor select:

```vue
                <div>
                    <label class="text-xs text-muted-foreground">Program</label>
                    <select v-model="form.program_id" :class="selectClass">
                        <option value="">Pilih program…</option>
                        <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.name }}</option>
                    </select>
                    <p v-if="formErrors.program_id" class="mt-1 text-xs text-destructive">{{ formErrors.program_id[0] }}</p>
                </div>
```

- [ ] **Step 5: Run tests + build**

Run: `php artisan test --compact --filter=CohortManagementTest` and `npm run build`
Expected: PASS; build green.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/CohortController.php resources/js/admin/views/Cohorts.vue tests/Feature/CohortManagementTest.php
git commit -m "feat: angkatan carries its program (admin)"
```

---

### Task 8: Applicants list gains program column + filter

**Files:**
- Modify: `app/Http/Controllers/Api/Admin/ApplicantController.php`
- Modify: `resources/js/admin/views/Applicants.vue`
- Test: `tests/Feature/ApplicantProgramFilterTest.php`

**Interfaces:**
- Consumes: `Application::program()` (Task 4), `/api/admin/programs` (Task 2).
- Produces: applicant row gains `program: string|null` (program name) and `referral_source`; `GET /api/admin/applications?program=<id>` filter.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ApplicantProgramFilterTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantProgramFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function makeApplication(Program $program, string $phone): Application
    {
        $person = Person::create([
            'name' => 'Uji '.$phone, 'phone' => $phone, 'email' => $phone.'@example.test',
        ]);

        return Application::create([
            'people_id' => $person->id, 'status' => 'pending',
            'program_id' => $program->id, 'referral_source' => 'instagram',
        ]);
    }

    public function test_rows_include_program_and_filter_works(): void
    {
        $a = Program::factory()->active()->create(['name' => 'Program A']);
        $b = Program::factory()->active()->create(['name' => 'Program B']);
        $this->makeApplication($a, '+628111111111');
        $this->makeApplication($b, '+628222222222');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/applications')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin)
            ->getJson("/api/admin/applications?program={$a->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.program', 'Program A')
            ->assertJsonPath('data.0.referral_source', 'instagram');
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --compact --filter=ApplicantProgramFilterTest`
Expected: FAIL — `program` key missing / filter ignored.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/Api/Admin/ApplicantController.php` `index()`:

- Validation gains: `'program' => ['nullable', 'integer', 'exists:programs,id'],`
- Eager load gains `'program:id,name'`: `->with(['person:id,name,phone,email,city_code', 'person.city:code,name', 'program:id,name'])`
- Add filter after the status `when`: `->when($request->filled('program'), fn ($q) => $q->where('program_id', $request->integer('program')))`

In `row()`, add:

```php
            'program' => $a->program?->name,
            'referral_source' => $a->referral_source,
```

- [ ] **Step 4: Update Applicants.vue**

In `resources/js/admin/views/Applicants.vue`:

- Import programs api: `import { api, programs as programsApi } from '@/api';` (adjust the existing `import { api } from '@/api';` line).
- Add state `const programs = ref([]);` and `const program = ref('');`.
- In `onMounted`, also load the catalog (admins hold both permissions; noted coupling):

```js
onMounted(async () => {
    fetchPage();
    try {
        const res = await programsApi.list();
        programs.value = res.data;
    } catch {
        programs.value = [];
    }
});
```

- `fetchPage` params gains: `if (program.value) params.set('program', program.value);` and a watcher `watch(program, () => fetchPage(1));`
- Filters row gains a program select next to the status select:

```vue
            <select v-model="program" :class="selectClass">
                <option value="">Semua program</option>
                <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
```

- Table: add header "Program" after "Domisili" and cell `{{ item.program ?? '—' }}` (bump loading/empty `colspan` from 6 to 7).

- [ ] **Step 5: Run tests + build + full suite**

Run: `php artisan test --compact --filter=ApplicantProgramFilterTest`, `npm run build`, then `php artisan test --compact`
Expected: all PASS, build green.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/ApplicantController.php resources/js/admin/views/Applicants.vue tests/Feature/ApplicantProgramFilterTest.php
git commit -m "feat: applicants list shows program and referral source, filterable by program"
```

---

## Self-Review

**Spec coverage (Phase 1 scope):** Program entity + fields verbatim → Task 1. `programs.manage` → Task 1. Admin CRUD + screen → Tasks 2-3. `applications.program_id` (nullable DB/required validation) + `referral_source` → Tasks 4-5. `cohorts.program_id` nullable + UI repoint path → Tasks 4, 7. `/program/{slug}` + `/program/{slug}/daftar` + chooser + closed state + draft-404 → Tasks 5-6. Public copy rules → embedded in view code. Applications admin program awareness → Task 8. Community card links to `/komunitas` (Phase 2, launched together) → Task 6.

**Placeholder scan:** none — every step carries full code or exact edit instructions.

**Type consistency:** `programs` api group (T3) consumed in T7/T8; `Program::factory()` states (T1) used in T2/T5/T6/T7/T8; `program.show`/`program.apply` route names consistent across T5/T6; cohort row `program:{id,name}` (T7) matches Cohorts.vue usage; applicant row `program: string|null` (T8) matches template. `ProgramPageController::show` created as stub in T5, finished in T6 — both signatures match.

**Sequencing note:** Task 5 registers `program.show` with a stub controller + view reference that isn't rendered by any Task 5 test (redirect assertions only); Task 6 supplies the view. Full-suite runs between tasks stay green because no test renders the landing before Task 6.
