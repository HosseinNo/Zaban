# O. Product Strategy Summary and Final Recommendation

## O.1 The product strategy, in one page

**Language institutes are logistics businesses that nobody has built proper logistics software
for.**

They run on terms, levels, small classes that fill or don't, instalment payments, and constant
communication — and they run all of it on spreadsheets, WhatsApp, and one person's memory. The
cost is not abstract: 15–25 staff-hours per person per month on work with no judgment content,
5–15% of expected tuition quietly uncollected, and a growth ceiling set by how much the manager
can personally hold in their head.

Existing software doesn't fit. K-12 ERPs model parents and grade levels and national curricula.
LMSs model content and ignore money. CRMs model the sale and ignore the delivery. Every
institute ends up with three tools that don't talk to each other and a fourth spreadsheet to
reconcile them.

**Lingo Talk is the operating system for these institutes.** One private workspace per
institute holding students, classes, terms, tuition, attendance, grades, and communication in
one connected data model — so that any question spanning two of them is answerable instantly,
and the work between them stops being manual.

Three things make it defensible:

1. **Domain shape.** Terms, levels, placement tests, instalments, and small-class capacity are
   modelled properly. A generalist cannot retrofit this without rebuilding their core.
2. **Local fluency.** Jalali calendar as a first-class citizen, RTL-first layout, SMS as the
   primary channel, instalment tuition as the default, local payment rails including cash and
   card-to-card. A Western SaaS product cannot economically match this, and a local competitor
   cannot easily match the product depth.
3. **Structural switching cost.** Once a term of history, a tuition ledger, and student portal
   adoption exist, leaving means losing the institute's memory. Churn in this category should
   be structurally low — and where it isn't, the cause is failed onboarding, not price.

The business model follows the customer's own mental model: priced per **active student**, with
unlimited staff on every plan, so the bill grows only when the institute grows, and nobody is
ever incentivised to share a login.

**Why it matters beyond the business case:** these institutes teach language to people trying
to study abroad, get a better job, or move country. The administrative friction they carry is
paid for by their students — in wasted trips, missed information, and money spent on
disorganisation rather than teaching. Making an institute run well is not a back-office
improvement; it shows up in the classroom.

---

## O.2 What to prioritise first

In strict order. Each is a gate on the next.

### 1. The teacher attendance screen
Not the dashboard, not the marketing site. **This one screen determines whether the entire
product works**, because attendance is the input to at-risk flags, retention reports, progress
tracking, and the manager's daily view — and teachers will only use it if it is genuinely
faster than a paper roster.

Target: under 20 seconds to mark a class, on a mid-range Android, in a corridor, offline. Give
it its own performance budget, its own CI gate, and more design iterations than any other
screen in the product.

### 2. The enrolment + payment flow
The front desk's most frequent task and the moment money enters the system. Under two minutes,
one screen, no re-typing, instalment plans as a default rather than an exception. If this is
slower than the notebook, staff will use the notebook and reconcile later — which means never.

### 3. Term builder with clone-previous-term
The feature that makes the second term dramatically cheaper than the first, which is precisely
when the customer decides whether to renew. It converts the institute's worst two weeks of the
year into an afternoon. This is the demo moment that closes S2 deals.

### 4. The manager overview with a real exception panel
The reason an owner opens the app daily. Exceptions above metrics, metrics above charts. If it
answers "what needs me today?" in five seconds, it becomes a habit; if it's a wall of charts,
it becomes a bookmark nobody clicks.

### 5. Finance: invoices, instalments, debt board
The value story that justifies the price. Institutes discover uncollected tuition that exceeds
the subscription cost — that discovery is the strongest sales argument available and it must
be demonstrable in their own data.

### 6. The student portal
The mechanism that delivers the promised time savings, by removing routine phone calls. Needs
no app install, opens from an SMS deep link, and logs in by phone number.

### 7. Onboarding and import
Not glamorous. The largest single cause of trial non-conversion and first-year churn. Target
three days from signup to first real enrolment, measured as a product metric.

**Everything else is secondary until these seven are excellent.**

---

## O.3 What to avoid at the start

| Avoid | Why |
|---|---|
| **Multi-branch in v1** | Real complexity, ~20% of the market, and a legitimate reason to upgrade later. Put `branch_id` in every table now; build the UI in phase 2 |
| **Building video conferencing** | Commoditised, expensive, unwinnable. Integrate and store the link, the recording, and the attendance |
| **Becoming an LMS** | The gravitational pull toward content authoring is strong and fatal. Institutes already have books and materials; they don't have operations software |
| **A public API before the domain model is stable** | An API is a contract. Signing it before the model settles guarantees breaking changes with real integrators attached |
| **Native mobile apps pre-PMF** | Two more codebases and an app-store release cycle for something a well-built PWA does. Revisit once the workflows are proven |
| **AI features before there's data** | Nothing to learn from, and a wrong automated grade costs more trust than the time it saves |
| **A permanent free tier** | Attracts the segment least likely to convert at full support cost. A cheap Starter plan captures the same market with a qualification filter attached |
| **Custom development for the first big customer** | The classic agency trap. One enterprise deal that forks the product costs more than it pays |
| **Adjacent verticals (music, sports, driving schools)** | The model fits, but expanding horizontally before winning vertically is how products in this category die |
| **Per-seat pricing** | Causes login sharing, which destroys attendance data and the audit trail |
| **Over-configuring v1** | Every setting is a support question and a test case. Ship opinionated defaults; add settings when customers actually diverge |

---

## O.4 The first 90 days

### Weeks 1–2 — Validate the blueprint against reality
- Interview **8–10 institute owners and academic managers** across S1 and S2 segments.
- Ask them to walk through their last term rollover, step by step, in their own words. Don't
  demo anything. Don't validate the plan — try to break it.
- Sit at a front desk for a day. Watch a teacher take attendance. Watch a payment get recorded.
- **Recruit 3 design partners**: one S1, two S2, ideally one with two branches. Offer a free
  first year in exchange for weekly access and permission to observe real usage.
- Adjust this blueprint from what you learn. It is a hypothesis, not a plan of record.

### Weeks 3–6 — Design
- Design system: tokens, components, RTL-first, both themes. ([M](M-ui-ux-direction.md))
- High-fidelity screens for the seven priority surfaces above, in Persian, with realistic data.
- **Prototype the teacher attendance screen and test it with 5 real teachers on their own
  phones.** Iterate until it beats a paper roster on a stopwatch. Do this before writing the
  backend, because the result may change the data model.
- Test the enrolment flow with a real front-desk employee under time pressure.

### Weeks 7–18 — Build the MVP
- Foundation first: multi-tenancy with the cross-tenant leakage test suite, auth, RBAC, the
  calendar/localisation layer, audit logging. These are impossible to retrofit.
- Then vertical slices in priority order, each shippable to the design partners:
  students → terms/classes → enrolment → attendance → finance → communication → reports.
- Design partners get access from the first slice. Weekly feedback, in their office, not on a
  call.
- Instrument every feature from day one. Shipping without usage measurement means guessing in
  phase 2.

### Weeks 19–30 — The design-partner term
- All three partners run a **complete real term** in Lingo Talk. Enrolment through term close.
- The success criterion is unambiguous: **can they run the term without opening Excel?** Every
  time they fall back to a spreadsheet, that's a specific product gap — log it, fix it.
- Measure: onboarding time, attendance marking rate, payments recorded in-product vs. actual,
  time to enrol, staff adoption per role.
- Fix relentlessly. Do not add features. Do not build phase 2 items. Do not start the marketing
  site's animations.

### Week 30+ — General availability
- Launch only when all three partners have completed a term and would genuinely recommend it.
- Their term-close reports and their named quotes are the launch content. One real institute
  saying "we found 84 million rials we hadn't collected" outperforms any amount of feature copy.

---

## O.5 How to structure the launch

### Go-to-market sequence

**Phase 1 — Design partners (months 1–7).** Three institutes, free, high touch. The product is
built with them, not for them.

**Phase 2 — Controlled launch (months 7–10).** 15–20 institutes, sourced from the design
partners' referrals and direct outreach. Paid, discounted, with hands-on onboarding for every
one. The goal is not revenue — it's proving the onboarding process works without the founders
personally doing it.

**Phase 3 — Open launch (months 10+).** Public marketing site, self-serve trial, demo-led sales
for S2+. Timed to land **6–8 weeks before a major term start**, because that is the only window
in which an institute has the bandwidth to consider changing systems.

### Channels, in order of expected return

1. **Referral from existing customers.** Institute owners in this market know each other and
   talk constantly. This will be the dominant channel — build the referral programme into the
   product at launch, not later.
2. **Direct outreach with a specific hook.** Not "we have software." Instead: "how much of last
   term's tuition is still uncollected?" — a question they can't answer and immediately want to.
3. **Content and SEO.** Practical guides on term planning, retention, and pricing for institute
   owners. This is a market with almost no good operational content aimed at it.
4. **Term-timing campaigns.** Concentrated spend 6–8 weeks pre-term.
5. **Community and events.** Language-teaching associations and teacher-training programmes.

### The demo script that works
1. Ask about their last term rollover. Let them describe the pain in their own words.
2. Ask what percentage of expected tuition they collected. Watch them not know.
3. Show the term builder cloning a term in three minutes.
4. Show the debt board, populated with their kind of numbers.
5. Show a teacher marking attendance in fifteen seconds on a phone.
6. Offer to import their student list, free, this week.

Don't tour the feature list. Solve their most-felt problem live, in under ten minutes.

---

## O.6 How to keep the product practical

Seven rules to hold onto when the roadmap starts arguing with itself.

1. **The weekly-use test.** Every feature must be used by someone at the institute at least
   weekly. If it's used once a year, it's a report or an export, not a feature.
2. **The sixty-second rule.** Every core task completable in under 60 seconds by an untrained
   user. If it needs training, redesign it before documenting it.
3. **Defaults over settings.** Every setting is a support question, a test case, and a decision
   the customer didn't want to make. Ship opinionated defaults; add configuration only when
   real customers actually diverge.
4. **Say no to the roadmap document.** This blueprint's phase 2 is a prior, not a commitment.
   Build what paying customers ask for repeatedly. One customer asking is a note; three is a
   feature.
5. **The top support category is a roadmap item.** The most common ticket is almost always a
   product defect or a UX failure. Fix the cause, don't staff the symptom.
6. **Measure adoption per role, not per tenant.** A tenant where the owner logs in daily and no
   teacher ever marks attendance is a churn in progress, and tenant-level metrics hide it.
7. **Protect the teacher panel.** Every feature request that would make it heavier gets
   rejected by default. Its speed is load-bearing for the entire product.

---

## O.7 How to make sure the first release is valuable

The release is valuable if, and only if, **one real institute can run one complete term on it
without falling back to a spreadsheet.**

That single sentence is the acceptance criterion for v1. It's stricter than a normal MVP bar,
and it's correct here, because there is no partial adoption of an operations system — it
either becomes the source of truth or it becomes shelfware.

Concretely, the first release must be able to:

- [ ] Register a student from first enquiry to enrolled, including a placement test
- [ ] Build a term with classes, teachers, rooms, and a full session calendar — with conflicts
      caught before publishing
- [ ] Take a payment in cash **and** online, with an instalment plan, and produce a receipt
- [ ] Let a teacher mark attendance on a phone, offline, in under 20 seconds
- [ ] Let a teacher enter and publish grades without a spreadsheet
- [ ] Show a student their schedule, balance, homework, and grades without them phoning anyone
- [ ] Send an announcement to one class and a reminder to everyone overdue, with delivery proof
- [ ] Tell the owner, accurately, how many students they have, how much they've collected, and
      who owes what
- [ ] Close the term: lock grades, issue certificates, carry balances forward, and produce a
      retention number
- [ ] Export everything, in full, at any time

Ten items. Everything else in this blueprint is elaboration on top of them.

---

## O.8 The final recommendation

**Build narrow, build deep, and launch with one institute's real term as your proof.**

The temptation in this category is to build wide — every module, every role, every feature on
the comparison table — because education software is evaluated on feature lists. Resist it. A
broad, shallow product loses to a spreadsheet, because the spreadsheet at least does one thing
exactly the way the institute wants.

Instead: pick the single-branch institute with 150–400 students as your customer, build the
seven priority surfaces until each is genuinely better than the manual alternative, and prove
it by running a real term. Then sell that proof.

**Three things to hold onto:**

1. **The teacher panel is the product's foundation.** Not the dashboard — the dashboard is
   downstream of data that only exists if teachers use the panel. Over-invest in it.
2. **Onboarding is a product feature, not a service.** More customers will be lost between
   signup and first real enrolment than to any competitor. Instrument it, staff it, and treat a
   drift from three days to three weeks as a P0 bug.
3. **The financial ledger must be beyond question.** Append-only, idempotent, reconcilable,
   drillable. The moment an owner catches one wrong number, they stop trusting all of them —
   and an institute that doesn't trust the money data will keep the notebook, and keeping the
   notebook is how they churn.

Everything else in this blueprint — the roadmap, the pricing tiers, the AI features, the
marketplace — is a conversation for after a real institute has run a real term and asked for
their second one.

**Start there.**
