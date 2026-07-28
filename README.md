# Lingo Talk — Product Blueprint

**The operating system for language institutes.**

Lingo Talk is a multi-tenant SaaS platform that gives every language institute a private
workspace to run its entire operation: admissions, academics, scheduling, attendance,
exams, tuition, communication, and reporting — in one system instead of a spreadsheet,
a WhatsApp group, a paper ledger, and someone's memory.

This repository contains the **product blueprint** (A–R), a built **Persian marketing site**,
and an interactive **prototype of the three dashboards**. There is no backend yet — by design.
The order was: define the product → define its structure → define its workflows → prove the
critical screens → then build the real thing.

---

## How to read this

Documents A–O are the core blueprint; P–R are binding amendments added as the scope
sharpened. Read in order for the full picture; jump directly for a specific working session.

| # | Document | Use it when |
|---|---|---|
| A | [Product summary](docs/A-product-summary.md) | You need the one-page definition, positioning, and non-goals |
| B | [Target users and use cases](docs/B-target-users.md) | Segmenting, writing personas, prioritising features |
| C | [Main business problem](docs/C-business-problem.md) | Writing sales/marketing copy, validating with institutes |
| D | [Key value proposition](docs/D-value-proposition.md) | Landing page copy, demo script, objection handling |
| E | [Information architecture](docs/E-information-architecture.md) | Building navigation, routing, sitemap |
| F | [Page-by-page breakdown](docs/F-page-breakdown.md) | Designing or building any individual screen |
| G | [Main user flows](docs/G-user-flows.md) | Wireframing journeys, writing acceptance criteria |
| H | [Dashboard structure](docs/H-dashboard-structure.md) | Designing the four role dashboards |
| I | [Data and entity model](docs/I-data-model.md) | Schema design, API design, tenancy decisions |
| J | [Permissions model](docs/J-permissions.md) | Auth, RBAC, security review |
| K | [Subscription / monetization](docs/K-monetization.md) | Pricing page, billing logic, plan gating |
| L | [MVP vs future roadmap](docs/L-roadmap.md) | Sprint planning, scope cuts, investor narrative |
| M | [UI / UX direction](docs/M-ui-ux-direction.md) | Design system, tokens, components, RTL rules |
| N | [Risks and edge cases](docs/N-risks-edge-cases.md) | QA planning, defensive design, support playbooks |
| O | [Final recommendation](docs/O-final-recommendation.md) | Deciding what to build first and how to launch |
| **P** | **[تطبیق با بازار ایران](docs/P-iran-market.md)** | **Binding amendment to A–O. Read before building anything.** |
| Q | [کلاس آنلاین](docs/Q-online-classroom.md) | Virtual classroom: provider adapter, BBB vs Meet trade-off, auto-attendance, recordings |
| R | [تکلیف و نمره‌دهی](docs/R-assignments-grading.md) | Assignments, audio submissions, rubrics, the grading screen, gradebook |

---

## The five-line version

1. **Who it's for:** language institutes (1–50 branches), independent tutors, and corporate
   language training units.
2. **What it replaces:** Excel registration sheets, paper attendance, WhatsApp announcements,
   handwritten payment ledgers, and a manager who is the only person who knows anything.
3. **How it works:** each institute gets an isolated tenant workspace with its own users,
   roles, branches, terms, classes, students, money, and reports.
4. **Why they pay:** it removes 15–25 hours of admin work per staff member per month, cuts
   tuition leakage, and makes the institute look like a professional organisation to students.
5. **How we make money:** tiered monthly/annual subscription priced on active students and
   branches, plus usage add-ons (SMS credits, online-payment fees) and a paid onboarding
   package for larger institutes.

---

## Market (confirmed)

The launch market is **Iran — Persian-speaking language institutes and Iranian learners**,
and the product surface is **Persian-first**. This is not a cosmetic choice; it shapes the
product core, and the binding specification is [P. تطبیق با بازار ایران](docs/P-iran-market.md),
which overrides A–O wherever they conflict:

- **Jalali (Shamsi) calendar is a first-class citizen**, not a display filter. Terms,
  schedules, invoices, and reports are authored in Jalali and stored in UTC ISO-8601.
- **RTL-first UI** with a fully mirrored layout system; English LTR is the secondary locale.
- **SMS is the primary communication channel**, not email. Email is secondary and optional.
- **Instalment tuition is the default**, not an edge case. Most students do not pay in full
  up front, and debt tracking is a headline feature rather than an accounting afterthought.
- **Local payment rails** (IRR gateways, card-to-card receipts with proof upload, cash at
  the front desk) must all be first-class payment methods alongside online gateways.
- **Term ("ترم") based operation** on ~7–13 week cycles with fixed intake dates, not
  rolling enrolment.

Beyond the list above, section P also settles: the زوج/فرد class-day convention, book-based
level structure (AEF 2A, Top Notch 1B), the ۰–۲۰ grading scale, service-vs-advertising SMS
lines, moving lunar holidays, one-click closure for snow/pollution days, Iranian hosting
constraints, and the eNamad/Samandehi requirements.

Everything is still abstracted behind interfaces (calendar system, payment provider,
messaging provider, locale) so the same product could sell into Turkish or Arabic markets
later — but Iran is the target, not a configuration.
See [I. Data model](docs/I-data-model.md#i10-localisation-and-calendar-strategy).

---

## سایت معرفی — `site/`

The Persian marketing site is built and reviewable.

| File | What it is |
|---|---|
| `site/index.html` | The site. Persian, RTL, light + dark. Fonts loaded from `site/fonts/` — this is the version to deploy |
| `site/fonts/dana-*.woff2` | Dana FaNum, converted to woff2 (~27 KB each) and self-hosted |
| `site/build-standalone.py` | Inlines the fonts as data-URIs → `build/lingotalk-preview.html`, a single shareable file |

Built to the rules in [M](docs/M-ui-ux-direction.md) and [P](docs/P-iran-market.md):
zero external requests, Persian numerals, `tabular-nums`, RTL logical properties,
44px+ touch targets, `prefers-reduced-motion` honoured, and no reliance on glyphs the
font doesn't ship.

Interactive: the hero is a working attendance widget (tap a student, submit, it times you),
plus role tabs, a pricing calculator, and a validating demo form that accepts Persian digits.

```bash
python3 site/build-standalone.py    # → build/lingotalk-preview.html
```

**Not real yet:** customer counts, testimonials, and prices are placeholders, flagged by a
banner at the top of the page. Fill them in before this goes public.

---

## پنل‌ها — `app/`

Interactive prototype of all three dashboards, in Persian.

| File | What it is |
|---|---|
| `app/index.html` | The prototype. Hash-routed, mock data, fonts from `site/fonts/` |
| `app/build-standalone.py` | Inlines fonts → `build/lingotalk-app-preview.html` |

**Four flows are built end to end** — the ones with the highest daily use:

1. **Start an online class** — teacher picks BigBlueButton / Google Meet / Skyroom, with the
   real trade-off stated under each option (see [Q](docs/Q-online-classroom.md)). Live state
   propagates to the student portal and the manager dashboard.
2. **Attendance** — default-present roster, auto-suggested from the meeting for online
   students, submit with an undo toast.
3. **Submit an assignment** — writing (word count, draft autosave) and **speaking**
   (in-browser recording with a timer and waveform).
4. **Grade** — SpeedGrader-style: one student per screen, click-to-score rubric, saved
   phrases, `Ctrl+Enter` to save and advance.

Other sections render an honest "not built in this prototype" state rather than a dead link.

```bash
python3 app/build-standalone.py    # → build/lingotalk-app-preview.html
```

---

## Status

| Phase | State |
|---|---|
| Product definition (A–O) | ✅ Complete |
| Iran market spec (P) | ✅ Complete |
| Online classroom spec (Q) | ✅ Complete |
| Assignments & grading spec (R) | ✅ Complete |
| Marketing site | ✅ Draft built — needs real content |
| Dashboard prototype | ✅ Four core flows built |
| Backend | ❌ Not started — the prototype has no server |
| MVP implementation | ⏳ Blocked on design sign-off |

Next action: see [O. Final recommendation](docs/O-final-recommendation.md#o4-the-first-90-days).
