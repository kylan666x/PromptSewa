# DESIGN.md — PromptVellum visual system

> **Agents: read this file before ANY UI work** (new pages, components, copy
> surfaces, or restyles). It is the durable design contract for this repo.

## Direction

**"Working manuscript on a maker's desk."** A light, warm-paper marketplace —
the browsing surface — with **ink-dark product blocks** floating on it, and one
saffron accent that always means *act now*. Inspired by the craft level of
godofprompt.ai; **recreated from PromptVellum's own product truth — never a
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
- Semantic: emerald = owned/free/success, rose = destructive, sky = informational/manual payment. Keep zinc-* utilities inside the dark world.
- Legacy amber-500 gradients are retired for new UI; saffron replaces them.

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
