# L. MVP vs Future Roadmap

## L.1 The scoping principle

**The MVP is not "the smallest thing we can ship." It's the smallest thing that can run a real
institute for a full term without falling back to a spreadsheet.**

That's a much higher bar than a typical MVP, and it's the correct one here. An institute that
has to keep a parallel spreadsheet for even one core function will keep the spreadsheet, keep
using it for everything, and churn. There is no partial adoption of an operations system — it
either becomes the source of truth or it becomes shelfware.

**The MVP scoping test:** *Can an institute of 150 students run their entire Autumn term in
Lingo Talk, from enrolment through term close, without opening Excel once?* If a feature's
absence breaks that sentence, it's in the MVP. If not, it isn't.

---

## L.2 MVP — Version 1.0

**Target: 4–5 months of build, then one design-partner term (~3 months) before general
availability.**

### In scope

#### Foundation
- Multi-tenant architecture with row-level isolation and the cross-tenant leakage test suite
- Authentication: phone + SMS OTP, password optional, session management
- RBAC with the 7 system role templates (custom roles deferred)
- Institute onboarding wizard and configuration
- Jalali/Gregorian calendar layer, RTL-first UI, fa-IR and en locales
- Audit logging on every write
- Full data export

#### Admissions (basic)
- Lead capture from the public page and manual entry
- Lead pipeline with stages and follow-up dates
- Placement test booking and result recording *(manual scoring — no online test engine yet)*
- Registration and enrolment flow
- Waitlist with offer-and-expiry

#### Academics
- Programs, levels, courses
- Term creation **with clone-previous-term** — this is MVP, not Phase 2, because it's the
  feature that makes the second term dramatically cheaper than the first, which is exactly
  when the customer decides whether to renew
- Class creation, scheduling, teacher assignment
- Live conflict detection (teacher, room, availability, hour cap)
- Session generation with holiday exclusion
- Room management
- Enrolment, transfer, withdrawal
- Capacity enforcement

#### Attendance
- Teacher marking (mobile-first, default-present, offline queue)
- Manager oversight and correction with an edit window
- Absence notifications
- Attendance rates at student, class, teacher, and institute level
- Unmarked-session exception queue
- Printable attendance sheet (the paper fallback institutes genuinely still need)

#### Assessment (basic)
- Grade components with weights
- Gradebook entry and publishing
- Exams as scheduled events with manual grade entry
- Progress view for students
- Simple certificate generation with public verification

#### Finance
- Fee schedules and per-class pricing
- Invoice generation with gap-free numbering
- **Instalment plans** — MVP, not optional. This is how the market actually pays
- Payment recording: cash, card, bank transfer, one online gateway
- Payment proof upload and verification queue
- Discounts and coupons
- Receipts
- Outstanding balance tracking and the debt board
- Refunds with approval
- Append-only transaction ledger

#### Communication
- Announcements with audience targeting
- SMS integration (one provider) with per-recipient delivery logs
- Email (secondary)
- Templates with variables
- Automated triggers: payment due, payment overdue, absence marked, session cancelled,
  enrolment confirmed
- SMS credit accounting

#### Panels
- Institute dashboard with KPIs, exception panel, and quick actions
- Teacher panel: today, classes, attendance, gradebook, homework, schedule
- Student portal: home, schedule, classes, payments, grades, announcements, self re-enrolment
- Guardian access for minors

#### Reports (core set only — 8 reports)
Enrolment & growth · Revenue & collections · Outstanding & ageing · Attendance ·
Teacher workload · Class fill rate · Lead conversion · Term-over-term retention.
All with filters, drill-down, and CSV/Excel export.

#### Public surfaces
- Marketing site: home, features, pricing, demo, about, contact, FAQ, blog, auth
- Institute public page: template-based landing, course list, open classes, enquiry form

#### Platform
- Super admin: tenant list and detail, subscription management, plans and entitlements,
  usage metrics, basic support ticketing, platform analytics
- Billing: plans, trials, subscriptions, dunning, invoices

### Explicitly OUT of the MVP

| Deferred | Why | Phase |
|---|---|---|
| Multi-branch | Real complexity, and the launch segment is single-branch. Design the data model for it now (every table has `branch_id`), build the UI later | 2 |
| Online exam engine | Large build; manual grade entry covers the term-one need | 2 |
| Question bank | Depends on the exam engine | 2 |
| Custom roles | The 7 templates cover ~90% of institutes | 2 |
| In-app messaging (1:1 conversations) | Announcements cover the critical path; conversations are a support-load multiplier | 2 |
| Campaign builder / newsletter | Announcements suffice for term one | 2 |
| Mobile apps | The PWA is enough. Native apps before product-market fit are a distraction | 3 |
| Teacher payroll | Adjacent domain, high localisation cost | 2/3 |
| Expense tracking | Accounting-adjacent, not operations | 3 |
| Accounting integrations | Needs a stable financial model first | 3 |
| Video conferencing | Never build. Integrate | 2 (integration) |
| Content marketplace | Different business | 4 |
| AI features | Nothing until there's data worth learning from | 3/4 |
| Public API | Needs a stable domain model | 3 |
| Multi-currency per tenant | Rare; one currency per institute is correct | 3 |

**Why "clone previous term" is MVP but multi-branch is not:** the term-clone is used by every
customer, every term, and directly determines renewal. Multi-branch is used by ~20% of
customers and can be sold as the reason to upgrade later. Feature priority should follow
frequency × impact-on-renewal, not perceived sophistication.

---

## L.3 Phase 2 — Months 6–12 (post-validation)

Built on evidence from the design-partner term, not on this list alone. The ordering below is
a prior, not a commitment.

### Multi-branch (the biggest single item)
Branch scoping across every list and report · branch scope switcher · branch-scoped roles ·
inter-branch student transfer · consolidated and comparative reporting · per-branch invoice
series and configuration.
**Trigger:** the third customer asking, or the first Pro-tier deal contingent on it.

### Online exams
Question bank (multiple choice, fill-in, matching, short answer, audio prompt for listening,
recorded response for speaking) · timed delivery with autosave · auto-grading for objective
types · manual grading queue for subjective · result analytics and item difficulty ·
**online placement tests** — which is the highest-value use, because it converts leads without
staff involvement, at any hour.

### Communication depth
1:1 and small-group in-app messaging · campaign builder with segments and scheduling ·
newsletter · WhatsApp/Telegram channel integration (this market lives there) ·
richer automation rules with conditions and delays · A/B message testing.

### Academic depth
Makeup class scheduling and tracking · custom grading scales · level-progression rules with
automatic next-level suggestion · syllabus and lesson-plan tracking · homework with rubrics ·
teacher-to-student feedback threads · student self-assessment.

### Financial depth
Teacher payroll basis and payout tracking · expense recording · profit-per-class analysis ·
multi-gateway support · automated late fees · scholarship and hardship workflows ·
corporate/B2B invoicing for the S4 segment.

### Platform depth
Custom roles and permission sets · saved and scheduled report delivery · report builder ·
webhooks · integrations: Google Calendar, Zoom/Meet/Skyroom, accounting export ·
white-label student portal · SSO for enterprise.

### Growth features
Referral programme with tracking and credits · campaign attribution end to end ·
public certificate verification portal · institute directory (opt-in) ·
conversion-optimised institute landing templates.

---

## L.4 Phase 3 — Year 2. Premium and enterprise

Features that become paid tiers, add-ons, or enterprise requirements.

| Feature | Monetisation |
|---|---|
| **Native mobile apps** (teacher + student) | Included, but a Pro/Enterprise sales driver. Only after the PWA proves the workflows |
| **Advanced analytics & custom dashboards** | Pro+ |
| **Predictive retention scoring** | Pro+ — flags students likely to not re-enrol, early enough to intervene |
| **Automated intervention workflows** | Pro+ — at-risk student triggers a defined sequence |
| **Public API (read + write)** | Add-on |
| **SSO / SAML / directory sync** | Enterprise |
| **Dedicated database / regional hosting** | Enterprise |
| **Custom SLA and support** | Enterprise |
| **Multi-institute group management** | Enterprise — for franchise groups with a parent entity above the tenants |
| **Advanced compliance & retention policies** | Enterprise |
| **Custom certificate and report templates** | Pro+ |
| **Accounting integrations** | Add-on, per integration |
| **Digital credentials with external verification** | Add-on |

---

## L.5 Future roadmap — Year 2+ (directional)

Ideas worth pursuing once the core is unquestionably solid. Each is stated with the condition
that must be true before starting it.

### Integrated online classes
Native or deeply-embedded virtual classroom with attendance auto-captured from join/leave
events, recording linked to the session, and materials in-context.
**Precondition:** ≥ 30% of tenant sessions are already `online` or `hybrid` delivery mode.
**Warning:** still likely better as a deep integration than a build. Revisit the buy/build
decision with real data, not enthusiasm.

### AI-powered learning support
- **Automated speaking assessment** — student records, system scores pronunciation and fluency
  against a rubric, teacher reviews. The highest-value AI application in this domain by a
  wide margin: it removes the most time-expensive, least-scalable assessment task in
  language teaching.
- **Writing feedback** — first-pass correction and suggestions, teacher-reviewed.
- **Placement test scoring** from an adaptive question set.
- **Auto-generated practice** targeted at a student's demonstrated weaknesses.
**Precondition:** enough graded submissions per level to validate scoring against teacher
judgement. Never ship an AI grade that a teacher hasn't approved — one wrong automated grade
costs more trust than the feature saves in time.

### Personalised learning paths
Per-student progression informed by attendance, grades, and submission patterns, with
recommended focus areas and supplementary material.
**Precondition:** multiple completed terms of clean data per student.

### Predictive analytics
Enrolment forecasting for capacity planning · revenue forecasting from the instalment schedule ·
churn prediction per student and per class · optimal class scheduling from historical demand
by time slot — arguably the most valuable of these, because time-slot selection is currently
pure guesswork and directly determines fill rate.
**Precondition:** 8+ terms of history across a meaningful number of tenants.

### Digital certificates and credentials
Verifiable credentials with an external verification page, QR codes, optional blockchain
anchoring for institutes that want it, and a portable student credential wallet.
**Precondition:** certificate issuance is already a routine, high-volume action.

### Teacher marketplace
Institutes post openings; qualified teachers on the platform apply. Solves a genuine, painful
problem — teacher shortage at term start is chronic.
**Precondition:** enough teacher accounts across enough institutes for liquidity. This is a
network-effect feature that is worthless below critical mass, so it must not be built early.
**Risk:** institutes may perceive it as poaching. Structure it as opt-in on both sides.

### Integrated CRM
Full marketing automation: campaign management, landing page builder, ad-platform attribution,
lead scoring with behavioural signals, drip nurture.
**Precondition:** institutes are consistently using the basic lead pipeline. If they aren't,
a bigger CRM won't help.

### Content marketplace
Institutes and teachers publish and sell course materials, lesson plans, and question banks
to other institutes on the platform.
**Precondition:** a large tenant base and clear IP/licensing terms. Genuine expansion revenue,
but a different business with different mechanics — treat it as a separate product decision.

### Student mobile app with offline learning
Downloadable materials, offline homework, spaced-repetition vocabulary, streaks.
**Warning:** this drifts toward being a learning app rather than an operations platform.
Only pursue if institutes ask for it as a retention tool *for their students*, and keep it
strictly institute-branded — never a direct-to-consumer product that competes with our own
customers.

### Adjacent verticals
The same platform shape fits music schools, sports academies, tutoring centres, and driving
schools. Terms, levels, classes, attendance, and instalments are identical; only the
curriculum vocabulary changes.
**Precondition:** dominant position in language institutes first. Expanding horizontally
before winning vertically is the most common way products in this category die.

---

## L.6 Sequencing rationale

```
MVP (m0–5)          Phase 2 (m6–12)         Phase 3 (y2)         Future (y2+)
──────────          ───────────────         ────────────         ────────────
Run one institute   Run many institutes     Sell upmarket        New value
for one term        of every shape

Core operations  →  Multi-branch         →  Mobile apps       →  AI assessment
Basic everything    Online exams            Predictive          Learning paths
Single branch       Deep communication      Enterprise          Marketplace
Manual grading      Custom roles            API                 Adjacent verticals
Simple reports      Report builder          SSO                 Content
                    Integrations            Advanced analytics
```

**The rule that governs the sequence:** *don't build for the customer you want until you've
kept the customer you have.* Every phase-2 item should be validated by a real request from a
paying customer, not by this document. This roadmap is a hypothesis with an ordering, not a
plan of record.

---

## L.7 What could reorder this roadmap

Honest signals that should override the plan above:

| Signal | Response |
|---|---|
| 3+ prospects lost specifically on multi-branch | Pull multi-branch forward, before online exams |
| Design-partner teachers won't use the panel | Stop everything and fix it. Nothing else matters if attendance data is unreliable |
| Institutes keep asking for WhatsApp instead of SMS | Move WhatsApp integration into the MVP — channel fit beats feature count |
| Placement testing turns out to be the #1 demo request | Pull the online exam engine forward; it's a lead-conversion feature, not an academic one |
| Onboarding takes 3 weeks instead of 3 days | Freeze features and build import and migration tooling until it's 3 days |
| Trial→paid sits below 20% | The problem is activation, not features. Stop building and instrument the funnel |
| Support volume dominated by one category | That category is a product defect. Fix it before shipping anything new |
