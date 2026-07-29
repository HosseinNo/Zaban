# K. Subscription and Monetization Model

## K.1 Pricing philosophy

**Price on the value metric the customer already thinks in: active students.**

An institute measures its own size in students. It sets its own prices per student. Pricing
Talkora per student means the bill grows exactly when the customer's ability to pay grows,
and it never punishes them for adding staff — which is what makes per-seat pricing so
destructive in this market. A 400-student institute with 30 part-time teachers cannot pay
per user, and per-user pricing would push them to share logins, which destroys the audit
trail and the entire teacher panel value proposition.

### The four rules

1. **Unlimited staff on every plan, always.** Every teacher, front-desk person, and manager
   gets their own login. This is non-negotiable — shared logins break attendance data, audit
   trails, and permissions.
2. **Unlimited students in the portal.** Students never count as seats. The metric is *active
   enrolled students in the current term*, not total records.
3. **Branches are a plan dimension**, because multi-branch is genuinely more product and
   correlates with willingness to pay.
4. **Never gate the data.** Export is available on every plan, including during suspension.
   Holding data hostage is short-term revenue and long-term reputational death in a market
   where institute owners all know each other.

---

## K.2 Plan structure

| | **Starter** | **Growth** ★ | **Pro** | **Enterprise** |
|---|---|---|---|---|
| **For** | Tutors, very small institutes | Single-branch institutes | Multi-branch, growing | Chains, corporate training |
| **Active students** | up to 75 | up to 400 | up to 1,500 | Unlimited |
| **Branches** | 1 | 1 | up to 5 | Unlimited |
| **Staff users** | Unlimited | Unlimited | Unlimited | Unlimited |
| **Student portal** | ✔ | ✔ | ✔ | ✔ |
| **Teacher panel** | ✔ | ✔ | ✔ | ✔ |
| **Classes, terms, attendance** | ✔ | ✔ | ✔ | ✔ |
| **Tuition, invoices, instalments** | ✔ | ✔ | ✔ | ✔ |
| **Online payment gateway** | ✔ | ✔ | ✔ | ✔ |
| **Exams & gradebook** | Basic | ✔ | ✔ | ✔ |
| **Announcements & SMS** | 200 SMS/mo | 1,000 SMS/mo | 4,000 SMS/mo | Custom |
| **Automated reminders** | Payment only | ✔ Full | ✔ Full | ✔ Full |
| **Reports** | 5 core | Full library | Full + custom views | Full + custom + API |
| **Lead & admissions CRM** | Basic list | ✔ Pipeline | ✔ Pipeline + scoring | ✔ + integrations |
| **Placement tests** | — | ✔ | ✔ | ✔ |
| **Online exams** | — | ✔ | ✔ | ✔ |
| **Re-enrolment campaigns** | — | ✔ | ✔ | ✔ |
| **Waitlist management** | — | ✔ | ✔ | ✔ |
| **Custom roles & permissions** | — | — | ✔ | ✔ |
| **Branch comparison reports** | — | — | ✔ | ✔ |
| **Custom domain** | — | Add-on | ✔ | ✔ |
| **Certificates** | — | ✔ | ✔ + custom templates | ✔ |
| **API access** | — | — | Read | Read + write |
| **Data export** | ✔ | ✔ | ✔ | ✔ |
| **Audit log** | 30 days | 1 year | Unlimited | Unlimited |
| **Support** | Help centre + email | Email, 1 business day | Priority, 4 h | Dedicated CSM + SLA |
| **Onboarding** | Self-serve | Guided setup call | Full migration | White-glove + training |
| **Price (monthly)** | Entry | 3.5× Starter | 9× Starter | Custom |
| **Price (annual)** | −20% | −20% | −20% | Negotiated |

★ = most popular, and the plan the pricing page should visually anchor on.

### Price-point guidance (relative, not absolute)

Absolute numbers must be set from local market research, but the *ratios* and the *anchoring
logic* should hold:

- **Starter** should cost roughly what one month of one student's tuition costs. That makes
  the decision trivial: "one student pays for the software."
- **Growth** should land near 1–2% of the institute's monthly tuition revenue at 300 students.
  Above 3% the deal gets hard; below 0.5% we're leaving money on the table and signalling low
  value.
- **Pro** is priced on the multi-branch capability, not on student count alone — the branch
  dimension is what a chain is actually buying.
- **Enterprise** is negotiated, floor-priced at 2× Pro, and includes a mandatory onboarding fee.

### Anti-patterns explicitly avoided

| Anti-pattern | Why we avoid it |
|---|---|
| Per-teacher / per-user pricing | Causes login sharing, which destroys attendance data and audit integrity |
| Charging for the student portal | The portal is the mechanism that delivers the promised time savings; taxing it undermines the value story |
| Gating data export | Reputational suicide in a tight-knit market |
| Locking core operations (attendance, payments) behind upper tiers | These are the product. Gate *sophistication*, never *operation* |
| Transaction fees on institute tuition | We are not a payment processor. Taking a cut of their revenue makes us a rival, not a tool |
| Hard cut-off at the student limit mid-term | Never break a running term. Grace, warn, then bill |

---

## K.3 Trial

| Parameter | Value | Reasoning |
|---|---|---|
| Length | **14 days** | Long enough to import data and run a real week; short enough to force a decision |
| Plan during trial | **Growth, full features** | A crippled trial demonstrates a crippled product |
| Credit card required | **No** | This market resists card-on-file for trials; requiring it halves signups |
| Included SMS | 100 free credits | Institutes must test the communication loop end to end or they can't evaluate it |
| Extension | Once, 7 days, by a rep with a recorded reason | Track extensions — a high rate means onboarding is broken, not that customers need more time |
| At expiry | Read-only for 7 days → export-only for 30 days → scheduled deletion with notice | Never an abrupt lockout |

**Conversion mechanic:** the trial-to-paid decision is not driven by the countdown. It's driven
by whether the institute has entered real data. The in-product conversion prompt should
therefore reference their own data: *"You've enrolled 43 students and collected 128,000,000
IRR this month in Talkora. Keep going →"* That reframes the price against value they've
already realised.

---

## K.4 Add-ons

Add-ons are how a Growth customer becomes worth more without changing plans. Each is priced
independently and billed on the same cycle.

| Add-on | Model | Notes |
|---|---|---|
| **SMS credit packs** | Pre-paid bundles, tiered by volume | Passed through at a modest margin. Never a profit centre — inflated SMS pricing is the fastest way to lose trust. Auto-top-up available |
| **Extra branch** | Per branch, per month | For Pro tenants beyond 5 |
| **Extra student blocks** | Per block of 100 above the plan ceiling | Cheaper than upgrading a whole tier; a soft landing that reduces upgrade friction |
| **Custom domain** | Flat monthly | Included in Pro |
| **Onboarding & migration** | One-time fee, tiered by data volume | Real service: data cleaning, import, configuration, 2 training sessions. Sold to almost every S2/S3 customer |
| **Staff training** | Per session | Especially valuable at term rollover |
| **Priority support** | Monthly | Included in Pro/Enterprise |
| **Extended audit retention** | Monthly | Compliance-driven demand |
| **API access** | Monthly | Read on Pro; write on Enterprise |
| **Additional storage** | Per block | Most tenants never hit this |
| **Custom report build** | One-time per report | High-margin, deepens lock-in, and reveals what belongs in the standard library |

---

## K.5 The onboarding fee (a real revenue line, not a tax)

For institutes above ~200 students, charge a one-time onboarding fee covering:
data cleaning and import, academic structure configuration, financial and payment setup,
message template localisation, and two live staff training sessions.

**Why this is right, not greedy:**
1. It funds the work that most determines whether the customer succeeds and stays.
2. It filters out unserious buyers — a paid onboarding is a commitment signal.
3. It makes migration our job rather than the customer's, which removes the single largest
   adoption barrier.
4. Institutes that pay for onboarding activate faster and churn measurably less.

Waive it as a negotiation lever on annual contracts. Never waive it silently for everyone —
that turns it from a service into a discount.

---

## K.6 Billing mechanics

| Mechanic | Rule |
|---|---|
| **Cycles** | Monthly or annual. Annual = 20% discount, paid up front |
| **Currency** | Local currency for the launch market; USD for international. Prices set per currency, not FX-converted |
| **Payment methods** | Local gateway, bank transfer with proof upload, and card for international. Bank transfer must be first-class — many institutes will not put a card on file |
| **Upgrade** | Immediate, prorated for the remainder of the period |
| **Downgrade** | At period end, never mid-cycle. Warn explicitly if current usage exceeds the target plan's limits and name what will be affected |
| **Student-limit overage** | Warn at 80%, 90%, 100%. At 100%: 14-day grace, then either auto-add a student block (if opted in) or block *new* enrolments while leaving all existing operations fully functional. **Never break a running term** |
| **Failed payment (dunning)** | day 0 retry · day 1 notify · day 3 retry · day 5 notify + in-app banner · day 7 retry · day 10 read-only · day 20 suspended · day 45 scheduled deletion. Export available at every stage |
| **Pause** | Up to 3 months, at a nominal fee, data retained. Offered as a retention alternative before cancellation — seasonal institutes genuinely need this |
| **Cancellation** | Self-serve, at period end, with an exit survey and a retention offer. No phone-call requirement — forcing one generates public complaints worth more than the saved account |
| **Refunds** | Pro-rated on annual plans within the first 30 days; case-by-case after |
| **Invoices** | Generated automatically, VAT/tax-compliant per country, downloadable, emailed |

---

## K.7 Expansion revenue

Net revenue retention above 100% is what makes this business work. The expansion paths, in
order of expected contribution:

1. **Student growth into the next tier.** The customer succeeds → they cross the ceiling →
   they upgrade. This is the healthiest expansion because it's caused by their growth, and it
   is why per-student pricing is the right metric.
2. **Branch expansion.** A single-branch institute opening a second location is the strongest
   upgrade trigger in the product, and it's a moment we can detect and act on.
3. **SMS consumption growth.** Usage rises naturally as automation adoption deepens.
4. **Add-on attach.** Custom domain, API, priority support, training.
5. **Feature-tier upgrades.** Custom roles, branch comparison reports, custom report builds.

**Target:** NRR ≥ 108% by month 18. Track expansion MRR separately from new MRR from day one —
conflating them hides whether the pricing model actually works.

---

## K.8 Unit economics targets

| Metric | Target | Notes |
|---|---|---|
| Gross margin | > 80% | SMS pass-through is the main variable cost; keep the margin on it thin and honest |
| CAC (self-serve) | < 1 month of ACV | Content, SEO, referrals |
| CAC (sales-assisted) | < 4 months of ACV | Demo-led for S2/S3 |
| CAC payback | < 6 months | |
| LTV:CAC | > 4:1 | Achievable given structurally low churn |
| Monthly logo churn | < 2.5% | Below 2% is realistic once onboarding is solid |
| Monthly revenue churn | < 1% | Expansion offsets logo churn |
| Trial → paid | > 25% | Below 20% means onboarding, not pricing, is broken |
| Onboarding fee attach (S2+) | > 60% | |

**The structural advantage:** switching cost is genuinely high once a term of history, a
tuition ledger, and student portal adoption exist. Churn in this category should be low. If
it isn't, the cause is almost always failed onboarding — not price, and not features.

---

## K.9 Free tier — recommendation: don't

**Recommendation: no permanent free tier at launch.** Instead: a full-featured 14-day trial,
and a genuinely cheap Starter plan.

Reasoning:
- A free tier for institutes attracts the segment least likely to convert (institutes with
  fewer than 30 students) while carrying full support cost.
- The product's value only becomes visible after real data entry, which free-tier users
  rarely complete.
- Support load per free account is nearly identical to a paying one, because the questions
  are the same.
- A cheap Starter plan captures the same market with a qualification filter attached.

**Revisit in Phase 3** with a genuinely constrained free tier aimed at *independent tutors*
(one teacher, ≤ 15 students, no SMS, community support only) purely as a top-of-funnel and
word-of-mouth engine — but only once support is scaled and self-serve onboarding converts
without human involvement.

---

## K.10 Marketing and growth levers tied to monetisation

| Lever | Mechanism |
|---|---|
| **Referral programme** | An institute referring another gets 1 month free per converted referral (capped); the referred institute gets 20% off their first 3 months. Institute owners in this market talk to each other constantly — this is the highest-leverage channel available |
| **Annual prepay discount** | 20% — improves cash flow and locks in retention through at least two term cycles, which is when the product proves itself |
| **Term-timing campaigns** | Buying intent spikes 6–8 weeks before term start. Concentrate spend there; it is the only moment an institute has the bandwidth to consider changing systems |
| **Migration offer** | "Bring your Excel file, we'll import it free" — removes the single biggest adoption barrier and is cheap to deliver |
| **Public certificate verification** | Every certificate carries a verification link to a Talkora page. Free branded distribution to students and employers |
| **Institute directory (Phase 3)** | An opt-in public directory of institutes on the platform. Genuine value to them, genuine SEO to us — but only opt-in, and never a marketplace that puts us between them and their students |
| **Coupon codes** | Platform-level coupons for campaigns, events, and partnerships, with redemption tracking that feeds the funnel report |
