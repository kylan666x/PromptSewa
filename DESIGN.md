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
- **Frame outside the circle (v1.7.4 ruling — SUPERSEDED by the v1.7.5 composite-box ruling below; kept for history): the PHOTO is circular-clipped by an inner clipper; the FRAME protrudes around it.** `x-user-avatar` was three layers: (1) the wrapper is `relative inline-block isolate` plus the caller's size and **never** `overflow-hidden`; (2) an inner clipper `<span class="block size-full overflow-hidden rounded-full">` wraps the photo / initials badge, and it is the ONLY place the circle is created; (3) the frame art is `pointer-events-none absolute z-10 object-contain` pulled outward by a size-keyed negative inset — **xs/sm `-inset-[8%]`, md/lg `-inset-[12%]`**. v1.7.5 deleted the negative inset: nothing protrudes any more — do not reintroduce it, and do not reintroduce v1.7.2's un-isolated `-inset-1` ring or v1.7.3-hotfix's `inset-0`-inside-clip.
- **THE COMPOSITE BOX (v1.7.5 ruling — supersedes v1.7.4 G1 and the v1.7.3-hotfix geometry): the wrapper IS the frame art's canvas, and every layer is inset against that ONE box.** Three layers, no exceptions: (1) **WRAPPER** — `relative inline-block isolate` + a size class that comes from the `size` prop (`xs/sm/md/lg/xl`) and NOTHING else: no rounding, no background, no border, no overflow, and **no caller-supplied geometry**; (2) **FRAME** — `pointer-events-none absolute inset-0 z-10 size-full object-contain`, rendered only when a frame is equipped, filling the box exactly; (3) **PHOTO/BADGE** — `absolute overflow-hidden rounded-full` at `style="inset:{(100−hole)/2}%"` when framed and `inset-0` when frameless, with `size-full object-cover` inside and the initials badge filling the same box.
  - **Why (the frame-misalignment saga, 4th geometry ruling):** the v1.7.4 hero merged `size-24 rounded-3xl border-4 border-paper bg-saffron` onto the wrapper, so the box the photo resolved against (the padding box) was NOT the box a reader saw (the bordered saffron squircle), and the overlay's four over-constraining insets gave a replaced `<img>` a non-square 88×109 box that `object-contain` letterboxed — three coordinate systems, ring and photo hanging off the top-left. One box removes the possibility.
  - **Callers may never inject geometry:** any `<x-user-avatar>` tag carrying `rounded-*`, `size-*`, `bg-*`, `border*` or `overflow-*` fails the suite (`tests/Arch/UserAvatarGeometryTest`). Sizes flow through the prop only; the hero uses `size="xl"`. The component additionally strips those tokens before merging, so the ban is belt-and-braces rather than the only defence.
  - **Nothing protrudes, so the v1.7.4 ancestor-clip contract is no longer load-bearing for frames.** Card roots, the navbar pill and admin panels stay un-clipped anyway (harmless, and the cover element still needs its own clip for full-bleed) — keep it that way as a trap-free default, not as frame insurance.
- **Frame art spec (v1.7.5): 512×512 alpha PNG, ring may run to the canvas edges, transparent centre hole 35–70% of the canvas — 62% is the shipped standard, and each frame stores its own `hole_percent`.** The photo is inset to fill that hole exactly, so art with a different hole is a data change, not a code change: measure the hole (largest fully transparent disc) and set it in Admin → Frames ("Centre hole %", bounds `Frame::HOLE_MIN/MAX`). The founder's *Abyssal* ring measures **37.5%** (192px disc at 258,254) and is stored at 38. Do not upload art whose hole falls outside the bounds — the model refuses it rather than rendering a ring that cannot line up.
- **Per-frame hole data (v1.7.5):** `frames.hole_percent` (tinyint unsigned, default 62, backfilled). The photo inset is emitted as an inline style because the exact percent is dynamic — no arbitrary Tailwind class can exist per-frame at build time. The bounds live in ONE place (`Frame::HOLE_MIN/HOLE_MAX/HOLE_DEFAULT`); the admin form renders its `min`/`max`/`value` from those constants and the model throws on an out-of-range save.
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
