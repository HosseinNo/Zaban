# M. UI / UX Direction

## M.1 The design thesis

**Talkora should look like a well-made instrument, not a marketing campaign.**

The person using this is at a front desk with a queue of parents, or a teacher in a corridor
with four minutes between classes. They are not browsing. They are working. Every design
decision resolves toward the same question: *does this help someone complete a task faster, or
does it decorate the screen?*

The visual character to aim for: **quiet, dense, precise, and unmistakably local.** Confident
enough to be plain. The product should feel like it was made by people who understand how an
institute actually runs — which means it should look nothing like a generic SaaS template with
a gradient hero and a purple button.

### Three governing tensions, resolved

| Tension | Resolution |
|---|---|
| **Simplicity vs. depth** | Simple surface, deep interior. The default view shows what most people need most of the time; power lives behind filters, the command palette, and progressive disclosure — never behind a "advanced mode" toggle |
| **Density vs. breathing room** | Dense in the app, generous on marketing. A work tool used for six hours a day should show more per screen, not less. An 8px spacing scale, not 24px |
| **Beautiful vs. fast** | Fast wins, always. No decorative animation, no hero imagery in the app, no font that loads late. A 200ms interaction beats a beautiful one |

---

## M.2 Layout system

### Application shell

| Breakpoint | Shell |
|---|---|
| `≥1440px` | Sidebar 260px expanded · content max 1440px centred with fluid gutters · optional right rail for detail panels |
| `1280–1439px` | Sidebar 260px · content fluid · detail opens as an overlay panel |
| `1024–1279px` | Sidebar collapsed to 64px icon rail with tooltips · content fluid |
| `768–1023px` | Sidebar becomes a drawer · content full width · tables switch to a condensed layout |
| `<768px` | Drawer nav · tables become card lists · bottom action bar for primary actions |

**Breakpoints:** `375 · 640 · 768 · 1024 · 1280 · 1440`. Mobile-first authoring; the app shell
is designed at 1440 and 375 simultaneously, never one and then "made responsive."

### Content patterns

| Pattern | Use | Rules |
|---|---|---|
| **List + detail** | Students, teachers, classes, invoices | Detail opens as a right panel on ≥1280px (preserving list context), full page below |
| **Dashboard grid** | All four dashboards | 12-column, 16px gutters, widgets snap to 3/4/6/12 |
| **Tabbed record** | Student profile, class detail, exam | **Sticky identity header** above tabs — the user must always see who/what they're looking at |
| **Wizard** | Term builder, signup, import, onboarding | Linear steps, visible progress, save-and-resume at every step, back always available |
| **Data table** | Every list | Sticky header + first column, column config persisted per user, row density toggle, bulk-select bar |
| **Side panel** | Quick edits, record payment, resolve an alert | Never navigate away from a list to perform a routine action |
| **Modal** | Only for destructive confirmation and short focused creates | Modals are a last resort; side panels are preferred because they preserve context |

### The 4/8 spacing scale

```
--space-1:  4px     --space-5:  20px    --space-9:  48px
--space-2:  8px     --space-6:  24px    --space-10: 64px
--space-3:  12px    --space-7:  32px    --space-11: 80px
--space-4:  16px    --space-8:  40px    --space-12: 96px
```

**App density:** component padding 12–16px, section gaps 24px, page gutters 24–32px.
**Marketing density:** section gaps 64–96px, generous by contrast — the shift in rhythm
between marketing and app is intentional and signals "you've entered the tool."

---

## M.3 Colour

### Philosophy

One dominant neutral ramp, one action colour, and semantic colours used *only* semantically.
The interface is 90% neutral. Colour is a signal, not a texture — the moment colour becomes
decorative, it stops being able to mean anything, and in a product where red means "this
student owes money," that's a functional failure, not a taste failure.

### Palette

**Neutrals** — slightly cool, not pure grey (pure grey reads cheap and flattens depth)

```
--ink-950  #0B1220   page text on light, app background in dark
--ink-900  #131C2E
--ink-800  #1E2A42
--ink-700  #2C3A56   secondary text on light
--ink-600  #475569
--ink-500  #64748B   muted text, placeholders
--ink-400  #94A3B8   disabled text, subtle icons
--ink-300  #CBD5E1   borders (strong)
--ink-200  #E2E8F0   borders (default), dividers
--ink-100  #F1F5F9   subtle fills, table stripes, hover
--ink-50   #F8FAFC   page background (light)
--white    #FFFFFF   surfaces, cards
```

**Brand and action**

```
--brand-900 #0F2A54   brand ink — sidebar, marketing headers
--brand-700 #16437F
--brand-600 #1D5AA8
--brand-500 #2563C7   PRIMARY ACTION — buttons, links, active nav, focus
--brand-400 #4B85DD
--brand-200 #BFD5F2   subtle backgrounds
--brand-100 #E6EFFB   selected rows, active nav background
--brand-50  #F3F8FE
```

`--brand-500 #2563C7` on white = **5.9:1** — passes AA for normal text, so it can be used for
links and small labels, not just large buttons. That constraint drove the choice.

**Accent** — used sparingly, for positive emphasis and educational warmth

```
--accent-600 #B26A00
--accent-500 #D98324   progress, achievement, certificates, highlights
--accent-100 #FDF1E1
```

**Semantic** — each has exactly one meaning, applied consistently everywhere

```
--success-600 #0A7048   paid, present, passed, healthy
--success-100 #E3F5EC
--warning-600 #A65F00   due soon, needs attention, below capacity
--warning-100 #FDF2E0
--danger-600  #B42318   overdue, absent, failed, destructive
--danger-100  #FDE9E7
--info-600    #1D5AA8   neutral system information
--info-100    #E6EFFB
```

**Dark mode** — a re-tuned ramp, never an inversion

Surfaces climb rather than sink: `#0B1220` page → `#141C2B` card → `#1C2637` raised.
Brand and semantic hues are desaturated and lightened (`--brand-500` becomes `#5B92E5`,
`--danger-600` becomes `#F0736A`) to hold contrast against dark surfaces without vibrating.
Every pair is contrast-verified independently — light-mode values are never assumed to carry over.

### Colour usage rules

| Rule | Detail |
|---|---|
| **Colour never carries meaning alone** | Overdue = red text **+** a warning icon **+** the word. Colour-blind users and greyscale printouts must both work |
| **Semantic colours are reserved** | Green never means "brand," red never means "emphasis." A red button is destructive, always |
| **One primary action per screen** | Exactly one filled brand button. Everything else is secondary (outline) or tertiary (text) |
| **Class colours are a separate categorical scale** | 8 distinguishable hues assigned to classes, consistent across schedule, portal, and reports. Chosen for colour-blind separability, never overlapping the semantic set |
| **Charts use their own accessible sequence** | Never the semantic palette, except where a series genuinely means "overdue" |
| **Tenant branding is bounded** | Institutes set a brand colour used in the portal header, emails, and certificates — it never overrides action or semantic colours, because a tenant with a red brand colour must not turn every button into a delete button |

---

## M.4 Typography

### Font selection

| Role | Font | Rationale |
|---|---|---|
| **Persian (primary)** | **Vazirmatn** (variable) | The best open Persian UI face available: proper Arabic-script proportions, a complete weight axis, well-designed Persian numerals, and a coherent Latin companion. Free, self-hostable, no CDN dependency |
| **Latin (in fa context)** | Vazirmatn's Latin | Keeps mixed-script lines optically consistent — critical because institute UIs are full of "IELTS B2" inside Persian sentences |
| **Latin (en locale)** | **Inter** (variable) | Neutral, excellent at small sizes, tabular figures |
| **Numerals in data** | Vazirmatn / Inter **tabular figures** | Mandatory in every table, price, time, and countdown. Proportional figures make columns wobble and are the single most common typographic tell of amateur data UI |
| **Codes & references** | **JetBrains Mono** | Invoice numbers, verification codes, IDs |

Fonts are **self-hosted, subset, preloaded, `font-display: swap`** with a metric-compatible
fallback so text is never invisible and never reflows on load.

### Type scale

App scale (a work tool — tighter than a marketing scale):

| Token | Size / line-height | Weight | Use |
|---|---|---|---|
| `display` | 32 / 40 | 600 | Dashboard KPI numbers only |
| `h1` | 24 / 32 | 600 | Page titles |
| `h2` | 20 / 28 | 600 | Section headings |
| `h3` | 17 / 24 | 600 | Card and panel titles |
| `body-lg` | 16 / 24 | 400 | Primary reading, form inputs |
| `body` | 15 / 22 | 400 | Default UI text, table cells |
| `body-sm` | 13 / 20 | 400 | Secondary text, metadata |
| `label` | 13 / 16 | 500 | Form labels, table headers (uppercase avoided in Persian — it doesn't exist) |
| `caption` | 12 / 16 | 400 | Timestamps, helper text, footnotes |
| `mono` | 13 / 20 | 400 | Codes, references |

Marketing scale runs larger: `display` 48–64, `h1` 36–40, `body` 17–18, with line lengths
capped at 65–75 characters.

**Persian typographic requirements** (routinely botched, and immediately visible to native
readers):
- Line-height **1.6–1.75** for Persian body text — Arabic-script ascenders and descenders need
  more vertical room than Latin. Using a Latin-tuned 1.5 makes Persian text look cramped.
- **Never** letter-space Persian. It breaks the connected script.
- **No synthetic bold.** Use real weights from the variable axis.
- **No uppercase transform** — the concept doesn't exist in the script and it silently breaks
  any mixed Latin.
- Persian digits (`۱۲۳`) in fa display; Latin digits in exports, invoice numbers, and anything
  copy-pasted into another system.
- Mixed-direction runs (a Latin course code in a Persian sentence, a phone number, a price)
  need proper bidirectional isolation, or they render in the wrong order — a bug users report
  as "the numbers are backwards."

### Minimums
Body text never below 13px, and never below **16px on mobile inputs** (iOS auto-zooms below
16px, which is a jarring, avoidable defect). Body contrast ≥ 4.5:1; secondary text ≥ 4.5:1 too
— "muted" is not permission to fail contrast.

---

## M.5 Components

### Design tokens (structural)

```
--radius-sm   4px    inputs, chips, badges
--radius-md   8px    buttons, cards, panels
--radius-lg   12px   modals, large surfaces
--radius-full        avatars, pills

--shadow-xs   0 1px 2px rgba(11,18,32,.06)          subtle lift
--shadow-sm   0 2px 6px rgba(11,18,32,.08)          cards
--shadow-md   0 8px 20px rgba(11,18,32,.10)         side panels, dropdowns
--shadow-lg   0 20px 48px rgba(11,18,32,.16)        modals

--border      1px solid var(--ink-200)
--focus-ring  0 0 0 3px rgba(37,99,199,.35)

--z-base 0 · --z-sticky 10 · --z-dropdown 20 · --z-panel 40 · --z-modal 60 ·
--z-toast 80 · --z-tooltip 100
```

Elevation is a **fixed ladder**, not per-component invention. Every surface picks a rung.

### Key component rules

| Component | Rules |
|---|---|
| **Button** | Heights 32/36/40/48. One filled primary per screen. Destructive is filled danger and spatially separated from confirm. Loading state disables and shows an inline spinner while preserving width (no layout jump). Icon-only buttons always carry an accessible label |
| **Input** | Visible label above, always — never placeholder-as-label. Helper text persistent below. Errors below the field, stating cause and fix. Validate on blur, not on keystroke. Correct input types so mobile keyboards match (`tel`, `email`, `numeric`) |
| **Table** | Sticky header and first column. Tabular figures. Numeric columns right-aligned in LTR / start-aligned appropriately in RTL. Row hover. Bulk-select bar appears on selection. Sortable headers announce sort state. Below 768px, rows become cards — never a horizontally scrolling table on a phone |
| **Data card (mobile row)** | Primary identifier, 2–3 key facts, one primary action. Whole card tappable, minimum 48px tall |
| **Badge / status pill** | Icon + text + colour. Never colour alone. A fixed vocabulary per domain so "active" looks identical everywhere |
| **Empty state** | Illustration optional, explanation mandatory, primary action mandatory. Never a bare "No data" |
| **Toast** | Bottom-start (RTL-aware), auto-dismiss 4s, never steals focus, `aria-live="polite"`. Destructive actions get an **Undo** in the toast rather than a confirmation dialog beforehand, where reversal is possible |
| **Modal** | Escape and backdrop close, unless there are unsaved changes — then confirm. Focus trapped, focus returned on close. Max one modal deep, ever |
| **Side panel** | Slides from the inline-end edge, 480–640px, non-blocking where possible, closes to the same list scroll position |
| **Command palette** | `⌘K` / `Ctrl+K`. Searches records and commands. The power path for daily users |
| **Date picker** | Renders the institute's calendar natively — Jalali picker with Jalali month names and a Saturday-first week for fa tenants. Keyboard-enterable text input alongside |
| **Avatar** | Photo where available; initials with a deterministic background colour otherwise. Student photos matter — teachers identify by face on the attendance roster |

### Touch targets
Minimum **44×44pt** everywhere, **48px** on the teacher attendance screen (used at speed, one
hand, often standing). Minimum 8px between adjacent targets. Hit areas extend beyond visual
bounds where the icon is small.

---

## M.6 Motion

**Rule: motion explains, it never entertains.** If an animation doesn't communicate a
relationship — where something came from, what changed, what's loading — it doesn't ship.

| Token | Duration | Easing | Use |
|---|---|---|---|
| `instant` | 100ms | ease-out | Hover, focus, colour change |
| `fast` | 160ms | ease-out | Dropdowns, tooltips, toggles |
| `base` | 220ms | cubic-bezier(.2,.8,.2,1) | Side panels, modals, tabs |
| `slow` | 320ms | cubic-bezier(.2,.8,.2,1) | Page transitions, large expansion |

Rules:
- Animate `transform` and `opacity` only. Never width, height, top, or left.
- Exits run at ~65% of enter duration — fast exits feel responsive.
- Panels and modals originate from their trigger, preserving spatial logic.
- List items stagger at 30ms, capped at 6 items — beyond that it's a delay, not a delight.
- Every animation is interruptible; user input cancels it immediately.
- `prefers-reduced-motion` collapses all of it to opacity-only transitions. Not optional.
- **Nothing on the dashboard animates on load.** A manager opening the app to check overdue
  payments should not wait for numbers to count up.

---

## M.7 Loading, errors, and feedback

| State | Treatment |
|---|---|
| **< 300ms** | No indicator. Showing a spinner for 200ms creates flicker that feels slower than nothing |
| **300ms – 2s** | Skeleton matching the final layout exactly — reserved dimensions, so nothing shifts when data lands (CLS = 0) |
| **> 2s** | Skeleton + a progress message naming what's happening ("Generating 240 sessions…") |
| **> 10s** | Move it to the background: "We'll notify you when the export is ready." Never hold the UI |
| **Optimistic writes** | Attendance marking and similar high-frequency actions apply immediately with a subtle pending indicator, reconciling on confirmation and rolling back visibly on failure |
| **Error** | State the cause and the fix, in the user's language, near the thing that failed. Never a raw error code alone — if a code is needed for support, show it as secondary text |
| **Offline** | Persistent, calm banner. Cached data remains visible and readable. Queued actions listed with sync status. Never a blocking modal |
| **Success** | Brief and unobtrusive: an inline checkmark or a toast. Never a modal for a routine save |
| **Destructive** | Prefer undo-after over confirm-before, where reversal is genuinely possible. Where it isn't (deleting a term with enrolments), require a typed confirmation and state the blast radius: "This will affect 142 enrolments" |

---

## M.8 RTL and internationalisation

RTL is not a variant. It is the **default authoring direction**, and LTR is derived from it.
Building LTR-first and mirroring later produces a permanently second-class Persian experience,
and this market notices immediately.

| Rule | Detail |
|---|---|
| **Logical properties only** | `margin-inline-start`, `padding-inline-end`, `inset-inline`. `left`/`right` never appear in the codebase; enforce with a lint rule |
| **Direction is one root attribute** | `dir="rtl"` on `<html>`. No stylesheet fork, no duplicated components |
| **Icons mirror selectively** | Directional icons (back, forward, next, progress, indent) mirror. Non-directional (search, calendar, user, settings, play) do not. A mirrored play button is a classic tell |
| **Numbers, times, and Latin text** | Stay LTR within RTL lines, with bidi isolation so phone numbers and course codes never reverse |
| **Charts mirror** | Axis order and reading direction follow the text direction; time still flows in the reading direction |
| **Tables mirror** | The identifier column sits at the inline-start; numeric alignment follows the inline-end |
| **Navigation mirrors** | Sidebar on the inline-start edge — the right side in RTL. Breadcrumb separators flip |
| **Shadows and gradients mirror** | A light source implied from the inline-start in one direction should mirror in the other, or the UI looks subtly wrong without anyone being able to say why |
| **Content length** | Persian runs ~10–15% longer than English for the same content. Layouts must tolerate it without truncation. Test every screen in both locales at the largest text size |

---

## M.9 Accessibility

Treated as a correctness requirement, not a compliance exercise. Target **WCAG 2.2 AA**.

| Area | Requirement |
|---|---|
| **Contrast** | 4.5:1 body, 3:1 large text and UI boundaries, verified in light **and** dark independently |
| **Focus** | Always visible: a 3px brand ring at 2px offset. Focus rings are never removed. Tab order matches visual order |
| **Keyboard** | Every action reachable without a mouse. Tables navigable. Modals trap and restore focus. `Esc` closes. `⌘K` opens the palette |
| **Screen readers** | Semantic HTML first, ARIA only where semantics fall short. Icon-only buttons labelled. Live regions for toasts and async results. Form errors use `role="alert"` |
| **Colour independence** | Every colour-coded state also carries an icon or text label |
| **Text scaling** | Layouts survive 200% zoom and OS-level text scaling without clipping or overlap |
| **Reduced motion** | Fully honoured |
| **Charts** | Every chart has a table equivalent and a text summary of its key insight |
| **Forms** | Visible labels, persistent helper text, errors adjacent to fields, first invalid field focused on submit, and an error summary with anchors when there are several |
| **Language** | `lang` attribute correct per element for mixed-script content, so screen readers switch voice correctly |

---

## M.10 Performance budget

Performance *is* the UX for a tool used all day.

| Metric | Budget |
|---|---|
| LCP | < 2.0s on a mid-range Android over 4G |
| INP | < 200ms |
| CLS | < 0.05 (skeletons reserve exact dimensions) |
| Initial JS (app shell) | < 180KB gzipped |
| Route chunk | < 80KB gzipped |
| Time to interactive (dashboard) | < 3s on a mid-range Android |
| Attendance screen ready | **< 1.5s from tap**, cold, on a mid-range phone |

Techniques: route-level code splitting · virtualised lists beyond 50 rows · server-side
pagination on every list · aggregates from materialised views, not live joins · images as
WebP/AVIF with explicit dimensions · self-hosted subset fonts · service worker caching the
teacher and student shells for offline use.

**The teacher attendance screen has its own budget and its own performance test in CI.** If it
regresses, the build fails. Everything downstream — attendance rates, at-risk flags, retention
reports, the manager's dashboard — depends on teachers actually using it, and they will only
use it if it is faster than paper.

---

## M.11 Marketing site design direction

A deliberate contrast with the app: generous, confident, and image-led — but still restrained.

- **Layout:** wide sections, 64–96px vertical rhythm, max 1200px content, one idea per section
- **Imagery:** real product screenshots with realistic Persian data. **No stock photos of
  smiling people at laptops**, no abstract 3D shapes, no illustration style that contradicts
  the product's seriousness
- **Type:** large display sizes, restrained weight contrast, generous line-height
- **Colour:** predominantly light with `--brand-900` used as a full-bleed anchor on one or two
  sections. Accent used once per page, at most
- **Motion:** subtle scroll reveals (opacity + 8px translate), nothing parallax, nothing that
  delays reading
- **Proof over adjectives:** every claim carries a mechanism or a number. "Save 20 hours a
  month" is worthless without *how*
- **Persian-first copy** that reads as though it was written in Persian — because it was.
  Translated marketing copy is instantly recognisable and undermines the entire local-fluency
  positioning

---

## M.12 What "done" looks like — the design quality checklist

Before any screen ships:

**Visual**
- [ ] Uses tokens only — no hardcoded hex, spacing, or radius values
- [ ] Icons from one family, consistent stroke weight, correct size token
- [ ] One primary action; secondary and tertiary correctly subordinated
- [ ] Elevation from the fixed ladder, not invented
- [ ] Tabular figures in every numeric column

**Both directions, both themes**
- [ ] Verified in RTL and LTR
- [ ] Verified in light and dark, with contrast checked independently in each
- [ ] Directional icons mirror; non-directional ones don't
- [ ] Persian text at 1.6+ line-height, no letter-spacing, no synthetic bold

**States**
- [ ] Loading skeleton matches the final layout (zero shift)
- [ ] Empty state explains and offers an action
- [ ] Error state states cause and fix
- [ ] Offline behaviour defined
- [ ] Disabled state distinguishable from read-only

**Interaction**
- [ ] Touch targets ≥44px (≥48px on teacher attendance)
- [ ] Full keyboard operation; visible focus everywhere
- [ ] Destructive actions confirmed or undoable
- [ ] Long operations async with progress

**Accessibility**
- [ ] Contrast passes AA in both themes
- [ ] Colour never the sole carrier of meaning
- [ ] Labelled controls, correct roles, live regions where needed
- [ ] Survives 200% zoom
- [ ] `prefers-reduced-motion` honoured

**Performance**
- [ ] Route chunk within budget
- [ ] Lists virtualised beyond 50 rows
- [ ] Images sized and modern-format
- [ ] Tested on a mid-range Android over throttled 4G — not only on a developer's laptop
