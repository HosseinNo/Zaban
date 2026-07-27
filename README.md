# Lingo Talk — Product Blueprint

**The operating system for language institutes.**

Lingo Talk is a multi-tenant SaaS platform that gives every language institute a private
workspace to run its entire operation: admissions, academics, scheduling, attendance,
exams, tuition, communication, and reporting — in one system instead of a spreadsheet,
a WhatsApp group, a paper ledger, and someone's memory.

This repository currently contains **the product blueprint only**. No code yet — by design.
The order is: define the product → define its structure → define its workflows → define its
growth path → then build.

---

## How to read this

Documents are numbered A–O and match the agreed output structure. Read in order for the
full picture; jump directly for a specific working session.

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

## Market assumption (stated explicitly)

The primary launch market is **Iran / Persian-speaking language institutes**. That is not a
cosmetic choice — it shapes the product core:

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

Everything above is abstracted behind interfaces (calendar system, payment provider,
messaging provider, locale) so the same product sells into Turkish, Arabic, and European
institute markets without a rewrite. See [I. Data model](docs/I-data-model.md#localisation-and-calendar-strategy).

---

## Status

| Phase | State |
|---|---|
| Product definition | ✅ Complete — this repository |
| Design system + high-fidelity screens | ⏳ Next |
| MVP implementation | ⏳ Blocked on design sign-off |

Next action: see [O. Final recommendation](docs/O-final-recommendation.md#the-first-90-days).
