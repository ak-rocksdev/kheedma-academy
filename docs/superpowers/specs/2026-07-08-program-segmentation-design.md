# Program Segmentation & Eligibility — Design (Spec 1 of 2)

Status: Approved design, ready for implementation planning.
Date: 2026-07-08
Source: `docs/kheedma_academy_program_segmentation_note.md` (meeting 8 Juli 2026) + brainstorming decisions below.

## Context

The public site currently shows every open program in one flat list on the `/daftar`
chooser. There is no notion of program type, participant journey stage, or access
eligibility. The meeting note requires separating **general (intro) programs** from the
**Kheedma Affiliate Community** classes, showing locked classes as teasers, and gating
access on the visitor's history.

Existing structure this design builds on (verified in code):

- `Program` — catalog item (slug, name, tagline, description, status draft/active/inactive,
  selection_mode). Public pages: `/daftar` chooser + `/program/{slug}` landing + apply form.
- `Cohort` (Angkatan) — belongs to a Program, owns the registration window.
- `Person` ↔ `User` — public members can log in (community door); member area at `/akun`.
- `CommunityMembership` — one row per Person who joined the community.
- `Application` — a person's **self-proposal** to a program (status pending/accepted/rejected).
- `Enrollment` + `StatusEvent` — models and relations exist; **no write path yet**.

## Decisions (from brainstorming)

1. **Two program types**: `general` (kelas untuk user umum; many classes allowed) and
   `affiliate_community` (many classes, tiered by level).
2. **Levels are sequential**: each affiliate class is one Program row with a numeric
   `level`; completing level N unlocks level N+1.
3. **Level 1 gate**: completing **any** general program unlocks affiliate Level 1.
   No per-program prerequisite field (uniform rule; YAGNI on `required_program_id`).
4. **Completion is attendance-backed** (user decision): a program counts as completed
   only when the admin has marked the person "hadir" and completed. The *mechanics*
   (sessions, attendance UI, marking completion) belong to **Spec 2 (Enrollment,
   Attendance & Completion)**. This spec only consumes the resulting data.
5. **Interim behavior**: the eligibility rules are implemented for real from day one.
   Until Spec 2 ships and admins record completions, no one qualifies — every affiliate
   class renders as a locked teaser. No temporary bypass logic to remove later.
6. **Terminology**: "user umum" = visitor with no DB record; "member" = has a `Person`
   record (and possibly a login). Staff `User` accounts are unrelated to this feature.

## Eligibility contract (the load-bearing definition)

A Person has **completed a program** when they have an `Enrollment` in any `Cohort` of
that program that has a `StatusEvent` with `status = 'completed'`. Spec 2 will create
those events (admin marks completion, backed by attendance); this spec only reads them.

Access rules, evaluated by one service:

| Program type | Accessible when |
|---|---|
| `general` | Always (guest or member) — existing funnel unchanged |
| `affiliate_community` level 1 | Person logged in AND has completed ≥1 `general` program |
| `affiliate_community` level N>1 | Person logged in AND has completed an `affiliate_community` program of level N-1 |

Guests (not logged in) are never eligible for affiliate classes; their lock popup routes
them to the general funnel / community door instead.

## Design

### 1. Data — migration on `programs`

- `type` string, default `'general'` — values `general | affiliate_community`.
- `level` unsignedTinyInteger, nullable — required (≥1) when type is
  `affiliate_community`, null otherwise. Enforced by validation, not DB constraint.
- `locked_message` text, nullable — per-class popup copy; when null the UI falls back to
  a global default message.
- Backfill: existing rows keep `type = 'general'` (column default suffices).

### 2. `ProgramEligibility` service (`app/Support/ProgramEligibility.php`)

Small, stateless, the single source of truth:

- `canAccess(?Person $person, Program $program): bool`
- `lockReason(?Person $person, Program $program): ?string` — null when accessible;
  otherwise one of `guest` (not logged in), `needs_general` (no completed general
  program), `needs_previous_level` (level N-1 incomplete). The UI maps reasons to copy
  and CTAs.

Completion lookup uses the eligibility contract above (exists-query over
enrollments → cohorts → program type/level + status events), eager-loading safe.

### 3. Public chooser `/daftar`

Two sections replace the flat list:

- **Program** — `general` programs open for registration (existing card style + CTA).
- **Kheedma Affiliate Community** — ALL `active` affiliate classes ordered by level,
  regardless of registration window, each rendered one of three ways:
  - unlocked + registration open: normal card linking to the program page;
  - unlocked + registration closed: normal card, "Pendaftaran ditutup" note — the
    landing page already handles the closed state (existing behavior);
  - **locked teaser** (ineligible): dimmed card, "Terkunci" badge, level shown; clicking opens a
    Blade partial modal toggled by a few lines of vanilla JS in `resources/js/app.js`
    (the public bundle has no Alpine/Vue — verified; no new dependency) with the
    class's `locked_message` (or global default) and a
    context CTA — guest → "Daftar program" (scroll to general section) + "Gabung
    Komunitas"; logged-in but ineligible → explanation of the requirement
    (complete a general program / complete level N-1).

The community door card stays as-is.

### 4. Program landing + server-side guard

- `/program/{slug}` stays viewable for affiliate classes (teaser value): hero + copy
  render, but the apply CTA is disabled with the same lock modal when ineligible.
- **Server-side enforcement**: `GET /program/{slug}/daftar` and the `POST` store route
  reject ineligible visitors using the same `ProgramEligibility` service (redirect back
  to the program page with the lock message). UI state is never the only gate.
  Insertion point verified: `ApplicationController::create/store` already guard with
  `isOpen()` → redirect; the eligibility check slots directly after, resolving the
  person via `$request->user()?->person` (User→Person is a HasOne).

### 5. Member area `/akun`

New "Program untuk Anda" section: affiliate classes listed with unlocked/locked state
for the logged-in person (same service, same modal), so members can see their ladder.

### 6. Admin panel (Programs module)

- Form fields added: **Tipe** (select: Program Umum / Affiliate Community), **Level**
  (number input, shown only when tipe = affiliate; required there), **Pesan terkunci**
  (textarea, optional, with placeholder showing the global default).
- List: type badge (+ level for affiliate), so admin sees the catalog segmentation at
  a glance.
- Validation (server): `type in:general,affiliate_community`; `level` required-integer-min:1
  when affiliate, prohibited/null when general.

### 7. Testing (PHPUnit feature tests)

- `ProgramEligibility` matrix: guest / member without completions / member with
  completed general / member with completed affiliate L1 — against general, affiliate
  L1, affiliate L2 programs (completion fixtures built directly on
  enrollments + status_events tables).
- Chooser: sections render; locked affiliate shows teaser state; unlocked shows CTA.
- Guard: ineligible `POST /program/{slug}/daftar` is rejected server-side; eligible
  passes.
- Admin: create/update affiliate program requires level; general forbids level;
  type/level/locked_message round-trip in the API.

## Out of scope (Spec 2: Enrollment, Attendance & Completion — next)

- Creating Enrollments from accepted applications (admin "masukkan ke Angkatan").
- Sessions & attendance (absensi "hadir") management in the admin panel — **mandatory
  requirement**, elevated from the meeting note's Phase 4.
- Marking completion (admin manual, attendance-backed) → the data this spec consumes.
- Status Event recording UI, notifications, payments.

## Open considerations

- Global default `locked_message` (used when a class has none):
  "Kelas ini khusus member yang sudah menyelesaikan program sebelumnya. Selesaikan
  program yang sedang dibuka dulu, lalu kelas ini terbuka untukmu." Stored as a value
  in a new `config/kheedma.php` (the project has no `lang/` directory — verified; a
  config value keeps copy edits out of logic code without adding locale machinery).
- If a future affiliate class must skip the chain (e.g. invite-only masterclass), the
  uniform rule needs a per-program override — deliberately deferred (YAGNI) and easy to
  add as a nullable column later.
