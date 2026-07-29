# B. Target Users and Use Cases

## B.1 Customer segments

The buyer is the institute. The user is everyone in it. Segment by **operational complexity**,
not by revenue — complexity is what determines which features they need and what they'll pay.

| Segment | Students | Branches | Staff | Primary pain | Plan fit |
|---|---|---|---|---|---|
| **S0 — Independent tutor** | 10–60 | 0 | 1 | Scheduling chaos, chasing payments | Starter |
| **S1 — Small institute** | 60–300 | 1 | 2–6 | Registration workload, no visibility on debt | Growth |
| **S2 — Medium institute** | 300–1,200 | 1–3 | 6–25 | Term rollover, teacher coordination, retention | Growth / Pro |
| **S3 — Large institute / chain** | 1,200–6,000 | 3–15 | 25–120 | Cross-branch consistency, reporting, permission control | Pro / Enterprise |
| **S4 — Corporate training unit** | 100–800 | virtual | 4–20 | Client-based billing, group reporting, attendance proof | Enterprise |

### Where to start

**Land in S1–S2.** They have enough pain to pay and enough simplicity to onboard in a week.
S0 is a volume play with weak monetisation and high churn — serve it with a cheap
self-serve tier that acts as a marketing funnel, not a focus. S3 requires multi-branch
maturity we won't have on day one; sell to them at month 9+ once branch scoping is battle-tested.

### Segment-specific behaviour

**S0 — Independent tutor.** Doesn't think of themselves as an institute. Wants a schedule,
a payment link, and a place to send files. Will not configure anything. Must reach value in
under 10 minutes with zero setup. Their "branch" is null and the entire branch concept must
be invisible to them.

**S1 — Small institute.** One office, a whiteboard with the class schedule on it, an Excel
file named `students-final-v3-NEW.xlsx`, and a manager who is also the owner and sometimes
also a teacher. Buys on *"stop losing money and time."* Highest-intent segment.

**S2 — Medium institute.** Has an academic manager separate from the owner, 15–40 teachers,
and a real front desk with 2–4 people. Buys on *"stop the chaos at term rollover."* This
segment generates the feature requests that make the product good. Highest expansion revenue.

**S3 — Large institute / chain.** Has a finance person, an IT-adjacent person, and existing
half-broken software they hate. Buys on *"one report across all branches"* and *"control
who can see what."* Long sales cycle, high ACV, demands SLA and data export guarantees.

**S4 — Corporate training unit.** Teaches employees of client companies. Billing is B2B
(invoice the company, not the student), attendance is a compliance artifact, and reporting
is delivered to the client's HR. Needs the same core with a different money model.

## B.2 User roles

Six roles. Each has a distinct purpose, home screen, and permission envelope.

### 1. Public visitor
**Who:** Institute owner researching solutions, or a prospective student on an institute's
public enrolment page.
**Goal:** Understand whether this solves their problem; take one low-commitment action.
**Success:** Books a demo, starts a trial, or submits an enrolment enquiry.
**Sees:** Marketing site, institute public pages, public course catalogue.
**Never sees:** Any tenant data beyond what an institute explicitly publishes.

### 2. Institute owner / manager (tenant admin)
**Who:** Owner, general manager, academic manager, or branch manager.
**Goal:** Run the institute profitably without being the bottleneck.
**Daily:** Check today's operations, resolve exceptions, approve things, chase money.
**Weekly:** Review enrolment pipeline, teacher workload, unpaid balances.
**Per term:** Build the term, create classes, assign teachers, run re-enrolment, close books.
**Success:** Doesn't need to ask anyone a question to know what's happening.
**Critical insight:** This person is drowning. The dashboard must answer "what needs me today?"
in under five seconds, or they will keep using WhatsApp.

**Sub-roles** (same base role, different scope/permissions — see [J](J-permissions.md)):
- **Owner** — full tenant access including billing and user management
- **Academic manager** — academics + teachers + students; no finance, no billing
- **Finance manager** — money + reporting; read-only academics
- **Branch manager** — full operational access scoped to one branch
- **Front desk / registrar** — registration, enrolment, payment recording, no reports

### 3. Teacher
**Who:** Part-time or full-time language teacher, often teaching at more than one institute.
**Goal:** Teach. Spend as little time as possible on administration.
**Daily:** See today's classes, mark attendance, post homework.
**Per term:** Enter grades, write progress notes, submit final assessment.
**Success:** Attendance marked in under 20 seconds per class, on a phone, possibly offline.
**Critical insight:** Teachers are the adoption risk. They didn't buy the product and often
resent new systems. If the teacher panel is slower than a paper sheet, the whole system's
data quality collapses — because attendance and grades are the inputs to everything else.
**Design consequence:** the teacher panel is **mobile-first, offline-tolerant, and ruthlessly
minimal.** It is the single most important surface for product success and should get the most
design attention per screen.

### 4. Student (and, where relevant, parent/guardian)
**Who:** Adult learner (majority) or a minor whose parent handles money and communication.
**Goal:** Know when class is, what's due, what they owe, and how they're doing.
**Success:** Never needs to phone the institute to ask a routine question.
**Critical insight:** Student self-service is the mechanism that reduces front-desk workload.
It's not a nice-to-have — it's how the institute gets its promised time savings. But it only
works if adoption is high, which means: no forced app install, phone-number login, SMS OTP,
and a link that opens straight into the relevant page.
**Parent handling:** A student record can have a linked guardian contact who receives
financial and attendance notifications and can log in with their own credentials scoped
to their children. Not a separate role in v1 — a relationship on the student record.

### 5. Super admin (platform team)
**Who:** Talkora founders, ops, and support staff.
**Goal:** Keep tenants healthy, paying, and supported; keep the platform safe.
**Daily:** Trials expiring, failed payments, support queue, error rates.
**Success:** Sees a tenant sliding toward churn before the tenant does.
**Critical constraint:** Must be able to help a customer **without** casually reading their
data. Impersonation ("view as") requires a reason, is time-boxed, is announced to the tenant
owner, and is fully audit-logged. This is a trust requirement, not a compliance checkbox.

### 6. Support / staff member (platform-side, restricted)
**Who:** Support agent or onboarding specialist at Talkora.
**Goal:** Resolve tickets fast.
**Sees:** Ticket queue, tenant metadata, subscription state, configuration — not student
personal data or financial records unless granted scoped, expiring access by the tenant.

## B.3 Jobs to be done

Framed as the customer would say it, with the feature that answers it.

### Institute owner / manager

| Job | Answered by |
|---|---|
| "I want to know how many students we have right now, not last month" | Live overview KPIs |
| "I want to know who owes us money without opening three files" | Debt board + ageing report |
| "I want next term set up in an afternoon, not two weeks" | Term builder + clone-previous-term |
| "I want to know which classes are underfilled while I can still fix it" | Fill-rate alerts pre-term-start |
| "I want to stop students silently disappearing between terms" | Re-enrolment pipeline + at-risk list |
| "I want to see all my branches in one number and each branch separately" | Branch scope switcher + consolidated reports |
| "I want new staff productive in a day" | Role templates + guided task UI |
| "I want to stop being the only person who knows things" | Everything in the record, not in the manager's head |

### Teacher

| Job | Answered by |
|---|---|
| "Tell me where I need to be and when" | Today view + weekly schedule |
| "Let me mark attendance in ten seconds" | One-tap roster with default-present |
| "Let me tell the class something without collecting phone numbers" | Class announcement |
| "Let me record grades without a spreadsheet" | Gradebook with per-component weights |
| "Tell me who's about to fail or drop out" | Per-class risk flags |
| "Don't make me do admin at home" | Mobile-first, offline queue |

### Student

| Job | Answered by |
|---|---|
| "When and where is my class?" | Schedule + next-class card |
| "What do I owe and how do I pay it?" | Balance + one-tap pay |
| "What's my homework and when is it due?" | Assignments list |
| "How am I doing?" | Progress + grades + attendance rate |
| "How do I sign up for next term?" | Re-enrolment offer with saved details |
| "Where's the file the teacher mentioned?" | Class materials |

### Super admin

| Job | Answered by |
|---|---|
| "Which tenants are about to churn?" | Health score + usage decay alerts |
| "Who's on a trial that ends this week?" | Trial pipeline |
| "Is the platform healthy?" | Ops dashboard: error rate, job queue, delivery rates |
| "Why is this customer angry?" | Ticket + tenant timeline + audit log |
| "What's MRR and where is it moving?" | Revenue dashboard |

## B.4 Representative use cases (narrative)

**UC-1 — Term rollover at a 400-student institute.**
Six weeks before term end, the academic manager opens Term Builder, clones last term's class
structure, adjusts the calendar to the new Jalali dates, and publishes. The system generates
re-enrolment offers for every currently-enrolled student, prices them with the loyalty
discount, and sends SMS with a payment link. Over three weeks, the manager watches a single
board: *offered → viewed → paid → enrolled*, with the non-responders auto-escalating to a
call list for the front desk. Classes that stay below minimum viable size are flagged 10 days
before start so students can be merged rather than refunded.

**UC-2 — Walk-in enquiry converts to enrolment.**
A prospective student walks in. Front desk creates a lead in 20 seconds (name, phone, target
language). Books a placement test for Thursday. Student takes the test; the result writes a
recommended level onto the lead. Front desk sees which classes at that level still have seats
and at what times, enrols the student, takes a 40% deposit in cash, and sets a two-instalment
plan for the rest. Student receives an SMS with their schedule and portal link before they
leave the building.

**UC-3 — Teacher marks attendance on a phone between classes.**
Teacher opens the app in the corridor. "Today" shows two classes. Taps the 6:00 PM class,
sees 14 faces defaulted to present, taps the 3 absentees, adds "left early" on one, submits.
Absence notifications fire to those three students (and their guardians if minors). Total
elapsed time: 18 seconds. If the building's wifi is down, it queues locally and syncs later.

**UC-4 — Owner catches a revenue leak.**
The overview shows *Unpaid this term: 84,000,000 IRR across 37 students*. Owner opens the
debt board sorted by days overdue, filters to "no payment activity in 21 days", selects 12
students, and sends a templated reminder with a payment link. Three pay within an hour. The
rest go to the front desk's call queue with a note.

**UC-5 — Multi-branch consistency check.**
A chain manager compares three branches on fill rate, attendance rate, and teacher workload.
Branch 2 has 92% fill and 71% attendance — a retention problem, not a sales problem. Drilling
in shows one teacher's classes account for most of the absence. That's a conversation the
manager would never have had without the data.

**UC-6 — Corporate client cohort.**
A company enrols 30 employees in Business English. Billing goes to the company as a single
invoice with an instalment schedule; individual students see no balance. Monthly, the HR
contact receives an attendance and progress report as PDF, generated on a schedule.

## B.5 Anti-users (who we are not building for)

Naming these prevents roadmap drift.

- **K-12 schools** — different data model (grade levels, parents-as-primary, national
  curriculum, report cards). Adjacent, not same. Do not accept these customers in year one.
- **Universities** — credit hours, faculties, accreditation, degree audits. No.
- **Pure online course sellers** — they need a course platform (Teachable-shaped), not an
  operations platform. Their problem is content and checkout, not scheduling and attendance.
- **Institutes that refuse to change process** — an institute where the owner insists on
  keeping paper and treats the software as a data-entry duplicate will churn. Qualify these
  out during the sales call rather than onboarding them and losing them at month three.
