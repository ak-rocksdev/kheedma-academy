# Enrollment, Attendance & Auto-Completion — Design (Spec 2 of 2)

Status: Approved design, ready for implementation planning.
Date: 2026-07-10
Companion: `docs/superpowers/specs/2026-07-08-program-segmentation-design.md` (Spec 1, shipped on
branch `feat/program-segmentation`) — this spec supplies the completion data Spec 1's
eligibility engine reads. Two small tail items (program thumbnail upload, dashboard stats)
are included to close every gap from the admin-vs-public audit.

## Context

The public catalog is segmented and gated (Spec 1): affiliate classes unlock when a person
has an `Enrollment` in a `Cohort` of a qualifying program carrying a `StatusEvent` with
`status = 'completed'`. Nothing writes that data yet — there is no admin flow to enroll an
accepted applicant, no session/attendance recording, and no completion marking. `Enrollment`
and `StatusEvent` models exist with full relations; `enrollments` has
`unique(people_id, cohort_id)`; `status_events` is append-only (no `updated_at`,
`created_by` nullable FK to users).

## Governing decision (from brainstorming)

**Attendance is the only recurring operational input.** Operators/admins (later possibly
mentors, per SOP) enroll participants and tick attendance per session. There is NO manual
"mark completed" button — the system derives completion automatically. Rationale: keep web
operations minimal; attendance is already the team's SOP artifact.

## Data model

1. **`cohort_sessions`** (named to avoid Laravel's `sessions` table): `id`, `cohort_id`
   (FK, cascade), `title` (string), `scheduled_at` (nullable datetime), `position`
   (unsignedTinyInteger, default 0, for ordering), timestamps.
2. **`attendances`**: `id`, `cohort_session_id` (FK, cascade), `enrollment_id` (FK,
   cascade), `marked_by` (nullable FK users, nullOnDelete), `created_at` only (a row IS
   "hadir"; unmarking deletes the row). Unique `(cohort_session_id, enrollment_id)`.
3. **`cohorts.required_attendance`**: unsignedTinyInteger nullable. Null = must attend ALL
   sessions of the cohort (evaluated dynamically at each change, so adding a session later
   raises the bar coherently). Set once at cohort create/edit — configuration, not
   recurring ops.

## Completion engine (the load-bearing rule)

`App\Support\AttendanceCompletion` — single writer of system-authored completion:

- `sync(Enrollment $enrollment): void`, called after every attendance change affecting
  that enrollment (and after session deletion, for every enrollment of the cohort).
- Let `hadir` = attendance rows for the enrollment; `requirement` =
  `cohort.required_attendance ?? cohort_sessions count`; engine is a no-op when
  `requirement === 0` (no sessions defined yet).
- **Skip** enrollments whose latest status event is `dropped` (a dropped participant is
  never auto-completed; re-activation = admin records a new manual status event, after
  which the engine resumes on the next sync).
- `hadir >= requirement` → ensure ONE system-authored `completed` StatusEvent exists:
  `status = 'completed'`, `note = 'auto:attendance'` (the system-authorship marker),
  `occurred_at = now()`, `created_by = null`.
- `hadir < requirement` → delete system-authored completed events for the enrollment
  (`status = 'completed' AND note = 'auto:attendance'` only). Manual events are NEVER
  touched; append-only applies to human-authored history, and system-authored derivations
  are correctable by the system that wrote them.
- Eligibility (Spec 1) is untouched: it keeps reading `status = 'completed'` events, so a
  participant's affiliate ladder unlocks the moment their attendance crosses the bar.

## Enrollment flows (two doors)

1. **From Applicants**: after setting an application to `accepted`, the admin UI offers
   "Masukkan ke Angkatan…" listing the applied program's cohorts (newest first). Creates
   the enrollment (linking `application_id`) + a manual `accepted` StatusEvent
   (`created_by` = actor). Skippable — enrolling later from door 2 is fine.
2. **From the cohort detail page**: an "Tambah peserta" picker listing accepted
   applications of the cohort's program whose person is not yet enrolled in this cohort.

Constraints: when `application_id` is supplied, the target cohort MUST belong to the
application's program (422 otherwise) and the application must be `accepted`; duplicate
enroll (unique person+cohort) → friendly 422. **Un-enroll**
(`DELETE`) allowed only while the enrollment has no attendance rows and no status events
other than the initial `accepted`; otherwise 422 directing the admin to record `dropped`
instead (history is preserved, per the v1 concept's append-only mandate).

**Drop**: roster action recording a manual `dropped` StatusEvent with a required note
(reason) — feeds the "what % drop, when, why" analytics goal.

## API surface (all under `auth:sanctum` + active-user middleware)

| Endpoint | Permission |
|---|---|
| `GET /api/admin/cohorts/{cohort}` (detail: cohort + sessions + roster w/ hadir counts + latest status) | `cohorts.view` |
| `POST /api/admin/enrollments` `{cohort_id, application_id \| people_id}` | `enrollments.manage` |
| `DELETE /api/admin/enrollments/{enrollment}` (guarded, see above) | `enrollments.manage` |
| `POST /api/admin/enrollments/{enrollment}/drop` `{note}` | `enrollments.manage` |
| `POST /api/admin/cohorts/{cohort}/sessions`, `PATCH/DELETE /api/admin/sessions/{session}` | `cohorts.manage` |
| `PUT /api/admin/sessions/{session}/attendance` `{enrollment_ids: int[]}` (declarative: the full "hadir" set for that session; adds/removes diff, then syncs completion) | `attendance.record` |
| `POST /api/admin/programs/{program:id}/thumbnail`, `DELETE …/thumbnail` | `programs.manage` |
| `GET /api/admin/stats` | any authenticated staff (no extra permission; returns only counts) |

**New permissions**: `enrollments.manage` (admin), `attendance.record` (admin + mentor —
prepares the "mentor fills attendance" SOP with zero rework). Added to `PermissionSeeder`
alongside the existing list; seeder stays idempotent + cache-flush.

## Admin UI

1. **`CohortDetail.vue`** (route `cohorts/:id`, meta permission `cohorts.view`; reached
   from a "Kelola" action on the cohorts list). Three sections:
   - **Peserta**: table — name, hadir `x/y` (y = effective requirement), latest status
     badge (accepted/completed/dropped), joined date; actions: Tambah peserta (picker),
     Keluarkan (drop w/ required reason), Hapus (only when un-enrollable). Completed rows
     get the success badge — this is where the team watches auto-completion light up.
   - **Sesi**: ordered list + add/edit/delete (title, scheduled_at). Deleting a session
     deletes its attendance (cascade) and resyncs completion for the cohort.
   - **Absensi**: session picker → roster checklist (dropped members shown disabled) →
     "Simpan absensi" submits the declarative hadir set.
2. **Applicants.vue / PersonDetail.vue**: after an accept action, an enroll dialog offers
   the program's cohorts. PersonDetail's enrollment history gains hadir counts.
3. **Programs.vue**: thumbnail upload field with preview + remove (accept jpeg/png/webp,
   max 2 MB; upload replaces + deletes the old file). List shows a small cover chip.
4. **Dashboard.vue**: replaces the static entity cards with live counts from
   `GET /api/admin/stats`: pelamar pending, member komunitas, Angkatan aktif, peserta
   aktif (enrolled, not dropped/completed), lulusan (completed). Tiles follow the admin
   theme; entity explainer cards may remain below the numbers.

## Public/member side

**No changes.** The moment attendance crosses the requirement, Spec 1's eligibility opens
the affiliate ladder for that person.

## Storage note (thumbnail)

Files stored on the `public` disk under `programs/`; served via `asset('storage/…')`
(Spec 1's cover partial already reads `thumbnail_path`). Local + deploy need
`php artisan storage:link` — add to deploy notes.

## Testing (PHPUnit, seeded roles+permissions)

- Completion engine matrix: reach requirement → auto event written once (idempotent);
  correction below requirement → auto event retracted, manual events untouched; custom
  `required_attendance` vs default-all; requirement 0/no sessions = no-op; dropped
  participant never auto-completes; re-activation resumes.
- **End-to-end eligibility integration**: person completes general program via attendance
  alone → `ProgramEligibility::canAccess` flips true for affiliate L1.
- Enrollment: both doors; duplicate 422; un-enroll guard (with attendance → 422, clean →
  204); drop requires note and writes `dropped` with actor.
- Attendance endpoint: declarative set add/remove diff; permission `attendance.record`
  grants mentor access, participant forbidden.
- Sessions CRUD + cascade resync on delete.
- Thumbnail: valid upload stores + old file deleted; oversize/wrong-type 422; remove
  endpoint clears path + file.
- Stats: counts correct against seeded fixtures; requires auth.

## Out of scope

- Mentor-facing UI/login flows (permission is pre-wired only).
- Notifications (SMTP still pending), data export (old Tahap F), payment.
- Editing/deleting manual status events; attendance history audit UI.

## Open considerations

- `GET /api/admin/stats` deliberately has no dedicated permission: every staff role may
  see aggregate counts; individual modules stay permission-gated.
- Session `position` keeps ordering explicit; UI orders by `position, scheduled_at`.
- `attendances.created_at` doubles as "when marked"; no `updated_at` needed (rows are
  insert/delete only) — mirrors the append-only spirit of `status_events`.

---

## Amendment (2026-07-10, later same day): completion concept removed

User decision during development review (no production data yet): there is NO "lulus"
status at this stage — the only measure is **"pernah diikuti"** (has attendance), sourced
directly from `attendances`. Consequences, applied on branch `feat/enrollment-attendance`:

- `App\Support\AttendanceCompletion` deleted; no system-authored `completed` StatusEvents
  (existing dev sample events purged by migration `2026_07_10_400001`).
- `cohorts.required_attendance` dropped. Sessions are presented as "kelas" — added
  dynamically, unknown count upfront.
- **Eligibility contract (supersedes Spec 1's)**: a Person may access affiliate level 1
  when they have an Enrollment in any cohort of a `general` program with ≥1 attendance;
  level N>1 likewise against level N-1. Attendance row = final fact per kelas
  ("enroll + hadir = selesai kelas itu, data berhenti di situ").
- Enrollment (person ↔ cohort) remains the journey spine: `accepted`/`dropped` status
  events and drop analytics are unchanged. Dashboard "graduates" tile became
  `attended_participants` ("Pernah hadir": unique persons with ≥1 attendance).
- PersonDetail now exposes the per-kelas breakdown (classes with attended state and
  recorded time) on each enrollment.
- Deferred idea (future spec): participant-side per-kelas enroll/RSVP from the member
  area, with operator verification.
