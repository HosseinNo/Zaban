# E. Information Architecture

## E.1 Top-level surfaces

Lingo Talk has five distinct surfaces, each with its own navigation model and URL space.

| Surface | Audience | Host pattern | Nav model |
|---|---|---|---|
| **Marketing site** | Prospective institutes | `lingotalk.com` | Horizontal top nav |
| **Institute app** | Managers, front desk, finance | `app.lingotalk.com/{tenant}` | Persistent sidebar + top bar |
| **Teacher panel** | Teachers | `app.lingotalk.com/{tenant}/teach` | Mobile bottom nav / desktop sidebar |
| **Student portal** | Students, guardians | `app.lingotalk.com/{tenant}/me` | Mobile bottom nav / desktop sidebar |
| **Super admin console** | Platform team | `admin.lingotalk.com` | Sidebar, visually distinct |
| *(Institute public page)* | Prospective students | `{tenant}.lingotalk.com` or custom domain | Simple, institute-branded |

**Routing principle:** the tenant is always resolved from the URL, never from session state.
A user with access to two institutes has two distinct URLs, not a hidden toggle. This prevents
the entire class of "I edited the wrong institute" errors, and makes every URL shareable and
bookmarkable.

**Role principle:** a single human may hold multiple roles (an owner who also teaches). Roles
are separate *surfaces*, not modes — the user switches with an explicit control and always
knows which surface they're in, reinforced by a distinct top-bar treatment per surface.

---

## E.2 Public marketing site

```
lingotalk.com
│
├── Home                                    /
│   ├── Hero + primary CTA
│   ├── Problem framing
│   ├── Core capability overview (6 blocks)
│   ├── Role-based value (owner / teacher / student)
│   ├── Product visual walkthrough
│   ├── Metrics / social proof
│   ├── Customer stories
│   ├── Pricing preview
│   ├── FAQ preview
│   └── Final CTA
│
├── Features                                /features
│   ├── Overview (all modules)              /features
│   ├── Admissions & registration           /features/admissions
│   ├── Classes & scheduling                /features/classes
│   ├── Attendance                          /features/attendance
│   ├── Exams & grading                     /features/exams
│   ├── Finance & tuition                   /features/finance
│   ├── Communication                       /features/communication
│   ├── Reports & analytics                 /features/reports
│   ├── Student portal                      /features/student-portal
│   ├── Teacher panel                       /features/teacher-panel
│   ├── Multi-branch                        /features/multi-branch
│   └── Security & data ownership           /features/security
│
├── Solutions                               /solutions
│   ├── For small institutes                /solutions/small-institutes
│   ├── For growing institutes              /solutions/growing-institutes
│   ├── For multi-branch chains             /solutions/multi-branch
│   ├── For independent tutors              /solutions/tutors
│   ├── For online institutes               /solutions/online
│   └── For corporate training               /solutions/corporate
│
├── Pricing                                 /pricing
│   ├── Plan comparison
│   ├── Student-count calculator
│   ├── Add-ons (SMS, onboarding, extra branches)
│   ├── Plan feature matrix
│   └── Billing FAQ
│
├── Request Demo                            /demo
│   └── Confirmation + calendar booking     /demo/confirmed
│
├── About                                   /about
│   ├── Story & mission
│   ├── Team
│   └── Careers                             /about/careers
│
├── Contact                                 /contact
│   ├── Sales
│   ├── Support
│   └── Partnerships
│
├── FAQ                                     /faq
│   ├── Product
│   ├── Pricing & billing
│   ├── Data & security
│   ├── Migration & onboarding
│   └── Technical
│
├── Resources                               /resources
│   ├── Blog index                          /blog
│   ├── Blog post                           /blog/{slug}
│   ├── Guides                              /resources/guides
│   ├── Templates & downloads               /resources/templates
│   ├── Customer stories                    /customers
│   ├── Product changelog                   /changelog
│   └── Help centre                         /help
│
├── Legal                                   /legal
│   ├── Terms of service                    /legal/terms
│   ├── Privacy policy                      /legal/privacy
│   ├── Data processing                     /legal/dpa
│   └── Refund policy                       /legal/refunds
│
└── Auth
    ├── Log in                              /login
    ├── Sign up (institute trial)           /signup
    ├── Forgot password                     /forgot-password
    ├── Reset password                      /reset-password
    ├── Verify account                      /verify
    └── Accept invitation                   /invite/{token}
```

### Institute public page (tenant-owned, template-based)

```
{tenant}.lingotalk.com
├── Home (institute landing)                /
├── Courses                                 /courses
│   └── Course detail                       /courses/{slug}
├── Schedule (open classes with seats)      /schedule
├── Register / Enquiry                      /register
├── Placement test booking                  /placement-test
├── About the institute                     /about
├── Contact                                 /contact
└── Student login                           → /me
```

This is deliberately minimal. It exists so an institute can put a working enrolment funnel
online in ten minutes — not to compete with website builders.

---

## E.3 Institute app (manager / staff)

The primary surface. Sidebar sections, ordered by daily frequency of use.

```
app.lingotalk.com/{tenant}
│
├── ⌂ Overview                                      /
│   ├── Today
│   ├── This term
│   └── Alerts & actions required
│
├── ◱ Admissions                                    /admissions
│   ├── Leads (pipeline board)                      /admissions/leads
│   │   └── Lead detail                             /admissions/leads/{id}
│   ├── Demo & enquiry requests                     /admissions/enquiries
│   ├── Placement tests                             /admissions/placement
│   │   ├── Scheduled
│   │   ├── Results
│   │   └── Test templates                          /admissions/placement/templates
│   ├── Registrations (in progress)                 /admissions/registrations
│   ├── Waitlist                                    /admissions/waitlist
│   └── Re-enrolment pipeline                       /admissions/re-enrolment
│
├── ◉ Students                                      /students
│   ├── All students (list + filters)               /students
│   ├── Student profile                             /students/{id}
│   │   ├── Overview
│   │   ├── Enrolments
│   │   ├── Attendance
│   │   ├── Grades & progress
│   │   ├── Finance
│   │   ├── Documents
│   │   ├── Communication log
│   │   └── Notes & activity
│   ├── Groups & tags                               /students/groups
│   ├── Guardians                                   /students/guardians
│   └── Import / export                             /students/import
│
├── ◈ Teachers                                      /teachers
│   ├── All teachers                                /teachers
│   ├── Teacher profile                             /teachers/{id}
│   │   ├── Overview
│   │   ├── Classes & schedule
│   │   ├── Availability
│   │   ├── Workload & hours
│   │   ├── Performance (attendance, ratings, results)
│   │   ├── Payroll basis
│   │   └── Documents
│   ├── Availability matrix                         /teachers/availability
│   └── Substitution log                            /teachers/substitutions
│
├── ▤ Classes                                       /classes
│   ├── All classes                                 /classes
│   ├── Class detail                                /classes/{id}
│   │   ├── Overview
│   │   ├── Roster
│   │   ├── Sessions
│   │   ├── Attendance
│   │   ├── Gradebook
│   │   ├── Homework & materials
│   │   ├── Announcements
│   │   └── Settings
│   ├── Schedule (calendar / timetable)             /classes/schedule
│   ├── Rooms & resources                           /classes/rooms
│   ├── Session log (held / cancelled / makeup)     /classes/sessions
│   └── Conflicts & warnings                        /classes/conflicts
│
├── ◷ Terms                                         /terms
│   ├── All terms                                   /terms
│   ├── Term detail                                 /terms/{id}
│   ├── Term builder (create / clone)               /terms/new
│   ├── Term calendar & holidays                    /terms/{id}/calendar
│   └── Term close-out                              /terms/{id}/close
│
├── ▦ Curriculum                                    /curriculum
│   ├── Programs / languages                        /curriculum/programs
│   ├── Levels & progression                        /curriculum/levels
│   ├── Courses                                     /curriculum/courses
│   │   └── Course detail                           /curriculum/courses/{id}
│   └── Materials library                           /curriculum/materials
│
├── ✓ Attendance                                    /attendance
│   ├── Today's sessions (marking status)           /attendance
│   ├── By class                                    /attendance/classes
│   ├── By student                                  /attendance/students
│   ├── Unmarked sessions (exception queue)         /attendance/unmarked
│   └── Absence follow-up                           /attendance/follow-up
│
├── ✎ Exams & Grades                                /exams
│   ├── Exam schedule                               /exams
│   ├── Exam detail                                 /exams/{id}
│   ├── Exam templates & question bank              /exams/templates
│   ├── Grading queue                               /exams/grading
│   ├── Results & analytics                         /exams/results
│   └── Certificates                                /exams/certificates
│
├── ₪ Finance                                       /finance
│   ├── Overview (collected / expected / overdue)   /finance
│   ├── Invoices                                    /finance/invoices
│   │   └── Invoice detail                          /finance/invoices/{id}
│   ├── Payments                                    /finance/payments
│   ├── Instalment plans                            /finance/plans
│   ├── Outstanding balances (debt board)           /finance/outstanding
│   ├── Refunds & credits                           /finance/refunds
│   ├── Discounts & coupons                         /finance/discounts
│   ├── Pricing & fee schedules                     /finance/pricing
│   ├── Payment methods & gateways                  /finance/methods
│   ├── Expenses (Phase 2)                          /finance/expenses
│   └── Teacher payouts (Phase 2)                   /finance/payouts
│
├── ✉ Communication                                 /communication
│   ├── Announcements                               /communication/announcements
│   ├── Campaigns (SMS / email)                     /communication/campaigns
│   ├── Inbox (conversations)                       /communication/inbox
│   ├── Templates                                   /communication/templates
│   ├── Automations (triggers & rules)              /communication/automations
│   ├── Delivery log                                /communication/log
│   └── Credits & usage                             /communication/credits
│
├── ◫ Reports                                       /reports
│   ├── Report library                              /reports
│   ├── Enrolment & growth                          /reports/enrolment
│   ├── Revenue & collections                       /reports/revenue
│   ├── Outstanding & ageing                        /reports/outstanding
│   ├── Retention & churn                           /reports/retention
│   ├── Attendance                                  /reports/attendance
│   ├── Teacher workload                            /reports/teachers
│   ├── Class & course performance                  /reports/courses
│   ├── Lead conversion funnel                      /reports/leads
│   ├── Branch comparison                           /reports/branches
│   ├── Saved & scheduled reports                   /reports/saved
│   └── Exports                                     /reports/exports
│
├── ⌗ Branches                                      /branches
│   ├── All branches                                /branches
│   ├── Branch detail                               /branches/{id}
│   ├── Rooms & capacity                            /branches/{id}/rooms
│   └── Branch settings & hours                     /branches/{id}/settings
│
├── ⚙ Settings                                      /settings
│   ├── Institute profile & branding                /settings/profile
│   ├── Users & roles                               /settings/users
│   │   ├── Users
│   │   ├── Roles & permissions
│   │   └── Invitations
│   ├── Academic settings                           /settings/academic
│   │   ├── Grading scales
│   │   ├── Attendance rules
│   │   ├── Level progression rules
│   │   └── Class defaults
│   ├── Financial settings                          /settings/finance
│   │   ├── Currency & tax
│   │   ├── Invoice numbering & templates
│   │   ├── Payment gateways
│   │   └── Late-fee & reminder policy
│   ├── Communication settings                      /settings/communication
│   │   ├── SMS provider & sender ID
│   │   ├── Email sender & domain
│   │   └── Default templates
│   ├── Localisation                                /settings/localisation
│   │   ├── Language & direction
│   │   ├── Calendar system (Jalali / Gregorian)
│   │   ├── Timezone
│   │   └── Number & date formats
│   ├── Public page                                 /settings/public-page
│   ├── Integrations                                /settings/integrations
│   ├── Data & privacy                              /settings/data
│   │   ├── Export
│   │   ├── Retention policy
│   │   └── Deletion requests
│   ├── Audit log                                   /settings/audit
│   └── Subscription & billing                      /settings/billing
│
└── Global elements (persistent, not nav items)
    ├── Command palette (⌘K) — search + actions
    ├── Global search — students, classes, invoices, teachers
    ├── Quick create (+) — student, class, payment, announcement
    ├── Notifications
    ├── Branch scope switcher (multi-branch tenants only)
    ├── Term scope switcher
    └── Account menu — profile, switch surface, switch institute, log out
```

### Navigation grouping rationale

The sidebar has 13 top-level items — above the conventional 7±2 guidance, deliberately.
Institute software is a **known-item navigation** problem, not a discovery problem: users know
they want "finance" and want to get there in one click. Burying finance under a "Business"
group adds a click to a daily task to satisfy an abstract rule.

Mitigations for the length:
- Items are grouped visually into four bands with subtle separators: **Daily** (Overview,
  Admissions, Students, Teachers, Classes) · **Academic** (Terms, Curriculum, Attendance,
  Exams) · **Business** (Finance, Communication, Reports) · **Configuration** (Branches, Settings).
- Sections not enabled by the plan or the tenant's configuration are **hidden entirely**,
  not disabled. A single-branch institute never sees Branches. A Starter tenant never sees
  Campaigns. Most tenants see 8–10 items.
- Sidebar collapses to icons on narrow desktop and to a drawer on mobile.
- The command palette is the real power-user path and should be taught during onboarding.

---

## E.4 Teacher panel

Optimised for a phone in a corridor between classes. Five destinations maximum.

```
app.lingotalk.com/{tenant}/teach
│
├── ▸ Today                              /teach                    [bottom nav 1]
│   ├── Next class card (prominent)
│   ├── Today's sessions with mark-status
│   ├── Pending tasks (unmarked attendance, ungraded work)
│   └── Recent announcements
│
├── ▤ Classes                            /teach/classes            [bottom nav 2]
│   └── Class detail                     /teach/classes/{id}
│       ├── Roster (with photos)
│       ├── Attendance                   /teach/classes/{id}/attendance
│       │   └── Session marking          /teach/classes/{id}/attendance/{sessionId}
│       ├── Gradebook                    /teach/classes/{id}/grades
│       ├── Homework                     /teach/classes/{id}/homework
│       │   ├── Assign
│       │   └── Submissions & feedback
│       ├── Materials                    /teach/classes/{id}/materials
│       ├── Announcements                /teach/classes/{id}/announce
│       └── Student progress             /teach/classes/{id}/students/{studentId}
│
├── ◷ Schedule                           /teach/schedule           [bottom nav 3]
│   ├── Week view
│   ├── Month view
│   └── Availability (submit to manager) /teach/schedule/availability
│
├── ✉ Messages                           /teach/messages           [bottom nav 4]
│   ├── Conversations with students
│   └── Institute announcements
│
└── ☰ More                               /teach/more               [bottom nav 5]
    ├── My students (across all classes)
    ├── My workload & hours
    ├── Substitution requests
    ├── Files & resources
    ├── Profile & availability
    └── Settings / log out
```

**Explicitly excluded from the teacher panel:** finance, other teachers' data, enrolment
management, institute reports, student personal contact details beyond what's needed to
teach. Teachers see what they need to teach and nothing more — this is both a permission
boundary and a cognitive-load decision.

---

## E.5 Student portal

Answers four questions: *when, what, how much, how am I doing.*

```
app.lingotalk.com/{tenant}/me
│
├── ⌂ Home                               /me                       [bottom nav 1]
│   ├── Next class card
│   ├── Balance due card (if any)
│   ├── Upcoming homework
│   ├── New announcements
│   └── Progress snapshot
│
├── ◷ Schedule                           /me/schedule              [bottom nav 2]
│   ├── Week / month view
│   ├── Session detail (room, teacher, online link)
│   └── Add to calendar
│
├── ▤ My Classes                         /me/classes               [bottom nav 3]
│   └── Class detail                     /me/classes/{id}
│       ├── Overview & syllabus
│       ├── Homework                     /me/classes/{id}/homework
│       ├── Materials & downloads        /me/classes/{id}/materials
│       ├── Grades                       /me/classes/{id}/grades
│       ├── My attendance                /me/classes/{id}/attendance
│       └── Class announcements
│
├── ₪ Payments                           /me/payments              [bottom nav 4]
│   ├── Current balance & due dates
│   ├── Pay now                          /me/payments/pay
│   ├── Instalment plan
│   ├── Payment history
│   ├── Invoices & receipts              /me/payments/invoices/{id}
│   └── Upload payment proof             /me/payments/proof
│
└── ☰ More                               /me/more                  [bottom nav 5]
    ├── Progress & certificates          /me/progress
    ├── Exams & results                  /me/exams
    │   └── Take online exam             /me/exams/{id}/take
    ├── Enrol in a class                 /me/enrol
    │   ├── Browse open classes
    │   ├── Placement test
    │   └── Re-enrol for next term       /me/enrol/re-enrol
    ├── Announcements                    /me/announcements
    ├── Messages                         /me/messages
    ├── Documents                        /me/documents
    ├── Profile & contact details        /me/profile
    ├── Notification preferences         /me/settings/notifications
    └── Log out
```

**Guardian view:** same portal, with a child-selector in the header when linked to more than
one student, and financial actions enabled. Academic detail is visible; private teacher notes
are not.

---

## E.6 Super admin console

Visually distinct from the tenant app (different accent, permanent environment banner) so
platform staff can never confuse the two.

```
admin.lingotalk.com
│
├── ⌂ Platform overview                          /
│   ├── MRR / ARR, growth, churn
│   ├── Tenant counts by plan and state
│   ├── Trials in flight
│   ├── System health (errors, queues, delivery rates)
│   └── Alerts requiring action
│
├── ⌗ Tenants                                    /tenants
│   ├── All institutes (list + filters)          /tenants
│   ├── Tenant detail                            /tenants/{id}
│   │   ├── Profile & contacts
│   │   ├── Subscription & invoices
│   │   ├── Usage (students, branches, SMS, storage)
│   │   ├── Health score & activity trend
│   │   ├── Users & roles
│   │   ├── Support history
│   │   ├── Audit log
│   │   └── Actions: suspend · extend trial · change plan · impersonate · export · delete
│   ├── Onboarding pipeline                      /tenants/onboarding
│   └── At-risk / churn watchlist                /tenants/at-risk
│
├── ₪ Billing                                    /billing
│   ├── Subscriptions                            /billing/subscriptions
│   ├── Invoices                                 /billing/invoices
│   ├── Payments & failures                      /billing/payments
│   ├── Dunning queue                            /billing/dunning
│   ├── Refunds & credits                        /billing/refunds
│   └── Revenue reports                          /billing/revenue
│
├── ◫ Plans & pricing                            /plans
│   ├── Plan definitions                         /plans
│   ├── Feature flags & entitlements             /plans/features
│   ├── Limits & quotas                          /plans/limits
│   ├── Coupons & promotions                     /plans/coupons
│   └── Add-ons                                  /plans/addons
│
├── ◐ Usage & analytics                          /analytics
│   ├── Product usage by feature
│   ├── Adoption funnels
│   ├── Cohort retention
│   ├── Feature flag exposure
│   └── Per-tenant engagement
│
├── ✉ Support                                    /support
│   ├── Ticket queue                             /support/tickets
│   │   └── Ticket detail                        /support/tickets/{id}
│   ├── SLA monitor                              /support/sla
│   ├── Canned responses                         /support/macros
│   └── Knowledge base editor                    /support/kb
│
├── ◈ Leads & sales                              /sales
│   ├── Demo requests                            /sales/demos
│   ├── Trial signups                            /sales/trials
│   ├── Pipeline                                 /sales/pipeline
│   └── Conversion analytics                     /sales/analytics
│
├── ▦ Platform content                           /content
│   ├── Marketing pages
│   ├── Blog & resources
│   ├── Help centre articles
│   ├── Changelog
│   ├── Email & SMS system templates
│   └── In-app announcements to tenants
│
├── ⚙ Platform settings                          /settings
│   ├── Platform staff & roles                   /settings/staff
│   ├── Global feature flags                     /settings/flags
│   ├── Providers (SMS, email, payment, storage) /settings/providers
│   ├── Localisation defaults                    /settings/locales
│   ├── Security & access policy                 /settings/security
│   ├── Data retention & compliance              /settings/compliance
│   └── Global audit log                         /settings/audit
│
└── ⚑ System                                     /system
    ├── Background jobs & queues
    ├── Error monitoring
    ├── Message delivery health
    ├── Database & storage metrics
    └── Maintenance mode & broadcast
```

---

## E.7 Cross-cutting navigation mechanics

### Scope switchers (institute app)
Two persistent controls in the top bar shape every list, report, and dashboard beneath them:

- **Branch scope** — `All branches ▾ | Main | Sadeghieh | Vanak` (hidden for single-branch tenants)
- **Term scope** — `Autumn 1404 ▾` (defaults to the active term; a "past term" state shows a
  clear amber banner to prevent editing history by accident)

Both are reflected in the URL as query parameters so any view is shareable in its exact
context.

### Global search
One input, `⌘K`. Searches students, teachers, classes, invoices, and leads with type-ahead
grouped by entity. Also exposes commands ("mark attendance", "create student", "go to
finance"). This is the fastest path for experienced users and should be surfaced in onboarding.

### Quick create
A `+` in the top bar with the six highest-frequency creates: student, enrolment, payment,
class, announcement, lead. Always available, context-aware defaults (creating a payment from
a student profile pre-fills the student).

### Breadcrumbs
Used from depth 3 onward: `Classes › IELTS B2 – Sat/Mon 18:00 › Attendance › 14 Mehr`.
Every segment is a link; the last is not.

### Deep-link discipline
Every screen has a canonical URL, including modal states (`?modal=record-payment&student=123`).
Every notification, SMS link, and email CTA opens the exact record — never a dashboard the
user has to navigate from. This is the single highest-leverage detail for student and teacher
adoption.

### Empty-state navigation
A tenant with no data should never see an empty table. Every list's empty state names the
first action and links to it: *"No classes yet — build your first term to create classes"*
with a primary button. The onboarding checklist in the overview is the spine that ties these
together.
