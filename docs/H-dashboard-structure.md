# H. Dashboard Structure

## H.1 The governing principle

**A dashboard is a launcher, not a report.**

The most common failure in operational SaaS is a dashboard full of charts that nobody acts on.
Every widget in Talkora must pass one test:

> *Does this either (a) tell the user something they must act on today, or (b) let them start
> a task they perform frequently?*

If neither, it belongs in Reports, not on the dashboard.

**Consequence:** exceptions come before metrics, and metrics come before charts. On the
manager dashboard, the *Needs attention* panel sits above the fold and the trend charts sit
below it. That ordering is inverted from most SaaS products and it is deliberate.

### Shared dashboard grammar

| Element | Rule |
|---|---|
| **KPI tile** | Big number · label · delta vs. comparable period · sparkline · clickable to the underlying list. Never a number without a comparison — a number alone is not information |
| **Alert item** | Severity dot · plain-language statement · count · one-click resolution. Never "12 issues" without naming them |
| **Quick action** | Opens a modal or side panel; never navigates away and loses context |
| **Chart** | Has a table equivalent, a legend, tooltips on hover/tap, and an accessible empty state |
| **Empty state** | Names the cause and the first action; never a blank rectangle |
| **Loading** | Skeleton matching the final layout, so nothing shifts when data arrives |
| **Density** | Compact — this is a work tool used for hours. 8px spacing scale, not 24px |
| **Refresh** | Live-ish: operational counts poll on a short interval; heavy aggregates are cached with a visible "as of HH:MM" and a manual refresh |

---

## H.2 Institute dashboard (owner / manager)

### Navigation

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ [Logo] Pardis Language    [Branch: All ▾] [Term: Autumn 1404 ▾]             │
│                                  [⌘K search] [+ Create] [🔔 3] [Avatar ▾]   │
├──────────────┬──────────────────────────────────────────────────────────────┤
│ DAILY        │                                                              │
│  ⌂ Overview  │                                                              │
│  ◱ Admissions│                        MAIN CONTENT                          │
│  ◉ Students  │                                                              │
│  ◈ Teachers  │                                                              │
│  ▤ Classes   │                                                              │
│ ─────────────│                                                              │
│ ACADEMIC     │                                                              │
│  ◷ Terms     │                                                              │
│  ▦ Curriculum│                                                              │
│  ✓ Attendance│                                                              │
│  ✎ Exams     │                                                              │
│ ─────────────│                                                              │
│ BUSINESS     │                                                              │
│  ₪ Finance   │                                                              │
│  ✉ Comms     │                                                              │
│  ◫ Reports   │                                                              │
│ ─────────────│                                                              │
│ CONFIG       │                                                              │
│  ⌗ Branches  │                                                              │
│  ⚙ Settings  │                                                              │
└──────────────┴──────────────────────────────────────────────────────────────┘
```

- Sidebar is persistent on ≥1280px, icon-collapsed 1024–1280px, a drawer below 1024px.
- Branch and term switchers are the **scope** for everything below them and are reflected in
  the URL.
- Sections the tenant's plan or configuration doesn't include are hidden entirely, not
  greyed out. A typical single-branch Growth tenant sees 10 items, not 15.

### Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Good morning, Reza          شنبه ۱۲ مهر ۱۴۰۴ · Week 4 of 12 · Autumn 1404  │
├─────────────────────────────────────────────────────────────────────────────┤
│ ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌───────────┐                     │
│ │ ACTIVE    │ │ COLLECTED │ │ OVERDUE   │ │ ATTENDANCE│    ← KPI ROW        │
│ │ STUDENTS  │ │ THIS TERM │ │           │ │ THIS WEEK │                     │
│ │   412     │ │  68%      │ │ 84.0M IRR │ │   91%     │                     │
│ │  ▲ 6.2%   │ │ ▓▓▓▓▓▓░░░ │ │ 37 people │ │  ▼ 2 pts  │                     │
│ │  ╱╲╱‾     │ │ 612M/900M │ │ ▲ 12%     │ │  ‾╲╱╲     │                     │
│ └───────────┘ └───────────┘ └───────────┘ └───────────┘                     │
├─────────────────────────────────────────────────────────────────────────────┤
│  ⚠ NEEDS ATTENTION                                             [Dismiss all]│
│  ● 3 sessions from yesterday still unmarked          [Remind teachers →]     │
│  ● 12 students overdue more than 14 days             [Send reminders →]      │
│  ● 2 classes below minimum size, term starts in 9d   [Review →]              │
│  ● 1 class has no teacher assigned                   [Assign →]              │
│  ○ 5 leads with no contact for 3+ days               [Open call list →]      │
├──────────────────────────────────────┬──────────────────────────────────────┤
│  TODAY                               │  QUICK ACTIONS                       │
│  09:00 IELTS B2 · Sara N. · R3  ✓    │  [+ Student]  [+ Payment]            │
│  11:00 German A1 · Ali M. · R1   ✓   │  [+ Class]    [+ Announcement]       │
│  16:00 English A2 · Nadia · R2   ⏳   │  [+ Lead]     [Import]               │
│  18:00 IELTS C1 · Sara N. · R3   ·   │                                      │
│  18:00 Turkish A1 · —— · R4      ⚠   │  TERM PROGRESS                       │
│  → 14 sessions today · 9 marked      │  ▓▓▓▓░░░░░░░░ Week 4/12              │
│                                      │  412 enrolled / 480 capacity (86%)   │
│                                      │  Re-enrolment opens in 3 weeks       │
├──────────────────────────────────────┴──────────────────────────────────────┤
│  ENROLMENT TREND (12 terms)        │  COLLECTED vs EXPECTED (monthly)       │
│  [line chart]                      │  [grouped bar chart]                   │
├─────────────────────────────────────────────────────────────────────────────┤
│  RECENT ACTIVITY                                          [View all →]      │
│  10:42  Maryam recorded 4,500,000 IRR from Sina Ahmadi                      │
│  10:31  Sara N. marked attendance for IELTS B2 (16/18 present)              │
│  09:55  New lead: Hasti Karimi — German, from Instagram                     │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Widget specification

| Widget | Content | Interaction | Why it earns its place |
|---|---|---|---|
| **Active students** | Count, Δ vs. same week last term, 12-term sparkline | → filtered student list | The headline health number |
| **Collected this term** | % collected, absolute collected/expected, progress bar | → finance overview | The single most-asked owner question |
| **Overdue** | Amount + student count, Δ vs. last week | → debt board sorted by age | Direct revenue recovery |
| **Attendance this week** | Institute-wide rate, Δ vs. last week | → attendance report | Leading indicator of churn |
| **Needs attention** | Max 6 exception types, each with a count and a one-click action | Resolves inline in a side panel | **The reason the manager opens the app** |
| **Today** | Every session with marking status; unassigned or unmarked flagged | → session or attendance | Real-time operational awareness |
| **Quick actions** | Six highest-frequency creates | Modal, context-preserving | Removes navigation from routine work |
| **Term progress** | Week N of M, enrolment vs. capacity, re-enrolment countdown | → term detail | Orients every other number in time |
| **Enrolment trend** | Line, 12 terms, with a term-over-term retention overlay | Hover for values, click to drill | Growth direction at a glance |
| **Collected vs expected** | Grouped bars, monthly | Click a month → payment list | Cash-flow reality |
| **Recent activity** | Last 20 audited events, filterable by user and type | → the affected record | Trust and oversight without micromanagement |

### Role variations (same page, different emphasis)

| Role | KPI row | Needs attention filtered to | Hidden |
|---|---|---|---|
| **Owner** | Students · Collected · Overdue · Attendance | Everything | — |
| **Academic manager** | Students · Attendance · Classes at risk · Unmarked sessions | Academic + teacher exceptions | Finance amounts, billing |
| **Finance manager** | Collected · Overdue · Ageing · Refunds pending | Financial exceptions only | Grades, attendance detail |
| **Front desk** | Today's enrolments · Payments taken today · Leads to call · Pending verifications | Their own task queue | Reports, institute-wide finance |
| **Branch manager** | Same as owner, scoped to their branch | Their branch only | Other branches entirely |

### Multi-branch behaviour
With scope = **All branches**, every KPI shows the consolidated figure plus a per-branch
breakdown on hover, and a **Branch comparison** strip appears below the KPI row: each branch
as a row with students, fill rate, collection rate, and attendance — sortable, with outliers
highlighted. Switching to a single branch removes the strip and rescopes everything.

---

## H.3 Teacher dashboard

Mobile-first. The desktop layout is the mobile layout in two columns — not a different design.

```
┌───────────────────────────────┐
│  Today · شنبه ۱۲ مهر          │
│                        [🔔 2] │
├───────────────────────────────┤
│ ┌───────────────────────────┐ │
│ │  NEXT CLASS  · in 25 min  │ │   ← dominant element
│ │  IELTS B2                 │ │
│ │  16:00 – 17:30 · Room 3   │ │
│ │  18 students              │ │
│ │  ┌───────────────────────┐│ │
│ │  │   MARK ATTENDANCE     ││ │   ← 56px, full width, primary
│ │  └───────────────────────┘│ │
│ │  [Roster]  [Materials]    │ │
│ └───────────────────────────┘ │
├───────────────────────────────┤
│  ⚠ PENDING (2)                │
│  · Attendance — English A2,   │
│    Thursday          [Mark →] │
│  · 6 homework submissions     │
│    ungraded          [Open →] │
├───────────────────────────────┤
│  TODAY'S CLASSES              │
│  ✓ 09:00 German A1   marked   │
│  ▸ 16:00 IELTS B2    now      │
│  · 18:00 IELTS C1    upcoming │
├───────────────────────────────┤
│  THIS WEEK                    │
│  8 classes · 12 hours         │
│  [Full schedule →]            │
├───────────────────────────────┤
│  FROM THE INSTITUTE           │
│  "Thursday is a holiday —     │
│   no classes."     2 days ago │
├───────────────────────────────┤
│ [▸Today][▤Classes][◷Sched]    │
│ [✉Msgs][☰More]                │  ← bottom nav, 5 items max
└───────────────────────────────┘
```

### Widget specification

| Widget | Rule |
|---|---|
| **Next class card** | The dominant element. Shows a live state during the session. Its primary button is `Mark attendance` — one tap from app-open to the marking screen |
| **Pending** | Unmarked attendance first (highest institutional value), then ungraded work, then unpublished grades. Persistent until cleared. This is the mechanism that keeps data complete |
| **Today's classes** | Chronological, each with an unambiguous marking state |
| **This week** | Class count and teaching hours — teachers care about hours because pay depends on them |
| **Institute announcements** | Last 3, read state tracked |

**Deliberately absent:** revenue, other teachers, institute-wide metrics, student contact
details beyond what teaching requires, anything requiring interpretation. The teacher panel is
a task list, not an analytics surface.

**Offline behaviour:** the last 7 days and next 7 days of schedule and roster are cached.
Attendance marked offline queues visibly and syncs automatically. A persistent, non-alarming
banner shows offline state; queued items are listed with their sync status.

---

## H.4 Student dashboard

```
┌───────────────────────────────┐
│  Hi Sina                [🔔 1]│
├───────────────────────────────┤
│ ┌───────────────────────────┐ │
│ │  NEXT CLASS               │ │
│ │  IELTS B2 · Sara Nouri    │ │
│ │  Tomorrow 16:00 · Room 3  │ │
│ │  [Add to calendar]        │ │
│ └───────────────────────────┘ │
├───────────────────────────────┤
│ ┌───────────────────────────┐ │
│ │  ⚠ 4,500,000 IRR due      │ │   ← only rendered when > 0
│ │     by ۲۰ مهر (in 8 days) │ │
│ │  ┌───────────────────────┐│ │
│ │  │       PAY NOW         ││ │
│ │  └───────────────────────┘│ │
│ │  [View instalment plan]   │ │
│ └───────────────────────────┘ │
├───────────────────────────────┤
│  HOMEWORK DUE                 │
│  · Unit 4 exercises  in 2d    │
│  · Speaking recording in 5d   │
├───────────────────────────────┤
│  MY PROGRESS                  │
│  Level B2 · Week 4 of 12      │
│  Attendance  ▓▓▓▓▓▓▓▓▓░  94%  │
│  Grade so far          17.5/20│
│  [See details →]              │
├───────────────────────────────┤
│  ANNOUNCEMENTS                │
│  "Thursday holiday — no class"│
├───────────────────────────────┤
│ [⌂Home][◷Sched][▤Classes]     │
│ [₪Pay][☰More]                 │
└───────────────────────────────┘
```

### Priority logic
Widgets render in this order, and conditional widgets disappear entirely when not applicable
(never render as an empty state):

1. **Next class** — always
2. **Balance due** — only if > 0; escalating visual weight as the due date nears, and a
   distinct overdue treatment
3. **Re-enrolment offer** — only in the final 4 weeks of a term; when present, it outranks
   homework
4. **Homework due** — only if unsubmitted work exists
5. **Announcements** — only if unread
6. **Progress** — always
7. **Certificates** — only after term completion

**Design consequence:** a student who owes nothing and has no homework sees a short, calm page
that confirms everything is fine. That is a feature. Padding it with widgets to fill the
screen would be a mistake.

---

## H.5 Super admin dashboard

Visually distinct from tenant surfaces — different accent colour and a permanent environment
banner — so platform staff can never mistake which system they're in.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  PLATFORM ADMIN                            [Search tenants] [Alerts 4] [You]│
├─────────────────────────────────────────────────────────────────────────────┤
│ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐            │
│ │ MRR      │ │ ACTIVE   │ │ TRIALS   │ │ CHURN    │ │ NRR      │            │
│ │ $18,400  │ │ 127      │ │ 23       │ │ 1.8%     │ │ 108%     │            │
│ │ ▲ 12%    │ │ ▲ 9      │ │ 6 ending │ │ ▼ 0.4pt  │ │ ▲ 3pt    │            │
│ └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘            │
├─────────────────────────────────────────────────────────────────────────────┤
│  ⚠ NEEDS ATTENTION                                                          │
│  ● 3 subscription payments failed (day 3+)               [Dunning queue →]  │
│  ● 6 trials ending within 48h, 4 highly engaged          [Sales list →]     │
│  ● 2 tenants with zero activity for 14 days              [Churn risk →]     │
│  ● 1 ticket breaching SLA in 2h                          [Open →]           │
│  ● SMS delivery rate 89% on provider B (normally 97%)    [Investigate →]    │
├──────────────────────────────────┬──────────────────────────────────────────┤
│  TENANT FUNNEL                   │  SYSTEM HEALTH                           │
│  Visitors      12,400            │  API error rate      0.12%   ✓           │
│  Signups          186  (1.5%)    │  p95 latency         340ms   ✓           │
│  Activated        119  (64%)     │  Job queue depth        24   ✓           │
│  Converted         47  (25%)     │  SMS delivered        97.2%  ✓           │
│  Retained 3mo      41  (87%)     │  Email delivered      99.1%  ✓           │
│                                  │  Storage              62%    ✓           │
├──────────────────────────────────┴──────────────────────────────────────────┤
│  MRR MOVEMENT (waterfall)         │  TENANTS BY HEALTH                      │
│  [new / expansion / contraction / │  [stacked bar: healthy / watch / at-risk│
│   churn waterfall]                │   per plan]                             │
├─────────────────────────────────────────────────────────────────────────────┤
│  RECENT SIGNUPS               │  SUPPORT QUEUE                              │
│  Ariana Institute · Growth    │  Open 12 · In progress 5 · Breaching 1      │
│  Sepehr Language · trial      │  Top category: import errors (4)  ← product │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Widget notes

| Widget | Purpose |
|---|---|
| **MRR / Active / Trials / Churn / NRR** | The five numbers that describe the business. NRR above 100% is the signal that expansion pricing works |
| **Needs attention** | Same exception-first pattern as the tenant dashboard. Money problems first, then churn risk, then SLA, then platform health |
| **Tenant funnel** | Visit → signup → activated → converted → retained, with conversion rates. Identifies which stage is leaking |
| **System health** | Error rate, latency, queue depth, message delivery, storage. Delivery rate belongs here because a silent SMS-provider degradation is invisible to tenants but destroys their trust |
| **MRR waterfall** | New / expansion / contraction / churn. The only honest way to read revenue movement |
| **Tenants by health** | Composition by plan; a rising at-risk band in one plan indicates a pricing or fit problem |
| **Support queue** | Volume plus the **top ticket category**, which should be read as a roadmap input — the most common ticket is almost always a product defect |

---

## H.6 Alerts, thresholds, and notification discipline

Alert fatigue is the failure mode that makes dashboards decorative. Rules:

| Rule | Detail |
|---|---|
| **Thresholds are configurable per tenant** | "Overdue" means 7 days at one institute and 30 at another |
| **Severity is capped** | Maximum 3 red items at once; beyond that they group into one "N issues" item |
| **Every alert is actionable** | An alert without a one-click action is a report; move it to Reports |
| **Alerts are dismissible with memory** | Dismissing suppresses that alert for a configured period, not forever, and not globally for other users |
| **Alerts age out** | An alert nobody has acted on in 30 days is either mis-tuned or unimportant. Surface this to the tenant admin as a tuning suggestion |
| **Channel matches urgency** | Dashboard for routine, in-app notification for time-sensitive, SMS/email only for money and schedule changes |

### Default alert catalogue

| Alert | Trigger (default) | Severity | Action |
|---|---|---|---|
| Unmarked attendance | Session ended > 24 h ago | ● High | Remind teacher |
| Overdue payment | Instalment > 14 days past due | ● High | Send reminders / call list |
| Class below minimum | Enrolled < min, term starts in < 14 d | ● High | Merge / cancel / market |
| Unassigned class | Published class with no teacher | ● High | Assign teacher |
| Trial ending | 3 days remaining | ● High | Convert (super admin) |
| Subscription payment failed | Any failure | ● High | Dunning |
| Stale lead | No contact in 3 days | ○ Medium | Open call list |
| At-risk student | Absence rate above threshold | ○ Medium | Contact student |
| Capacity exceeded | Enrolled > max | ○ Medium | Review roster |
| Low SMS credits | Below one week of typical usage | ○ Medium | Top up |
| Schedule conflict | Teacher or room double-booked | ● High | Resolve |
| Grades unpublished | Term ended > 7 days ago | ○ Medium | Publish |
| Pending payment verification | Proof uploaded > 24 h ago | ○ Medium | Verify |
