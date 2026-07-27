# F. Page-by-Page Breakdown

Every page below uses the same template:

> **Purpose** — the one job this page does
> **First view** — what the eye lands on before scrolling or thinking
> **Sections** — the page's structure, in order
> **Actions** — what the user can do here
> **Key data** — what must be visible without a click
> **Next step** — where the user goes from here
> **Empty / edge states** — what it looks like when there's nothing, or something's wrong

---

# PART 1 — PUBLIC MARKETING SITE

## F.1 Landing page (`/`)

**Purpose:** Make an institute owner recognise their own problem in the first eight seconds,
then hand them one obvious next action.

**First view (above the fold):**
Headline, one-sentence subhead, two CTAs (`Request a demo` primary / `Start free trial`
secondary), and a real product screenshot — the manager overview, with plausible Persian data,
not an abstract illustration. Slim trust bar underneath: institute count, student count,
"used in N cities."

**Sections in order:**

| # | Section | Content | Why here |
|---|---|---|---|
| 1 | Hero | Headline, subhead, dual CTA, product screenshot | Immediate recognition + action |
| 2 | Trust bar | Institute logos or counts | Credibility before the argument |
| 3 | Problem mirror | "Your student list is in Excel, announcements are in WhatsApp, and only one person knows who owes money." 4 pain cards. | Recognition beats persuasion |
| 4 | The shift | Before/after visual: scattered tools → one workspace | The core idea in one image |
| 5 | Capability blocks | 6 blocks: Admissions · Classes · Attendance · Finance · Communication · Reports. Each: icon, name, one line, screenshot, link to feature page | Scannable proof of substance |
| 6 | Role tabs | Tabbed: Owner / Academic manager / Teacher / Student — each with 3 benefits + a matching screenshot | Multiple stakeholders in the room |
| 7 | Numbers | 3 quantified outcomes (hours saved, collection improvement, enrolment time) with a footnote on methodology | Credibility through specificity |
| 8 | Customer story | One institute, named, with a photo, a quote, and a metric | Social proof that isn't a logo wall |
| 9 | Multi-branch strip | Short block for chains, links to Solutions | Qualifies larger buyers upward |
| 10 | Migration reassurance | "Bring your Excel file. We import it. You keep your data, always." | Answers the #1 unspoken objection |
| 11 | Pricing preview | 3 cards, from-price, link to full pricing | Price transparency builds trust in this market |
| 12 | FAQ | 6 highest-friction questions, accordion | Removes final hesitation |
| 13 | Final CTA | Repeat both CTAs, add "or talk to a human" with phone number | Multiple exits for different comfort levels |
| 14 | Footer | Full sitemap, contact, legal, language switcher | Navigation of last resort |

**Actions:** Request demo · Start trial · Log in · Watch product tour (2 min) · Explore
features · View pricing · Call sales · Switch language (فا/EN).

**Key data visible:** Price floor, institute count, "no credit card required," trial length.

**Next step:** `/demo` (primary), `/signup` (secondary), `/features/*` (researchers).

**Edge states:** Slow connection → hero text and CTA render before the screenshot;
the screenshot has a reserved aspect-ratio box so nothing shifts (CLS). Video is
click-to-play, never autoplay with sound.

---

## F.2 Features pages (`/features`, `/features/{module}`)

**Purpose:** Convert a researching buyer from "sounds nice" to "this specifically does the
thing I struggle with."

**First view:** Module name, one-line definition, and the highest-value screenshot of that
module — full-bleed, real data.

**Structure of each module page:**
1. Hero — module name, one-sentence definition, screenshot
2. The problem — 2–3 sentences describing the manual process it replaces
3. Capability list — 6–10 specific capabilities, each with a sentence (this is where buyers
   verify their must-have; be exhaustive and concrete, not aspirational)
4. Workflow walkthrough — 3–4 steps with real screenshots showing the actual sequence
5. Detail callouts — the two or three things competitors don't do (instalment plans,
   Jalali term calendar, offline attendance)
6. Related modules — cross-links, because the integration *is* the product
7. Plan availability — which plans include this
8. CTA — demo, with a module-specific hook

**Actions:** Request demo · Start trial · Jump to related module · View pricing.

**Next step:** another feature page (researchers read 3–5) or `/demo`.

**Edge state:** Features not yet built are shown in a clearly-labelled "Coming in [quarter]"
band, never mixed in with shipped capability. Overselling the roadmap creates churn.

---

## F.3 Pricing page (`/pricing`)

**Purpose:** Let a buyer self-qualify into a plan and remove price anxiety before the sales call.

**First view:** Three plan cards, billing toggle (monthly/annual with the annual saving
labelled), and a single sentence explaining what pricing is based on: *"Priced by active
students and branches. Unlimited staff on every plan."*

**Sections:**
1. Billing toggle — monthly ↔ annual (−20%)
2. Plan cards — Starter / Growth / Pro, with Growth marked "Most popular." Each card: price,
   student ceiling, branch count, 6 headline features, CTA. Enterprise as a fourth,
   contact-only card.
3. Student calculator — a slider ("How many active students?") that highlights the matching
   plan and shows the real monthly figure. Removes the mental arithmetic that kills conversion.
4. Full comparison table — every feature × every plan, grouped by module, sticky header,
   horizontally scrollable in its own container on mobile.
5. Add-ons — SMS credit packs, extra branches, onboarding & migration package, custom domain,
   priority support, API access.
6. Billing FAQ — trial mechanics, what happens at the student ceiling, payment methods,
   currency, refunds, upgrade/downgrade proration, cancellation.
7. CTA band — "Not sure? Talk to us and we'll tell you which plan fits."

**Actions:** Toggle billing period · Move the calculator · Select a plan → `/signup?plan=X` ·
Compare features · Contact sales · Start trial.

**Key data:** Exact prices in local currency, student limits, branch limits, trial length,
"no credit card for trial," what happens when limits are exceeded.

**Next step:** `/signup` with plan preselected, or `/demo` for larger institutes.

**Edge states:** Above the top self-serve tier the calculator switches to "Let's talk —
custom pricing above 2,000 students" with a contact form. Never show a plan the buyer
can't actually purchase in their country.

---

## F.4 Request demo page (`/demo`)

**Purpose:** Capture a qualified lead with the minimum friction that still allows the sales
call to be useful.

**First view:** A short form on one side, and on the other side: what happens next, how long
the demo takes, and one customer quote. The form is visible without scrolling on mobile.

**Form fields (7, no more):**
| Field | Required | Why |
|---|---|---|
| Full name | ✓ | |
| Institute name | ✓ | |
| Phone (with country code) | ✓ | Primary channel; validated format |
| Email | ✓ | Confirmation + calendar invite |
| Approximate student count | ✓ | Segments the lead and routes the rep |
| Number of branches | ✓ | Qualifies plan tier |
| What's your biggest challenge? | optional | Free text — the single most useful field for the sales call |

Below the form: preferred contact time (morning/afternoon/evening) as chips, and a
`Prefer to book directly?` link to a calendar picker.

**Sections:** Hero + form · What to expect (3 steps: 15-min call → tailored walkthrough →
migration plan) · Who it's for · One testimonial · Alternative contacts (phone, WhatsApp).

**Actions:** Submit · Book a slot directly · Call instead · Start trial instead (for those
who don't want a call at all — always give this exit).

**Next step:** `/demo/confirmed` — a real confirmation page stating exactly when someone will
call, with a calendar file, the rep's name and photo, and links to the product tour and
pricing so the wait isn't dead time.

**Edge / error states:** Inline validation on blur, not on keystroke. Errors below the field
with the cause and the fix. Duplicate submission from the same phone within 24 h → friendly
"We already have your request, expect a call by [time]" instead of a second lead. Submission
failure → the form retains all input and offers a phone number.

---

## F.5 Sign-up page (`/signup`)

**Purpose:** Get an institute from "interested" to "inside a working workspace with their own
data" — in one sitting.

**Design decision:** Three short steps, not one long form. Each step is a separate screen with
a visible progress indicator. Steps 2 and 3 happen *after* the account exists, so an abandoned
signup is still a captured lead we can follow up.

**Step 1 — Create account (the only step that blocks access)**
Fields: institute name · your name · phone (OTP-verified) · email · password · country/language.
Auto-generated workspace subdomain, editable, with live availability check.
Below: "No credit card. 14-day trial. Cancel anytime."

**Step 2 — Tell us about your institute (shapes the workspace, skippable)**
Languages taught · approximate student count · number of branches · does term start soon? ·
what do you want to fix first? (multi-select: registration, payments, attendance, communication,
reporting). These answers configure default modules, seed the level structure, and set the
onboarding checklist order.

**Step 3 — First value (never blocks)**
Three cards: *Import your student list* · *Create your first term* · *Invite your team*.
A fourth, quieter option: *Explore with sample data* — pre-populated demo tenant data the
user can wipe with one button. This is the highest-converting option for hesitant users and
should not be hidden.

**Actions:** Create account · Verify phone · Skip · Import file · Load sample data · Invite team.

**Next step:** the institute Overview, with the onboarding checklist expanded.

**Edge states:** Phone already registered → offer login or "add another institute to this
account." OTP not received → resend with a 60-second cooldown and a fallback to email
verification. Weak password → strength meter with specific requirements, not a generic error.
Abandoned at step 1 → account exists; a recovery email/SMS goes out at 1 h, 24 h, and 72 h.

---

# PART 2 — INSTITUTE APP (MANAGER)

## F.6 Institute overview / dashboard (`/`)

Detailed widget specification in [H. Dashboard structure](H-dashboard-structure.md#h2-institute-dashboard-owner--manager).

**Purpose:** Answer *"what needs me today?"* in under five seconds, then *"how is the term
going?"* in under thirty.

**First view:** A greeting line with today's Jalali date, then a single row of four KPI tiles,
then — critically — the **Needs attention** panel. Not a chart. Charts are below the fold.

**Sections in order:**
1. **KPI row** — Active students (Δ vs. last term) · Collected this term / Expected (with %
   bar) · Overdue amount + student count · Attendance rate this week
2. **Needs attention** — the exception queue, max 6 items, each with a one-click resolution:
   unmarked attendance sessions · overdue payments past threshold · classes below minimum
   size with the term starting soon · unassigned classes · leads with no contact in 3 days ·
   expiring trial / failed subscription payment
3. **Today** — sessions today with live marking status (marked / unmarked / in progress),
   staff on site, rooms in use
4. **Quick actions** — Add student · Record payment · Create class · Send announcement ·
   Add lead
5. **Term progress** — where the term is (week 4 of 12), enrolment vs. capacity, re-enrolment
   pipeline status if the term is ending
6. **Charts** — enrolment trend (12 terms), revenue collected vs. expected (monthly),
   attendance trend
7. **Recent activity** — audit feed, filterable, last 20 events
8. **Onboarding checklist** — only until complete, then it disappears permanently

**Actions:** Everything in Needs attention resolves inline or in a side panel — the user
should not have to navigate away to clear an exception. Quick actions open modals.

**Next step:** wherever the exceptions point. A well-designed overview is mostly a launcher.

**Edge states:** New tenant → checklist takes the whole page, KPIs show as "available once
you add students." No active term → prominent "Create your term" state. All clear → an
explicit "Nothing needs your attention" affirmation, not a blank space.

---

## F.7 Student list (`/students`)

**Purpose:** Find any student in under five seconds and act on groups of students in bulk.

**First view:** Search input focused by default, filter chips, and the table with the most
recently active students first.

**Sections:** Header (count, search, filters, export, add) · Active filter chips (removable) ·
Table · Bulk action bar (appears on selection) · Pagination.

**Table columns:** Photo + name (+ national/student ID) · Phone · Current class(es) · Level ·
Status badge · Balance (red if overdue) · Attendance % · Last activity. Columns are
user-configurable and the choice persists per user.

**Filters:** Status (active / inactive / graduated / on hold / prospective) · Branch · Term ·
Class · Level · Language · Balance state (paid / partial / overdue) · Attendance band ·
Enrolment date range · Tags · Guardian-linked.

**Actions:** Add student · Import CSV/Excel · Export · Open profile · Bulk: send message,
add to class, apply tag, apply discount, change status, export selection.

**Key data:** balance and attendance in the row — the two things a manager checks constantly.

**Next step:** student profile, or a bulk communication.

**Edge states:** No results → "No students match these filters" + a clear-filters button
(never an empty table with no explanation). Zero students → onboarding empty state with
Import and Add. Import in progress → a persistent progress banner with a row-level error
report, and a downloadable file of failed rows with the reason per row.

---

## F.8 Student profile (`/students/{id}`)

**Purpose:** The single place where everything about one student is knowable and actionable.
This is the most-visited detail page in the product.

**First view:** A persistent header card: photo, name, student ID, status badge, current
class, level, phone (tap-to-call), balance (prominent, colour-coded), and a primary action
button (`Record payment` if a balance exists, `Enrol in class` if not).

**Layout:** Sticky header card + tabbed body. Tabs never lose the header — the manager must
always see who they're looking at and what they owe.

**Tabs:**

| Tab | Contains | Key actions |
|---|---|---|
| **Overview** | Personal details, guardian, enrolment summary, current classes, snapshot of attendance/grades/balance, tags, internal notes | Edit details, add note, add tag, message |
| **Enrolments** | All enrolments across all terms: class, term, status, dates, price paid, completion | Enrol, transfer class, withdraw, freeze |
| **Attendance** | Per-class attendance history, rate, absence pattern, calendar heatmap | Mark manually, excuse absence, message about absence |
| **Grades & progress** | Grades by class and component, exam results, level progression timeline, teacher comments | Add grade, issue certificate, print report |
| **Finance** | Invoices, instalment schedule with due dates, payments, discounts applied, outstanding balance, refunds | Record payment, create plan, apply discount, issue refund, send reminder, print receipt |
| **Documents** | ID scans, contracts, certificates, uploaded payment proofs | Upload, download, delete (audited) |
| **Communication** | Every SMS, email, and in-app message sent to this student with delivery status | Send message, resend |
| **Activity** | Full audit trail: who changed what, when | Filter, export |

**Next step:** Record a payment (most common), enrol in next term, or message.

**Edge states:** Overdue balance → an amber banner across the top with `Send reminder` and
`Record payment`. Withdrawn student → grey header treatment, actions restricted to
reactivate/export. Duplicate detected (same phone/national ID) → a merge suggestion banner
with a side-by-side comparison and an explicit, reversible merge.

---

## F.9 Class detail (`/classes/{id}`)

**Purpose:** The operational hub for one class — roster, sessions, attendance, grades,
materials, and communication in one place.

**First view:** Header with class name, course + level, term, teacher (with photo), schedule
(days/times), room, branch, and — most importantly — **capacity as a visual bar**:
`14 / 18 enrolled · 4 seats · 2 on waitlist`. Fill rate is the number a manager wants first.

**Header status strip:** term week progress (`Week 4 of 12`), next session, and a health
indicator combining fill rate, attendance rate, and payment rate for the class.

**Tabs:**

| Tab | Contains |
|---|---|
| **Overview** | Schedule, teacher, room, syllabus, capacity, price, enrolment window, financial summary for the class, health flags |
| **Roster** | Enrolled students with attendance %, balance, and grade-to-date; waitlist below with promote action |
| **Sessions** | Every session with date, status (held / cancelled / makeup / upcoming), attendance marked indicator, teacher who taught it (may differ from assigned) |
| **Attendance** | Grid: students × sessions. Read-only for managers by default, editable with permission; per-student and per-session rates |
| **Gradebook** | Components (quiz, midterm, final, participation) with weights; per-student scores; computed final; publish control |
| **Homework & materials** | Assignments with due dates and submission counts; shared files |
| **Announcements** | Messages sent to this class with delivery stats |
| **Settings** | Rename, reschedule, change teacher, change room, adjust capacity, change price, cancel class |

**Actions:** Enrol student · Promote from waitlist · Add session · Cancel session · Schedule
makeup · Change teacher (with substitution logging) · Message class · Export roster · Print
attendance sheet (a paper fallback that institutes genuinely need) · Close class.

**Next step:** a student's profile, session attendance, or the gradebook.

**Edge states:** Below minimum viable size with < 14 days to start → red banner with
`Merge with another class` and `Notify enrolled students`. Overbooked → warning with the
overflow list and a waitlist conversion action. No teacher assigned → blocking banner: the
class cannot be published without one. Schedule conflict (teacher or room) → inline conflict
card naming the other class and offering resolution.

---

## F.10 Payment / record payment (`/finance/payments`, payment modal)

**Purpose:** Take money and produce a correct record in under 30 seconds, at a counter, with
someone waiting.

### The record-payment modal (highest-frequency money action)

**First view:** Student (pre-filled if opened from a profile), amount due prominently shown,
and the amount field focused with the full outstanding amount pre-filled.

**Fields:** Student (searchable) · Applies to (invoice/instalment, defaulting to the oldest
unpaid) · Amount (pre-filled, editable, with quick chips: `Full` `Half` `Next instalment`) ·
Method (Cash · Card · Bank transfer · Online gateway · Cheque) · Date (defaults to today,
Jalali) · Reference number · Received by (defaults to the logged-in user) · Attach proof ·
Note.

**Live feedback:** As the amount changes, the modal shows `Remaining balance after this
payment: X` — this single line prevents the most common front-desk error.

**On submit:** Receipt generated · SMS with receipt link sent to student (toggleable, on by
default) · Balance updated · Instalment schedule recalculated · Audit entry written.

**Edge states:** Overpayment → offer credit-to-account or refund, never silently accept.
Payment against a withdrawn enrolment → warning requiring confirmation. Gateway timeout →
mark pending, never mark paid, and put the transaction in a reconciliation queue.

### Student-facing payment page (`/me/payments/pay`)

**First view:** The amount due, the due date, and one primary button. Nothing else above
the fold.

**Sections:** Amount due card · What this covers (term, class, instalment number) · Payment
method selection · Pay now → gateway · Alternative: bank transfer details + upload proof ·
Instalment schedule with paid/pending/overdue states · Payment history · Downloadable receipts.

**Actions:** Pay full · Pay a specific instalment · Pay custom amount (if the institute allows
it) · Upload proof of a card-to-card transfer · Download receipt · Set a payment reminder.

**Edge states:** Payment fails → clear cause, unchanged balance, and a retry that does not
re-create the invoice. Payment succeeds but callback is delayed → "Processing — we'll confirm
within a few minutes" with no double-charge possible (idempotency key on the transaction).
Zero balance → celebratory paid state with receipt history, not an empty page.

---

## F.11 Exam page

### Manager view — exam detail (`/exams/{id}`)

**Purpose:** Run an assessment event end to end: define, schedule, administer, grade, publish.

**First view:** Exam name, class/course, type (placement / midterm / final / quiz), date and
time, duration, total marks, pass mark, and a status pipeline: `Draft → Scheduled → In progress
→ Grading → Published`.

**Sections:** Details & settings · Participants (auto-populated from the class roster, with
add/remove and special accommodations) · Structure (sections, weights, question bank if
online) · Administration (paper or online; for online: window, attempts, shuffle, proctoring
settings) · Grading (per-student entry grid, bulk import, per-component breakdown) ·
Results & analytics (distribution histogram, mean/median, pass rate, hardest questions,
comparison against previous cohorts) · Publishing (when students can see results, whether to
show correct answers).

**Actions:** Edit · Schedule · Duplicate for another class · Enter grades · Import grades from
file · Publish results · Notify students · Generate certificates · Export.

**Edge states:** Grades incomplete at publish → blocking list of missing students. Student
absent → mark absent, excluded from the average and from pass-rate statistics. Re-sit → a
linked exam attempt preserving the original record rather than overwriting it.

### Student view — take exam (`/me/exams/{id}/take`)

**First view:** Exam name, instructions, question count, duration, attempts remaining, and one
`Start` button. A confirmation step ("The timer starts now and cannot be paused") prevents
accidental starts.

**During:** Persistent countdown (colour shift at 5 minutes remaining) · question navigator
showing answered/unanswered/flagged · one question per screen on mobile, paginated on desktop ·
autosave every answer with a visible "saved" indicator · flag-for-review · explicit submit
with an unanswered-question warning.

**Edge states:** Connection lost → answers persist locally, a banner shows offline state, and
sync resumes automatically; the timer is authoritative on the server, never the client. Tab
closed → resume from the last saved answer with the correct remaining time. Time expires →
auto-submit with a clear message, no data loss.

---

## F.12 Reports page (`/reports` and children)

**Purpose:** Turn operational data into decisions, and let a manager export or schedule any
of it without asking for help.

**First view (`/reports`):** A library of report cards grouped by category (Academic ·
Financial · Operational · Growth), each with a name, one-line description, and a thumbnail
sparkline of its headline metric. Below: saved and scheduled reports.

**Structure of an individual report page:**
1. **Filter bar (sticky)** — date range (with Jalali presets: this term, last term, this year),
   branch, term, course, level, teacher, class. Filters are in the URL and shareable.
2. **Headline metrics** — 3–4 large numbers with period-over-period deltas
3. **Primary visualisation** — the right chart for the data: trend → line, comparison → bar,
   composition → stacked bar (not pie beyond 5 categories), distribution → histogram, cohort →
   heatmap
4. **Breakdown table** — the same data as rows, sortable, with the export button. Every chart
   has a table equivalent — for accessibility and because managers trust tables
5. **Drill-down** — clicking any chart segment or row navigates to the filtered underlying list
6. **Insight strip** — plain-language observations: *"Attendance in evening classes is 11 points
   below morning classes this term."* Rule-based in v1, not ML

**Core reports:** Enrolment & growth · Revenue & collections · Outstanding & ageing ·
Retention & churn (term-over-term cohort) · Attendance · Teacher workload · Class & course
performance · Lead conversion funnel · Branch comparison.

**Actions:** Filter · Change granularity (day/week/month/term) · Export CSV/Excel/PDF ·
Save as a named view · Schedule delivery (email/SMS to defined recipients on a cadence) ·
Share a link · Drill down.

**Edge states:** No data in range → "No data for [range]. Try a wider range" + a button to
expand it, never a blank axis frame. Partial data (term in progress) → an explicit
"Term in progress — 4 of 12 weeks" label so figures aren't misread as final. Slow query →
skeleton with a shimmer, and for very large tenants an async "we'll email you the export"
path rather than a hanging request.

---

## F.13 Term builder (`/terms/new`)

**Purpose:** Compress the single most painful annual/quarterly workflow — building next term —
from two weeks to an afternoon. This is the product's highest-value screen for the S2 segment.

**Flow (a wizard, not a form):**

1. **Basics** — term name, Jalali start/end dates, enrolment window, branch(es)
2. **Start from** — *Clone previous term* (recommended, pre-selected) / *Start empty* /
   *Import from file*. Cloning copies class structure, pricing, and teacher assignments, and
   shifts every date by the term offset.
3. **Calendar** — holidays and closures, so session generation skips them correctly
4. **Classes** — a review grid of every cloned class: keep / edit / remove / add. Inline edit
   of schedule, teacher, room, capacity, price. Conflicts (teacher double-booked, room clash)
   are flagged live in the grid and block publishing until resolved.
5. **Pricing** — per-course fee, early-bird discount, loyalty discount for returning students,
   instalment plan template
6. **Review & publish** — a summary: N classes, N sessions generated, N teachers assigned,
   N conflicts (must be zero), estimated capacity and revenue. Publishing generates all
   sessions, opens enrolment, and can trigger the re-enrolment campaign.

**Actions:** Save draft at every step · Clone · Bulk-shift all dates · Resolve conflict ·
Publish · Publish and launch re-enrolment campaign.

**Edge states:** Conflicts present → publish is disabled with a conflict list, each linking to
its resolution. Term overlaps an existing one → warning with an overlap explanation (legitimate
for intensive courses, so warn but allow). Cloning a term with removed teachers → those classes
are flagged unassigned and listed explicitly.

---

## F.14 Communication composer (`/communication/announcements/new`)

**Purpose:** Send the right message to the right people without exporting a phone list.

**First view:** Audience selector first, message second. The count of recipients is visible
and live-updating from the moment the first filter is applied — this is what prevents
mis-sends.

**Sections:** Audience (by branch / term / class / level / status / balance state / teacher /
tag, combinable, with a live `→ 142 recipients` counter and a "preview recipients" link) ·
Channel (in-app / SMS / email, with per-channel cost shown for SMS) · Content (template
picker, variable insertion `{{student_name}}`, character count and SMS-segment count) ·
Schedule (now or later) · Preview (rendered as a real SMS and a real email, with variables
resolved against a sample recipient) · Send.

**Actions:** Select audience · Preview recipients as a list · Insert variable · Save template ·
Send test to self · Schedule · Send.

**Edge states:** Insufficient SMS credits → blocked with the exact shortfall and a top-up
link, before composing, not after. Audience is zero → send disabled with an explanation.
Audience over a threshold (e.g. 500) → an explicit confirmation step naming the count.
Delivery failures → per-recipient status in the delivery log with a retry-failed-only action.

---

# PART 3 — TEACHER PANEL

## F.15 Teacher dashboard (`/teach`)

**Purpose:** Tell a teacher where to be and what's outstanding — in one screen, on a phone.

**First view:** **Next class card**, large: class name, time, room, and a `Mark attendance`
button. If a class is happening right now, this card is in a live state with the button
as the single dominant element on screen.

**Sections:** Next class card · Today's sessions (chronological, each with marked/unmarked
state) · Pending tasks (unmarked attendance from previous days — the highest-value nag in the
product; ungraded submissions; unpublished grades) · This week at a glance · Institute
announcements.

**Actions:** Mark attendance · Open class · Post homework · Message a class · Request
substitution · View schedule.

**Next step:** attendance marking (the dominant path, by a wide margin).

**Edge states:** No classes today → "No classes today" with the next scheduled class and its
date. New teacher, no assignments → "You have no classes assigned yet — your manager will
assign them." Offline → a banner, cached data shown, and queued actions listed with their
sync state.

---

## F.16 Attendance marking (`/teach/classes/{id}/attendance/{sessionId}`)

**Purpose:** Mark a full class in under 20 seconds on a phone. This screen's performance
determines the data quality of the entire product.

**Design rules:**
- Everyone defaults to **present**. The teacher marks the exceptions only — this matches
  reality (most students attend) and cuts taps by ~85%.
- Each student is one full-width row: photo, name, and a segmented control
  (`Present · Absent · Late · Excused`) with 48px targets.
- Marking absent reveals an optional inline reason field — never a separate modal.
- A running count sits at the top: `16 present · 2 absent · 1 late`.
- Submit is a fixed bottom bar, always reachable, showing what will happen:
  `Submit — notifies 2 students`.

**Sections:** Session header (class, date, session N of M, room) · Bulk actions (mark all
present / all absent — for a cancelled session) · Student list · Session notes (what was
covered — feeds the student's progress view) · Submit bar.

**Actions:** Set per-student status · Add reason · Add session note · Mark session cancelled ·
Submit · Edit after submission (within an institute-configured window; edits are audited).

**Edge states:** Offline → marks queue locally with a visible "will sync" indicator; submitting
offline is allowed and confirmed optimistically. Already submitted → read-only with an `Edit`
action if within the edit window, and a clear "locked" explanation if not. Student added to
the class mid-term → appears with sessions before their enrolment date marked N/A, not absent.
Substitute teacher → can mark, and the session records who actually taught.

---

## F.17 Gradebook (`/teach/classes/{id}/grades`)

**Purpose:** Enter and manage grades without a spreadsheet, with the final grade computed
correctly and transparently.

**First view:** A grid — students as rows, grade components as columns, with the weight shown
in each column header (`Midterm 30%`). The computed final column is on the right, visually
separated and clearly derived.

**Interaction:** Click any cell to edit inline; Enter moves down, Tab moves right — spreadsheet
muscle memory, deliberately. Autosave per cell with a saved indicator. Mobile falls back to a
per-student card view, because a grid is unusable on a phone.

**Sections:** Component setup (name, max score, weight — weights must total 100%, validated
live) · Grade grid · Class statistics (mean, median, distribution, pass rate) · Publish
control (grades are invisible to students until explicitly published) · Comments per student.

**Actions:** Configure components · Enter grades · Import from file · Bulk-apply a score ·
Add a per-student comment · Publish to students · Export · Print report cards.

**Edge states:** Weights don't total 100% → the final column shows a warning instead of a
number, with the shortfall named. Score above maximum → rejected inline with the valid range.
Missing grades at publish → a list of which students are missing which component, with the
option to publish partial and notify only the complete.

---

# PART 4 — STUDENT PORTAL

## F.18 Student dashboard (`/me`)

**Purpose:** Answer the four student questions instantly: when is class, what do I owe, what's
due, how am I doing.

**First view:** **Next class card** — class name, day/time (with a relative label:
"Tomorrow, 6:00 PM"), room or online-join button, and teacher. Directly below, if a balance
exists: the **amount due card** with the due date and a `Pay now` button.

**Sections in priority order:** Next class · Balance due (conditional; hidden when zero) ·
Upcoming homework (next 3, with due dates) · New announcements · Progress snapshot (current
level, attendance %, grade to date) · Quick links (schedule, materials, payments) ·
Re-enrolment offer (conditional, appears in the final weeks of a term as a prominent card).

**Actions:** Join online class · Pay · Open homework · Read announcement · View schedule ·
Re-enrol · Message the institute.

**Edge states:** No active enrolment → the page becomes an enrolment invitation: browse open
classes, book a placement test. Term ended → results summary, certificate download, and the
re-enrolment offer as the primary action. Everything up to date → a calm confirming state
("You're all set — next class Saturday 6:00 PM"), never a page of empty cards.

---

## F.19 Student enrolment (`/me/enrol`)

**Purpose:** Let a student enrol themselves, which is the mechanism that removes front-desk
load.

**Flow:** Choose language/program → level (from placement result or history, with "not sure?
book a placement test") → browse open classes filtered by level with day/time/teacher/seats
remaining → select → review price with any applicable discounts itemised → choose payment
(full or instalment plan) → pay or reserve → confirmation with the schedule and portal link.

**Key detail:** seats remaining must be live, and a selection holds a seat for 15 minutes with
a visible countdown during checkout. Without this, two students pay for the last seat.

**Edge states:** Class fills during checkout → the hold expires with an apology, an immediate
waitlist offer, and alternative classes at the same level. Payment fails → the enrolment is
held in `pending_payment` for a configurable window rather than being destroyed. Outstanding
balance from a previous term → the institute can configure whether this blocks new enrolment;
if it does, the message explains it and links to payment.

---

# PART 5 — SUPER ADMIN

## F.20 Platform overview (`/`)

**Purpose:** Health of the business and health of the system, on one screen.

**First view:** MRR with trend, active tenants, trials expiring this week, and open critical
alerts.

**Sections:** Revenue KPIs (MRR, ARR, net revenue retention, churn) · Tenant funnel (trials →
active → at-risk → churned) · Needs attention (failed subscription payments, trials expiring
in 48 h, tenants with zero activity for 14 days, SLA-breaching tickets, error-rate spikes) ·
System health (API error rate, job queue depth, SMS/email delivery rates, storage) · Recent
signups · Support queue summary.

---

## F.21 Tenant management (`/tenants`, `/tenants/{id}`)

**Purpose:** Everything about one customer, and the ability to act on it.

**List view:** Institute name, plan, students (used/limit), branches, MRR, health score,
last activity, state. Filters by plan, state, health, country, signup cohort.

**Detail view sections:** Profile & primary contacts · Subscription (plan, cycle, next renewal,
payment method, invoice history) · Usage against limits (students, branches, staff, SMS,
storage) with over-limit flags · Health score with its component breakdown (login frequency,
enrolments created, attendance marked, payments recorded — usage decay is the churn predictor)
· Users and their last login · Support history · Audit log · Danger-zone actions.

**Actions:** Change plan · Extend trial · Apply credit or discount · Suspend / reactivate ·
Impersonate (reason required, time-boxed, tenant-owner notified, fully logged) · Export
tenant data · Schedule deletion (with a grace period and a mandatory typed confirmation).

**Edge states:** Over student limit → a banner with the overage and options (upgrade prompt to
the tenant, grace period, or manual limit increase). Payment failed → the dunning state and
timeline are visible, with the next automated action and its date. Deletion requested → a
countdown banner with cancellation available throughout the grace period.

---

## F.22 Plans & entitlements (`/plans`)

**Purpose:** Change pricing and feature gating without a deployment.

**Sections:** Plan list (name, price by currency and cycle, active tenant count) · Plan editor
(price, limits, included features, trial length, visibility) · Feature/entitlement matrix
(feature × plan grid, editable) · Quotas (students, branches, staff, SMS, storage, API rate) ·
Coupons (code, discount, applicability, redemption limit, expiry, usage stats) · Add-ons.

**Critical rule:** changing a plan definition must **never** silently change entitlements for
existing subscribers. Existing tenants stay on their subscribed snapshot; migration to a new
plan version is an explicit, per-tenant, communicated action. Getting this wrong removes
features from paying customers without warning — an unrecoverable trust failure.

---

## F.23 Support & tickets (`/support/tickets`)

**Purpose:** Resolve customer problems with full context and without over-broad data access.

**Sections:** Queue (priority, SLA countdown, assignee, tenant, subject) · Ticket detail
(conversation thread, tenant context sidebar showing plan/usage/health/recent errors, internal
notes, canned responses, resolution) · SLA monitor · Escalation path.

**Actions:** Reply · Add internal note · Change priority/status · Assign · Request scoped,
expiring tenant access · Link to a known issue · Convert to a product feedback item.

**Edge state:** Data access request → the tenant owner receives an approval request; access is
scoped, expiring, and every record viewed under it is logged and visible to the tenant in
their own audit log.
