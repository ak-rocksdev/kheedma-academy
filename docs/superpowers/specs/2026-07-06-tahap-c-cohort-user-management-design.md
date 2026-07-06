# Tahap C — Cohort & User Management (granular Spatie authorization)

Status: Approved design, ready for implementation planning.
Date: 2026-07-06

## Context

Kheedma Academy v1 is a Laravel 13 marketing site (Blade) plus an admin SPA (Vue 3,
Sanctum cookie auth). Layer 1 (public application form) is done. Layer 2 (admin panel)
is partially built: admin auth + shadcn-vue shell + the Applicants module (Tahap B —
list/search applications, view a Person's cross-attempt history, record the intake
decision and pre-filter task result).

The v1 concept (`docs/Kheedma_Academy_v1_Concept.docx`, §7) lists six required admin
capabilities. Done so far: (1) see/search applicants + person history. This tahap builds
the foundation for the operational core: **cohort management** and the **user/team
accounts** that cohorts reference. Enrollment, Status Events, Person merge, and export
are explicitly out of scope here (Tahap D/E/F).

Decided during brainstorming:

- **Mentor role in v1: admin-only.** Mentors are `User` accounts (role `mentor`) that
  admins create and assign to cohorts as a reference. No mentor-scoped views or login
  restrictions are built now, but the data model keeps that path open.
- **User management scope: full.** A team-management module: list all accounts, create,
  edit, assign role, set/reset password, activate/deactivate.
- **Password & status: admin-managed, no mail server.** Admin types a password (or
  generates a random one shown once). Deactivation is an `is_active` flag that blocks login.
- **Cohort status: derived from dates** (Upcoming / Active / Ended). No status column.
- **Authorization: granular Spatie permissions.** Move from raw role checks to permissions
  assigned to roles. Spatie Permission v8 is already installed; roles `admin`, `mentor`,
  `participant` exist; 0 permissions defined so far (role-based only).

## Goals

1. Formalize authorization on granular permissions, enforced on both existing and new routes.
2. Full user/team management (CRUD + role + password + active status), admin-only, with
   safety guards.
3. Cohort management (CRUD) with a mentor reference and date-derived status.
4. Feature-test coverage for all new backend behavior.

## Non-goals (later tahap)

- Enrollment (assign people to cohorts) and Status Events — Tahap D.
- Merge two Person records — Tahap E.
- Export all data — Tahap F.
- Mentor login / mentor-scoped views — future cohort problem.
- Backfilling tests for Tahap B (debt noted, not addressed here).

## Design

### 1. Authorization foundation (Spatie permissions)

Permissions (guard `web`):

| Permission            | Used by                                  |
|-----------------------|------------------------------------------|
| `applications.view`   | Applicants list/detail (Tahap B)         |
| `applications.review` | Record intake decision (Tahap B)         |
| `people.view`         | Person detail (Tahap B)                  |
| `people.merge`        | Defined now, used in Tahap E             |
| `cohorts.view`        | Cohort list (Tahap C)                    |
| `cohorts.manage`      | Cohort create/update/delete (Tahap C)    |
| `users.manage`        | User/team management (Tahap C)           |
| `data.export`         | Defined now, used in Tahap F             |

Role → permission mapping:

- `admin` → all permissions.
- `mentor` → `applications.view`, `people.view`, `cohorts.view` (read-only; not logging
  in yet in v1 but ready).
- `participant` → none.

Enforcement:

- New idempotent `RolePermissionSeeder`: `Permission::findOrCreate` for each, then
  `Role::findOrCreate` + `syncPermissions` per role. Called from `DatabaseSeeder` **before**
  `AdminUserSeeder`.
- Existing routes migrate from `role:admin|mentor` to `permission:<name>`:
  - `GET /api/admin/applications` → `permission:applications.view`
  - `PATCH /api/admin/applications/{application}` → `permission:applications.review`
  - `GET /api/admin/people/{person}` → `permission:people.view`
- `AuthController::profile()` adds `permissions` (via `getAllPermissions()->pluck('name')`)
  alongside `roles`, so the SPA can gate navigation and controls.

### 2. Data changes

Migration adding to `users`:

- `phone` string, nullable (the mentor "contact" from the concept's thin-entity model).
- `is_active` boolean, default `true`.

`User` model: add `phone`, `is_active` to fillable; cast `is_active` to boolean.

Login/session:

- `AuthController::login` rejects users with `is_active = false` (message: account
  deactivated), in addition to the existing staff-role gate.
- New `EnsureUserIsActive` middleware on the authenticated API group so a session that is
  deactivated mid-use is rejected (401/403), letting the SPA auto-logout.

### 3. User / team management module (admin-only)

`App\Http\Controllers\Api\Admin\UserController` — `index`, `store`, `update`, `destroy`,
behind `permission:users.manage`.

- `index`: list all staff accounts (admin + mentor), searchable by name/email/phone,
  returns id, name, email, phone, roles, `is_active`. Supports optional `role` filter
  (used to populate the cohort mentor dropdown, e.g. `?role=mentor`).
- `store`: validate name, email (unique), phone (nullable), role (`in:admin,mentor`),
  password (required, min length) OR a `generate_password` flag that produces a random one.
  Assigns the role via `syncRoles`. When generated, the plaintext password is returned once
  in the response for the admin to copy.
- `update`: edit name, phone, role; optional password change; toggle `is_active`.
- `destroy`: delete an account.

Safety guards (return 422/403 with a clear message):

- Cannot deactivate, demote (remove admin role from), or delete **yourself**.
- Cannot remove the **last admin** (demote or delete) — at least one active admin must remain.

### 4. Cohort management module

`App\Http\Controllers\Api\Admin\CohortController` — `index`, `store`, `update`, `destroy`.
`index` behind `permission:cohorts.view`; write actions behind `permission:cohorts.manage`.

- Fields: `name` (required), `start_date` (nullable date), `end_date` (nullable date,
  after_or_equal start_date), `mentor_id` (nullable, must reference a `User` holding the
  `mentor` role — validated with a rule/closure).
- Status is a **derived accessor** on `Cohort`: `Upcoming` (start in the future or no start),
  `Active` (started, not yet ended), `Ended` (end date passed). No stored column.
- `index` returns cohorts with mentor name and derived status; eager-loads mentor to avoid N+1.
- `destroy`: guard that blocks deletion if the cohort has enrollments (relation is empty now,
  but the guard is written so Tahap D is safe).

### 5. Frontend

- Router (`resources/js/admin/router.js`): add `users` (`/admin/users`) and `cohorts`
  (`/admin/cohorts`) routes with `meta.permission`. Router `beforeEach` checks the required
  permission and redirects unauthorized users to the dashboard.
- Auth store (`stores/auth.js`): store `permissions`; add `can(permission)` helper.
- `AppShell.vue`: render sidebar nav items conditionally on `can(...)`.
- New shadcn-vue components following existing conventions (`components/ui/*`): a **Dialog**
  (create/edit modals) and a **Select** (role and mentor dropdowns). Reuse existing `Button`,
  `Input`, `Badge`.
- Views: `Users.vue` (list + search + create/edit modal + activate/deactivate) and
  `Cohorts.vue` (list + create/edit modal + delete).
- `api.js`: add `users` and `cohorts` endpoint groups.

### 6. Testing (PHPUnit feature tests)

- Authorization: a `mentor`/unauthorized user is forbidden from `users.manage` and
  `cohorts.manage` endpoints; admin is allowed. `/me` includes the `permissions` array.
- Users: admin can list/create/update/deactivate; created account gets the requested role;
  generated password is returned and works for login; guards — cannot self-deactivate,
  cannot remove the last admin.
- Login: a deactivated user cannot log in; reactivating restores access.
- Cohorts: create/update/delete; `mentor_id` must reference a mentor (non-mentor rejected);
  status accessor derives Upcoming/Active/Ended correctly; delete guard blocks when
  enrollments exist (set up via factory once Enrollment factory is available, otherwise assert
  the guard path directly).
- Factories/states: add a `UserFactory` mentor state (and admin state) as needed; a
  `CohortFactory`.

## Testable UI surfaces (for user acceptance)

1. `/admin/users` — list, search, create (with password shown once), edit, activate/deactivate;
   guard behavior (self, last-admin).
2. Deactivated-account login rejection.
3. `/admin/cohorts` — list with derived status badges, create/edit/delete, mentor dropdown
   limited to mentor-role users, status changes with dates.
4. Permission-gated nav/controls.
5. Existing Applicants module still works after route permission migration (no regression).

## Open considerations

- Password policy: minimum length and whether to enforce complexity — default to a sane
  minimum (e.g. 8) unless the user wants stricter.
- Whether `EnsureUserIsActive` should also invalidate the session server-side on hit
  (cleaner) versus just returning 401 (simpler) — default to returning 401 and letting the
  SPA clear state.
