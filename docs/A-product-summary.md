# A. Product Summary

## A.1 One-sentence definition

**Lingo Talk is a multi-tenant SaaS platform that runs the complete operation of a language
institute — admissions, academics, scheduling, attendance, assessment, tuition, and
communication — in one private workspace per institute.**

## A.2 What it actually is

Not a website builder. Not an LMS. Not an accounting package. Not a video-conferencing tool.

Lingo Talk is an **operational system of record**. The defining question for any feature is:
*"Does the institute's front desk, academic manager, or owner make a decision or perform a
task with this every week?"* If no, it does not belong in the core product.

The product has three layers:

| Layer | What it does | Who touches it |
|---|---|---|
| **System of record** | Stores the truth about students, classes, terms, money, attendance, grades | Everyone, indirectly |
| **Operational workflows** | Turns records into daily work: enrol, schedule, collect, mark, notify | Managers, teachers, front desk |
| **Decision layer** | Turns work into insight: retention, revenue, workload, conversion | Owners, academic managers |

Most competing tools stop at layer one and call themselves "school management software."
Layers two and three are where institutes actually feel the value and where the retention lives.

## A.3 Product pillars

1. **One workspace, one truth.**
   Every student, payment, class, and message lives in a single tenant. No parallel
   spreadsheets. If it isn't in Lingo Talk, it didn't happen.

2. **Term-shaped, not calendar-shaped.**
   Language institutes operate in terms. The entire product — enrolment, finance, reporting,
   capacity — is organised around the term as the primary time unit, with the term-rollover
   moment treated as the most important workflow in the product.

3. **Money is a first-class object.**
   Tuition, instalments, discounts, and debt are modelled properly from day one, not bolted
   on. An institute that can't see who owes what will not keep paying us.

4. **Non-technical by default.**
   The person using this most is a 24-year-old front-desk employee under time pressure with
   a queue of parents in front of them. Every core task must be completable in under
   60 seconds without training.

5. **Progressive depth.**
   A one-branch institute with 80 students sees a simple product. A 12-branch chain with
   4,000 students sees a powerful one. Same codebase, capability revealed by configuration
   and plan — never by a different product.

6. **Multi-tenant isolation as a security guarantee.**
   Cross-tenant data leakage is the single existential failure mode for this business. Tenant
   scoping is enforced at the data layer, not in application code paths.

## A.4 What Lingo Talk is deliberately *not* (v1 non-goals)

| Not this | Why | What we do instead |
|---|---|---|
| A video conferencing platform | Commoditised, expensive, we would lose | Deep integration with Zoom / Google Meet / Skyroom; store the link, the recording URL, and attendance |
| A full LMS with authoring tools | 10x scope, different buyer | File distribution, homework, quizzes; SCORM/content marketplace deferred to Phase 3 |
| A general accounting system | Regulated, localised, low differentiation | Complete tuition ledger + clean export to accounting tools |
| A student-facing marketplace | Different business (demand gen), destroys trust with institutes who fear disintermediation | Institute-branded public pages only |
| A generic school ERP (K-12) | Different data model (parents, grade levels, national curriculum) | Language-institute-specific: levels, terms, placement, CEFR-style progression |
| A website builder | Distraction | One good institute landing/enrolment page per tenant, template-based |

Writing these down matters more than the feature list. Most education SaaS products die from
scope creep into LMS and video, not from missing features.

## A.5 The product in one operational picture

```
                    ┌──────────────────────────────────────────┐
                    │        PUBLIC MARKETING SITE             │
                    │   lingotalk.com — sells the platform     │
                    └──────────────┬───────────────────────────┘
                                   │ demo request / trial signup
                                   ▼
┌───────────────────────────────────────────────────────────────────────────┐
│                     LINGO TALK PLATFORM (multi-tenant)                    │
│                                                                           │
│  ┌───────────────────┐   ┌───────────────────┐   ┌───────────────────┐    │
│  │  TENANT: Institute│   │  TENANT: Institute│   │  TENANT: Tutor    │    │
│  │  "Pardis Language"│   │  "Iran Zamin"     │   │  "Sara M."        │    │
│  │  3 branches       │   │  1 branch         │   │  0 branches       │    │
│  ├───────────────────┤   ├───────────────────┤   ├───────────────────┤    │
│  │ Manager dashboard │   │ Manager dashboard │   │ Solo dashboard    │    │
│  │ Teacher panel     │   │ Teacher panel     │   │ Teacher panel     │    │
│  │ Student panel     │   │ Student panel     │   │ Student panel     │    │
│  │ Institute landing │   │ Institute landing │   │ Institute landing │    │
│  └───────────────────┘   └───────────────────┘   └───────────────────┘    │
│                                                                           │
│  Shared platform services: auth · billing · SMS/email · storage ·         │
│  search · reporting engine · audit log · scheduler                        │
└───────────────────────────────────┬───────────────────────────────────────┘
                                    │
                    ┌───────────────▼──────────────┐
                    │      SUPER ADMIN CONSOLE     │
                    │  tenants · plans · revenue · │
                    │  support · health · content  │
                    └──────────────────────────────┘
```

## A.6 Core capability map

```
ADMISSIONS          ACADEMICS           FINANCE            COMMUNICATION      INTELLIGENCE
─────────────       ─────────────       ─────────────      ─────────────      ─────────────
Lead capture        Course catalogue    Tuition plans      Announcements      Enrolment trends
Demo request        Levels & tracks     Instalments        SMS campaigns      Retention cohorts
Placement test      Terms               Discounts          Email              Revenue & debt
Registration        Class creation      Coupons            In-app inbox       Teacher workload
Enrolment           Scheduling          Invoices           Automated          Attendance health
Waitlist            Teacher assignment  Payments           reminders          Course performance
Follow-up           Capacity            Refunds            Parent updates     Lead conversion
Re-enrolment        Attendance          Debt tracking      Delivery logs      Class fill rate
                    Homework                                                   Placement funnel
                    Exams & grading
                    Progress tracking
                    Makeup classes
```

## A.7 How success is measured

**Product health (leading indicators)**

| Metric | Target by month 12 |
|---|---|
| Weekly active institutes / paying institutes | > 90% |
| Attendance marked within 24h of class | > 85% |
| Payments recorded in Lingo Talk vs. reported total | > 95% |
| Institutes using the re-enrolment flow at term rollover | > 60% |
| Median time from lead capture → enrolled | < 5 days |

**Business health (lagging indicators)**

| Metric | Target by month 12 |
|---|---|
| Paying institutes | 120 |
| Net revenue retention | > 105% |
| Logo churn (monthly) | < 2.5% |
| Trial → paid conversion | > 25% |
| Payback period on CAC | < 6 months |

**The one metric that matters:** *percentage of an institute's enrolments that were created
in Lingo Talk in the last 30 days.* If that number is above 90%, the institute cannot leave.
If it is below 50%, they are running a parallel system and will churn. Every product decision
should be evaluated against its effect on this number.

## A.8 Positioning statement

> For language institutes that have outgrown spreadsheets but can't afford enterprise school
> software, **Lingo Talk** is the operating platform that runs registration, classes, tuition,
> and communication in one place. Unlike generic school-management systems built for K-12
> curricula, Lingo Talk is built around the way language institutes actually work — terms,
> levels, placement tests, instalments, and small classes that fill or don't.

## A.9 Brand character

The product should read as **"the quiet professional"**: confident, precise, low-ornament.

| It should feel | It must never feel |
|---|---|
| Calm under load | Playful or gamified |
| Dense but legible | Sparse and slow |
| Reliable, boring in the good way | Trendy, experimental |
| Local and fluent (Persian typography done properly) | Translated-from-English |
| Fast | Animated for its own sake |

The visual expression of this is specified in [M. UI/UX direction](M-ui-ux-direction.md).
