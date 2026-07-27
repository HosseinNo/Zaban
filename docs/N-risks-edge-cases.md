# N. Risks and Edge Cases

Each risk below is stated with its real-world trigger, its consequence, and the **product
mechanism** that handles it. A risk without a mechanism is just anxiety.

---

## N.1 Product and operational risks

### R1 — Multi-branch complexity

**Trigger:** A chain wants consolidated reporting, but each branch wants autonomy over pricing,
schedules, and staff. Students transfer between branches mid-term. A teacher works at two.

**Consequence if unhandled:** either branches become silos (defeating the point of one platform)
or everything is global (making branch managers unable to work without seeing irrelevant data).

**Mechanism**
- Branch is a **scope**, not a sub-tenant. Every table carries `branch_id` from day one, even
  in the single-branch MVP, so the model never needs migrating.
- Settings resolve hierarchically: institute default → branch override. Which level a value
  came from is shown in the UI.
- Students belong to a primary branch but their history follows them on transfer; the transfer
  is a recorded event, not a field edit.
- Teachers hold multi-branch membership; the conflict detector works across branches, because
  a teacher can't be in two cities at once.
- Every aggregate carries its scope in the query **and states it in the label** — "Revenue
  (Vanak branch)" — so a branch manager never mistakes their number for the institute total.
- Consolidated reports are a Pro-tier capability with explicit per-branch breakdown, never a
  single number without composition.

---

### R2 — Payment failures

**Trigger:** Gateway timeouts, lost callbacks, duplicate callbacks, partial payments,
overpayments, payments recorded against the wrong student, refunds after a term has closed.

**Consequence if unhandled:** double-charged students, phantom balances, an untrustworthy
ledger. Once an institute stops trusting the money numbers, the product is dead — they'll
return to the notebook for finance and the rest follows.

**Mechanism**
- **Idempotency key on every gateway-initiated payment.** Duplicate callbacks are a normal
  occurrence, not an exception.
- Payments are never marked complete on a client redirect — only on a verified server-side
  callback or a reconciliation poll.
- A reconciliation job polls the gateway for any transaction stuck in `pending` beyond a
  threshold and resolves it.
- Overpayment creates an explicit `ACCOUNT_CREDIT` — never silently absorbed, never silently
  refunded.
- Partial payments apply oldest-instalment-first by default, configurable per institute.
- Financial records are **append-only**: a wrong payment is reversed with a reversal entry that
  cites the original, never edited or deleted.
- Offline payment methods (cash, card-to-card with proof upload) are first-class, so a gateway
  outage never blocks the front desk.
- A daily finance reconciliation report surfaces any discrepancy between recorded payments and
  the ledger.

---

### R3 — Schedule conflicts

**Trigger:** A teacher assigned to two classes at the same time. A room double-booked. A class
scheduled during a holiday. A term extended past its rooms' availability.

**Consequence if unhandled:** a class with no teacher on day one — the most publicly
embarrassing failure an institute can have, and one they will blame the software for.

**Mechanism**
- **Live conflict detection during term building**, not on save. Every edit re-checks: teacher
  double-booking, room double-booking, declared availability violation, weekly hour cap, room
  capacity vs. class maximum.
- Conflicts render inline in the grid with a specific resolution action, not a generic error.
- **Hard conflicts block publishing.** Soft conflicts (a teacher slightly over their preferred
  hours) warn and allow, because managers legitimately override in a crunch.
- Session generation excludes holiday dates automatically.
- Post-publish changes show an impact preview before applying: "This affects 14 enrolled
  students and 8 future sessions."
- A standing conflicts view (`/classes/conflicts`) catches anything introduced after publish.

---

### R4 — Teacher replacement mid-term

**Trigger:** A teacher quits, gets sick, or is dismissed. Sometimes across five classes at once,
sometimes the night before.

**Consequence if unhandled:** manual reassignment across every class, students unnotified,
attendance history attributed to the wrong person, payroll wrong.

**Mechanism**
- Three distinct change types: **permanent reassignment**, **temporary substitution** (date
  range), and **single-session cover** — because they have different payroll and reporting
  consequences.
- **Past sessions retain the teacher who actually taught them.** `session.teacher_id` is
  separate from `class.teacher_id`. Rewriting history to match the current assignment corrupts
  both workload reports and payroll.
- Bulk reassignment: when a teacher leaves, reassign all their classes in one guided flow with
  eligibility ranking, not class by class.
- Eligible replacements are ranked by availability, level qualification, and remaining hours —
  with ineligible teachers shown alongside a warning rather than hidden, because in a crisis
  managers override.
- Both teachers and all affected students are notified automatically, with the specific dates.
- Substitution history is visible on the class and feeds the teacher's workload report.

---

### R5 — Class capacity overflow and underfill

**Trigger:** Two staff enrol the last student simultaneously. A manager overrides capacity for
a VIP. A class sits at 4 students with a minimum of 8, ten days before start.

**Consequence:** overcrowded classrooms and unhappy teachers, or classes that run at a loss.

**Mechanism**
- Capacity check is **transactional at enrolment commit**, not a pre-check. Concurrent
  enrolment cannot overbook.
- The loser of a race gets an immediate waitlist offer and alternative classes at the same
  level — never a silent failure.
- Overbooking is possible only through an explicit permission-gated override that records a
  reason and appears on the class health indicator.
- **Underfill is monitored as an alert:** classes below minimum with < 14 days to start appear
  in the manager's *Needs attention* panel with merge, move, and market actions.
- Merging classes moves enrolments with their financial records intact and notifies students of
  their new schedule — not just of the cancellation.
- Waitlist offers expire (default 24 h) before passing to the next person, so a dormant
  waitlist doesn't hold a seat hostage.

---

### R6 — Incomplete registrations

**Trigger:** A student is half-registered when the front desk gets interrupted. An online
enrolment abandons at payment. A placement test is booked but never taken.

**Consequence:** ghost records, seats held by nobody, revenue reported that doesn't exist.

**Mechanism**
- Explicit intermediate states: `pending_payment`, `reserved`, `awaiting_placement`,
  `waitlisted` — each with its own expiry and its own visibility in the admissions pipeline.
- Reservations hold a seat for a configurable window with a visible countdown, then release it
  automatically and notify the student.
- Incomplete registrations appear in an admissions work queue, never silently in the student
  list where they'd inflate counts.
- **Revenue reports count only completed enrolments**; pending amounts appear as a separate,
  clearly-labelled pipeline figure.
- Every abandoned flow is resumable from a deep link sent by SMS.

---

### R7 — Unpaid balances

**Trigger:** A student attends all term and never completes payment. A guardian disputes an
amount. A student re-enrols owing money from last term.

**Consequence:** direct revenue loss and awkward confrontations at the front desk.

**Mechanism**
- Automated, escalating reminders at institute-configured intervals, on the institute's chosen
  channel, with a payment link.
- A **debt board** sorted by age with one-click bulk reminders and a call list for
  non-responders.
- Ageing report (0–7, 8–30, 31–60, 60+ days) so the owner sees the shape of the problem.
- Balance is visible on the student profile, in the class roster, and in the list view — a
  teacher's roster can optionally show a payment flag (institute-configurable; off by default,
  because many institutes consider it inappropriate for teachers to see).
- Configurable policy on re-enrolment with outstanding debt: block, warn, or allow.
- Instalment plans exist precisely so partial payment is a normal, tracked state rather than a
  failure state.
- Every reminder sent is logged per recipient, so a disputed "nobody told me" has an answer.

---

### R8 — Permission mistakes

**Trigger:** A front-desk employee is given manager access "temporarily." A departing teacher
keeps access. A new module ships with no permission mapping and is visible to everyone.

**Consequence:** data exposure, unauthorised financial actions, and — worst — cross-tenant
leakage.

**Mechanism**
- **Deny by default.** New features are hidden until a permission key is explicitly mapped;
  unmapped features are invisible, not universally visible.
- Permission checks are string-key based and enforced in the data layer — never role-name
  comparisons scattered through controllers.
- **Custom roles cannot grant permissions the granting user doesn't hold.** Escalation attempts
  are blocked and logged.
- Deactivation revokes sessions immediately; role changes invalidate existing sessions.
- A quarterly access review is surfaced to the owner: who has what, who hasn't logged in.
- Every permission change is audited with actor and reason.
- A **cross-tenant leakage test suite runs on every build.** This is the one failure that ends
  the company, so it gets an automated gate rather than a code review.

---

### R9 — Communication failures

**Trigger:** SMS provider outage or silent degradation. Wrong phone numbers. A message sent to
the wrong audience. Students with notifications disabled. Credits exhausted mid-campaign.

**Consequence:** students miss a cancellation and turn up to a closed building. Or 400 people
receive a message meant for one class — which is a public, reputation-damaging error.

**Mechanism**
- **Per-recipient delivery logs** with provider status. "We told them" is verifiable, not an
  opinion.
- Provider failover: a secondary SMS provider with automatic switchover on failure rate, and
  delivery-rate monitoring on the super-admin dashboard so a silent degradation is caught
  centrally rather than by 40 confused tenants.
- **Live recipient count from the first filter**, plus a "preview recipients" list before send.
  This single detail prevents most mis-sends.
- An explicit confirmation step above a threshold audience size, naming the count.
- Credit balance checked **before** composing, with the shortfall named and a top-up link.
- Critical operational notifications (cancellation, schedule change) go to multiple channels
  and appear in-app regardless of channel preferences — a student can mute marketing, not a
  cancellation.
- Invalid phone numbers are flagged on the student record after the first delivery failure and
  surfaced in a data-quality queue.

---

### R10 — Attendance inconsistencies

**Trigger:** A teacher forgets. Marks it three days late from memory. A substitute has no
access. A student joins mid-term. A session is cancelled but marked as everyone absent.

**Consequence:** attendance rates that nobody trusts, which invalidates at-risk flags,
retention reports, and any progress claim made to a student or parent.

**Mechanism**
- Marking takes under 20 seconds, works offline, and is available on a phone — the primary
  defence is that the correct path is easier than the alternative.
- Reminder push 30 minutes after session end; unmarked after 24 hours escalates to the
  manager's exception queue.
- Substitutes can mark, and the session records who actually taught.
- **`not_applicable` is a distinct status** for cancelled sessions and pre-enrolment dates.
  Conflating either with `absent` corrupts every downstream rate — this is the single most
  common data-integrity bug in attendance systems.
- Cancelled sessions mark everyone `not_applicable` automatically and are excluded from rates.
- Edits allowed within a configurable window (default 48 h), fully audited before/after;
  beyond the window requires manager approval.
- Attendance rate calculations exclude `not_applicable` from the denominator, and the report
  states the denominator so the number is interpretable.

---

### R11 — Reporting inaccuracies

**Trigger:** Cached aggregates go stale. A report includes withdrawn students. Term boundaries
are ambiguous. Two reports disagree on "active students." Timezone handling shifts a session
across a date boundary.

**Consequence:** the owner catches one wrong number and stops trusting every number. Trust in
reporting is binary and unrecoverable-ish.

**Mechanism**
- **A single definition per metric, documented in-product.** "Active student" is defined once,
  computed in one place, and a tooltip on every report states the definition and the exclusions.
- Every report shows an **"as of" timestamp** and a manual refresh.
- Materialised views refresh on a defined schedule; the schedule is visible, not hidden.
- Reports covering an in-progress term are labelled as such ("Week 4 of 12"), so partial
  figures aren't read as final.
- Every chart has a **drill-down to the underlying rows** — a manager can always verify a
  number by looking at what composes it. This is the strongest possible trust mechanism and it
  costs little.
- All timestamps stored UTC; all civil dates resolved through one formatting layer, tested
  across timezone and calendar boundaries.
- Financial reports reconcile against the append-only ledger, not against denormalised caches.

---

### R12 — Onboarding complexity

**Trigger:** An institute signs up, sees an empty system, doesn't know where to start, and
never enters real data.

**Consequence:** the single largest cause of trial non-conversion and first-year churn. More
customers are lost here than to price or missing features combined.

**Mechanism**
- A **sequenced onboarding checklist** ordered by the institute's own stated priority from
  signup step 2, not a generic list.
- Every step is skippable and resumable; nothing blocks other work.
- **Sample data** as a first-class option — explore a fully-populated institute, then wipe it
  in one click.
- Import tooling that tolerates real spreadsheets: fuzzy column mapping, a validation preview
  with per-row reasons, and a downloadable failed-rows file that can be fixed and re-uploaded
  without creating duplicates.
- "Send us your file and we'll import it" as a real, staffed service — the core of the paid
  onboarding package.
- Guided setup call for every institute above 200 students, included in onboarding.
- Automated nudges at day 3, 7, and 11 that link directly to the unfinished task.
- **Onboarding time is a tracked product metric** with a target (3 days to first real
  enrolment). If it drifts, feature work stops until it's fixed.

---

### R13 — Staff adoption resistance

**Trigger:** Teachers see it as surveillance. Front desk finds it slower than the notebook.
A long-serving admin whose value came from being the only person who knew things feels
threatened. The owner buys it and nobody uses it.

**Consequence:** partial adoption, dual systems, data rot, churn at renewal. **This is the
highest-probability failure mode in the entire product**, and it is a design problem, not a
training problem.

**Mechanism**
- **The teacher panel must be faster than paper.** Not comparable — faster. Under 20 seconds
  to mark a class, on a phone, offline-tolerant. It has its own CI performance gate.
- Teachers see only what they need to teach: no finance, no other teachers, no institute
  metrics. It reads as a tool, not a monitoring system.
- Front-desk flows are single-screen with pre-filled defaults and under-two-minute completion
  targets.
- Role-specific first-run guidance — a teacher's first login explains the teacher panel, not
  the product.
- **Adoption is measured and surfaced to the owner** in week two: who has logged in, which
  classes are being marked, which staff aren't using it. Problems become visible while they're
  still fixable.
- Communicate the personal benefit per role, explicitly: teachers get fewer admin messages and
  no end-of-term grade scramble; front desk stops being blamed for lost information.
- The owner's onboarding includes an explicit switchover date for retiring the old system.
  **Parallel running beyond one term is the strongest churn predictor there is**, and the
  customer-success playbook should treat it as an escalation.

---

## N.2 Business and platform risks

### R14 — Cross-tenant data leakage
**The existential risk.** One incident, publicly known, ends the company in a market where
institute owners all know each other.
**Mechanism:** database-level row isolation (RLS), tenant context derived only from session +
URL, an automated leakage test suite gating every build, penetration testing before GA, and
tenant-scoped credentials in the data layer so a missing predicate returns zero rows rather
than another tenant's rows.

### R15 — Data loss
**Mechanism:** automated encrypted backups, point-in-time recovery, **quarterly restore
drills** (an untested backup is not a backup), soft deletes with grace periods everywhere,
append-only financial and audit records, and self-serve export always available.

### R16 — SMS provider dependency
A single provider outage silently breaks the product's most visible feature.
**Mechanism:** provider abstraction from day one, a secondary provider configured, automatic
failover on failure-rate threshold, delivery-rate monitoring on the platform dashboard, and
per-tenant provider configuration for institutes with their own contracts.

### R17 — Payment gateway dependency
**Mechanism:** gateway abstraction, multiple providers per market, and — critically — **cash
and bank-transfer methods that work with no gateway at all.** An institute must be able to
operate fully during a gateway outage.

### R18 — Regulatory and data-residency change
**Mechanism:** data residency as a configuration, not an assumption; the dedicated-database
option designed for in Phase 3; documented retention and deletion policies; per-country
tax/invoice configuration rather than hardcoded rules.

### R19 — Seasonality
Institute buying and usage concentrate around term boundaries. Revenue and support load are
spiky, and a bad term-rollover experience costs a whole quarter.
**Mechanism:** annual billing to smooth cash flow; concentrate marketing 6–8 weeks pre-term;
staff support for the rollover peak; make term-rollover the most polished flow in the product,
because it's when the customer re-decides.

### R20 — Competitive response from a general school ERP
**Mechanism:** depth in the language-institute-specific model (levels, placement, terms,
instalments), local fluency (calendar, RTL, payment rails, SMS) that a generalist won't
replicate economically, and switching cost from accumulated history.

### R21 — Building the wrong thing
**Mechanism:** design partners running real terms before GA; ship-then-measure with feature
usage instrumented from day one; a documented rule that phase-2 items require a paying
customer's request, not a roadmap document; and treating the top support-ticket category as a
roadmap input rather than a staffing problem.

---

## N.3 Edge cases that need explicit product decisions

These are the ones that get discovered in production if they aren't decided in design.

| # | Edge case | Decision |
|---|---|---|
| 1 | Student enrols in two classes at overlapping times | Warn, allow with confirmation. Legitimate for exam prep + conversation |
| 2 | Student transfers class mid-term | Attendance and grades from both classes retained, linked. Price difference calculated and applied as a charge or credit |
| 3 | Term extended after publication | Additional sessions generated; students notified; pricing unchanged unless explicitly adjusted |
| 4 | Class cancelled after students paid | Automatic pro-rata credit calculation, refund or transfer offered per student, institute chooses the default |
| 5 | Student pays for a class that never runs | Full credit to account, refund available, never silently retained |
| 6 | Teacher marks attendance for a cancelled session | Blocked; session status governs and shows why |
| 7 | Two staff edit the same record concurrently | Optimistic locking with a clear conflict message showing both versions — never a silent last-write-wins |
| 8 | Student has two records (duplicate) | Detection on phone and national ID at creation; merge tool with a side-by-side comparison, reversible for 30 days |
| 9 | Guardian linked to students at different branches | Supported; the portal shows a child selector with per-child branch context |
| 10 | Student turns 18 mid-term | Guardian link retained but financial notifications can be reassigned; no automatic change, because families differ |
| 11 | Institute changes currency | Blocked once transactions exist. Historical amounts must never be silently reinterpreted |
| 12 | Institute changes calendar system | Allowed — it's a presentation layer. All stored dates are unaffected |
| 13 | Term spans a calendar-year boundary | Fully supported. Reports offer both term-based and calendar-based grouping |
| 14 | Holiday declared after sessions generated | Bulk-cancel affected sessions with an option to auto-schedule makeups; students notified |
| 15 | Teacher teaches at two institutes on the platform | One user account, two memberships, independent data. The conflict detector cannot see across tenants — a documented, accepted limitation, surfaced to the teacher in their own schedule view |
| 16 | Student studies at two institutes | Same: one account, two memberships, isolated records |
| 17 | Payment received after a student withdrew | Blocked from normal recording; routed to finance for a refund or credit decision |
| 18 | Refund requested after term close | Allowed with approval; creates a reversal entry and reopens the invoice in a clearly-labelled adjusted state |
| 19 | Grades published then corrected | Allowed with approval; the student is notified of the change and the original is retained in the audit log |
| 20 | Certificate issued in error | Revocable; the public verification page then shows "revoked" rather than 404, because a missing page looks like a system failure |
| 21 | Institute exceeds its student limit mid-term | Grace period, then new enrolments blocked. **All existing operations continue.** Never break a running term over billing |
| 22 | Subscription lapses mid-term | Read-only, then suspended — but export always available and student-facing schedule remains readable, so the institute's students aren't punished for the institute's billing problem |
| 23 | Institute requests deletion with students mid-term | 30-day grace, mandatory export, explicit warning about active enrolments, owner re-authentication required |
| 24 | Time zone change (DST or institute relocation) | Sessions store UTC with the institute timezone; a relocation triggers an explicit review of future sessions rather than a silent shift |
| 25 | Bulk import contains duplicates of existing students | Matched on phone/national ID, flagged for review, never auto-merged and never silently duplicated |
| 26 | A class has zero enrolments at term start | Auto-flagged; the institute chooses to cancel, postpone, or run it |
| 27 | Student requests data deletion (right to erasure) | Personal data anonymised; financial and academic aggregates retained for the institute's legal obligations, with the anonymisation recorded |
| 28 | Online exam submitted after the timer expired | Server time is authoritative; the submission is accepted but flagged as late with the actual submission time recorded |
| 29 | Two students enrol into the last seat simultaneously | Transactional capacity check; loser gets an immediate waitlist offer plus alternatives |
| 30 | Institute wants to keep a class across two terms | Supported via a linked continuation class, preserving the roster and history |

---

## N.4 Risk priority

| Risk | Probability | Impact | Priority |
|---|---|---|---|
| Staff adoption resistance (R13) | **High** | **High** | **1 — design for it from day one** |
| Onboarding complexity (R12) | **High** | **High** | **2 — instrument and staff it** |
| Cross-tenant leakage (R14) | Low | **Catastrophic** | **3 — automated gate, no exceptions** |
| Attendance inconsistency (R10) | High | High | 4 |
| Payment failures (R2) | Medium | **High** | 5 |
| Reporting inaccuracy (R11) | Medium | High | 6 |
| Schedule conflicts (R3) | Medium | High | 7 |
| Building the wrong thing (R21) | Medium | High | 8 |
| Unpaid balances (R7) | High | Medium | 9 |
| Communication failure (R9) | Medium | Medium | 10 |
| Multi-branch complexity (R1) | Medium | Medium | 11 |
| Teacher replacement (R4) | High | Low–Medium | 12 |
| Capacity edge cases (R5) | Medium | Medium | 13 |
| Provider dependency (R16, R17) | Medium | Medium | 14 |
| Data loss (R15) | Low | Catastrophic | 15 — mitigated by discipline |

**The two at the top are not technical problems.** They are product-design and
customer-success problems, and they are where most of this category's failures actually come
from. Budget accordingly: the teacher panel and the onboarding flow deserve more design and
engineering attention per screen than anything else in the product.
