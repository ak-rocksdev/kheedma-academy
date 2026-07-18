# Batch Cohorts, Class (Session) Revival & Attendance Confirmation — Design

Status: Approved design, ready for implementation planning.
Date: 2026-07-18
Reverses (deliberately): the launch decision of 2026-07-15 "one cohort = one meeting" —
sessions were kept in the backend but hidden in the UI. This spec revives them, now with a
real operational need. PO decision 2026-07-18 ("Opsi A").
Companion: `2026-07-17-assignments-grading-score-gate-design.md` (Spec 1 — assignments &
score gate). This spec changes WHERE Spec 1's admin UI lives (per class block instead of one
card per cohort); Spec 1's data model and scoring rules are untouched and already fit.
Amends: the shipped cohort-level type/location/meeting-link feature (backlog 2026-07-17,
already in production) — those fields move DOWN to the class level, because reality moved
first (a Saturday offline meeting was rescheduled to Friday night online for one class).

## Vocabulary (PO-confirmed mapping)

| Everyday term | Entity | Example |
|---|---|---|
| Program | `Program` | "Kheedma Affiliate Circle" (30 days) |
| Angkatan / batch | `Cohort` | "Batch Agustus Putra", "Batch Agustus Putri (Online)" |
| **Kelas** (weekly meeting with its own schedule, materi, tugas) | **`CohortSession`** | "Kelas 1: Riset Produk", Sabtu 8 Agustus |

UI copy says "Kelas"; code and tables keep the existing English names (`cohort_sessions`).

## Context

The July batch ran under launch mode as 2–3 independent single-meeting cohorts, one created
per week while the mentor composed the material. Students registered per class — a
deliberate psychological device (intent requires a concrete action). The PO now wants the
next batch registered ONCE (one enrollment covers all classes, including classes created
later), with the psychological device preserved by a new per-class **attendance
confirmation** the student triggers on the web — because absences happen (most students
couldn't attend one Saturday; the meeting moved to Friday night online) and mentors need to
see them coming to arrange solutions (join online at the same hour, join the live with the
offline group, or another schedule).

What already supports this with zero new machinery:

- Registration is single-door today: an application targets a program and lands on its
  `openCohort()` (`ApplicationController::store`). With cohort = batch, "register once,
  enrolled in everything" is automatic — classes born later live *inside* the batch, so no
  back-fill, no batch entity, no auto-enroll engine.
- Session CRUD API endpoints already exist (`POST /cohorts/{cohort}/sessions`,
  `PATCH/DELETE /sessions/{session}`, `PUT /sessions/{session}/attendance`) — dormant, not
  removed. Attendance is per session by schema. Spec 1's assignments are per session by
  design.

## Governing decisions

1. **Cohort = batch; CohortSession = class.** Admins keep creating classes one at a time
   while composing material — same workflow, new container.
2. **Stop auto-seeding the invisible session** (`CohortController::store`). Admins create
   classes explicitly through the revived UI. Existing single-session cohorts keep their
   session untouched.
3. **Class-level venue**: `type` (offline|online), location fields, and `meeting_url` move
   from `cohorts` to `cohort_sessions`. The cohort keeps `materials_url`, the registration
   window, and the mentor. Data migration copies each cohort's venue values onto its
   existing session(s), then drops the cohort columns — one source of truth, no dual-write.
4. **Attendance confirmation (konfirmasi kehadiran) is intent, not attendance.** A student
   declares per upcoming class: attending / cannot attend (+ optional note). It never
   writes `attendances` — the mentor still records actual presence. One mutable row per
   (class, student); history is not needed, the latest intent is the signal.
5. **Legacy July-batch cohorts stay as they are** (single-meeting angkatan). Spec 1's
   average is per person per program, which covers both shapes seamlessly: new shape = one
   enrollment × 4 class assignments; legacy = 3 enrollments × 1 assignment each. No formula
   change.
6. **Putri track is data, not code**: a separate cohort whose classes are all online (no
   female mentor yet, offline may come later).
7. **Selling offline** is a fixed copy block shown on offline classes (direct mentor
   monitoring, video-editing review, live social-posting practice) — static Indonesian
   copy, not a CMS feature, until the PO asks for editability.

## Data model

```
cohort_sessions (existing table, new columns)
├─ type              string nullable ('offline'|'online')
├─ location_name     string nullable
├─ location_address  string nullable
├─ location_lat      decimal(10,7) nullable
├─ location_lng      decimal(10,7) nullable
└─ meeting_url       string nullable

session_confirmations (new)
├─ id
├─ cohort_session_id  FK cascade
├─ enrollment_id      FK cascade
├─ status             string ('attending' | 'cannot_attend')
├─ note               text nullable (student's reason/context)
└─ created_at / updated_at    UNIQUE (cohort_session_id, enrollment_id)
```

Migration order: add session columns → copy venue values from each cohort to its sessions →
drop `cohorts.type/location_*/meeting_url`. The venue helpers on `Cohort` (`isOnline()`,
`mapsUrl()`, `mapsEmbedUrl()`, `mapsDirectionsUrl()`, `googleCalendarUrl()`'s location part)
move to `CohortSession`; callers (member area, admin) re-point per class.

Models: `SessionConfirmation` (belongsTo session/enrollment). `CohortSession` gains
`hasMany(SessionConfirmation)` + `hasOne` per-enrollment accessor as needed; `Enrollment`
gains `hasMany(SessionConfirmation)`.

Confirmation rules:

- A student may set or change their confirmation **until the class starts**
  (`scheduled_at`); after that the row freezes (attendance takes over as the fact).
- States are derived per (class, student): *belum konfirmasi* (no row) / *hadir* /
  *berhalangan*. No deadline mechanics beyond the start time.
- Authorization mirrors submissions (Spec 1): only for one's own active enrollment in the
  class's cohort.

## Admin UI (Vue SPA — `CohortDetail.vue` rework)

The cohort detail becomes a batch cockpit: a **class list** replaces the single hidden
mainSession assumption. Same shadcn-vue kit, same dialog patterns.

1. **Class blocks.** One block per class, ordered by `position`/schedule: title, date-time,
   venue chip (offline → location name, online → "Online"), and the block's tools. A
   "+ Tambah kelas" button opens the class dialog: title, datetime picker, type toggle
   revealing either the existing `LocationPicker` (offline) or a meeting-URL input
   (online). Edit/delete reuse the dormant session endpoints (delete guarded by existing
   attendance/assignment data — confirm dialog states what exists).
2. **Confirmation recap per class block**: "12 hadir · 3 berhalangan · 8 belum konfirmasi",
   expandable to the list of names with notes (the mentor's signal for arranging
   solutions). Read-only for admins — the row belongs to the student.
3. **Attendance per class.** The existing roster table stays single-table but is scoped to
   a selected class (the class blocks act as the selector); the hadir toggle and progress
   bar work exactly as today, just per class.
4. **Spec 1 surfaces relocate here**: the assignment card lives inside each class block;
   the roster's "Nilai" column follows the selected class. (Spec 1's rules are unchanged —
   assignments were per-session all along.)

## Member UI (`/akun`, tab "Kelas & Program")

The enrolled-program card becomes a **class timeline** (design language unchanged:
teal/orange, rounded-3xl cards, "kamu" register):

1. Each class row: title, schedule (`startLabel`-style formatting moves per class), venue
   chip; location map/embed or meet link (the existing collapsible location object moves
   from cohort level to class level, shown for enrolled members only).
2. **Upcoming class → confirmation prompt**: "Bisa hadir di kelas ini?" with two actions —
   "Insya Allah hadir" / "Berhalangan" (opens an optional note field: "Kabari kendalamu,
   biar mentor carikan solusi"). Current choice shown as a chip, changeable until the
   class starts. This is the concrete per-class action that preserves the psychological
   commitment the per-class registration used to provide.
3. **Past class** → attendance result and (per Spec 1) the assignment card with score and
   feedback.
4. **Offline benefit block** on offline classes: a short "Kenapa hadir offline?" list —
   pemantauan langsung mentor, review editing video oleh mentor, praktik posting sosmed
   langsung.

## Public catalog

The program landing page lists the batch's classes (title + date + type only — no location
details or links for guests): the visitor learns the program contains many classes before
registering. Reuses the open cohort's session list; no new query concepts.

## Sequencing with Spec 1

- Spec 1 Phase 1 (data & rules) is independent — either order.
- **This spec's Phase A must land before Spec 1 Phase 2/3 (admin & member UI)**, because
  those UIs mount inside the class blocks/timeline this spec builds.

## Delivery phases (each independently shippable)

1. **Phase A — Class revival.** Migrations (session venue columns, data copy, drop cohort
   columns, stop auto-seed), model/helper moves, admin class blocks + per-class attendance,
   member/public venue display re-pointed per class. Feature tests: migration data copy,
   session CRUD authorization, attendance per class unchanged behavior.
2. **Phase B — Attendance confirmation.** `session_confirmations` table + model, member
   set/change endpoint (own enrollment, before start only), member prompt UI, admin recap
   per class. Feature tests: ownership, freeze-at-start, state derivation, recap counts.
3. **Phase C — Catalog & copy.** Public class list on the program page, offline benefit
   block, copy pass.

## Out of scope (deliberate)

- Notifications (WA/email) for confirmations or schedule changes — the recap is pull-based
  for now.
- Auto-enroll machinery, batch entities, enrollment back-fill — made unnecessary by
  cohort = batch.
- Confirmation history/audit trail (single mutable row; `updated_at` is enough).
- Editable offline-benefits CMS content.
- Rescheduling workflow (mentor edits the class's datetime/venue by hand; confirmations
  simply remain editable until the new start time).
