# I. Data and Entity Model

## I.1 Tenancy strategy

**Decision: shared database, shared schema, row-level tenant isolation — enforced at the
database layer, not in application code.**

Every tenant-owned table carries a non-nullable `institute_id`, and every query is filtered by
a session-scoped tenant context (PostgreSQL Row-Level Security, or the equivalent enforced in
a single data-access layer that application code cannot bypass).

### Why this over the alternatives

| Option | Verdict |
|---|---|
| Database per tenant | Rejected for v1. Operationally expensive at 100+ tenants; migrations become a fleet operation; cross-tenant analytics require ETL |
| Schema per tenant | Rejected. Same migration problem, and PostgreSQL degrades with thousands of schemas |
| **Shared schema + RLS** | **Chosen.** One migration path, cheap tenant creation, straightforward analytics, and isolation guaranteed by the database rather than by developer discipline |
| Hybrid (dedicated DB for enterprise) | Planned for Phase 3, as a paid enterprise option. Design the data-access layer now so the connection target is a per-tenant configuration |

### Non-negotiable isolation rules

1. Tenant context is derived from the authenticated session and the URL tenant slug — **never**
   from a request body or query parameter.
2. No application code writes a raw query without a tenant predicate. Enforce with RLS so that
   a missing predicate returns zero rows rather than another tenant's rows.
3. Every tenant-owned table has `institute_id` in its primary index, so tenant filtering is
   also the performance strategy.
4. Cross-tenant reads exist in exactly one place: the super-admin analytics layer, which reads
   from a separate role with explicit, audited access.
5. Automated tests include a cross-tenant leakage suite that runs on every build. This is the
   one class of bug that ends the company.

---

## I.2 Entity map

```
                              ┌──────────────┐
                              │  INSTITUTE   │  ← tenant root
                              └──────┬───────┘
        ┌────────────┬───────────────┼──────────────┬─────────────┐
        ▼            ▼               ▼              ▼             ▼
   ┌────────┐  ┌──────────┐   ┌───────────┐  ┌──────────┐  ┌────────────┐
   │ BRANCH │  │   USER   │   │  PROGRAM  │  │   TERM   │  │SUBSCRIPTION│
   └───┬────┘  └────┬─────┘   └─────┬─────┘  └────┬─────┘  └────────────┘
       │            │               │             │
       │       ┌────┴────┬──────────┐│            │
       │       ▼         ▼          ▼│            │
       │  ┌─────────┐ ┌────────┐ ┌───┴───┐        │
       │  │ STUDENT │ │TEACHER │ │ LEVEL │        │
       │  └────┬────┘ └───┬────┘ └───┬───┘        │
       │       │          │          │            │
       │       │          │      ┌───▼────┐       │
       │       │          │      │ COURSE │       │
       │       │          │      └───┬────┘       │
       │       │          │          │            │
       │       │          └────┬─────┴────────────┘
       │       │               ▼
       │       │        ┌─────────────┐
       └───────┼───────▶│    CLASS    │◀──── ROOM
               │        └──────┬──────┘
               │               │
        ┌──────▼──────┐   ┌────▼─────┐   ┌──────────┐  ┌────────────┐
        │  ENROLMENT  │──▶│ SESSION  │──▶│ATTENDANCE│  │ ASSIGNMENT │
        └──────┬──────┘   └──────────┘   └──────────┘  └─────┬──────┘
               │                                              │
        ┌──────▼──────┐   ┌──────────┐   ┌──────────┐   ┌─────▼──────┐
        │   INVOICE   │──▶│INSTALMENT│──▶│ PAYMENT  │   │ SUBMISSION │
        └──────┬──────┘   └──────────┘   └──────────┘   └────────────┘
               │
        ┌──────▼──────┐   ┌──────────┐   ┌──────────┐
        │  DISCOUNT   │   │  COUPON  │   │  REFUND  │
        └─────────────┘   └──────────┘   └──────────┘

   ┌──────┐   ┌──────────────┐   ┌──────┐   ┌──────────────┐   ┌───────────┐
   │ LEAD │──▶│ DEMO_REQUEST │   │ EXAM │──▶│ EXAM_RESULT  │   │CERTIFICATE│
   └──────┘   └──────────────┘   └──────┘   └──────────────┘   └───────────┘

   ┌──────────────┐  ┌─────────┐  ┌──────────────┐  ┌───────────┐  ┌────────┐
   │ ANNOUNCEMENT │  │ MESSAGE │  │NOTIFICATION  │  │ AUDIT_LOG │  │ REPORT │
   └──────────────┘  └─────────┘  └──────────────┘  └───────────┘  └────────┘

   PLATFORM-SCOPE (not tenant-owned):
   ┌──────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
   │ PLAN │  │ SUBSCRIPTION │  │SUPPORT_TICKET│  │PLATFORM_USER │
   └──────┘  └──────────────┘  └──────────────┘  └──────────────┘
```

---

## I.3 Core entities

### INSTITUTE (tenant root)
The container everything else hangs from.

| Field | Notes |
|---|---|
| `id`, `slug` | Slug is the URL identifier, immutable after creation |
| `name`, `legal_name`, `logo_url`, `brand_color` | |
| `country`, `timezone`, `locale`, `direction` | `direction` = rtl/ltr |
| `calendar_system` | `jalali` \| `gregorian` \| `hijri` — see §I.10 |
| `currency`, `currency_minor_units` | |
| `status` | `trial` \| `active` \| `past_due` \| `suspended` \| `cancelled` |
| `settings` (JSONB) | Academic, financial, communication, and attendance policy |
| `feature_flags` (JSONB) | Resolved entitlements snapshot from the subscription |
| `created_at`, `trial_ends_at` | |

**Relationships:** has many of nearly everything. Deleting an institute is a scheduled,
reversible operation with a grace period — never a cascade delete on request.

### BRANCH
A physical or virtual location. Every tenant has at least one, created implicitly at signup.

`id · institute_id · name · code · address · phone · timezone · working_hours (JSONB) ·
manager_user_id · is_default · status`

**Rules:** branch is a **scope**, not a sub-tenant. Students transfer between branches with
history intact. A user may have access to multiple branches. Single-branch tenants never see
the concept in the UI.

### USER
One human, one login, across every role and possibly multiple institutes.

| Field | Notes |
|---|---|
| `id`, `phone` (unique, primary identifier), `email`, `password_hash` | Phone is primary because SMS OTP is the dominant auth path in this market |
| `full_name`, `avatar_url`, `locale`, `timezone` | |
| `phone_verified_at`, `email_verified_at`, `last_login_at` | |
| `status` | `active` \| `invited` \| `suspended` |
| `mfa_enabled`, `mfa_secret` | |

**Critical design decision:** `USER` is global; membership in an institute is a separate
`INSTITUTE_MEMBERSHIP` record. This makes the common real-world cases work: a teacher who
works at three institutes has one account, and a student who studies at two institutes does
too.

### INSTITUTE_MEMBERSHIP (join)
`id · institute_id · user_id · role_id · branch_ids[] · status · invited_by · joined_at ·
last_active_at`

The unit of access control. A user's permissions are resolved per institute, per branch.

### ROLE and PERMISSION
`ROLE`: `id · institute_id (null = system template) · name · description · permissions[] ·
is_system · scope_type (institute | branch)`

System templates: Owner, Academic Manager, Finance Manager, Branch Manager, Front Desk,
Teacher, Student. Tenants on higher plans can clone and customise. Permissions are string keys
(`students.create`, `finance.refund.issue`) — see [J](J-permissions.md).

### STUDENT
A learner. Distinct from `USER` — a student record exists whether or not the person has ever
logged in, which matters because institutes register people who never touch the portal.

| Field | Notes |
|---|---|
| `id · institute_id · user_id (nullable)` | Nullable = registered but no portal account |
| `student_code` | Human-readable, per-institute sequence |
| `first_name · last_name · birth_date · gender · national_id` | |
| `phone · email · address · emergency_contact` | |
| `photo_url` | Used on attendance rosters — teachers identify by face |
| `primary_branch_id · status` | `prospective · active · inactive · graduated · withdrawn` |
| `current_level_id · source · tags[] · notes` | `source` = lead attribution |
| `guardian_ids[]` | |
| `balance_cached` | Denormalised for list performance; recomputed on every ledger write |

### GUARDIAN
`id · institute_id · full_name · phone · email · relationship · user_id (nullable) ·
student_ids[] · receives_notifications · can_login`

Not a role in v1 — a relationship on the student. Guardians with `can_login` get a portal
account scoped to their linked students.

### TEACHER
`id · institute_id · user_id · teacher_code · employment_type (full_time | part_time |
contract) · languages[] · levels_qualified[] · hourly_rate · max_weekly_hours ·
availability (JSONB) · branch_ids[] · bio · photo_url · documents[] · status · hired_at`

`availability` is a weekly grid of available slots, used by conflict detection in the term
builder.

---

## I.4 Academic entities

### PROGRAM
A language or track. `id · institute_id · name (English, German, IELTS Prep) · type
(general | exam_prep | business | conversation | kids) · description · status`

### LEVEL
A rung on a program's ladder. `id · institute_id · program_id · name (A1, Elementary 2) ·
code · sequence · description · cefr_equivalent · prerequisite_level_id · duration_hours`

**Rule:** `sequence` defines progression. The system suggests the next level at re-enrolment
by incrementing sequence when the student passes.

### COURSE
The reusable template. A course is *what is taught*; a class is *a specific instance of it*.

`id · institute_id · program_id · level_id · name · code · description · syllabus ·
total_sessions · session_duration_minutes · sessions_per_week · default_price ·
default_capacity_min · default_capacity_max · materials[] · status`

### TERM
The primary time unit of the whole product.

| Field | Notes |
|---|---|
| `id · institute_id · name` | e.g. "پاییز ۱۴۰۴" |
| `start_date · end_date` | Stored UTC; authored and displayed in the tenant's calendar system |
| `enrolment_opens_at · enrolment_closes_at` | |
| `status` | `draft · published · active · ended · closed` |
| `holidays` (JSONB) | Dates excluded from session generation |
| `branch_ids[]` · `cloned_from_term_id` | |
| `settings` (JSONB) | Early-bird deadline, loyalty discount %, default instalment template |

**`closed` is distinct from `ended`:** ended = date passed; closed = grades locked,
certificates issued, financials reconciled. Reports treat them differently.

### CLASS
A specific instance of a course, in a term, at a time, with a teacher.

| Field | Notes |
|---|---|
| `id · institute_id · branch_id · term_id · course_id · level_id` | |
| `name · code` | |
| `teacher_id` | Nullable while draft; required to publish |
| `room_id · schedule` (JSONB) | `schedule` = weekday + start/end time array |
| `start_date · end_date · total_sessions` | |
| `capacity_min · capacity_max · enrolled_count (cached)` | |
| `price · price_override_reason` | |
| `delivery_mode` | `in_person · online · hybrid` |
| `online_meeting_url · online_provider` | |
| `status` | `draft · published · active · completed · cancelled` |
| `color` | Consistent identity across schedule, portal, and reports |

### ROOM
`id · institute_id · branch_id · name · capacity · equipment[] · availability (JSONB) · status`

### SESSION
One scheduled meeting of a class. Generated in bulk when a term publishes.

`id · institute_id · class_id · session_number · scheduled_date · start_time · end_time ·
room_id · teacher_id (actual, may differ from class.teacher_id) · status (scheduled | held |
cancelled | makeup) · cancellation_reason · makeup_for_session_id · topic_covered ·
attendance_marked_at · attendance_marked_by`

**Why sessions are materialised rather than computed:** attendance, substitutions, room
changes, cancellations, and makeups all attach to a specific occurrence. A computed schedule
cannot carry that state.

### ENROLMENT
The join between a student and a class — and the anchor for money and academics.

| Field | Notes |
|---|---|
| `id · institute_id · student_id · class_id · term_id` | |
| `status` | `pending_payment · reserved · active · completed · withdrawn · transferred · waitlisted` |
| `enrolled_at · enrolled_by · start_date · end_date` | |
| `price · discount_total · final_price` | Priced at enrolment time; later price changes do not retroactively alter it |
| `invoice_id · waitlist_position` | |
| `final_grade · passed · attendance_rate` | Computed at term close |
| `transferred_to_enrolment_id · withdrawal_reason` | |

**Invariant:** `COUNT(active enrolments) <= class.capacity_max` is enforced transactionally at
insert. Overbooking is possible only via an explicit permission-gated override that records a
reason.

---

## I.5 Assessment entities

### ATTENDANCE
`id · institute_id · session_id · enrolment_id · student_id · status (present | absent | late |
excused | not_applicable) · minutes_late · reason · marked_by · marked_at · modified_at ·
modified_by`

**Rules:** exactly one record per (session, student). `not_applicable` covers students who
joined after the session date or a cancelled session — never conflate with absent, because
that corrupts every attendance rate downstream. Modifications keep the original values in the
audit log.

### EXAM
`id · institute_id · class_id (nullable) · course_id · level_id · name · type (placement |
quiz | midterm | final | mock | retake) · format (paper | online) · scheduled_at ·
duration_minutes · total_marks · pass_mark · weight · sections (JSONB) · settings (JSONB) ·
status (draft | scheduled | in_progress | grading | published) · created_by`

Placement exams have `class_id = null` and link to a `LEAD` or `STUDENT` instead.

### EXAM_RESULT
`id · institute_id · exam_id · student_id · enrolment_id (nullable) · score · max_score ·
percentage · passed · section_scores (JSONB) · answers (JSONB, online only) · started_at ·
submitted_at · graded_by · graded_at · feedback · attempt_number · status`

### GRADE_COMPONENT and GRADE
`GRADE_COMPONENT`: `id · institute_id · class_id · name · weight_percent · max_score ·
sequence` — weights must total 100 per class, validated on write.

`GRADE`: `id · institute_id · enrolment_id · component_id · score · comment · entered_by ·
entered_at · published_at`

Final grade = weighted sum, computed and stored on the enrolment at term close so historical
grades survive later component changes.

### ASSIGNMENT / HOMEWORK
`id · institute_id · class_id · title · description · attachments[] · assigned_at · due_at ·
max_score · submission_type (file | text | audio | none) · created_by · status`

### SUBMISSION
`id · institute_id · assignment_id · student_id · content · attachments[] · submitted_at ·
is_late · score · feedback · graded_by · graded_at · status`

### CERTIFICATE
`id · institute_id · student_id · enrolment_id · level_id · certificate_number · issued_at ·
issued_by · template_id · verification_code · pdf_url · status`

`verification_code` powers a public verification page — a small feature with outsized
perceived value to students and employers.

---

## I.6 Financial entities

**Absolute rules for this entire subsystem:**
1. All money stored as **integer minor units** (`amount_minor`) plus a currency code. No
   floating point, anywhere, ever.
2. Financial records are **append-only**. A mistake produces a reversal entry, not an update
   or a delete.
3. Every money-moving row carries `created_by` and an immutable `created_at`.

### FEE_SCHEDULE
`id · institute_id · course_id (nullable) · level_id (nullable) · term_id (nullable) ·
name · amount_minor · currency · applies_to · effective_from · effective_to · status`

### INVOICE
`id · institute_id · student_id · enrolment_id (nullable) · invoice_number (per-institute
gap-free sequence) · issue_date · due_date · subtotal_minor · discount_minor · tax_minor ·
total_minor · paid_minor · balance_minor · currency · status (draft | issued | partially_paid |
paid | overdue | cancelled | refunded) · line_items (JSONB) · notes · issued_by`

Invoices can be issued to a `GUARDIAN` or an external `payer_organisation` (corporate clients),
which is why `student_id` and the payer are modelled separately.

### INSTALMENT
`id · institute_id · invoice_id · sequence · amount_minor · due_date · paid_minor ·
status (pending | paid | partially_paid | overdue | waived) · reminder_sent_at ·
last_reminder_count`

### PAYMENT
`id · institute_id · student_id · invoice_id · instalment_id (nullable) · amount_minor ·
currency · method (cash | card | bank_transfer | online_gateway | cheque | credit) ·
gateway · gateway_transaction_id · gateway_response (JSONB) · reference_number ·
proof_url · status (pending | pending_verification | completed | failed | reversed) ·
paid_at · recorded_by · verified_by · verified_at · idempotency_key · notes`

**`idempotency_key` is mandatory** on every gateway-initiated payment. Duplicate callbacks are
the most common real-world payment bug and the most damaging to trust.

### REFUND
`id · institute_id · payment_id · invoice_id · amount_minor · reason · status (requested |
approved | processed | rejected) · requested_by · approved_by · processed_at · method · notes`

### DISCOUNT
`id · institute_id · name · type (percentage | fixed) · value · scope (enrolment | invoice |
student) · reason_required · applies_to (JSONB) · max_uses · valid_from · valid_to ·
requires_permission · status`

Standard kinds: early bird, returning student, sibling, referral, staff, scholarship, hardship.

### COUPON
`id · institute_id (null = platform-level) · code (unique per scope) · type · value ·
applies_to (course/level/term/plan) · max_redemptions · redemptions_used ·
max_per_student · valid_from · valid_to · min_purchase_minor · status · created_by`

Used both by institutes for student discounts and by the platform for subscription discounts —
same shape, different scope.

### ACCOUNT_CREDIT
`id · institute_id · student_id · amount_minor · source (overpayment | refund | goodwill |
referral) · consumed_minor · expires_at · notes`

Prevents the classic bug where an overpayment vanishes.

### TRANSACTION_LOG (ledger)
`id · institute_id · entity_type · entity_id · direction (debit | credit) · amount_minor ·
balance_after_minor · description · created_by · created_at · reversal_of_id`

An append-only ledger over the top of everything above. This is what makes financial reporting
trustworthy and disputes resolvable.

---

## I.7 Communication entities

### ANNOUNCEMENT
`id · institute_id · title · body · audience (JSONB: branches, terms, classes, levels, roles,
balance_state, tags) · channels[] (in_app | sms | email) · scheduled_at · sent_at ·
recipient_count · created_by · status (draft | scheduled | sending | sent | failed)`

### MESSAGE
A one-to-one or small-group conversation message.
`id · institute_id · conversation_id · sender_user_id · recipient_user_id · body ·
attachments[] · read_at · sent_at`

### NOTIFICATION_DELIVERY
The per-recipient delivery record — the thing that makes "we told them" verifiable.
`id · institute_id · notification_type · channel · recipient_user_id · recipient_address ·
subject · body_rendered · provider · provider_message_id · status (queued | sent | delivered |
failed | bounced) · error_code · cost_minor · sent_at · delivered_at`

### TEMPLATE
`id · institute_id (null = system) · key · channel · locale · subject · body ·
variables[] · is_system · status`

### AUTOMATION_RULE
`id · institute_id · trigger (enrolment_created | payment_due_in_n_days | payment_overdue |
absence_marked | absence_threshold_crossed | session_cancelled | term_ending |
grade_published | certificate_issued) · conditions (JSONB) · channel · template_id ·
delay_minutes · is_active · created_by`

This entity is where "automation" stops being a marketing word and becomes a feature.

---

## I.8 Admissions entities

### LEAD
`id · institute_id · branch_id · full_name · phone · email · source (website | instagram |
referral | walk_in | phone | campaign) · campaign_id · utm (JSONB) · interested_program_id ·
interested_level_id · goal · preferred_schedule · budget_note · stage (new | contacted |
assessed | proposed | enrolled | lost | dormant) · score · assigned_to_user_id ·
next_follow_up_at · lost_reason · converted_student_id · converted_at · created_at`

### DEMO_REQUEST (platform-level)
`id · institute_name · contact_name · phone · email · student_count · branch_count ·
challenge_text · country · source · utm (JSONB) · status (new | contacted | demo_scheduled |
demo_completed | trial_started | converted | lost) · assigned_to · scheduled_at ·
outcome_notes · created_at`

Distinct from `LEAD`: `LEAD` is an institute's prospective *student*; `DEMO_REQUEST` is the
platform's prospective *institute*. Confusing the two is a common modelling error.

### PLACEMENT_TEST_BOOKING
`id · institute_id · lead_id (nullable) · student_id (nullable) · exam_id · scheduled_at ·
branch_id · status (booked | completed | no_show | cancelled) · result_level_id · result_score`

### WAITLIST_ENTRY
`id · institute_id · class_id · student_id · position · added_at · notified_at ·
expires_at · status (waiting | offered | accepted | declined | expired)`

**Rule:** when a seat frees, the top entry is offered with an expiry (default 24 h) before the
offer passes to the next. Silent, unmanaged waitlists are useless.

---

## I.9 Platform entities (not tenant-scoped)

### PLAN
`id · code · name · description · prices (JSONB: currency → monthly/annual) ·
limits (JSONB: max_students, max_branches, max_staff, sms_included, storage_gb, api_rate) ·
features[] · trial_days · is_public · sequence · status`

### SUBSCRIPTION
`id · institute_id · plan_id · plan_snapshot (JSONB) · status (trialing | active | past_due |
paused | cancelled | expired) · billing_cycle · current_period_start · current_period_end ·
trial_ends_at · cancel_at_period_end · cancelled_at · cancellation_reason · price_minor ·
currency · discount_id · payment_method_id · addons (JSONB)`

**`plan_snapshot` is essential:** it freezes the entitlements the tenant actually bought.
Editing a plan definition must never silently change what an existing subscriber has.

### USAGE_RECORD
`id · institute_id · period · metric (active_students | branches | staff | sms_sent |
storage_bytes | api_calls) · value · recorded_at`

Drives limit enforcement, overage billing, and the health score.

### SUPPORT_TICKET
`id · institute_id · reporter_user_id · subject · body · category · priority · status (open |
in_progress | waiting_on_customer | resolved | closed) · assigned_to_platform_user_id ·
sla_due_at · first_response_at · resolved_at · satisfaction_rating · linked_issue_id`

### TENANT_HEALTH_SNAPSHOT
`id · institute_id · date · score · components (JSONB: login_frequency, enrolments_created,
attendance_marked_rate, payments_recorded, active_user_count, feature_breadth) · trend`

The churn-prediction substrate. Usage decay — not complaints — is what precedes cancellation.

### AUDIT_LOG (both scopes)
`id · institute_id (nullable for platform events) · actor_user_id · actor_type (user |
platform_staff | system) · action · entity_type · entity_id · before (JSONB) · after (JSONB) ·
ip_address · user_agent · impersonated_by · reason · created_at`

**`impersonated_by` and `reason` are mandatory** for any platform-staff action inside a tenant.

---

## I.10 Localisation and calendar strategy

The single most-underestimated source of rework in this product. Decide it once, up front.

### Storage
- **All timestamps stored in UTC as ISO-8601.** No exceptions, no local-time columns.
- **All dates that are genuinely date-only** (term start, due date, birth date) stored as
  `DATE` in the institute's timezone-resolved civil date. A due date is a calendar day, not
  an instant, and treating it as a timestamp causes off-by-one errors at midnight boundaries.
- **Calendar system is a presentation concern**, resolved per institute and overridable per
  user. Never store a Jalali string.

### Presentation
- A single date-formatting layer converts UTC → institute timezone → institute calendar →
  locale-formatted string. No date is ever formatted anywhere else in the codebase.
- Date **input** accepts the institute's calendar natively: a Jalali institute gets a Jalali
  picker with Jalali month names and a Saturday-first week. Converting in the user's head is
  a bug, not a workaround.
- Numerals follow the locale: Persian digits for `fa-IR` display, Latin digits in exports and
  identifiers.

### RTL
- The entire layout system is logical-property based (`start`/`end`, never `left`/`right`), so
  direction is a single root attribute rather than a stylesheet fork.
- Icons with direction semantics (back, next, progress) mirror; icons without (search, calendar,
  user) do not.
- Numbers, currency, times, and Latin-script names stay LTR inside RTL text, with correct
  bidirectional isolation so a phone number never renders reversed.
- Charts mirror their axis order; time still flows in the reading direction.

### Multi-locale content
Templates, level names, course names, and announcements are per-locale where it matters:
`TEMPLATE` has a `locale` column, and student-facing content falls back institute locale →
platform default.

---

## I.11 Key invariants

These are the rules that keep the data trustworthy. Enforce in the database where possible,
in the service layer otherwise, and test all of them.

| # | Invariant |
|---|---|
| 1 | Every tenant-owned row has a non-null `institute_id`, and no query executes without a tenant predicate |
| 2 | `active_enrolments(class) <= class.capacity_max` unless an override with a recorded reason exists |
| 3 | Exactly one `ATTENDANCE` row per (session, student); a student's attendance for sessions before their enrolment date is `not_applicable` |
| 4 | `invoice.paid_minor = SUM(completed payments)` and `balance = total - paid`, always, recomputed on every write |
| 5 | `SUM(instalment.amount_minor) = invoice.total_minor` for any invoice with a plan |
| 6 | Invoice numbers are gap-free and monotonic per institute per series, allocated transactionally |
| 7 | Grade component weights total exactly 100 per class before grades can be published |
| 8 | A published class has a teacher; an unassigned class cannot be published |
| 9 | Financial rows are never updated or deleted — corrections are reversal entries |
| 10 | Deleting a student is a soft delete plus anonymisation; financial and audit history is retained |
| 11 | A `SESSION` cannot exist on a date in its term's `holidays` |
| 12 | Every payment initiated through a gateway carries a unique `idempotency_key` |
| 13 | Entitlements resolve from `subscription.plan_snapshot`, never from the live plan definition |
| 14 | Every write to a tenant-owned entity produces an `AUDIT_LOG` row with actor and before/after |

---

## I.12 Denormalisation and performance

Deliberate caches, each with a defined recompute trigger:

| Cached field | Recomputed on |
|---|---|
| `student.balance_cached` | Any invoice, instalment, payment, refund, or credit write for that student |
| `class.enrolled_count` | Enrolment create, withdraw, or transfer |
| `enrolment.attendance_rate` | Attendance write for that enrolment |
| `institute.feature_flags` | Subscription change |
| Dashboard aggregates | Materialised view refreshed every 5–15 min, with a visible "as of" timestamp |

**Index strategy:** every tenant-owned table is indexed on `(institute_id, <primary access
key>)`. The most important composite indexes: `(institute_id, term_id, status)` on enrolments,
`(institute_id, scheduled_date)` on sessions, `(institute_id, status, due_date)` on instalments,
`(institute_id, phone)` and `(institute_id, national_id)` on students for duplicate detection.

**Archival:** terms older than three years move to partitioned cold storage, remaining
queryable for reports but out of the hot path. Institutes accumulate history fast and the
active working set is almost always the current and previous term.

---

## I.13 Data ownership and exit

A written product commitment, not just a policy page:

1. **Full export at any time**, self-serve, without contacting support — every entity, in CSV
   and JSON, with a documented schema.
2. **Export survives cancellation** for 90 days, including during suspension for non-payment.
3. **Deletion is scheduled and reversible** during a 30-day grace period, then irreversible,
   with a certificate of deletion issued.
4. **Anonymisation on request** for individual students (right to erasure) while preserving
   the aggregate financial and academic record required for the institute's own accounting.

This is a sales asset, not just a compliance obligation. "What if you disappear?" is asked in
nearly every enterprise conversation, and a concrete answer wins the deal.
