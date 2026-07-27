# G. Main User Flows

Each flow below is written as an implementable sequence: the happy path, the decision points,
the failure branches, and the system side-effects. These convert directly into acceptance
criteria.

Notation: `→` step · `⤷` branch · `⚙` system action (no user involvement) · `⚠` failure path.

---

## G.1 Visitor → demo request

**Goal:** capture a qualified lead with enough context for a useful sales call.
**Success metric:** form completion rate > 35% of page visits; contacted within 4 business hours.

```
1. Visitor lands on / or /features/* (usually from search, Instagram, or referral)
2. Reads, scrolls, clicks "Request a demo"     → /demo
3. Sees form + "what happens next" + one testimonial
4. Fills 7 fields (name, institute, phone, email, students, branches, challenge)
   ⚷ Inline validation on blur; phone format checked against country
5. Submits
   ⚙ Lead record created with source, campaign, UTM, referrer, device
   ⚙ Lead scored: student count × branch count × challenge keywords → hot / warm / cold
   ⚙ Routed to a rep by segment and language
   ⚙ Confirmation SMS + email sent immediately
   ⚙ Internal notification to the assigned rep (hot leads: immediate push)
6. → /demo/confirmed
   Shows: rep name + photo, promised contact window, calendar file,
   product tour video, pricing link
7. ⤷ Optional: visitor books a specific slot from the embedded calendar
   ⚙ Slot reserved, calendar invites sent to both parties
8. Rep contacts within SLA (hot: 1 h, warm: 4 h, cold: 24 h)
9. Demo delivered → outcome logged: trial started / proposal sent / not a fit / follow-up
   ⤷ Trial started → hands off to G.3 (onboarding)
   ⤷ Not a fit → reason recorded (too small, wrong segment, price, timing) and
     added to a nurture sequence
```

**⚠ Failure branches**
| Failure | Handling |
|---|---|
| Duplicate submission (same phone, < 24 h) | Friendly "we already have your request" — do not create a second lead |
| Submission error | Form state preserved, error explained, phone number offered as fallback |
| Invalid phone | Inline, before submit, with the expected format shown |
| No response after 3 contact attempts | Auto-move to nurture; stop calling. Respect the person's silence |
| Visitor doesn't want a call | The "start a trial instead" exit is always visible on the demo page |

---

## G.2 Visitor → sign up (self-serve trial)

**Goal:** an institute inside a working workspace in one sitting.
**Success metric:** > 60% of signups reach "first real data entered" within 24 h.

```
1. Visitor clicks "Start free trial" (from /, /pricing, or /features/*)
2. → /signup, step 1 of 3
3. Enters institute name, own name, phone, email, password, country/language
   ⚙ Workspace subdomain auto-suggested from the institute name, live availability check
4. Phone verification: 6-digit OTP by SMS
   ⤷ Not received → resend after 60 s cooldown → fall back to email verification
5. ⚙ Tenant provisioned:
     - isolated tenant record + schema/row-scope
     - owner user created with the owner role
     - 14-day trial subscription on the Growth plan (full features during trial)
     - default configuration seeded by country: locale, calendar, currency, timezone
     - default level structure seeded by the languages selected in step 2
     - default message templates, grading scale, attendance rules
6. → Step 2: institute profile (languages, student count, branches, term timing,
   "what to fix first"). Skippable.
   ⚙ Answers configure enabled modules and the ORDER of the onboarding checklist —
     an institute that says "payments" sees the finance setup task first
7. → Step 3: first value. Three cards + one quiet fourth:
   ⤷ Import student list → G.3 step 4
   ⤷ Create first term    → G.5
   ⤷ Invite team          → invitation flow
   ⤷ Explore with sample data → tenant seeded with realistic demo records,
     wipeable in one click from Settings (highest-converting option for the hesitant)
8. → Institute Overview with the onboarding checklist expanded
9. ⚙ Lifecycle sequence begins: day 1 welcome, day 3 tip, day 7 check-in,
     day 11 trial-ending, day 14 conversion, day 16 grace, day 21 data-retention notice
```

**⚠ Failure branches**
| Failure | Handling |
|---|---|
| Phone already registered | Offer login, password reset, or "add another institute to this account" |
| Subdomain taken | Live check with suggestions, never a post-submit rejection |
| Abandoned at step 1 | Account exists → recovery message at 1 h / 24 h / 72 h |
| Abandoned at step 3 | Checklist persists; day-3 email links straight to the unfinished task |
| Trial expires unconverted | Read-only for 7 days (never immediate lockout), then export-only for 30 days, then scheduled deletion with notice |

---

## G.3 Institute onboarding (trial → operational)

**Goal:** the institute's real data in the system and its first real transaction completed.
**This is the flow that determines whether the customer converts.** Treat it as the most
important product surface after the teacher panel.

```
1. Owner lands on Overview; onboarding checklist is the dominant element
2. Task 1 — Institute profile: name, logo, address, phone, working hours, branding colour
   ⚙ Applied immediately to the student portal and message templates — visible payoff
3. Task 2 — Academic structure
   → Confirm or edit the seeded languages/programs
   → Confirm or edit the level ladder (e.g. A1→C2, or the institute's own naming)
   → Create courses (name, level, duration, sessions/week, default price)
   ⚙ Common structures offered as one-click presets per language
4. Task 3 — Import students
   → Upload Excel/CSV, or add manually, or connect nothing and start fresh
   → Column mapping screen: system-detected mapping shown, user corrects
   → Validation preview: N valid, N warnings, N errors — with the specific issue per row
   → Confirm import
   ⚙ Students created, duplicates detected by phone/national ID and flagged for review
     rather than silently merged or silently duplicated
   ⤷ Errors → downloadable file of failed rows with a reason column; fix and re-upload
     (the re-upload matches on the same key and does not create duplicates)
5. Task 4 — Create the first term → G.5
6. Task 5 — Invite the team
   → Add users by phone/email with a role each (front desk, teacher, finance, academic)
   ⚙ Invitations sent; each invitee gets a role-appropriate first-run guide
7. Task 6 — Configure money
   → Currency, tax, invoice numbering, payment methods, instalment policy,
     late-fee and reminder policy
   → Connect a payment gateway (optional — cash-only institutes must be fully supported)
8. Task 7 — Configure communication
   → SMS provider and sender ID, email sender, review default templates
   ⚙ Trial SMS credits granted so the institute can test end to end without paying
9. Task 8 — First real action: enrol one student and record one payment
   ⚙ Checklist complete → celebration → checklist disappears permanently
10. ⚙ Health tracking begins; the customer-success signal is enrolments-created-per-week
```

**⚠ Failure branches**
| Failure | Handling |
|---|---|
| Messy import file (merged cells, multiple header rows, mixed formats) | Mapping screen tolerates it; unparseable → offer "send us the file and we'll import it" (a real service, not a deflection — this is where onboarding fees earn their keep) |
| Institute stalls at task 3 | Day-3 automated nudge + a customer-success call for institutes above 200 students |
| Owner doesn't have the data ready | "Save and continue later" everywhere; the checklist never blocks other work |
| Institute wants to run parallel with their old system | Accept it, but set an explicit switchover date during onboarding — parallel running past one term is the strongest churn predictor there is |

---

## G.4 Institute → create branch

```
1. Manager → Branches → "Add branch"                    (plan-gated: Growth+)
2. Enters: name, address, phone, working hours, timezone (defaults to institute),
   manager assignment
3. Adds rooms: name, capacity, equipment, availability windows
4. ⚙ Branch created; every list, report, and dashboard gains this branch in its scope filter
5. Assigns users to the branch with branch-scoped roles
   ⤷ Existing users can be given access to multiple branches
6. ⤷ Optional: copy course catalogue and pricing from an existing branch
7. Optional: separate public page, phone number, and invoice numbering series
8. ⚙ If this is the tenant's second branch, the branch scope switcher appears in the top bar
   for the first time, with a one-time explanatory tooltip
```

**Key rules**
- Branch 1 exists implicitly for every tenant. Single-branch institutes never see the concept.
- Branch is a **scope**, not a separate tenant: students can transfer between branches with
  history intact, teachers can teach at multiple branches, and consolidated reporting is
  always available.
- Deleting a branch with active classes is blocked; it must first be archived, which requires
  reassigning or completing its classes.

**⚠ Failure branches:** over the plan's branch limit → upgrade prompt with the exact price
difference. Timezone differs from the institute default → an explicit confirmation, because
session times will render differently across branches.

---

## G.5 Manager → create term and classes

```
1. Manager → Terms → "Create term"                      → /terms/new
2. Basics: name ("Autumn 1404"), Jalali start/end, enrolment window, branches included
3. Start from:
   ⤷ Clone previous term (default) — copies classes, pricing, teacher assignments,
     and shifts all dates by the term offset
   ⤷ Start empty
   ⤷ Import from file
4. Calendar: holidays and closures added
   ⚙ Session generation will skip these dates
5. Classes grid — for each class: course, level, schedule (days + times), teacher, room,
   branch, capacity (min and max), price, start/end
   ⚙ LIVE CONFLICT DETECTION on every edit:
      · teacher double-booked at the same time
      · room double-booked
      · teacher's declared availability violated
      · teacher weekly-hour cap exceeded
      · room capacity < class max capacity
   Each conflict renders inline in the grid with a specific resolution action
6. Pricing: per-course fee, early-bird discount + deadline, returning-student discount,
   instalment plan template (e.g. 40% deposit + 2 instalments)
7. Review: N classes · N sessions to be generated · N teachers · N conflicts · capacity ·
   projected revenue
   ⚠ Publish is DISABLED while any hard conflict remains
8. Publish
   ⚙ All sessions generated across the term, holidays excluded
   ⚙ Classes become visible on the public page and the student portal
   ⚙ Enrolment opens
   ⚙ Teachers notified of their assignments and can view their schedule
   ⤷ Optional and recommended: launch the re-enrolment campaign (G.11)
```

**⚠ Failure branches**
| Failure | Handling |
|---|---|
| No teacher available for a slot | Class saved as `unassigned`, excluded from publish until resolved, listed explicitly on the review step |
| Term overlaps an existing term | Warn and explain, but allow (intensive courses legitimately overlap) |
| Cloned teacher no longer employed | Flagged as unassigned with the former teacher's name shown for context |
| Published term needs a change | Allowed, with an impact preview: "This affects 14 enrolled students and 8 future sessions" and an optional notification |

---

## G.6 Manager → assign / change teacher

```
1. From Class detail → Settings → "Change teacher"
   (or from Term builder, or from Teachers → availability matrix)
2. System lists eligible teachers, ranked:
   ✓ available at this time (no conflict)
   ✓ qualified for this level/language
   ✓ under their weekly hour cap
   ⚠ shown with a warning if any condition fails — never hidden, because managers
     legitimately override in a crisis
3. Manager selects; if any warning applies, an explicit confirmation with the reason
4. Manager chooses the change type:
   ⤷ Permanent reassignment (rest of term)
   ⤷ Temporary substitution (date range) — original teacher retained on the class record
   ⤷ Single-session cover
5. ⚙ Effects:
     - future sessions reassigned; past sessions keep the teacher who actually taught
     - both teachers notified with the affected dates
     - students notified (toggleable; on by default for permanent changes)
     - substitution logged for payroll and for the teacher's workload report
     - class detail shows the substitution history
```

**⚠ Failure branches:** no eligible teacher → offer to cancel the affected sessions and
schedule makeups, or to merge the class. Teacher leaves mid-term → a guided bulk reassignment
across all their classes at once, not one class at a time.

---

## G.7 Manager → enrol a student (front desk, walk-in)

**Target: under 120 seconds.**

```
1. Front desk: Quick create (+) → "Enrol student"   (or from a lead, or a student profile)
2. Student identification:
   ⤷ Existing → search by name/phone/ID → select (history and level pre-load)
   ⤷ New → inline creation: name, phone, birth date, gender, national ID (optional),
     guardian (auto-required if under the institute's minor age threshold)
   ⚙ Duplicate check on phone and national ID as the field is typed
3. Level determination:
   ⤷ Known from history → pre-selected
   ⤷ Placement test result exists → pre-selected with the test date shown
   ⤷ Unknown → "Book placement test" (creates a booking and pauses the enrolment
     in `awaiting_placement`) or manual override with a reason
4. Class selection: open classes at that level, showing day/time, teacher, room, branch,
   start date, and **seats remaining** — filterable by day and time preference
   ⤷ Preferred class is full → offer waitlist, or alternatives at the same level
5. Pricing review: base fee, applicable discounts itemised (early bird, returning student,
   sibling, coupon), final amount. Manual discount requires the permission and a reason.
6. Payment arrangement:
   ⤷ Pay in full now
   ⤷ Instalment plan (template or custom): deposit + N instalments with due dates
   ⤷ Reserve without payment (permission-gated, with a hold expiry)
7. Payment capture (if paying now) → G.9
8. Confirm
   ⚙ Enrolment created, seat consumed, capacity decremented atomically
   ⚙ Invoice + instalment schedule generated
   ⚙ Receipt generated
   ⚙ SMS to student: welcome, schedule, first session date, portal link with a
     one-tap login token
   ⚙ Student portal account activated
   ⚙ Teacher's roster updated
   ⚙ Lead (if any) marked converted, with the conversion time recorded
9. → Confirmation screen: schedule summary, print receipt, print schedule card,
   "enrol another student"
```

**⚠ Failure branches**
| Failure | Handling |
|---|---|
| Last seat taken concurrently | Atomic capacity check at commit; loser gets an immediate waitlist offer and alternatives, never a silent overbooking |
| Student has an outstanding balance from a previous term | Institute-configurable: block, warn, or allow. If it blocks, the message says so plainly and links to payment |
| Class starts tomorrow | Warn about the shortened term and offer pro-rated pricing if the institute has that policy configured |
| Payment gateway down | Complete the enrolment as `pending_payment` with a hold; never lose the enrolment because the payment rail failed |

---

## G.8 Teacher → mark attendance

**Target: under 20 seconds. The most frequent write operation in the product.**

```
1. Teacher opens the app → Today → "Mark attendance" on the current/next class
   ⤷ Alternative entries: push notification 10 min after session start,
     class detail, or the pending-tasks list
2. Roster loads with EVERY student defaulted to PRESENT
3. Teacher taps only the exceptions:
   · tap "Absent" → optional inline reason
   · tap "Late" → optional minutes-late
   · tap "Excused" → reason required
4. Optional: session note ("Covered Unit 4, pages 32–38") — surfaces in the student's
   class view and in the progress record
5. Submit — the bottom bar states the consequence: "Submit — notifies 3 students"
6. ⚙ Effects:
     - attendance records written for every student
     - absence notifications sent to absent students (and guardians, for minors)
     - attendance rates recalculated for student, class, teacher, and institute
     - a student crossing the institute's absence threshold is flagged as at-risk and
       appears on the manager's Needs-attention panel
     - session marked complete; teacher's pending-task count decrements
```

**⚠ Failure branches**
| Failure | Handling |
|---|---|
| Offline | Queue locally, optimistic confirmation, visible sync-pending indicator, auto-sync on reconnect. Attendance must never be blocked by connectivity |
| Forgotten | Reminder push 30 min after session end; unmarked after 24 h escalates to the manager's exception queue |
| Session cancelled | "Cancel session" with a reason → all students marked N/A (not absent), students notified, and a makeup session optionally scheduled |
| Correction needed | Editable within an institute-configured window (default 48 h); every edit audited with before/after and actor |
| Substitute teacher | Can mark; the session records the actual teacher separately from the assigned teacher |
| Student not on the roster | "Add to session" for trial attendees or late enrolments, flagged for the manager to reconcile |

---

## G.9 Student → pay tuition

Two paths — both must be excellent, because a large share of this market pays in person.

### Path A — online, self-service
```
1. Student receives an SMS: "Your instalment of X is due on [date]. Pay: [short link]"
   ⤷ or opens the portal and sees the balance-due card
2. → /me/payments — amount due, what it covers, due date
3. "Pay now" → chooses full amount or a specific instalment
4. Payment method: gateway card payment / saved method / bank transfer
5. → Gateway (hosted, PCI-safe; we never touch card data)
6. Gateway callback
   ⚙ Idempotent transaction handling — a duplicate callback can never double-credit
   ⚙ Payment recorded, invoice updated, instalment marked paid
   ⚙ Receipt generated, SMS + email sent
   ⚙ Balance recalculated; if zero, the student's finance flags clear everywhere
7. → Success screen: receipt, updated schedule of remaining instalments
```

### Path B — offline (cash, card-to-card, bank transfer)
```
1. Student pays at the counter, or transfers and uploads proof
2a. Counter: front desk records the payment → G.10 modal → receipt printed and SMS'd
2b. Transfer: student uploads a screenshot/reference in the portal
    ⚙ Payment enters `pending_verification`; the balance is NOT yet reduced
    ⚙ Finance staff see it in a verification queue
3. Finance verifies against the bank record → approve or reject
   ⤷ Approved → recorded as a payment, receipt issued, student notified
   ⤷ Rejected → student notified with the reason, proof retained for the record
```

**⚠ Failure branches**
| Failure | Handling |
|---|---|
| Gateway declines | Clear reason, balance unchanged, retry available, alternative methods offered |
| Payment succeeds, callback lost | Reconciliation job polls the gateway; the student sees "processing", never a double charge |
| Partial payment | Applied to the oldest instalment first (configurable), remaining balance shown explicitly |
| Overpayment | Held as account credit or refunded — never silently absorbed |
| Payment after withdrawal | Blocked with an explanation; routed to finance for a refund decision |
| Currency/rounding | All money in integer minor units; no floating-point arithmetic anywhere in the ledger |

---

## G.10 Manager → record a payment (counter)

```
1. From the student profile (or Finance → Record payment, or Quick create)
2. Modal opens with: student, outstanding amount shown large,
   amount field focused and PRE-FILLED with the full outstanding
3. Adjust amount if partial — quick chips: Full · Half · Next instalment
   ⚙ Live line under the field: "Remaining after this payment: X"
4. Method · date (defaults today, Jalali) · reference number · received by · attach proof · note
5. Submit
   ⚙ Payment recorded and applied to instalments oldest-first
   ⚙ Receipt generated with the institute's numbering series
   ⚙ SMS with the receipt link (toggle, default on)
   ⚙ Audit entry with actor, timestamp, and before/after balance
6. → Receipt preview with Print and Send options
```

**⚠ Failure branches:** amount exceeds outstanding → explicit credit-or-refund choice.
Payment to the wrong student → a void action within a permission-gated window, which creates
a reversal entry rather than deleting the record (financial records are append-only). Duplicate
receipt number → blocked by the numbering series, which is transactional and gap-free.

---

## G.11 Term rollover and re-enrolment

**The highest-revenue flow in the product.** Retention is cheaper than acquisition, and most
institutes have no process for it at all.

```
── 6 weeks before term end ──
1. ⚙ System prompts the manager: "Autumn term ends in 6 weeks. Build the next term?"
2. Manager builds the next term (G.5) and publishes

── 4 weeks before ──
3. Manager → Admissions → Re-enrolment
   ⚙ Every currently-enrolled student is listed with a SUGGESTED next class:
     - next level if they're passing and attendance is adequate
     - repeat level if not
     - same weekday/time slot where one exists
     - same teacher where possible
4. Manager reviews and adjusts suggestions in bulk, then launches the campaign
   ⚙ Each student receives an SMS + portal offer:
     "Your next class: [course], [days] [time], starts [date].
      Reserve your seat: [link]. Early-bird price until [date]."
5. Student opens the link → one-tap accept → pays or reserves → enrolled
   ⤷ Wants a different class → browses alternatives at their level
   ⤷ Not continuing → optional one-tap reason (moved / cost / time / satisfied /
     dissatisfied) — this is the only reliable churn-reason data an institute will ever get

── 3 weeks before ──
6. ⚙ Reminder to non-responders
   ⚙ Manager's board: offered → viewed → accepted → paid → enrolled, per class

── 2 weeks before ──
7. ⚙ Non-responders after two touches move to a call list for the front desk,
   with the student's history and last teacher's note attached
   ⚙ Classes below minimum viable size are flagged with merge suggestions

── 1 week before ──
8. Manager resolves underfilled classes: merge, move students, or cancel with refunds
   ⚙ Affected students notified with their new arrangement, not just the cancellation

── Term start ──
9. ⚙ Previous term closed: final grades locked, certificates issued,
   outstanding balances carried forward with their ageing preserved,
   retention report generated (term-over-term, per class, per teacher, per level)
```

---

## G.12 Lead → enrolled student (full funnel)

```
1. CAPTURE — sources: public page form · Instagram/WhatsApp click · phone call logged by
   front desk · walk-in · referral code · placement-test booking
   ⚙ Lead created with source and campaign attribution
2. QUALIFY — front desk contacts within the SLA (default 24 h, configurable)
   Records: language wanted, current level (self-reported), goal (exam / travel /
   work / general), budget sensitivity, availability
   ⚙ Lead stage: New → Contacted
3. PLACEMENT — placement test booked (in person or online)
   ⚙ Stage: Contacted → Assessed. Result writes a recommended level to the lead
   ⤷ No-show → automated reschedule offer, then two follow-ups, then dormant
4. PROPOSE — front desk shows suitable classes with times and prices
   ⚙ Stage: Assessed → Proposed
5. CONVERT — enrolment (G.7)
   ⚙ Stage: Proposed → Enrolled. Lead converted; time-to-conversion recorded
   ⚙ Referral credit applied if a referral code was used
6. ⤷ LOST — reason recorded (price / schedule / location / chose competitor /
   not ready / unreachable) → nurture sequence, and re-surfaced automatically when a
   class matching their stated constraint opens
```

**Reporting outcome:** a real conversion funnel with drop-off at each stage, by source and
by campaign — the number that tells the institute where to spend marketing money. Almost no
institute has this today.

---

## G.13 Student → view schedule

Small flow, disproportionate impact: it is the most-used student feature and the one that
most reduces front-desk phone calls.

```
1. Student opens the portal (usually from an SMS deep link, already authenticated)
2. → /me — Next class card is the first thing on screen
3. → /me/schedule — week view by default, showing class, time, room, teacher, and
   an online-join button where applicable
4. Actions: switch to month · session detail · add to phone calendar (.ics) ·
   see the full term calendar including holidays and exam dates
5. ⚙ Any schedule change (cancellation, room change, time change, substitute teacher)
   pushes a notification and updates the view immediately
```

**Edge:** no enrolment → the page becomes an enrolment invitation rather than an empty
calendar. Multiple classes → colour-coded per class, consistent with the class colour used
everywhere else in the product.

---

## G.14 Super admin → manage institutes

```
1. Admin → Tenants; default sort is health score ascending (problems first)
2. Filters: plan · state (trial / active / past due / suspended / churned) · health ·
   country · signup cohort · usage vs. limit
3. Open a tenant → full context: subscription, usage, health components, users,
   support history, audit log
4. Actions by situation:
   ⤷ Trial ending, high engagement  → assign to sales for a conversion call
   ⤷ Trial ending, low engagement   → onboarding rescue: offer a setup session
   ⤷ Over student limit             → upgrade prompt, grace period, or manual increase
   ⤷ Payment failed                 → dunning sequence visible with the next action and date
   ⤷ Usage decay 14+ days           → churn-risk playbook triggered
   ⤷ Support escalation             → impersonate with a stated reason (time-boxed,
                                       owner-notified, fully logged)
   ⤷ Requests cancellation          → exit interview, retention offer, then scheduled
                                       deletion with a grace period and export
```

**Non-negotiable rule:** impersonation requires a reason string, expires automatically
(default 60 minutes), notifies the tenant owner, and writes every accessed record to an audit
log the tenant can read. Support convenience never outranks tenant trust.

---

## G.15 Super admin → manage subscriptions

```
1. Admin → Billing → Subscriptions (or from a tenant detail)
2. Views: active · trialing · past due · cancelled · scheduled changes
3. Common operations:
   · Change plan        → proration calculated and PREVIEWED before applying
   · Change cycle       → monthly ↔ annual with the credit/charge shown
   · Apply a discount   → percentage or fixed, for a duration, with a reason recorded
   · Extend a trial     → new date + reason (tracked, because habitual extension
                           masks a broken onboarding)
   · Add an add-on      → SMS pack, extra branch, priority support
   · Pause              → retention alternative to cancellation
   · Cancel             → immediate or at period end, with reason + exit survey
4. ⚙ Every change: tenant notified, invoice adjusted, entitlements updated at the
   next boundary, full audit trail written
5. Dunning on failed payment:
   day 0 retry → day 1 notify → day 3 retry → day 5 notify + in-app banner →
   day 7 retry → day 10 read-only mode → day 20 suspended → day 45 scheduled deletion
   ⚙ At every step: export remains available, data is never destroyed without notice
```

---

## G.16 Super admin → review reports

```
1. Admin → Platform overview: MRR, ARR, tenant counts, churn, trials, system health
2. Drill into any metric:
   · Revenue    → by plan, cohort, country, expansion vs. new vs. churn (MRR waterfall)
   · Retention  → logo and revenue retention by signup cohort
   · Usage      → feature adoption by plan; features nobody uses are candidates for removal
   · Funnel     → visit → signup → activated → converted → retained
   · Support    → volume by category (the top category is always a product defect
                   or a UX failure — treat it as a roadmap input, not a staffing problem)
3. Scheduled digests: weekly business summary, daily ops alert
4. Export for board reporting
```

---

## G.17 Cross-cutting flow rules

These apply to every flow above and should be enforced as platform-wide invariants.

| Rule | Why |
|---|---|
| **Every destructive action is confirmed and reversible where possible** | Front-desk staff work fast under pressure; mistakes are certain |
| **Every financial record is append-only** | Corrections create reversal entries, never edits or deletes |
| **Every automated message is previewable and toggleable** | Institutes must control their own voice and their own SMS spend |
| **Every long operation is async with progress** | Imports, exports, bulk sends, and report generation never block the UI |
| **Every list state is in the URL** | Filters, scope, and pagination are shareable and restorable |
| **Every write is audited with actor, timestamp, and before/after** | Disputes about who changed what are routine in institutes |
| **Every notification links to the exact record** | Deep-linking is the single biggest driver of student and teacher adoption |
| **Nothing critical requires connectivity** | Attendance and payment recording must degrade gracefully, not fail |
