# C. The Main Business Problem

## C.1 The core problem, stated plainly

**A language institute is a logistics business wearing an education costume — and almost none
of them are equipped to run logistics.**

An institute with 400 students is simultaneously managing: ~40 classes across ~15 time slots,
~25 teachers with conflicting availability, ~400 payment schedules in various states of
completion, a rolling admissions pipeline, and a communication load of several hundred
messages a week. Almost all of it is coordinated with tools that cannot enforce a rule,
cannot notify anyone, and cannot answer a question.

The result is an institute that is **profitable in spite of its operations, not because of
them**, and whose growth ceiling is set by how much its manager can personally hold in memory.

## C.2 The ten concrete pain points

### 1. Manual, disconnected operations
Registration happens on paper or in a spreadsheet. Payment is recorded in a notebook or a
different spreadsheet. Attendance is on a printed sheet in a folder. Grades live in the
teacher's personal file. **None of these systems know the others exist.** Every question that
spans two of them ("did this student who owes money show up this week?") requires a human to
manually cross-reference.

> **Cost:** 3–8 staff-hours per week per 100 students, just moving data between systems.

### 2. Scattered tools and spreadsheets
The typical stack: Excel for students, WhatsApp for announcements, a paper ledger or Excel
for money, Google Calendar or a whiteboard for schedule, a phone's contacts for numbers,
Telegram for teacher coordination, and a filing cabinet for contracts.

Each tool works. The **seams** between them are where the business bleeds. And every tool is
owned by a different person, so no one can answer a question alone.

> **Cost:** knowledge fragmentation. When the person who maintains the spreadsheet leaves,
> a measurable part of the business leaves with them.

### 3. Class scheduling is genuinely hard
Scheduling a term is a constraint-satisfaction problem: teacher availability × room capacity
× student level × preferred time slots × minimum viable class size × maximum class size.
Institutes solve it by hand, in a spreadsheet, over two weeks, badly. Then a teacher quits
and the whole thing has to be re-solved manually.

> **Cost:** 20–60 hours of senior management time per term, plus the revenue lost to classes
> that were scheduled at times students didn't want.

### 4. Weak communication
Announcements go out on WhatsApp groups that half the students have muted. Individual
reminders are sent manually or not at all. There's no record of what was sent to whom.
When a class is cancelled, the institute phones 14 people one at a time.

> **Cost:** absence, no-shows, missed payments, and a constant background hum of "nobody
> told me." Students who feel uninformed don't re-enrol.

### 5. No visibility into finances
The owner knows roughly what came in this month. They do **not** reliably know:
- exactly who owes what, and for how long
- what percentage of expected term revenue has actually been collected
- which discounts were given and by whom
- which classes are profitable versus subsidised by others

> **Cost:** 5–15% of expected tuition quietly uncollected. At 400 students this is often
> more than the annual cost of the software that would have prevented it.

### 6. No unified reporting
Any question that requires combining two data sources becomes a project. "How many students
who took a placement test last term actually enrolled?" is unanswerable in most institutes.
So the questions stop being asked, and decisions get made on intuition.

> **Cost:** the institute cannot see its own trends, so it repeats mistakes and can't
> identify what's working.

### 7. Low automation
Everything that could be automatic is manual: payment reminders, absence notifications,
schedule changes, term re-enrolment offers, certificate issuance, receipt generation.
Staff time is consumed by tasks with zero judgment content.

> **Cost:** an institute hires its 3rd and 4th admin person to do work that should not exist.

### 8. High dependency on staff memory
"Ask Maryam, she knows." Institutional knowledge lives in individuals: which student is on a
special payment arrangement, which teacher can't do Tuesdays, which parent must be called and
not messaged. When Maryam is sick, the institute degrades. When Maryam quits, it breaks.

> **Cost:** key-person risk on people earning modest salaries, and an onboarding cost of
> 4–8 weeks for every new admin hire.

### 9. Weak student retention tracking
Institutes measure enrolment, not retention. They rarely know their term-over-term retention
rate, and they almost never know *why* a student left — because nobody noticed they left
until the next term's numbers came in short.

> **Cost:** the highest-leverage growth lever in the business is invisible. Retaining an
> existing student costs a fraction of acquiring a new one, and institutes spend on ads while
> quietly losing 30% of their base each term.

### 10. Slow enrolment workflows
A prospective student's path — enquiry → placement test → level assignment → class selection →
payment → confirmation — takes days and multiple physical visits. Every step is a chance to
lose them. Leads that arrive at 11 PM from an Instagram post wait until morning, by which
time they've contacted two competitors.

> **Cost:** direct lost revenue at the top of the funnel, invisible because uncontacted leads
> never appear in any report.

## C.3 The compounding pattern

These ten problems aren't independent — they reinforce each other:

```
   Scattered tools ──► No unified data ──► No reporting ──► No visibility
          ▲                                                       │
          │                                                       ▼
   More manual work ◄── More staff hires ◄── More errors ◄── Decisions by intuition
          │                                                       │
          ▼                                                       ▼
   Staff memory dependency ─────────────────────────────► Weak retention tracking
                                                                  │
                                                                  ▼
                                                    Growth ceiling / revenue leak
```

An institute cannot fix any single link in isolation. Buying a better spreadsheet doesn't
help. Adding a WhatsApp bot doesn't help. **The only intervention that breaks the cycle is
integrating the data, which is precisely the thing point solutions can't do.**

This is the strategic argument for a single integrated platform, and it's the argument the
sales conversation should make.

## C.4 What institutes have already tried (and why it failed)

| Attempted solution | Why it fails |
|---|---|
| Better spreadsheets | No enforcement, no notifications, no multi-user safety, no history |
| Generic CRM (HubSpot/Zoho) | Models leads well, models classes and attendance not at all |
| Generic school ERP | Built for K-12: parents, grade levels, curriculum. Wrong shape, heavy, expensive |
| LMS (Moodle, Google Classroom) | Handles content and assignments; ignores money, scheduling, admissions |
| Custom-built software | One developer, no maintenance, breaks in 18 months, no one can extend it |
| Accounting software | Records money after the fact; doesn't drive collection or connect to enrolment |
| Hiring more admin staff | Linear cost for sublinear improvement; adds coordination overhead |

Each of these fixes one column of the problem. The institute ends up with **more** disconnected
tools than before — a common and demoralising outcome that makes them sceptical of the next
vendor. Expect this scepticism in the sales conversation and answer it with a migration plan,
not a feature list.

## C.5 The problems this creates for students (the second-order cost)

Institutes underweight this, but it's where retention is actually lost:

- Students don't know their schedule until someone tells them
- Students find out a class is cancelled when they arrive
- Students can't see what they owe until they're chased
- Students have no idea how they're progressing beyond a final grade
- Students must physically visit to enrol, pay, or ask a question
- Students who want to re-enrol have to re-negotiate everything from scratch

A student comparing an institute that texts them a schedule link with one that says "come in
on Saturday and we'll see" is making a quality judgment about the education, not the admin.
**Operational professionalism is read as educational quality.** That's the pitch.

## C.6 Why this problem is worth building a company around

1. **Universal.** Every language institute has all ten problems. There is no segment that
   has solved this.
2. **Expensive.** The cost is measurable in staff hours and uncollected tuition — both easy
   to quantify in a sales call.
3. **Recurring.** The pain recurs every single term, forever. Not a one-time project.
4. **Sticky.** Once enrolment, money, and history are in the system, migrating out means
   losing the institute's memory. Retention is structurally high.
5. **Underserved.** Existing tools are either K-12 ERPs (wrong shape) or Western SaaS
   (wrong locale, wrong price, wrong calendar, wrong payment rails).
6. **Expandable.** Same tenant base later absorbs online classes, content, certification,
   and CRM — expansion revenue without a new sale.
