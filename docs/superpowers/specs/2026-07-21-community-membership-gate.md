# Community Membership as the Gate to the Affiliate Ladder — Design

Status: Approved model (PO-confirmed 2026-07-21), ready for planning.
Date: 2026-07-21
Corrects a concept mismatch found in production: joining "Kheedma Affiliate Community" is
currently open to anyone, and the leveled community classes are gated on general-program
completion rather than on membership. The PO's real model makes **community membership the
entry point and the gate**.

## The model (PO-confirmed)

Three tiers, two gates:

1. **Program Umum** (e.g. "Kheedma Affiliate Circle", `type = general`) — the open door.
   Anyone registers, takes the intake's classes, gets scored.
2. **Kheedma Affiliate Community membership** — the **entry point** into the community.
   Becoming a member is a deliberate act, **restricted to Program Umum graduates**
   ("Khusus", limited by qualification, NOT a price change — joining stays free of charge).
   Not every graduate joins; only those who want to go deeper do.
3. **Community classes** (`type = affiliate_community`, Level 1/2/3) — **only community
   members** may take them. Level progression still applies above Level 1.

Two distinct steps, in order: **join the community first, then register for a Level class.**
The leveled cards shown to non-members are **promotional** — they exist so people see the
career path, not as something a non-member can enter.

Business intent: scores across the ladder let Kheedma identify serious participants for real
affiliator jobs at Kheedma Agency clients.

## Governing decisions

1. **Join gate (become a member):** the person must have **completed at least one Program
   Umum intake** — attended every class of one of its cohorts — AND, where that program sets
   a score bar and has soal, cleared the bar. This is exactly the `ProgramEligibility` rule
   built on 2026-07-21; its ROLE moves from gating the leveled programs to gating the join.
2. **Class gate (take a Level class):** the person must be a **community member**
   (`Person::communityMembership` exists). Since membership already requires general
   completion, membership transitively implies it — Level 1 needs only membership. Level N>1
   additionally needs the previous level completed (existing progression, unchanged).
3. **After joining, Level 1 opens immediately** — no extra condition for now (PO may add
   later).
4. **"Khusus" = qualification-limited, not paid.** The "GRATIS" badge on the join card
   becomes "Khusus"; joining remains free of charge.
5. **Grandfather existing members:** anyone who already holds a `CommunityMembership` keeps
   it. The new join gate applies to NEW joins only.
6. **Guests and non-graduates still SEE the community** (join card + leveled cards) as a
   locked/"Khusus" teaser, so the career path stays visible; they simply cannot join or
   enter a class.

## Gating architecture (where each check lives)

`ProgramEligibility` splits into two clearly-named checks:

- `canJoinCommunity(?Person): bool` + `joinLockReason(?Person): ?string`
  — reason: `guest` | `needs_general`. True when the person completed a Program Umum intake
  (+ score bar when applicable). This gates the **join** action and the join card's state.
- `canAccess(?Person, Program)` / `lockReason(?Person, Program)` for `affiliate_community`
  programs — reason: `guest` | `needs_membership` | `needs_previous_level`.
  - guest → `guest`
  - not a member → `needs_membership`
  - member, Level 1 → unlocked
  - member, Level N>1 → unlocked iff previous level completed (the completion rule reused at
    the affiliate scope), else `needs_previous_level`.

General programs stay ungated (`canAccess` returns true — unchanged).

The "completed an intake" primitive (`hasCompletedACohort`, attend every session of one
cohort, active enrollment) is shared by both the join gate (over general programs) and the
level-progression check (over the previous affiliate level).

## Surfaces to change

1. **`CommunityController::join`** — add the join gate: a person who fails
   `canJoinCommunity` cannot create a membership (redirect back with a locked notice).
   Guests hitting join are already funneled through account provisioning; the gate runs on
   the resolved `$person` before `communityMembership()->firstOrCreate(...)`.
   **`CommunityController::show`** — surface the locked state (form replaced by a "Khusus"
   explainer with what's required) when the viewer can't yet join.
2. **`ProgramEligibility`** — implement the two-check split above. Keep `hasCompletedACohort`.
3. **`MemberAreaController` + `ProgramPageController::chooser`/`show`** — the affiliate
   section's `locked`/`reason` now come from membership-based `lockReason`; the join card
   gains a `canJoin` state from `canJoinCommunity`.
4. **`resources/views/funnel/chooser.blade.php`** — the "Gabung Komunitas" card: badge
   "GRATIS" → "Khusus", copy repositioned as the graduates' next step (not a starting
   point), and rendered locked (non-clickable "Khusus"/lock) when the viewer can't join.
   The leveled cards stay shown; their lock reason becomes membership.
5. **Copy** — the section blurb "Terbuka bertahap setelah kamu menyelesaikan program" and
   the layout description mentioning "gabung komunitas affiliator" reviewed for the new
   two-step framing. No em-dashes; "kamu" register.

## Edge cases

- **Member who is later dropped from all general cohorts:** membership persists
  (grandfathered / a member is a member). Not revoked.
- **Program Umum with no score bar:** join gate needs completion only (score skipped),
  per the 2026-07-21 rule.
- **Admin/mentor:** unaffected; they are redirected to /admin on these funnels already.
- **No affiliate programs exist yet:** the leveled section stays hidden (existing
  `@if ($affiliate->isNotEmpty())`); the join card still governs by `canJoinCommunity`.

## Delivery phases

1. **Phase A — Gating core.** `ProgramEligibility` two-check split (`canJoinCommunity` +
   membership-based `canAccess`), `Person::isCommunityMember()` helper. Feature tests:
   join-gate reasons, membership unlocks Level 1, level progression, guest.
2. **Phase B — Join enforcement.** `CommunityController::join` gate + `show` locked state.
   Tests: non-graduate blocked from joining, graduate joins, existing member unaffected,
   guest funnel.
3. **Phase C — Presentation.** Chooser join card ("Khusus" + repositioned copy + locked
   teaser), leveled cards gated by membership, member-area affiliate section, copy pass.
   Playwright verification as a graduate vs a non-graduate vs a member.

## Out of scope (deliberate)

- Paid membership / payments (Khusus is qualification, not price).
- Revoking membership on drop.
- Migrating/among existing production members beyond grandfathering.
- Notifications on becoming eligible.
- Changing the Program Umum registration flow (covered by the enrolled-intake fix already
  on branch `fix/enrollment-intake-clarity`).
