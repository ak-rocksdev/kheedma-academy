# Assignments, Grading & Score-Based Community Gate — Design

Status: Approved design, ready for implementation planning.
Date: 2026-07-17
Supersedes (partially): the "attendance is the only measure" rule of
`2026-07-10-enrollment-attendance-completion-design.md`. Attendance recording stays exactly
as built; what changes is the *eligibility criterion* for the affiliate ladder, which moves
from "has attended" to "minimum average assignment score".
Related backlog (separate work, do not mix): datetime registration close, offline/online
cohort details, materials link (memory `class-feedback-backlog`, confirmed 2026-07-17).

## Context

The product is a mentoring funnel: students join a 30-day program (to be renamed
**"Kheedma Affiliate Circle"** — a data rename via the existing admin program form, no code)
consisting of classes with separate schedules, then graduate into the community ladder
(`programs.type = 'affiliate_community'`, `level` 1..N) already rendered as a locked/unlocked
ladder on `/daftar`.

What exists and is reused untouched:

- `programs` (`type` general|affiliate_community, `level`, `locked_message`, `status`) — the
  two-door chooser and ladder rendering in `ProgramPageController`.
- `cohorts` → `cohort_sessions` (pertemuan, ordered) → `attendances` (append-only, one row =
  hadir) with the roster UI in admin `CohortDetail.vue`.
- `enrollments` (person × cohort) + `status_events` (append-only status history).
- `App\Support\ProgramEligibility` — the single source of truth for ladder access.

What does not exist: any notion of assignments, submissions, scores, feedback, or a
configurable passing threshold.

## Governing decisions (from brainstorming, PO-confirmed 2026-07-17)

1. **Assignments attach to a session** (one per pertemuan). Because a session belongs to one
   cohort, targeting "soal sesuai jenis kelas yang diikuti" is automatic: only that cohort's
   enrolled students see it. The same mechanism serves 30-day program cohorts AND future
   community-level cohorts — nothing is hard-coded to one program.
2. **Submissions are append-only rows** (retake = new row), mirroring the `status_events`
   idiom. Version history is free; no separate history table.
3. **Effective score per assignment = score of the latest *graded* submission** (retake
   counts the latest grade, not the highest). A resubmission does not erase the previous
   grade until the mentor grades the new version.
4. **Average = mean of effective scores over ALL assignments of the program's cohort the
   student is enrolled in; an assignment never submitted counts as 0.** (Otherwise skipping
   all but one good assignment would still pass.)
5. **Threshold lives on the program**: `programs.min_average_score` (0–100, nullable).
   Null = no score gate → the legacy attendance rule keeps governing. This makes the change
   backward-compatible: existing programs behave identically until an admin fills the field.
6. Scores are integers 0–100. No per-assignment deadline for now (rhythm follows the session
   schedule); a deadline column is a trivial later addition if reality demands it.
7. Community *membership* (the free "Gabung Komunitas Affiliator" door,
   `community_memberships`) is untouched. The gate applies to the affiliate class ladder.

## Data model

Two new tables, one new column.

```
assignments
├─ id
├─ cohort_session_id   FK cascade, UNIQUE (one assignment per session)
├─ title               string
├─ body                text (the soal/instructions, plain text/markdown as typed)
├─ created_by          FK users nullOnDelete (mentor who wrote it)
├─ updated_by          FK users nullOnDelete (mentor who last edited)
└─ created_at / updated_at

assignment_submissions
├─ id
├─ assignment_id       FK cascade
├─ enrollment_id       FK cascade (identifies the student; no separate created_by)
├─ url                 string (the answer link)
├─ note                text nullable (student's optional note)
├─ score               unsignedTinyInteger nullable (0–100; null = ungraded)
├─ feedback            text nullable (mentor's written feedback)
├─ graded_by           FK users nullOnDelete
├─ graded_at           timestamp nullable
└─ created_at / updated_at   (created_at = submission time; updated_at moves on grading)

programs
└─ min_average_score   unsignedTinyInteger nullable (0–100)
```

Indexes: `assignment_submissions (assignment_id, enrollment_id)` — every read is "this
student's submissions for this assignment, newest first".

Models: `Assignment` (belongsTo session/creator, hasMany submissions), `AssignmentSubmission`
(belongsTo assignment/enrollment/grader). `CohortSession` gains `hasOne(Assignment)`;
`Enrollment` gains `hasMany(AssignmentSubmission)`.

## Derived rules (computed, never stored)

Centralized in `App\Support\AssignmentScoring` (companion to `ProgramEligibility`):

- **Submission state** per (assignment, enrollment): no rows → `belum_dikerjakan`; latest row
  ungraded → `menunggu_dinilai`; latest row graded → `dinilai`. No status column.
- **Effective score** = `score` of the latest graded submission, else null (counts as 0 in
  the average).
- **Average for an enrollment** = mean of effective scores across all assignments of the
  enrollment's cohort, missing = 0, rounded to 1 decimal for display. Enrollment with the
  latest status `dropped` never qualifies (consistent with `Enrollment::isActive()`).
- **Qualifies** = cohort's program has `min_average_score` set AND the cohort has ≥1
  assignment AND average ≥ threshold.

`ProgramEligibility` change (the single point of rework): `hasAttended(...)` is replaced by
"has a non-dropped enrollment in a qualifying prerequisite program that *passes*", where
*passes* means:

- prerequisite program has `min_average_score` **and** that enrollment's cohort has ≥1
  assignment → score rule (average ≥ threshold);
- otherwise → legacy attendance rule (≥1 attendance). This is both the null-threshold
  fallback and the misconfiguration guard (threshold set but no assignments written yet must
  not lock everyone out).

Lock reasons keep the same keys (`guest`, `needs_general`, `needs_previous_level`) so the
chooser/teaser rendering is untouched; only the human copy for the score case is new.

Data volume is tiny (tens of students × ~4 assignments), so averages are computed on the fly.
No cached/denormalized average that can go stale.

## Authorization

Follow the existing permission convention (`attendance.record` style), two new permissions:

- `assignments.manage` — create/edit assignment text (mentor of the cohort, admin).
- `assignments.grade` — score + feedback (mentor of the cohort, admin).

Member side: a student may submit only to an assignment of a session in a cohort where they
hold a non-dropped enrollment, and may only ever see their own submissions.

## Admin panel UI (Vue SPA)

Design system: reuse the existing shadcn-vue kit (`components/ui`: dialog, badge, button,
input, alert…) and the established CohortDetail "class-day cockpit" layout. No new visual
language; new pieces must look like they were always there. Mobile: follow the existing
card-list-on-mobile pattern (commit `a7ce10e`).

Status chips are one shared vocabulary across admin and member (same semantics, each side's
existing chip styling): *belum dikerjakan* = neutral/slate, *menunggu dinilai* = orange,
*dinilai* = teal.

1. **Assignment editor, inside each session block of `CohortDetail.vue`.** Per session: if
   no assignment, a quiet "+ Tulis tugas" affordance (visible with `assignments.manage`);
   if present, the assignment title with an edit action opening a dialog (title + body
   textarea), following the `CohortFormDialog` pattern. Show "diubah oleh {nama}" from
   `updated_by` in the dialog footer.
2. **Score column in the roster.** The roster table (attendance) gains one column per the
   session in focus: the student's effective score for that session's assignment, or its
   status chip when ungraded/missing. Clicking opens the **grading panel**.
3. **Grading panel** (dialog or side panel, consistent with existing dialogs): student name,
   the assignment, submission history newest-first (each version: link opening in a new tab,
   student note, submitted time; previous versions collapsed), then the grading form: score
   input (0–100) + feedback textarea + "Simpan nilai". Grading always targets the latest
   submission.
4. **Ungraded queue indicator.** Each session block shows a small count badge "N menunggu
   dinilai" so the mentor sees pending work without opening rows one by one. No separate
   queue page — the cohort detail IS the workspace (data volume does not justify more).
5. **Recap strip on cohort detail.** Per student row (or a compact summary section):
   running average and a qualification chip against the program's `min_average_score`
   ("Rata-rata 78 · Memenuhi syarat" / "Belum memenuhi"). Hidden when the program has no
   threshold.
6. **Program form** (`ProgramFormDialog`): one new optional field "Nilai rata-rata minimum"
   with helper text "Kosongkan jika kelulusan tidak diukur dengan nilai".

## Member UI (`/akun`, tab "Kelas & Program")

Design language: the existing public system — teal-900 ink, orange-600 accent, `rounded-3xl`
white/70 cards, `font-display` uppercase eyebrow labels, warm "kamu" copy, no em-dashes.

1. **Assignment card per session** inside the enrolled class card: eyebrow "Tugas", title,
   body, then by state —
   - *belum dikerjakan*: submit form (URL input + optional note textarea + "Kirim jawaban").
   - *menunggu dinilai*: confirmation state showing the submitted link + time, copy
     "Jawabanmu sudah terkirim, menunggu dinilai mentor."
   - *dinilai*: the score displayed prominently, mentor feedback in a quoted block, and a
     "Kirim ulang untuk perbaiki nilai" affordance reopening the submit form (new version).
   Own submission history shown compactly (versions with time + grade if any).
2. **Progress card per enrolled program with a threshold**: "Rata-ratamu X dari minimum Y",
   a simple progress bar toward the threshold, and the consequence stated plainly:
   below — "Capai rata-rata Y untuk membuka kelas komunitas"; met — celebratory state with a
   CTA to `/daftar` ("Kamu memenuhi syarat! Lanjut ke kelas komunitas"). No progress card
   when the program has no threshold.
3. **Chooser copy**: locked ladder cards' lock reason for the score case reads in terms of
   nilai (e.g. "Selesaikan program dengan nilai minimum untuk membuka kelas ini"), sourced
   from the same `lockReason` keys.

Validation copy is Indonesian, warm register; the URL field validates as a real URL with a
helpful message ("Formatnya harus link, contoh: https://…").

## Delivery phases (each independently shippable, review checkpoint after each)

1. **Phase 1 — Data & rules.** Migrations, models/relations, `AssignmentScoring`,
   `ProgramEligibility` rework with fallback. Feature tests: state derivation, effective
   score (latest-graded wins), average with missing=0, dropped exclusion, gate fallback
   matrix (null threshold / threshold without assignments / threshold met / not met).
   Nothing user-visible changes yet (no thresholds set in data).
2. **Phase 2 — Admin: write & grade.** API endpoints + permissions, assignment editor,
   grading panel, roster score column, ungraded badges, recap strip, program form field.
   Feature tests per endpoint incl. authorization.
3. **Phase 3 — Member: submit & progress.** Submit/resubmit endpoint (auth, ownership,
   throttle like the funnel), assignment cards, progress card, chooser lock copy. Feature
   tests: ownership boundaries, resubmit flow, progress rendering.
4. **Phase 4 — Rename & polish.** Rename the live program to "Kheedma Affiliate Circle" via
   admin (data), sweep for hard-coded old-name copy, then a design-review pass (contrast,
   mobile, empty/loading/error states) over every new surface.

UI phases (2 and 3) are built with the frontend-design + design-review workflow the project
already uses, against the design-system constraints written above.

## Out of scope (deliberate)

- Per-assignment deadlines; multiple assignments per session (unique constraint enforces
  the simple reality; relaxing it later is a one-line migration + UI loop).
- Notifications (WA/email) on new assignments or grades.
- A settings table/page (threshold lives on the program), a separate grades/queue page,
  denormalized average caching.
- Any change to the free community door (`community_memberships`) or to attendance.
- Community-level cohort content itself (Level 1..3 classes are ordinary programs/cohorts;
  they inherit all of this for free once created).
