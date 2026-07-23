# Promote a Participant Account to Staff — Design

Status: Approved (PO-confirmed 2026-07-23), ready for planning.
Date: 2026-07-23

Real case that triggered this: `hafidarrasyid39@gmail.com` (users #3, role `participant`,
linked to Person #3) on production may need to become a mentor or admin. Today that is
impossible through the UI: the Tim (Users) page only lists accounts that already hold the
`admin` or `mentor` role, and the Person account panel deliberately manages only the
participant PIN and active flag. The only route is tinker on the VPS.

## Governing decisions (PO-confirmed)

1. **Entry point: the Tim (Users) page.** A new "Angkat dari Peserta" action lives next to
   the add-staff button. The Person detail page is NOT touched.
2. **Promote is a pure role switch.** `syncRoles([role])` replaces `participant` with
   `mentor` or `admin`. Nothing else changes: the password (PIN) carries over untouched
   (PO explicitly accepts the staging-grade `112233` PINs), `is_active` is left as-is, and
   the `Person` link, enrollments, and attendance history are preserved.
3. **One role per user stays the rule.** No multi-role; the promoted user loses access to
   the member area and gains the staff panel. This matches the existing Tim UI assumption
   (`getRoleNames()->first()`).
4. **No demote path** for now (YAGNI). If a promotion was a mistake, the admin can edit or
   delete the staff account through the existing Tim dialogs; a real demote flow waits for
   a real case.
5. If a promoted account's PIN should change later, the existing staff edit dialog already
   covers it — the user appears in the Tim list immediately after promotion.

## Backend

Two additions to `App\Http\Controllers\Api\Admin\UserController`, both behind the existing
`permission:users.manage` middleware group in `routes/api.php`:

1. **`GET /admin/users/promotable?q=`** — search accounts holding the `participant` role by
   name, email, or phone (same `like` pattern as `index`), ordered by name, returning
   `{id, name, email, phone}` rows. Used by the search dialog. `q` optional like `index`;
   without it, return the participant list (production scale is small). Cap results
   (e.g. `limit(20)`) so the endpoint stays cheap as participants grow.
2. **`POST /admin/users/{user}/promote`** — payload: `role` required, `in:admin,mentor`.
   Guards:
   - Target must currently hold the `participant` role; otherwise a `ValidationException`
     ("Akun ini sudah staf.").
   - No self/last-admin guard needed: the actor is staff, the target is a participant, so
     neither guard can trigger.
   Action: `$user->syncRoles([$data['role']])`. Response: the same `row()` shape the other
   endpoints return, so the frontend can splice it into the staff list.

Route names/paths English per the language convention.

## Frontend (`resources/js/admin/views/Users.vue`)

A new "Angkat dari Peserta" button beside the existing add-staff button opens a dialog:

1. **Search step** — a debounced text input queries `/admin/users/promotable?q=`; results
   render as name + email rows; clicking one selects it.
2. **Role step** — radio/select `mentor` (default) or `admin`, mirroring the existing
   role select.
3. **Submit** — goes through the house-style confirm dialog (binding PO decision: every
   confirming action uses it) with copy naming the person and the target role, then
   `POST /promote`, success toast, dialog closes, staff list refreshes (the promoted user
   now appears in it).

Validation errors from the backend render in the dialog the same way the existing create
and edit dialogs render theirs. UI copy is Indonesian, warm register ("kamu"), no em-dashes;
identifiers and routes stay English.

## Testing (PHPUnit feature tests)

- `promotable` returns only participant-role accounts; staff never appear; `q` filters by
  name/email/phone; requires `users.manage` (mentor gets 403).
- `promote` happy path: participant becomes mentor (and admin variant); role is replaced,
  not added; password hash unchanged; `Person.user_id` link and enrollments untouched;
  response contains the `row()` shape.
- `promote` failure paths: target already staff → validation error; invalid role value;
  missing `users.manage` permission → 403; unauthenticated → 401.

## Out of scope

- Demote (staff → participant).
- Multi-role support.
- Any change to `PersonController::updateAccount` or the Person detail page (its existing
  guard already redirects staff-account management to the Tim menu, which stays correct).
- PIN/password changes during promotion.
