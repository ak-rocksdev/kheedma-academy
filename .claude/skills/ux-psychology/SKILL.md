---
name: ux-psychology
description: Use when evaluating, reviewing, or designing any user-facing flow, page, or feature — especially when feedback risks being a feature checklist ("add X, show Y") instead of explaining how the design shapes user behavior, motivation, habit, or trust.
---

# UX Psychology Evaluation Lens

## Overview

Evaluate flows by the behavior they produce, not the data they display. Every finding must name the psychological lever it pulls and the user behavior it changes. Source: `knowledge_ux.md` (Cialdini, Fogg B=MAP, Eyal's Hooked, Kahneman, Deci & Ryan).

## How to Evaluate a Flow

Walk the journey in four moments, applying the levers table at each:

1. **Arrive** — does the user get value before being asked for anything (reciprocity)? Is the next action pre-selected for their benefit (smart default / status quo bias)?
2. **Orient** — is there one obvious thing to do (decision fatigue)? Is detail revealed gradually (progressive disclosure)?
3. **Act** — does the user see progress toward their goal (goal gradient)? Are key actions framed by what they'd lose by skipping (loss aversion), not just gain?
4. **Return** — what triggers the next visit (Hooked: trigger → action → variable reward → investment)? Does the flow build competence, autonomy, relatedness (SDT)?

## Levers Quick Reference

| Lever | Effect | Apply as |
|---|---|---|
| Smart defaults | Users keep pre-set choices | Pre-select the beneficial option |
| Goal gradient | Effort rises near a goal | Progress bars, countdowns, checklists |
| Loss aversion | Losses feel ~2x gains | "Jangan sampai terlewat…" for key actions |
| Reciprocity | Value given first gets returned | Useful content before any ask |
| Social proof | People follow others | Counts, testimonials, peer presence |
| Progressive disclosure | Fewer choices = better decisions | Essentials first, detail on demand |
| SDT | Autonomy, competence, relatedness drive intrinsic motivation | Control, achievable challenge, human connection |

## Output Contract

Each finding is one line of: **journey moment → lever → concrete change → behavior it produces**. Rank by behavioral impact, not implementation ease. Cap the list; a short ranked list beats a feature inventory.

## Anti-Patterns to Flag (and never recommend)

Dark patterns, choice overload, meaningless badges, over-reliance on extrinsic rewards, opaque data practices — trust lost costs more than any lever gains.

## Common Mistakes

- Feature-checklist review ("add mentor name") without naming the lever or behavior — that is an incomplete finding here.
- Framing everything as loss aversion; reserve it for genuinely high-stakes actions.
- Adding progress indicators to things that aren't goals.
