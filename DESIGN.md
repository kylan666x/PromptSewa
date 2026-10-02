# DESIGN.md — PromptSewa visual system

> **Agents: read this file before ANY UI work** (new pages, components, copy
> surfaces, or restyles). It is the durable design contract for this repo.

## Direction

**"Working manuscript on a maker's desk."** A light, warm-paper marketplace —
the browsing surface — with **ink-dark product blocks** floating on it, and one
saffron accent that always means *act now*. Inspired by the craft level of
godofprompt.ai; **recreated from PromptSewa's own product truth — never a
copy** of GoP content, illustrations, mascot, or copy.

Two worlds, one system:

| Surface | World | Why |
| --- | --- | --- |
| Marketing + catalog (home, library, packs, prompt detail, about, auth) | **Light paper** — browsing happens on a desk in daylight | Persuade/Discover mode |
| Authenticated app (dashboard, purchases, checkout, admin, authoring) | **Dark ink canvas** — the workbench | Operate mode; inherits the legacy near-black look |

The dark pill navbar and dark footer sit on both worlds and stitch them together.

## Tokens (Tailwind v4 `@theme` in `core/resources/css/app.css`)

- `paper` `#f4f2ec` — page ground (light world). Deep variant `paper-deep` `#eae7dd`.
- `ink` `#17150f` — near-black: dark cards, navbar/footer ground, light-world text. Soft variant `ink-soft` `#211e15` for raised dark panels.
- `saffron` `#ffd43b` — THE accent. Primary CTA fill only (`text-ink` on top). Hover `saffron-deep` `#f0b429`. Never body text, never decoration.
- `official` `#1d9bf0` — the official-account badge circle (white check on top). Founder-sanctioned as the single new token addition (v1.5.0): **official blue circle badge is founder-sanctioned; saffron seal remains the verified badge.** Used ONLY inside the badge SVG — never as a UI accent.
- **Bottom dock (v1.5.0, T12)**: `x-mobile-dock` — ink ground (`ink`), exactly five slots, and the CENTER CTA carries the only saffron fill; tab states use text color only (saffron text = active). ≥44px targets, aria-labels, visible focus rings, safe-area padding. Mobile navigation is the dock — never reintroduce a burger drawer. Error/maintenance layouts are exempt.
- Semantic: emerald = owned/free/success, rose = destructive, sky = informational/manual payment. Keep zinc-* utilities inside the dark world.
- **Rose heart fill = saved state (founder-sanctioned, v1.7.1)** — the one exception to "rose = destructive": a bookmark heart that is filled rose (`text-rose-600 fill-current`, `aria-pressed="true"`) always means *saved*; outline (`ink/40` on cards, `ink/70` on the detail page) means unsaved. Rose remains destructive everywhere else.
- Legacy amber-500 gradients are retired for new UI; saffron replaces them.
- **Hero avatar geometry (v1.7.2 addendum — SUPERSEDED by the v1.7.3-hotfix ruling below; kept for history): the sanctioned shape was the SQUIRCLE + FRAME RING.** The v1.7.2 line sanctioned `rounded-3xl` (squircle) on the creator-profile hero with the frame drawn AROUND it (the `-inset-1` overlay). The post-deploy hotfix reversed this: see the circle-everywhere ruling — do not reintroduce the squircle hero or the `-inset-1` ring.
- **Frame surface parity (v1.7.3 addendum): frames render on EVERY avatar surface.** This REVERSES the v1.7.0 "cards stay clean" ruling — prompt cards, image-gallery cards, library creators grid, versions author rows, purchases rows, admin users tables and typeahead rows all carry the creator's frame when equipped (xs–lg all support the overlay; always `pointer-events-none` + `aria-hidden`). Frame animation classes (`frame-anim-spin|pulse|shine`) are sanctioned decorative motion — keyframes MUST stay gated behind `@media (prefers-reduced-motion: no-preference)`.
- **Circle-everywhere + picture-inside-frame (v1.7.3-hotfix ruling — SUPERSEDED by the v1.7.4 ruling below; kept for history): the avatar is ALWAYS a circle and the frame is a picture INSIDE that circle.** `x-user-avatar` hard-forced `rounded-full overflow-hidden` on the wrapper with an `inset-0` overlay over it. v1.7.4 reversed the inset-0 "inside-clip" — do not reintroduce it, and do not reintroduce v1.7.2's un-isolated `-inset-1` ring.
- **Frame outside the circle (v1.7.4 ruling): the PHOTO is circular-clipped by an inner clipper; the FRAME protrudes around it.** `x-user-avatar` is three layers: (1) the wrapper is `relative inline-block isolate` plus the caller's size and **never** `overflow-hidden` — it owns the stacking context only, because clipping it would eat the ornament; (2) an inner clipper `<span class="block size-full overflow-hidden rounded-full">` wraps the photo / initials badge, and it is the ONLY place the circle is created; (3) the frame art is `pointer-events-none absolute z-10 object-contain` pulled outward by a size-keyed negative inset — **xs/sm `-inset-[8%]`, md/lg `-inset-[12%]`** (a 12% ring would swallow a 20px avatar). The intended look is a ring kissing the photo's outer annulus, NOT a picture floating in the middle of a circle.
- **Frame art spec (v1.7.4): 512×512 PNG, ring may run to the canvas edges, transparent centre hole 55–70%.** The ring overlapping the photo's outer annulus is the point — do not upload "inner-clip" art (a frame drawn as a filled square with a small circular hole) because the protruding layout renders it at a different scale than intended.
- **Ancestor clip contract (v1.7.4): no ancestor of a framed avatar may carry `overflow-hidden`.** Card roots that clipped for cover bleed must move the clip onto the cover element itself; tight wrappers (the navbar account pill, table panels) must drop the clip. A clipping ancestor silently truncates the ornament — there is no error. Locked by rendered-HTML assertions, never by reading a Blade file.
- **Animated frames + reduced motion (unchanged since v1.7.3).** `frame-anim-spin|pulse|shine` are the only sanctioned motion and their keyframes MUST stay gated behind `@media (prefers-reduced-motion: no-preference)`.

## Typography

- Display + UI: **Space Grotesk** (`font-display`), loaded via Google Fonts CDN
  (self-host when brand assets are procured). Tight tracking (-0.02 to -0.04em),
  bold, sentence case. *Italic* marks the emphasized phrase in hero headings.
- Mono: **IBM Plex Mono** (`font-mono`) — version labels, chips, prompt bodies,
  small eyebrow rows. Mono is for code/data/labels only, never body copy.
- Body: Inter via the same CDN request.

## Components

- **Navbar:** floating dark pill (`rounded-full bg-ink text-paper`), sticky,
  saffron logo mark or uploaded brand logo, mono section links, saffron "Sign up" pill.
- **CTA pills:** fully rounded; primary `bg-saffron text-ink`, secondary
  `border-ink/15 bg-white text-ink` (light world) / `border-white/10 bg-white/5` (dark world).
- **Dark blocks:** big rounded cards (`rounded-3xl bg-ink text-paper`), used for
  statement sections and feature grids on the paper ground. Subtle starfield or
  radial glow allowed inside them; no glass/blur decoration.
- **Chips:** mono, small, bordered (`border-ink/10 bg-white` on light,
  `border-white/10 bg-white/5` on dark). Used for categories, tags, stats, roadmap status.
- **Prompt cards:** white paper cards with `border-ink/10`, ink text, saffron
  price chip, creator row with verified-badge tick when the creator holds a badge.
- **Empty state:** dark dashed block — it doubles as a GoP-style dark card on light pages.

## Interaction & motion

- One marquee moment (tool-logo strip, CSS `@keyframes marquee`); everything
  else is hover lifts (`-translate-y-0.5`), 150–200ms ease transitions, and
  Alpine dropdowns. No entrance-animation spam.
- Focus: `focus-visible:ring-2 ring-saffron/70` (WCAG 2.1 AA is binding —
  contrast ≥4.5:1 body text; ink on paper and paper on ink both pass; saffron
  never carries small text on paper).

## Voice & content rules

- PromptVellum's own copy only. Never reproduce GoP headlines, mascot, or
  testimonials. **No fabricated claims**: stats come from the database,
  unbuilt features get honest "Coming soon" / "On the roadmap" chips.
- Currency renders as NPR paisa (integer) — never floats.

## Conflict policy

The legacy zinc-dark look **remains valid inside the dark world** (dashboard,
admin, checkout). When touching a dark-world page, keep its existing zinc
palette; do not half-migrate it to paper. When creating a public/catalog page,
use the paper world. Mixed pages are a defect — pick the world the route
belongs to (see the table above).
