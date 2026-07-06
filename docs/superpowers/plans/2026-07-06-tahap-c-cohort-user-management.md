# Tahap C — Cohort & User Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add granular Spatie authorization, a full user/team management module, and cohort management to the Kheedma Academy admin SPA.

**Architecture:** Laravel 13 API (Sanctum cookie SPA auth) exposes admin endpoints guarded by granular `permission:` middleware; a Vue 3 SPA (Pinia + hand-rolled shadcn-style UI) consumes them. Permissions are seeded and mapped to the existing `admin`/`mentor`/`participant` roles; the SPA gates nav and routes on the permissions returned by `/me`.

**Tech Stack:** PHP 8.4, Laravel 13, spatie/laravel-permission v8, Laravel Sanctum v4, Vue 3 (`<script setup>`), Vue Router, Pinia, Tailwind v4, Vite, PHPUnit 12.

## Global Constraints

- PHP control structures always use curly braces; explicit return types and param type hints on all methods.
- Run `vendor/bin/pint --dirty --format agent` before each backend commit.
- Tests are PHPUnit classes (no Pest). Create with `php artisan make:test`. Run with `php artisan test --compact`.
- Feature tests seed `RoleSeeder` then `PermissionSeeder` in `setUp` (permissions must exist before permission checks).
- Do not add npm/composer dependencies. Build new UI from the existing hand-rolled pattern (plain Vue components under `resources/js/admin/components/ui/<name>/`, native `<select>` styled with `selectClass`, `cn()` from `@/lib/utils`).
- Frontend copy is Indonesian, no em-dashes. Brand: teal base, orange accent used sparingly.
- Permission names (verbatim): `applications.view`, `applications.review`, `people.view`, `people.merge`, `cohorts.view`, `cohorts.manage`, `users.manage`, `data.export`.
- Roles: `admin` (all permissions), `mentor` (`applications.view`, `people.view`, `cohorts.view`), `participant` (none).

---

## File Structure

**Backend — create:**
- `database/seeders/PermissionSeeder.php` — defines permissions + maps to roles + flushes Spatie cache.
- `app/Http/Middleware/EnsureUserIsActive.php` — rejects deactivated sessions.
- `database/migrations/2026_07_06_000001_add_account_fields_to_users_table.php` — `phone`, `is_active`.
- `app/Http/Controllers/Api/Admin/UserController.php` — team CRUD.
- `app/Http/Controllers/Api/Admin/CohortController.php` — cohort CRUD.
- `database/factories/CohortFactory.php`.
- Tests: `tests/Feature/PermissionSeederTest.php`, `AuthPermissionsTest.php`, `AccountStatusTest.php`, `UserManagementTest.php`, `CohortManagementTest.php`.

**Backend — modify:**
- `database/seeders/DatabaseSeeder.php` — call `PermissionSeeder` between `RoleSeeder` and `AdminUserSeeder`.
- `app/Http/Controllers/Api/AuthController.php` — `is_active` login gate + `permissions` in profile.
- `app/Models/User.php` — fillable `phone`/`is_active`, cast `is_active`, factory states.
- `app/Models/Cohort.php` — derived `status` accessor.
- `database/factories/UserFactory.php` — `admin()` / `mentor()` states.
- `routes/api.php` — permission middleware on existing routes + new users/cohorts routes + `EnsureUserIsActive` on the auth group.

**Frontend — create:**
- `resources/js/admin/components/ui/dialog/Dialog.vue` + `index.js` — minimal modal.
- `resources/js/admin/views/Users.vue`, `resources/js/admin/views/Cohorts.vue`.

**Frontend — modify:**
- `resources/js/admin/api.js` — `users` + `cohorts` endpoint groups.
- `resources/js/admin/stores/auth.js` — `can(permission)`.
- `resources/js/admin/router.js` — users/cohorts routes + permission guard.
- `resources/js/admin/components/AppShell.vue` — permission-gated nav.

---

## Task 1: Permissions foundation (seeder + role mapping)

**Files:**
- Create: `database/seeders/PermissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/PermissionSeederTest.php`

**Interfaces:**
- Produces: `PermissionSeeder` (idempotent) creating the 8 permissions (guard `web`) and syncing them to roles; flushes Spatie cache. Later tasks assume these permission names exist.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PermissionSeederTest.php`:

```php
<?php

namespace Tests\Feature;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_permissions_to_roles(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $admin = Role::findByName('admin', 'web');
        $mentor = Role::findByName('mentor', 'web');

        $this->assertTrue($admin->hasPermissionTo('users.manage'));
        $this->assertTrue($admin->hasPermissionTo('cohorts.manage'));
        $this->assertTrue($mentor->hasPermissionTo('applications.view'));
        $this->assertFalse($mentor->hasPermissionTo('users.manage'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PermissionSeederTest`
Expected: FAIL — class `Database\Seeders\PermissionSeeder` not found.

- [ ] **Step 3: Create the seeder**

Create `database/seeders/PermissionSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Define granular permissions and attach them to roles. Idempotent.
     * Runs after RoleSeeder, since the roles must exist first.
     */
    public function run(): void
    {
        $permissions = [
            'applications.view',
            'applications.review',
            'people.view',
            'people.merge',
            'cohorts.view',
            'cohorts.manage',
            'users.manage',
            'data.export',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions($permissions);
        Role::findOrCreate('mentor', 'web')->syncPermissions([
            'applications.view',
            'people.view',
            'cohorts.view',
        ]);
        Role::findOrCreate('participant', 'web')->syncPermissions([]);

        // Spatie caches the permission map; without this flush newly seeded
        // permissions can be missed by cached lookups.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

- [ ] **Step 4: Wire it into DatabaseSeeder**

In `database/seeders/DatabaseSeeder.php`, change the `$this->call([...])` list to:

```php
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            AdminUserSeeder::class,
        ]);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=PermissionSeederTest`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/PermissionSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/PermissionSeederTest.php
git commit -m "feat: seed granular permissions and map to roles"
```

---

## Task 2: Enforce permissions on existing routes + expose in /me

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php:88` (the `profile` method)
- Modify: `routes/api.php`
- Test: `tests/Feature/AuthPermissionsTest.php`

**Interfaces:**
- Consumes: permission names from Task 1.
- Produces: `/me` and login responses include a `permissions: string[]` field. Existing admin routes require `permission:applications.view|applications.review|people.view`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AuthPermissionsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_me_includes_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.permissions', fn ($perms) => in_array('users.manage', $perms, true));
    }

    public function test_route_requires_matching_permission(): void
    {
        $participant = User::factory()->create();
        $participant->assignRole('participant');

        $this->actingAs($participant)
            ->getJson('/api/admin/applications')
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AuthPermissionsTest`
Expected: FAIL — `user.permissions` missing / participant not forbidden (currently gated by role).

- [ ] **Step 3: Add permissions to the profile payload**

In `app/Http/Controllers/Api/AuthController.php`, replace the `profile` method body's return array with:

```php
    private function profile($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
```

- [ ] **Step 4: Swap role middleware for permission middleware**

In `routes/api.php`, replace the `Route::middleware('role:admin|mentor')->prefix('admin')->group(...)` block with per-route permission middleware:

```php
    // Staff-only operational modules (granular permissions).
    Route::prefix('admin')->group(function () {
        Route::get('/applications', [ApplicantController::class, 'index'])->middleware('permission:applications.view');
        Route::patch('/applications/{application}', [ApplicantController::class, 'update'])->middleware('permission:applications.review');
        Route::get('/people/{person}', [PersonController::class, 'show'])->middleware('permission:people.view');
    });
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=AuthPermissionsTest`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/AuthController.php routes/api.php tests/Feature/AuthPermissionsTest.php
git commit -m "feat: enforce granular permissions on admin routes; expose permissions in /me"
```

---

## Task 3: Account fields + active-status guard

**Files:**
- Create: `database/migrations/2026_07_06_000001_add_account_fields_to_users_table.php`
- Create: `app/Http/Middleware/EnsureUserIsActive.php`
- Modify: `app/Models/User.php`
- Modify: `app/Http/Controllers/Api/AuthController.php` (login method)
- Modify: `routes/api.php` (auth group middleware)
- Test: `tests/Feature/AccountStatusTest.php`

**Interfaces:**
- Produces: `users.phone` (nullable string), `users.is_active` (boolean, default true). `User` fillable includes `phone`, `is_active`; `is_active` cast to bool. Deactivated users cannot log in and get 401 on authenticated routes.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AccountStatusTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123'), 'is_active' => false]);
        $user->assignRole('admin');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertStatus(422);
    }

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123'), 'is_active' => true]);
        $user->assignRole('admin');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk();
    }

    public function test_deactivated_session_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('admin');

        $this->actingAs($user)->getJson('/api/me')->assertStatus(401);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AccountStatusTest`
Expected: FAIL — `is_active` column/behavior missing.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_06_000001_add_account_fields_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'is_active']);
        });
    }
};
```

- [ ] **Step 4: Update the User model**

In `app/Models/User.php`, change the `#[Fillable(...)]` attribute and add the cast:

```php
#[Fillable(['name', 'email', 'phone', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
```

And in `casts()` add the `is_active` line:

```php
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
```

- [ ] **Step 5: Add the login gate**

In `app/Http/Controllers/Api/AuthController.php` `login()`, immediately after the `hasAnyRole` staff check block and before `$request->session()->regenerate();`, add:

```php
        if (! $user->is_active) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Akun ini dinonaktifkan. Hubungi admin.',
            ]);
        }
```

- [ ] **Step 6: Create the middleware**

Create `app/Http/Middleware/EnsureUserIsActive.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Reject a session whose account was deactivated mid-use so the SPA logs out.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            abort(401, 'Akun dinonaktifkan.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 7: Apply the middleware to the auth group**

In `routes/api.php`, add the import at the top:

```php
use App\Http\Middleware\EnsureUserIsActive;
```

Change the authenticated group opener from `Route::middleware('auth:sanctum')->group(function () {` to:

```php
Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {
```

- [ ] **Step 8: Run migration + test**

Run: `php artisan migrate` then `php artisan test --compact --filter=AccountStatusTest`
Expected: PASS (all three).

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_07_06_000001_add_account_fields_to_users_table.php app/Http/Middleware/EnsureUserIsActive.php app/Models/User.php app/Http/Controllers/Api/AuthController.php routes/api.php tests/Feature/AccountStatusTest.php
git commit -m "feat: add user phone/is_active fields and deactivation guard"
```

---

## Task 4: User/team management API

**Files:**
- Create: `app/Http/Controllers/Api/Admin/UserController.php`
- Modify: `database/factories/UserFactory.php` (add `admin()` / `mentor()` states)
- Modify: `routes/api.php` (users routes)
- Test: `tests/Feature/UserManagementTest.php`

**Interfaces:**
- Consumes: `users.manage` permission (Task 1), `is_active` field (Task 3).
- Produces: `GET/POST /api/admin/users`, `PATCH/DELETE /api/admin/users/{user}`. Row shape: `{ id, name, email, phone, role, is_active }`. `store` returns `201` with `{ user, generated_password }` (`generated_password` is the plaintext only when auto-generated, else `null`).

- [ ] **Step 1: Add factory states**

In `database/factories/UserFactory.php`, add these two methods inside the class:

```php
    /** Assign the admin role after creation (roles must be seeded first). */
    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('admin'));
    }

    /** Assign the mentor role after creation (roles must be seeded first). */
    public function mentor(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('mentor'));
    }
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/UserManagementTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_admin_can_create_a_mentor_with_generated_password(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/users', [
                'name' => 'Ustadz Budi',
                'email' => 'budi@kheedma.id',
                'phone' => '0811111111',
                'role' => 'mentor',
            ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'mentor')
            ->assertJsonPath('user.is_active', true)
            ->assertJsonPath('generated_password', fn ($p) => is_string($p) && strlen($p) >= 8);

        $this->assertTrue(User::where('email', 'budi@kheedma.id')->first()->hasRole('mentor'));
    }

    public function test_non_admin_is_forbidden(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($mentor)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_cannot_deactivate_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$admin->id}", ['is_active' => false])
            ->assertStatus(422);
    }

    public function test_cannot_remove_last_admin(): void
    {
        $admin = $this->admin();
        $other = User::factory()->admin()->create();

        // Demote the only *other* admin first is allowed; then the acting admin is last.
        $this->actingAs($admin)->deleteJson("/api/admin/users/{$other->id}")->assertNoContent();

        // Now $admin is the last admin; deleting them must be blocked (self-guard also applies).
        $this->actingAs($admin)->deleteJson("/api/admin/users/{$admin->id}")->assertStatus(422);
    }

    public function test_admin_can_deactivate_and_reactivate_another_user(): void
    {
        $admin = $this->admin();
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$mentor->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('user.is_active', false);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$mentor->id}", ['is_active' => true])
            ->assertJsonPath('user.is_active', true);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=UserManagementTest`
Expected: FAIL — no `/api/admin/users` route / controller.

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Api/Admin/UserController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /** Staff accounts (admin + mentor), searchable, with an optional role filter. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:admin,mentor'],
        ]);

        $users = User::query()
            ->with('roles:id,name')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'mentor']))
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $request->string('role'))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->row($u));

        return response()->json(['data' => $users]);
    }

    /** Create a staff account; auto-generates a password when none is supplied. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'in:admin,mentor'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $supplied = filled($data['password'] ?? null);
        $plain = $supplied ? $data['password'] : Str::password(12);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($plain),
            'is_active' => true,
        ]);
        $user->syncRoles([$data['role']]);

        return response()->json([
            'user' => $this->row($user),
            'generated_password' => $supplied ? null : $plain,
        ], 201);
    }

    /** Edit profile, role, password, or active status (with safety guards). */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'role' => ['sometimes', 'in:admin,mentor'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->guardSelfAndLastAdmin($request, $user, $data);

        foreach (['name', 'email', 'phone'] as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field];
            }
        }
        if (array_key_exists('is_active', $data)) {
            $user->is_active = $data['is_active'];
        }
        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        if (array_key_exists('role', $data)) {
            $user->syncRoles([$data['role']]);
        }

        return response()->json(['user' => $this->row($user->fresh())]);
    }

    /** Delete a staff account (never yourself, never the last active admin). */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => 'Tidak bisa menghapus akun sendiri.']);
        }
        if ($this->isLastActiveAdmin($user)) {
            throw ValidationException::withMessages(['user' => 'Minimal satu admin aktif harus tersisa.']);
        }

        $user->delete();

        return response()->json(null, 204);
    }

    /** Reject edits that would lock out the acting admin or empty the admin pool. */
    private function guardSelfAndLastAdmin(Request $request, User $user, array $data): void
    {
        $isSelf = $request->user()->is($user);
        $deactivating = array_key_exists('is_active', $data) && $data['is_active'] === false;
        $demoting = array_key_exists('role', $data) && $data['role'] !== 'admin' && $user->hasRole('admin');

        if ($isSelf && $deactivating) {
            throw ValidationException::withMessages(['is_active' => 'Tidak bisa menonaktifkan akun sendiri.']);
        }
        if ($isSelf && $demoting) {
            throw ValidationException::withMessages(['role' => 'Tidak bisa menurunkan peran akun sendiri.']);
        }
        if (($deactivating || $demoting) && $this->isLastActiveAdmin($user)) {
            throw ValidationException::withMessages(['role' => 'Minimal satu admin aktif harus tersisa.']);
        }
    }

    /** True when $user is an active admin and no other active admin remains. */
    private function isLastActiveAdmin(User $user): bool
    {
        if (! $user->hasRole('admin') || ! $user->is_active) {
            return false;
        }

        return User::role('admin')
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->doesntExist();
    }

    /**
     * @return array{id:int,name:string,email:string,phone:?string,role:?string,is_active:bool}
     */
    private function row(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'role' => $u->getRoleNames()->first(),
            'is_active' => (bool) $u->is_active,
        ];
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/api.php`, add the import:

```php
use App\Http\Controllers\Api\Admin\UserController;
```

Inside the `Route::prefix('admin')->group(...)` block, add:

```php
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::patch('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);
        });
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=UserManagementTest`
Expected: PASS (all cases).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Admin/UserController.php database/factories/UserFactory.php routes/api.php tests/Feature/UserManagementTest.php
git commit -m "feat: user/team management API with self and last-admin guards"
```

---

## Task 5: Cohort model status + management API

**Files:**
- Modify: `app/Models/Cohort.php` (derived `status` accessor + `$appends`)
- Create: `app/Http/Controllers/Api/Admin/CohortController.php`
- Create: `database/factories/CohortFactory.php`
- Modify: `routes/api.php` (cohorts routes)
- Test: `tests/Feature/CohortManagementTest.php`

**Interfaces:**
- Consumes: `cohorts.view` / `cohorts.manage` permissions, `User` mentor role, `Cohort::enrollments()`.
- Produces: `GET /api/admin/cohorts` (perm `cohorts.view`), `POST/PATCH/DELETE` (perm `cohorts.manage`). Row shape: `{ id, name, start_date, end_date, status, mentor: {id,name}|null, enrollments_count }`. `status` ∈ `upcoming|active|ended`.

- [ ] **Step 1: Add the status accessor + factory**

In `app/Models/Cohort.php`, add the `Attribute` import and `$appends`, plus the accessor. After the `use HasFactory;` line add:

```php
    protected $appends = ['status'];
```

Add the import near the top with the other `use` statements:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;
```

Add this method to the class (after `casts()`):

```php
    /**
     * Derived lifecycle from the dates (never stored):
     * upcoming (starts in the future or no start), active, or ended.
     */
    protected function status(): Attribute
    {
        return Attribute::make(get: function (): string {
            $today = now()->startOfDay();

            if ($this->start_date && $this->start_date->gt($today)) {
                return 'upcoming';
            }
            if ($this->end_date && $this->end_date->lt($today)) {
                return 'ended';
            }

            return $this->start_date ? 'active' : 'upcoming';
        });
    }
```

Create `database/factories/CohortFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Cohort;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cohort>
 */
class CohortFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Cohort '.fake()->unique()->numberBetween(1, 999),
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'mentor_id' => null,
        ];
    }
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/CohortManagementTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CohortManagementTest extends TestCase
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

    public function test_admin_can_create_a_cohort_with_a_mentor(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', [
                'name' => 'Angkatan 1',
                'start_date' => now()->addWeek()->toDateString(),
                'end_date' => now()->addMonths(2)->toDateString(),
                'mentor_id' => $mentor->id,
            ])
            ->assertCreated()
            ->assertJsonPath('cohort.name', 'Angkatan 1')
            ->assertJsonPath('cohort.status', 'upcoming')
            ->assertJsonPath('cohort.mentor.id', $mentor->id);
    }

    public function test_mentor_id_must_reference_a_mentor(): void
    {
        $notMentor = User::factory()->admin()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/cohorts', ['name' => 'X', 'mentor_id' => $notMentor->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mentor_id');
    }

    public function test_status_is_derived_from_dates(): void
    {
        $active = Cohort::factory()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);
        $ended = Cohort::factory()->create([
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
        ]);

        $this->assertSame('active', $active->status);
        $this->assertSame('ended', $ended->status);
    }

    public function test_cohort_with_enrollments_cannot_be_deleted(): void
    {
        $cohort = Cohort::factory()->create();
        DB::table('enrollments')->insert([
            'people_id' => 1,
            'cohort_id' => $cohort->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/cohorts/{$cohort->id}")
            ->assertStatus(422);
    }

    public function test_empty_cohort_can_be_deleted(): void
    {
        $cohort = Cohort::factory()->create();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/cohorts/{$cohort->id}")
            ->assertNoContent();
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=CohortManagementTest`
Expected: FAIL — no cohorts route / controller.

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Api/Admin/CohortController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CohortController extends Controller
{
    /** All cohorts, newest first, with mentor name and participant count. */
    public function index(): JsonResponse
    {
        $cohorts = Cohort::query()
            ->with('mentor:id,name')
            ->withCount('enrollments')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Cohort $c) => $this->row($c));

        return response()->json(['data' => $cohorts]);
    }

    public function store(Request $request): JsonResponse
    {
        $cohort = Cohort::create($this->validated($request));

        return response()->json([
            'cohort' => $this->row($cohort->load('mentor:id,name')->loadCount('enrollments')),
        ], 201);
    }

    public function update(Request $request, Cohort $cohort): JsonResponse
    {
        $cohort->update($this->validated($request));

        return response()->json([
            'cohort' => $this->row($cohort->fresh(['mentor:id,name'])->loadCount('enrollments')),
        ]);
    }

    public function destroy(Cohort $cohort): JsonResponse
    {
        if ($cohort->enrollments()->exists()) {
            throw ValidationException::withMessages(['cohort' => 'Cohort dengan peserta tidak bisa dihapus.']);
        }

        $cohort->delete();

        return response()->json(null, 204);
    }

    /** Shared validation; mentor_id must reference a user holding the mentor role. */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'mentor_id' => [
                'nullable',
                function (string $attribute, $value, $fail): void {
                    if ($value && ! User::role('mentor')->whereKey($value)->exists()) {
                        $fail('Mentor yang dipilih tidak valid.');
                    }
                },
            ],
        ]);
    }

    /**
     * @return array{id:int,name:string,start_date:?string,end_date:?string,status:string,mentor:?array{id:int,name:string},enrollments_count:int}
     */
    private function row(Cohort $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'start_date' => $c->start_date?->toDateString(),
            'end_date' => $c->end_date?->toDateString(),
            'status' => $c->status,
            'mentor' => $c->mentor ? ['id' => $c->mentor->id, 'name' => $c->mentor->name] : null,
            'enrollments_count' => (int) ($c->enrollments_count ?? 0),
        ];
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/api.php`, add the import:

```php
use App\Http\Controllers\Api\Admin\CohortController;
```

Inside the `Route::prefix('admin')->group(...)` block, add:

```php
        Route::get('/cohorts', [CohortController::class, 'index'])->middleware('permission:cohorts.view');
        Route::middleware('permission:cohorts.manage')->group(function () {
            Route::post('/cohorts', [CohortController::class, 'store']);
            Route::patch('/cohorts/{cohort}', [CohortController::class, 'update']);
            Route::delete('/cohorts/{cohort}', [CohortController::class, 'destroy']);
        });
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=CohortManagementTest`
Expected: PASS (all cases).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Cohort.php app/Http/Controllers/Api/Admin/CohortController.php database/factories/CohortFactory.php routes/api.php tests/Feature/CohortManagementTest.php
git commit -m "feat: cohort management API with date-derived status"
```

---

## Task 6: Minimal Dialog UI component

**Files:**
- Create: `resources/js/admin/components/ui/dialog/Dialog.vue`
- Create: `resources/js/admin/components/ui/dialog/index.js`

**Interfaces:**
- Produces: `<Dialog v-model:open="ref" title="...">slot</Dialog>` — a teleported modal that closes on overlay click and Escape. Consumed by Users.vue and Cohorts.vue.

- [ ] **Step 1: Create the component**

Create `resources/js/admin/components/ui/dialog/Dialog.vue`:

```vue
<script setup>
import { watch, onUnmounted } from 'vue';

const open = defineModel('open', { type: Boolean, default: false });
defineProps({ title: { type: String, default: '' } });

function onKey(e) {
    if (e.key === 'Escape') open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('keydown', onKey);
    } else {
        document.removeEventListener('keydown', onKey);
    }
});

onUnmounted(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-xl">
                <h2 v-if="title" class="text-lg font-bold text-foreground">{{ title }}</h2>
                <div class="mt-4">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
```

- [ ] **Step 2: Create the barrel file**

Create `resources/js/admin/components/ui/dialog/index.js`:

```js
export { default as Dialog } from './Dialog.vue';
```

- [ ] **Step 3: Verify the build compiles**

Run: `npm run build`
Expected: build succeeds with no Vue compile errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/admin/components/ui/dialog/
git commit -m "feat: minimal Dialog UI component"
```

---

## Task 7: Auth store permission helper + router + nav gating

**Files:**
- Modify: `resources/js/admin/stores/auth.js` (add `can`)
- Modify: `resources/js/admin/router.js` (users/cohorts routes + permission guard)
- Modify: `resources/js/admin/components/AppShell.vue` (permission-gated nav)

**Interfaces:**
- Consumes: `permissions` array on the user object from `/me` (Task 2).
- Produces: `auth.can(permission)` boolean helper; routes named `cohorts` (`/admin/cohorts`, meta `cohorts.view`) and `users` (`/admin/users`, meta `users.manage`).

- [ ] **Step 1: Add `can` to the auth store**

In `resources/js/admin/stores/auth.js`, add this function before the `return`:

```js
    function can(permission) {
        return Array.isArray(user.value?.permissions) && user.value.permissions.includes(permission);
    }
```

And add `can` to the returned object:

```js
    return { user, ready, isAuthenticated, fetchUser, login, logout, hasRole, can };
```

- [ ] **Step 2: Add routes + permission guard**

In `resources/js/admin/router.js`, add these two entries to the `children` array (after the `pelamar/:id` route):

```js
            {
                path: 'cohorts',
                name: 'cohorts',
                component: () => import('./views/Cohorts.vue'),
                meta: { permission: 'cohorts.view' },
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('./views/Users.vue'),
                meta: { permission: 'users.manage' },
            },
```

In the same file, extend `router.beforeEach` — add this check before the final closing brace of the callback, after the existing `guest` check:

```js
    if (to.meta.permission && !auth.can(to.meta.permission)) {
        return { name: 'dashboard' };
    }
```

- [ ] **Step 3: Gate the sidebar nav on permissions**

Replace the `<script setup>` of `resources/js/admin/components/AppShell.vue` with:

```vue
<script setup>
import { computed } from 'vue';
import { RouterView, RouterLink, useRouter } from 'vue-router';
import { LayoutDashboard, Users, GraduationCap, UserCog } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const nav = computed(() =>
    [
        { to: { name: 'dashboard' }, label: 'Dashboard', icon: LayoutDashboard, show: true },
        { to: { name: 'applicants' }, label: 'Pelamar', icon: Users, show: auth.can('applications.view') },
        { to: { name: 'cohorts' }, label: 'Cohort', icon: GraduationCap, show: auth.can('cohorts.view') },
        { to: { name: 'users' }, label: 'Tim', icon: UserCog, show: auth.can('users.manage') },
    ].filter((item) => item.show),
);

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>
```

Then replace the `<nav>...</nav>` block in the template with the simplified (all items are shown) version:

```vue
            <nav class="flex-1 space-y-1 p-3">
                <RouterLink
                    v-for="item in nav"
                    :key="item.label"
                    :to="item.to"
                    active-class="bg-accent text-accent-foreground"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-foreground/70 transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                </RouterLink>
            </nav>
```

- [ ] **Step 4: Verify the build compiles**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 5: Commit**

```bash
git add resources/js/admin/stores/auth.js resources/js/admin/router.js resources/js/admin/components/AppShell.vue
git commit -m "feat: permission-based nav and route guards in admin SPA"
```

---

## Task 8: Users.vue view + api client

**Files:**
- Modify: `resources/js/admin/api.js` (add `users` group)
- Create: `resources/js/admin/views/Users.vue`

**Interfaces:**
- Consumes: `/api/admin/users` endpoints (Task 4), `Dialog` (Task 6).
- Produces: the `/admin/users` screen.

- [ ] **Step 1: Add the users API group**

In `resources/js/admin/api.js`, add after the `auth` export:

```js
export const users = {
    list(query = '') {
        return api(`/admin/users${query}`);
    },
    create(payload) {
        return api('/admin/users', { method: 'POST', body: payload });
    },
    update(id, payload) {
        return api(`/admin/users/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/users/${id}`, { method: 'DELETE' });
    },
};
```

- [ ] **Step 2: Create the view**

Create `resources/js/admin/views/Users.vue`:

```vue
<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { users as usersApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

const router = useRouter();
const items = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editing = ref(null); // null = create mode
const form = ref({ name: '', email: '', phone: '', role: 'mentor', password: '' });
const formErrors = ref({});
const generatedPassword = ref('');
const saving = ref(false);

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await usersApi.list();
        items.value = res.data;
    } catch (e) {
        if (e.status === 401) return router.push({ name: 'login' });
        error.value = e.message ?? 'Gagal memuat data.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function openCreate() {
    editing.value = null;
    form.value = { name: '', email: '', phone: '', role: 'mentor', password: '' };
    formErrors.value = {};
    generatedPassword.value = '';
    dialogOpen.value = true;
}

function openEdit(user) {
    editing.value = user;
    form.value = { name: user.name, email: user.email, phone: user.phone ?? '', role: user.role, password: '' };
    formErrors.value = {};
    generatedPassword.value = '';
    dialogOpen.value = true;
}

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = { ...form.value };
        if (!payload.password) delete payload.password;
        if (editing.value) {
            await usersApi.update(editing.value.id, payload);
            dialogOpen.value = false;
        } else {
            const res = await usersApi.create(payload);
            if (res.generated_password) {
                generatedPassword.value = res.generated_password;
            } else {
                dialogOpen.value = false;
            }
        }
        await load();
    } catch (e) {
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function toggleActive(user) {
    error.value = '';
    try {
        await usersApi.update(user.id, { is_active: !user.is_active });
        await load();
    } catch (e) {
        error.value = e.message ?? 'Gagal mengubah status.';
    }
}
</script>

<template>
    <div class="mx-auto max-w-5xl">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Tim</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Akun Tim</h1>
            </div>
            <Button variant="accent" size="sm" @click="openCreate">Tambah Akun</Button>
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
                        <th class="px-4 py-3 font-semibold">Peran</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td></tr>
                    <tr v-else-if="!items.length"><td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Belum ada akun.</td></tr>
                    <tr v-for="user in items" :key="user.id" class="border-b border-border last:border-0">
                        <td class="px-4 py-3 font-medium text-foreground">{{ user.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <div>{{ user.email }}</div>
                            <div class="text-xs">{{ user.phone ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3"><Badge variant="secondary">{{ user.role }}</Badge></td>
                        <td class="px-4 py-3">
                            <Badge :variant="user.is_active ? 'success' : 'destructive'">
                                {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button variant="ghost" size="sm" @click="openEdit(user)">Ubah</Button>
                            <Button variant="ghost" size="sm" @click="toggleActive(user)">
                                {{ user.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="dialogOpen" :title="editing ? 'Ubah Akun' : 'Tambah Akun'">
            <div v-if="generatedPassword" class="space-y-3">
                <p class="text-sm text-foreground">Akun dibuat. Catat kata sandi ini, hanya ditampilkan sekali:</p>
                <code class="block rounded-md border border-border bg-background px-3 py-2 text-sm">{{ generatedPassword }}</code>
                <div class="flex justify-end">
                    <Button size="sm" @click="dialogOpen = false">Selesai</Button>
                </div>
            </div>
            <form v-else class="space-y-3" @submit.prevent="save">
                <div>
                    <Input v-model="form.name" placeholder="Nama" />
                    <p v-if="formErrors.name" class="mt-1 text-xs text-destructive">{{ formErrors.name[0] }}</p>
                </div>
                <div>
                    <Input v-model="form.email" placeholder="Email" />
                    <p v-if="formErrors.email" class="mt-1 text-xs text-destructive">{{ formErrors.email[0] }}</p>
                </div>
                <Input v-model="form.phone" placeholder="No. HP (opsional)" />
                <select v-model="form.role" :class="[selectClass, 'w-full']">
                    <option value="mentor">Mentor</option>
                    <option value="admin">Admin</option>
                </select>
                <div>
                    <Input v-model="form.password" type="password" :placeholder="editing ? 'Kata sandi baru (opsional)' : 'Kata sandi (kosongkan untuk generate)'" />
                    <p v-if="formErrors.password" class="mt-1 text-xs text-destructive">{{ formErrors.password[0] }}</p>
                    <p v-if="formErrors.role" class="mt-1 text-xs text-destructive">{{ formErrors.role[0] }}</p>
                    <p v-if="formErrors.is_active" class="mt-1 text-xs text-destructive">{{ formErrors.is_active[0] }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" size="sm" @click="dialogOpen = false">Batal</Button>
                    <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
```

- [ ] **Step 3: Verify the build compiles**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 4: Manual smoke test**

Run `composer run dev` (or `npm run dev` + `php artisan serve`). Log in as admin, open `/admin/users`. Create a mentor with a blank password → the generated password shows once. Toggle the mentor active/inactive. Confirm you cannot deactivate your own admin account (error surfaces).

- [ ] **Step 5: Commit**

```bash
git add resources/js/admin/api.js resources/js/admin/views/Users.vue
git commit -m "feat: users/team management screen"
```

---

## Task 9: Cohorts.vue view + api client

**Files:**
- Modify: `resources/js/admin/api.js` (add `cohorts` group)
- Create: `resources/js/admin/views/Cohorts.vue`

**Interfaces:**
- Consumes: `/api/admin/cohorts` endpoints (Task 5), `/api/admin/users?role=mentor` for the mentor dropdown (Task 4), `Dialog` (Task 6).
- Produces: the `/admin/cohorts` screen.

- [ ] **Step 1: Add the cohorts API group**

In `resources/js/admin/api.js`, add after the `users` export:

```js
export const cohorts = {
    list() {
        return api('/admin/cohorts');
    },
    create(payload) {
        return api('/admin/cohorts', { method: 'POST', body: payload });
    },
    update(id, payload) {
        return api(`/admin/cohorts/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/cohorts/${id}`, { method: 'DELETE' });
    },
};
```

- [ ] **Step 2: Create the view**

Create `resources/js/admin/views/Cohorts.vue`:

```vue
<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { cohorts as cohortsApi, users as usersApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

const router = useRouter();
const items = ref([]);
const mentors = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editing = ref(null);
const form = ref({ name: '', start_date: '', end_date: '', mentor_id: '' });
const formErrors = ref({});
const saving = ref(false);

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const STATUS = {
    upcoming: { label: 'Akan datang', variant: 'warning' },
    active: { label: 'Berjalan', variant: 'success' },
    ended: { label: 'Selesai', variant: 'secondary' },
};

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const [cRes, mRes] = await Promise.all([cohortsApi.list(), usersApi.list('?role=mentor')]);
        items.value = cRes.data;
        mentors.value = mRes.data;
    } catch (e) {
        if (e.status === 401) return router.push({ name: 'login' });
        error.value = e.message ?? 'Gagal memuat data.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function openCreate() {
    editing.value = null;
    form.value = { name: '', start_date: '', end_date: '', mentor_id: '' };
    formErrors.value = {};
    dialogOpen.value = true;
}

function openEdit(cohort) {
    editing.value = cohort;
    form.value = {
        name: cohort.name,
        start_date: cohort.start_date ?? '',
        end_date: cohort.end_date ?? '',
        mentor_id: cohort.mentor?.id ?? '',
    };
    formErrors.value = {};
    dialogOpen.value = true;
}

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = {
            name: form.value.name,
            start_date: form.value.start_date || null,
            end_date: form.value.end_date || null,
            mentor_id: form.value.mentor_id || null,
        };
        if (editing.value) {
            await cohortsApi.update(editing.value.id, payload);
        } else {
            await cohortsApi.create(payload);
        }
        dialogOpen.value = false;
        await load();
    } catch (e) {
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function remove(cohort) {
    error.value = '';
    try {
        await cohortsApi.remove(cohort.id);
        await load();
    } catch (e) {
        error.value = e.message ?? 'Gagal menghapus cohort.';
    }
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <div class="mx-auto max-w-5xl">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Cohort</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Daftar Cohort</h1>
            </div>
            <Button variant="accent" size="sm" @click="openCreate">Tambah Cohort</Button>
        </div>

        <div v-if="error" class="mt-4 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>

        <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold">Periode</th>
                        <th class="px-4 py-3 font-semibold">Mentor</th>
                        <th class="px-4 py-3 font-semibold">Peserta</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td></tr>
                    <tr v-else-if="!items.length"><td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Belum ada cohort.</td></tr>
                    <tr v-for="cohort in items" :key="cohort.id" class="border-b border-border last:border-0">
                        <td class="px-4 py-3 font-medium text-foreground">{{ cohort.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ fmtDate(cohort.start_date) }} – {{ fmtDate(cohort.end_date) }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ cohort.mentor?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ cohort.enrollments_count }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="STATUS[cohort.status]?.variant ?? 'secondary'">
                                {{ STATUS[cohort.status]?.label ?? cohort.status }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button variant="ghost" size="sm" @click="openEdit(cohort)">Ubah</Button>
                            <Button variant="ghost" size="sm" @click="remove(cohort)">Hapus</Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="dialogOpen" :title="editing ? 'Ubah Cohort' : 'Tambah Cohort'">
            <form class="space-y-3" @submit.prevent="save">
                <div>
                    <Input v-model="form.name" placeholder="Nama cohort" />
                    <p v-if="formErrors.name" class="mt-1 text-xs text-destructive">{{ formErrors.name[0] }}</p>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Mulai</label>
                        <Input v-model="form.start_date" type="date" />
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Selesai</label>
                        <Input v-model="form.end_date" type="date" />
                    </div>
                </div>
                <p v-if="formErrors.end_date" class="text-xs text-destructive">{{ formErrors.end_date[0] }}</p>
                <div>
                    <select v-model="form.mentor_id" :class="selectClass">
                        <option value="">Tanpa mentor</option>
                        <option v-for="mentor in mentors" :key="mentor.id" :value="mentor.id">{{ mentor.name }}</option>
                    </select>
                    <p v-if="formErrors.mentor_id" class="mt-1 text-xs text-destructive">{{ formErrors.mentor_id[0] }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" size="sm" @click="dialogOpen = false">Batal</Button>
                    <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
```

- [ ] **Step 3: Verify the build compiles**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 4: Manual smoke test**

Log in as admin, open `/admin/cohorts`. Create a cohort, pick a mentor from the dropdown (populated from mentor-role users created in Task 8). Confirm the status badge reflects the dates. Edit and delete an empty cohort. Confirm `/admin/pelamar` (Tahap B) still loads (no regression from the route permission change).

- [ ] **Step 5: Commit**

```bash
git add resources/js/admin/api.js resources/js/admin/views/Cohorts.vue
git commit -m "feat: cohort management screen"
```

- [ ] **Step 6: Run the full backend suite**

Run: `php artisan test --compact`
Expected: all tests pass.

---

## Self-Review

**Spec coverage:**
- Authorization foundation (permissions + role map + cache flush) → Task 1. ✓
- Enforce permissions on existing routes + `/me` payload → Task 2. ✓
- `phone` / `is_active` + login gate + `EnsureUserIsActive` → Task 3. ✓
- User CRUD + guards (self, last active admin) + generated password → Task 4. ✓
- Cohort CRUD + date-derived status + mentor-role validation + delete guard → Task 5. ✓
- Dialog component → Task 6; Select handled via native `<select>` per house convention (spec's custom Select intentionally dropped for consistency — noted). ✓
- Auth store `can` + router meta guard + nav gating → Task 7. ✓
- Users.vue → Task 8; Cohorts.vue → Task 9; api.js groups → Tasks 8-9. ✓
- Tests for all backend behavior → Tasks 1-5. ✓

**Placeholder scan:** No TBD/TODO; every code step shows full code. ✓

**Type consistency:** `can(permission)` (store) matches router/nav usage; `row()` shapes match the Vue templates (`user.role`, `user.is_active`, `cohort.mentor?.name`, `cohort.status`, `cohort.enrollments_count`); `generated_password` produced in Task 4 and consumed in Task 8; `?role=mentor` query produced in Task 4 index and consumed in Task 9. ✓

**Note:** The spec's custom `Select` component is deliberately replaced by native `<select>` + `selectClass`, matching the existing `Applicants.vue` convention and avoiding a new primitive. This is the one intentional deviation from the spec.
