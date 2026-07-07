# Concept: Two-Door Funnel — Programs, Community & Digital Products

Status: Approved concept, ready for phase-by-phase design & planning.
Date: 2026-07-06
Supersedes/extends: `docs/Kheedma_Academy_v1_Concept.docx` (v1 concept) — this document
extends v1 into the acquisition + monetization layer. It deliberately revisits two v1
exclusions (participant login, digital content purchase) as conscious decisions, not scope drift.

## 1. Problem & goals

The v1 funnel has a single generic application form (`/daftar`) with no destination:
applications are not attributed to any offering, and every operational step after
submission is manual. Meanwhile the business needs:

1. **Multiple sellable programs** — each promotable independently, not always active.
2. **An affiliator community** — an unselective pool of potential users, independent of
   program participation.
3. **Digital products** (e.g. special-content PDF materials) that community members can
   buy and read on the site.
4. **The site as the activity hub** — announcements centralized on the site (not the
   WhatsApp group), giving members a standing reason to return.

Everything must interconnect so the data is rich: one human, one traceable history
across community, purchases, and programs.

## 2. Locked decisions (from brainstorming)

| # | Decision |
|---|----------|
| 1 | **Program 1—N Angkatan.** Program = catalog item (slug, status). Angkatan (cohort) = an execution batch of a program with dates + mentor. Existing Tahap C structures stay; they gain a parent. |
| 2 | **Registration always has a destination.** Every sign-up targets either a Program or the Community. No floating registrations. |
| 3 | **Two-door entry from day one.** `/daftar` presents two choices: register to a program (active programs only) or join the community. Built before real applicant data accumulates. |
| 4 | **Community join creates an account** (login `participant`), using the `person.user_id` link reserved in the v1 schema. |
| 5 | **Selection mode is per program**: `selective` (admin evaluates the pre-filter task, one-click placement into an Angkatan) or `instant` (auto-enroll on registration — right for paid programs where payment is the filter). Both modes run on the same Enrollment + StatusEvent machinery. |
| 6 | **Payment is hybrid**: the Order model is gateway-ready, but launches with manual bank transfer + admin confirmation. A gateway (Midtrans/Xendit) later only adds a `payment_method`. |
| 7 | **Vocabulary split**: “Angkatan” is admin-only vocabulary. Public pages use promotional language (“Dibuka Kelas Baru”, “Pendaftaran ditutup 31 Juli”) and never leak internal entity terms. |
| 8 | **Slug URLs are the primary funnel**: `/program/{slug}` is what gets shared in ads/bio/WA. The generic `/daftar` chooser is the fallback path. |

## 3. Identity rule (the core that makes data rich)

All flows — program application, community join, product purchase — resolve the human
first: **find-or-create Person by normalized phone number** (the v1 anchor). One person
accumulates Applications, a CommunityMembership, Orders, and Enrollments under a single
stable ID. A login account (User, role `participant`) is created at community join (or
first purchase) and linked via the existing `people.user_id`.

Consequences:

- A community member who later applies to a program is recognized, not duplicated.
- Purchase history is a commitment signal visible when reviewing their application.
- The admin “merge two people” safety valve (Tahap E of v1) still covers anchor collisions.

## 4. Data model

### New entities

**Program**
- `slug` (unique, stable — batch changes never change the URL), `name`, `tagline`,
  `description`, `status` (`draft` / `active` / `inactive` — catalog visibility),
  `selection_mode` (`selective` | `instant`).
- Relations: hasMany Cohort (Angkatan), hasMany Application.
- **Model correction (2026-07-07, post-Phase-1):** the registration window originally
  sat on Program; it belongs to the *intake*, so it lives on the **Angkatan**
  (`registration_opens_at` / `registration_closes_at` on cohorts). “Program is open” is
  DERIVED: status `active` AND at least one Angkatan whose window is open. This replaces
  the previously planned `cohorts.accepting_enrollments` flag entirely — the open window
  IS the intake marker, so the invalid state “door open but no batch accepting” cannot
  be represented. Consequence: an Angkatan must exist before registration can open, and
  the public landing can show “Kelas dimulai {start_date}” from the open Angkatan.
- Inactive/closed program: its slug URL stays alive, showing “Pendaftaran ditutup” + a
  community join invitation (old shared links keep harvesting leads).

**CommunityMembership**
- `people_id`, `joined_at`, `referral_source`. One row per person (unique). Joining
  creates/finds the Person, creates the `participant` User, links `people.user_id`.

**Product**
- `slug`, `name`, `price` (integer IDR), `description`, `file_path` (private storage),
  `status` (`draft` / `active` / `inactive`).

**Order**
- `people_id`, `product_id`, `amount`, `status` (`pending` → `paid` / `cancelled`),
  `payment_method` (launches with `manual_transfer`; enum open for gateway values),
  `payment_proof_path` (nullable), `paid_at`, `confirmed_by` (users FK, nullable).
- Access rule: the person can read a product iff they have a `paid` order for it.

**Announcement**
- `title`, `body`, `published_at` (nullable = draft), `created_by`.
- Audience v1: all community members (targeting by program/angkatan can come later).

### Changes to existing entities

- `applications.program_id` — FK, **nullable at the DB level** (legacy rows predate
  programs) but **required by validation for every new submission** (no floating
  applications).
- `applications.referral_source` — **new column, closing a Layer 1 gap**: the v1 concept
  mandates capturing how the applicant heard about the Academy (“cannot be reconstructed
  later”), but the current `applications` table never got the column. Added to the form
  and table in Phase 1.
- `cohorts.program_id` — FK; an Angkatan belongs to a program. Migration note: the dev
  DB already holds Angkatan rows, so the migration adds the column nullable and the
  phase plan backfills existing rows to the first Program before tightening validation.
- `cohorts.registration_opens_at` / `registration_closes_at` (nullable timestamps) — the
  intake window, moved here from `programs` (see the Program model correction above).
  The Angkatan whose window is currently open is the intake target: `instant`-mode
  registrations auto-enroll into it, and selective-mode placements default to it. (The
  earlier `accepting_enrollments` boolean is superseded by this window.)
- **Person merge (v1 Tahap E) scope grows**: when built, the merge must also repoint
  `community_memberships` and `orders` (alongside applications/enrollments) to the
  surviving Person.

### Enrollment machinery (unchanged, two triggers)

Enrollment (Person ↔ Angkatan) + append-only StatusEvent remain exactly as designed in
v1. What’s new is *who pulls the trigger*:

- **Selective program**: admin reviews the application (pre-filter task verdict) →
  accepts → one-click “Masukkan ke Angkatan” creates the Enrollment + first StatusEvent.
- **Instant program**: registration (and payment, when the program is paid) creates the
  Enrollment automatically into the program’s Angkatan whose registration window is open.
- **Removal** (“keluarkan”) in both modes = a new StatusEvent (`dropped` + reason),
  never a delete. All drop-off metrics derive from this log.

Business rationale (recorded for posterity): for the free first class, selection stays —
mentor time is the scarcest resource, and rejecting before entry is cheaper and kinder
than expelling after (Amanah). For paid offerings, payment is the filter and `instant`
mode removes the redundant manual gate.

## 5. Public funnel

```
/daftar (chooser: promotional cards)
├── Program cards (active programs only; none active → community card only)
│     └── /program/{slug}  — promo landing (shareable primary URL)
│           └── /program/{slug}/daftar — application form, program pre-linked (no dropdown)
│                 └── selective: admin gate → Angkatan
│                     instant:   auto-enrolled → Angkatan
└── Community card (always shown)
      └── /komunitas — join form (name, phone, email, password)
            └── account + membership created immediately → member area
```

- The application form lives at `/program/{slug}/daftar`, **not** `/daftar/{slug}`:
  the latter collides with the existing `/daftar/terima-kasih` and
  `/daftar/cities/{province}` routes (a program slugged “cities” would break routing).
  Nesting under the program prefix removes the collision class entirely and reads
  naturally (landing → daftar).
- The existing application form fields (identity, region, socials, pre-filter task)
  carry over; Phase 1 adds the program link and the missing `referral_source` field.
- All public copy is promotional Indonesian, no internal terms, no em-dashes.

## 6. Member area (participant login)

Separate from the admin panel (public-site login for `participant` accounts). Grows by
phase:

1. **Minimal** (with community launch): login, profile, “you’re in” landing.
2. **Products**: catalog of active products, checkout (manual transfer instructions +
   proof upload), “Produk Saya” — purchased PDFs readable in the browser via an
   authenticated streaming route (no public file URLs).
3. **Announcements**: a feed replacing WhatsApp-group announcements.

## 7. Admin capabilities added

| Capability | Permission (new) |
|---|---|
| Program CRUD + status/window | `programs.manage` |
| Community members list/search | `community.view` |
| Product CRUD | `products.manage` |
| Orders list + confirm/cancel payment | `orders.confirm` |
| Announcement CRUD | `announcements.manage` |
| Enroll accepted applicant into Angkatan / record status events | `enrollments.manage` |

Existing screens gain program awareness: applications list gets a program column +
filter; the Angkatan screen gains its program parent and the registration-window
fields. Permission seeding follows the Tahap C pattern (seeder + cache flush; admin gets
all, mentor read-only where sensible).

## 8. Build phases

Each phase is its own spec → plan → implementation cycle (Tahap C workflow).

| Phase | Scope | Ships |
|---|---|---|
| **1. Program catalog + funnel** | Program entity/CRUD/permissions, `/program/{slug}`, `/daftar` chooser, `/daftar/{slug}` with `applications.program_id`, closed-program page | Door 1 |
| **2. Community + accounts** | CommunityMembership, participant account creation, member login + minimal area, `/komunitas` | Door 2 — launched together with Phase 1 as the new `/daftar` |
| **3. Enrollment engine** | Enrollment + StatusEvent UI (one-click placement, status recording, keluarkan-with-reason), auto-enroll trigger for instant mode (targets the open-window Angkatan) | Runs the first class |
| **4. Digital products** | Product, Order (manual flow), proof upload, admin confirmation, in-browser PDF reader | Monetization |
| **5. Announcements hub** | Announcement CRUD + member feed | Retention |

Phases 1–2 are built back-to-back and announced together so the two-door entry exists
before real applicant data accumulates.

## 9. Out of scope (unchanged from v1 exclusions unless listed above)

- Payment gateway integration (prepared for, not built — decision 6).
- LMS / learning-content delivery beyond purchased PDFs.
- Progress-tracking UI, mentor self-service tooling, AI features.
- Community forum/chat features on-site (the community still lives in WhatsApp; the
  site is registration + products + announcements).
- Per-participant announcement targeting (v1 audience = all members).

## 10. Open questions (to settle inside each phase’s spec)

1. **Phase 1**: exact program card/landing content model (what fields marketing needs:
   benefits list, schedule preview, FAQ?) — settle when designing the landing.
2. **Phase 2**: password reset for members without SMTP (same admin-set pattern as team
   accounts, or defer email infra?).
3. **Phase 4**: PDF protection level — streaming-only vs watermarking with buyer
   identity; pricing figures per product (business input).
4. **Phase 3**: whether `instant` mode requires payment integration first for paid
   programs, or supports free-instant programs at launch.
5. **Phase 5**: announcement reach — program participants who never joined the community
   have no account, so they cannot see the feed. Decide whether selective-mode enrollment
   should also provision a member account (extending the feed to all participants) or
   whether announcements stay community-only at launch.
