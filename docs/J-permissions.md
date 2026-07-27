# J. Permissions Model

## J.1 Design principles

1. **Deny by default.** No permission means no access. New features ship with no role granted
   until explicitly assigned.
2. **Two-dimensional authorisation.** Every check answers both *"can this role do this action?"*
   and *"is this record inside this user's scope?"* A branch manager with `students.read` still
   cannot read another branch's students.
3. **Permissions are strings, roles are bundles.** Code checks `can('finance.payment.create')`,
   never `if (user.role === 'manager')`. Role-name checks scattered through code are how
   permission systems rot.
4. **Data-layer enforcement.** Authorisation is applied in the data-access layer, not in
   controllers or components. A missed check must fail closed.
5. **UI reflects permissions, but never enforces them.** Hidden buttons are a usability
   feature; the API is the security boundary.
6. **Every sensitive action is audited** with actor, target, before/after, and — where
   required — a reason string.
7. **Roles are templates, not laws.** Tenants on higher plans clone and customise system roles.
   The seven system templates cover ~90% of real institutes.

---

## J.2 Permission key taxonomy

`<domain>.<entity>.<action>[.<qualifier>]`

Domains: `students · teachers · classes · terms · curriculum · attendance · exams · finance ·
communication · reports · admissions · branches · settings · users · platform`

Actions: `read · create · update · delete · export · approve · publish · override`

Qualifiers narrow scope: `.own` (records the user owns or is assigned to), `.branch` (their
branch only), `.all` (institute-wide).

Examples:
```
students.read.all              classes.update.own
finance.payment.create         attendance.mark.own
finance.discount.override      reports.finance.read
settings.billing.update        users.role.assign
```

---

## J.3 Role definitions

### Public / unauthenticated

| Can | Cannot |
|---|---|
| View the marketing site | Access any tenant data |
| View an institute's public page and open class list (name, level, schedule, seats, price) | See student names, phone numbers, or any roster |
| Submit an enquiry, demo request, or placement-test booking | See financial data of any kind |
| Verify a certificate by its public code | Enumerate records (rate-limited, no sequential IDs exposed) |
| Register a student account | Access any authenticated route |

**Security notes:** public forms are rate-limited per IP and per phone, protected against
automated abuse, and never reveal whether a phone number already exists (enumeration defence).
Public class listings expose *seats remaining as a band* ("3 seats left" / "almost full"), not
enrolled student identities.

---

### Student

| Can | Cannot |
|---|---|
| View own profile and edit own contact details | View any other student's data |
| View own schedule, classes, and materials | See other students' grades, attendance, or balances |
| View own attendance and grades (once published) | See teacher notes marked internal |
| View own balance, instalment plan, invoices, receipts | See institute financials |
| Make payments and upload payment proof | Modify their own grades, attendance, or balance |
| Submit homework and take assigned online exams | Enrol in a class that is full or above their level (without approval) |
| Enrol and re-enrol in open classes | Withdraw themselves without institute confirmation |
| Message the institute and their teachers | Message other students directly (v1 — no student-to-student channel) |
| Download own certificates and progress reports | Access anything outside their own record |
| Set own notification preferences | |

**Scope rule:** every student query is filtered to `student_id = self` at the data layer.
There is no student-facing endpoint that accepts another student's ID.

**Guardian variant:** identical, scoped to `student_id IN (linked children)`, with a child
selector. Guardians additionally see financial detail and absence notifications; they do not
see private teacher notes. Minors' records always require a guardian link before portal access
is enabled.

---

### Teacher

| Can | Cannot |
|---|---|
| View own schedule and assigned classes | View classes they are not assigned to |
| View rosters of own classes with photo, name, and level | See students' national ID, address, or financial data |
| Mark attendance for own classes (within the edit window) | Mark attendance for another teacher's class (unless recorded as a substitute) |
| Enter, edit, and publish grades for own classes | Change grades after term close without manager approval |
| Create assignments, upload materials, grade submissions | Enrol, withdraw, or transfer students |
| Send announcements and messages to own classes | Message the whole institute |
| Add private teaching notes on own students | See other teachers' notes, salaries, or performance data |
| View own workload, hours, and schedule history | See institute revenue, fees, or any financial report |
| Submit availability and request substitution | Approve their own substitution or change the schedule |
| Request a session cancellation | Cancel a session unilaterally (institute-configurable) |

**Scope rule:** `class.teacher_id = self` OR `session.teacher_id = self` (substitutes).
Everything the teacher sees derives from that set.

**Why teachers can't see fees:** teachers move between institutes and fee structures are
commercially sensitive. Institutes ask for this restriction unprompted, and it also reduces the
teacher panel's cognitive load.

---

### Front desk / registrar

The highest-volume operator. Broad create rights, narrow read and no analytics.

| Can | Cannot |
|---|---|
| Create and edit students and guardians | Delete students |
| Create leads, log contacts, book placement tests | Change institute settings |
| Enrol, transfer, and withdraw students (withdraw may require approval) | Create or edit classes, terms, or courses |
| Record payments and print receipts | Issue refunds |
| Apply pre-approved discounts and valid coupons | Create discounts or override prices |
| View a student's balance and instalment plan | View institute-wide financial reports |
| Send announcements to classes | Send institute-wide campaigns |
| Mark attendance for any class (front-desk backup) | Enter or change grades |
| View schedules and class availability | Manage users, roles, or teachers |
| View their own daily activity summary | Access reports beyond their own daily totals |

**Design note:** front desk needs *speed*, not breadth. Their permission set is wide on
transactional creates and deliberately empty on anything requiring judgment or carrying
financial risk.

---

### Academic manager

| Can | Cannot |
|---|---|
| Everything in the academic domain: programs, levels, courses, terms, classes | View revenue, invoices, payments, or any monetary amount |
| Build and publish terms, resolve conflicts | Issue refunds or apply discounts |
| Create classes, assign teachers, set capacity | Change subscription or billing |
| Manage teachers: profiles, availability, workload, substitutions | Manage institute-level users or roles |
| View and correct attendance institute-wide | Delete financial or audit records |
| Manage exams, grading policy, certificates | See teacher pay rates (institute-configurable) |
| Enrol, transfer, and withdraw students | |
| View all academic and operational reports | View financial reports |
| Send institute-wide academic announcements | |

**The key exclusion is money.** In most institutes the academic manager is not trusted with
financial data, and separating this is one of the first things a real customer asks for.

---

### Finance manager

| Can | Cannot |
|---|---|
| Full financial domain: invoices, instalments, payments, refunds, credits | Create or edit classes, terms, or courses |
| Create discounts and coupons, override prices with a reason | Enter or change grades |
| Verify uploaded payment proofs | Mark or change attendance |
| Configure fee schedules, payment methods, gateways, late-fee policy | Manage teachers or users |
| View all financial reports and export them | Send institute-wide non-financial announcements |
| Send payment reminders and dunning campaigns | Change academic settings |
| View student profiles (finance tab in full; academic tabs read-only) | Delete students |
| Reverse a payment (creates a reversal entry, never a delete) | Change subscription/billing unless also granted `settings.billing.update` |

---

### Branch manager

Full operational authority, scoped to one or more branches.

| Can | Cannot |
|---|---|
| Everything an academic manager can, within their branch | See or affect other branches |
| Financial operations within their branch (configurable per institute) | View consolidated multi-branch reports |
| Manage staff assigned to their branch | Manage institute-level settings or roles |
| View branch reports and their branch's slice of institute reports | Change subscription/billing |
| Enrol, transfer, and withdraw students in their branch | Transfer a student to another branch without the receiving branch's acceptance |

**Scope rule:** `branch_id IN membership.branch_ids` applied to every query, including
aggregates. A branch manager's "institute total" is their branch total, clearly labelled as
such so the number is never misread.

---

### Institute owner (tenant admin)

| Can | Cannot |
|---|---|
| Everything within their tenant | Access any other tenant |
| Manage users, roles, and custom permission sets | Bypass the audit log |
| Change subscription, plan, and payment method | Delete audit or financial records |
| Configure every institute setting | Grant themselves platform-level access |
| View every report, across every branch | Read data of institutes they don't belong to |
| Export all tenant data | Disable audit logging |
| Request tenant deletion | Permanently delete data without the grace period |

**Constraints even on the owner:**
- The last owner cannot remove their own owner role (lockout prevention).
- Deleting the institute requires re-authentication, a typed confirmation, and a 30-day grace
  period.
- Financial records remain append-only for the owner too. There is no "admin can edit anything"
  escape hatch, because in disputes the audit trail is the institute's own protection.

---

### Platform support agent

| Can | Cannot |
|---|---|
| View tenant metadata: plan, usage, health, users, last activity | View student personal data, grades, or financial records by default |
| View and manage support tickets | Change tenant academic or financial data |
| View error logs and delivery logs for a tenant | Impersonate without an approved, reasoned request |
| Extend a trial, apply a support credit (bounded) | Change a plan or issue a refund above a defined limit |
| Edit help-centre content | Access platform settings, provider credentials, or feature flags |

**Scoped access request:** for a ticket that genuinely requires data access, the agent requests
it; the tenant owner approves; access is granted for a fixed window (default 60 minutes),
scoped to the relevant records, and every record touched is written to an audit log the tenant
can read in their own Settings → Audit log.

---

### Super admin (platform)

| Can | Cannot |
|---|---|
| Manage all tenants, plans, entitlements, coupons | Read tenant data without impersonation, which is logged and announced |
| Suspend, reactivate, or schedule deletion of a tenant | Delete a tenant without the grace period |
| Impersonate a tenant user (reason required, time-boxed, owner notified) | Suppress the audit log |
| Manage platform staff and their roles | Silently alter a subscriber's entitlements (plan snapshots protect this) |
| Configure providers, feature flags, and global settings | Bypass MFA |
| View platform-wide analytics and revenue | |
| Issue refunds and adjust subscriptions | |

**Hard requirements for super admin accounts:** MFA mandatory, no shared accounts, IP
allowlist for destructive operations, and a separate break-glass account with its use alerting
the whole team.

---

## J.4 Permission matrix (condensed)

`✔` full · `◐` scoped/limited · `✖` none

| Capability | Public | Student | Teacher | Front desk | Academic mgr | Finance mgr | Branch mgr | Owner | Support | Super admin |
|---|---|---|---|---|---|---|---|---|---|---|
| View marketing site | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| View own data | — | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| View all students | ✖ | ✖ | ◐ own classes | ✔ | ✔ | ◐ finance view | ◐ branch | ✔ | ✖ | ◐ impersonation |
| Create/edit student | ✖ | ◐ own contact | ✖ | ✔ | ✔ | ✖ | ◐ branch | ✔ | ✖ | ✖ |
| Delete student | ✖ | ✖ | ✖ | ✖ | ◐ approval | ✖ | ◐ approval | ✔ | ✖ | ✖ |
| Enrol student | ✖ | ◐ self | ✖ | ✔ | ✔ | ✖ | ◐ branch | ✔ | ✖ | ✖ |
| Withdraw student | ✖ | ✖ | ✖ | ◐ approval | ✔ | ✖ | ◐ branch | ✔ | ✖ | ✖ |
| Create/edit class | ✖ | ✖ | ✖ | ✖ | ✔ | ✖ | ◐ branch | ✔ | ✖ | ✖ |
| Assign teacher | ✖ | ✖ | ✖ | ✖ | ✔ | ✖ | ◐ branch | ✔ | ✖ | ✖ |
| Build/publish term | ✖ | ✖ | ✖ | ✖ | ✔ | ✖ | ◐ branch | ✔ | ✖ | ✖ |
| Mark attendance | ✖ | ✖ | ◐ own | ✔ | ✔ | ✖ | ◐ branch | ✔ | ✖ | ✖ |
| Edit past attendance | ✖ | ✖ | ◐ window | ◐ window | ✔ | ✖ | ◐ branch | ✔ | ✖ | ✖ |
| Enter grades | ✖ | ✖ | ◐ own | ✖ | ✔ | ✖ | ◐ branch | ✔ | ✖ | ✖ |
| Publish grades | ✖ | ✖ | ◐ own | ✖ | ✔ | ✖ | ◐ branch | ✔ | ✖ | ✖ |
| Record payment | ✖ | ◐ own | ✖ | ✔ | ✖ | ✔ | ◐ branch | ✔ | ✖ | ✖ |
| Issue refund | ✖ | ✖ | ✖ | ✖ | ✖ | ✔ | ◐ limit | ✔ | ✖ | ◐ platform only |
| Create discount | ✖ | ✖ | ✖ | ✖ | ✖ | ✔ | ◐ limit | ✔ | ✖ | ✖ |
| Apply existing discount | ✖ | ✖ | ✖ | ✔ | ✖ | ✔ | ✔ | ✔ | ✖ | ✖ |
| View financial reports | ✖ | ✖ | ✖ | ✖ | ✖ | ✔ | ◐ branch | ✔ | ✖ | ◐ aggregate |
| View academic reports | ✖ | ✖ | ◐ own classes | ✖ | ✔ | ✖ | ◐ branch | ✔ | ✖ | ✖ |
| Send class announcement | ✖ | ✖ | ◐ own | ✔ | ✔ | ✖ | ✔ | ✔ | ✖ | ✖ |
| Send institute campaign | ✖ | ✖ | ✖ | ✖ | ◐ academic | ◐ financial | ◐ branch | ✔ | ✖ | ✖ |
| Manage users & roles | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ◐ branch staff | ✔ | ✖ | ✖ |
| Institute settings | ✖ | ✖ | ✖ | ✖ | ◐ academic | ◐ financial | ◐ branch | ✔ | ✖ | ✖ |
| Subscription & billing | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ✔ | ◐ trial ext. | ✔ |
| Export tenant data | ✖ | ◐ own | ✖ | ✖ | ◐ academic | ◐ financial | ◐ branch | ✔ | ✖ | ✔ |
| View audit log | ✖ | ✖ | ✖ | ✖ | ◐ academic | ◐ financial | ◐ branch | ✔ | ◐ metadata | ✔ |
| Manage tenants | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ◐ limited | ✔ |
| Impersonate | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ✖ | ◐ approved | ✔ |

---

## J.5 Approval workflows

Some actions are too consequential for a single actor but too common to require an owner.
These use a request → approve pattern with the request visible in the approver's
*Needs attention* panel.

| Action | Requester | Approver | Why |
|---|---|---|---|
| Refund above threshold | Finance manager | Owner | Direct revenue impact |
| Discount above threshold or outside policy | Front desk / finance | Finance manager or owner | Margin protection |
| Student withdrawal with refund | Front desk | Academic + finance | Two-sided consequence |
| Grade change after term close | Teacher | Academic manager | Academic integrity |
| Attendance edit outside the window | Teacher | Academic manager | Data integrity |
| Class cancellation with enrolled students | Academic manager | Owner | Customer impact |
| Capacity override | Front desk | Academic manager | Safety and teaching quality |
| Bulk delete or bulk status change | Any | Owner | Blast radius |
| Platform support data access | Support agent | Tenant owner | Privacy |

Every approval records the requester, approver, reason, and timestamp, and every request has
an expiry so nothing sits pending indefinitely.

---

## J.6 Security rules beyond RBAC

| Area | Rule |
|---|---|
| **Authentication** | Phone + SMS OTP primary; password optional and, when set, subject to strength rules and breach-list checking. MFA available to all, mandatory for owner and platform staff |
| **Sessions** | Short-lived access tokens with refresh rotation; device list visible to the user with individual revocation; sessions invalidated on role change |
| **Tenant switching** | Explicit action producing a new tenant-scoped session; never an implicit header |
| **Rate limiting** | Per IP, per user, per tenant. Stricter on auth, OTP, public forms, and export |
| **Enumeration defence** | Uniform responses for "phone exists"; non-sequential public identifiers; rate-limited certificate verification |
| **Field-level protection** | National ID, full address, and payment references masked in lists and revealed only on an audited action |
| **Export controls** | Bulk export is permission-gated, rate-limited, watermarked with the exporting user, and always audited |
| **Impersonation** | Reason required, time-boxed, owner-notified, banner visible throughout the session, every record touched logged |
| **Audit immutability** | Append-only, no delete path in the application, retained beyond the tenant's own retention window |
| **Data at rest / in transit** | Encrypted at rest; TLS everywhere; secrets in a managed store, never in configuration files |
| **Backups** | Automated, encrypted, tested by restore drill quarterly; point-in-time recovery within the retention window |
| **Least privilege for engineers** | Production data access requires a reason, is time-boxed, and is logged — the same rules that apply to support |

---

## J.7 Common permission failure modes to design against

| Failure | Prevention |
|---|---|
| A teacher sees another class's roster by editing the URL | Data-layer scope check on every read, not a UI filter |
| A branch manager's "total revenue" silently includes other branches | Aggregates carry the scope in the query and the label states it |
| A departing employee retains access | Deactivation revokes sessions immediately; a quarterly access review is surfaced to the owner |
| A new feature ships with no permission and defaults to visible | Permission keys are required at feature-flag registration; unmapped features are hidden |
| Support reads customer data casually | Approval-gated, time-boxed, tenant-visible access |
| An owner locks themselves out | Last-owner protection; a break-glass recovery path via verified identity |
| A plan change removes a feature a paying tenant relies on | Entitlements resolve from the subscription's plan snapshot |
| A role customisation grants an unintended escalation | Custom roles cannot grant permissions the granting user doesn't hold; escalation attempts are blocked and logged |
