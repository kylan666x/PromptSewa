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

### S4 (v1.9.0) — the Facebook/Instagram pass

Founder-authorised restyle of the **light world** (catalog + marketing) toward a
feed aesthetic. The dark world (dashboard, admin, checkout) keeps its palette;
only shared tokens and the public surfaces move.

- **Cards are `rounded-2xl`** with a quiet resting shadow (`shadow-card`) and a
  clear lift on hover (`hover:shadow-card-hover`). `rounded-3xl` is now reserved
  for **dark statement blocks** (`bg-ink`) — do not spend it on content cards.
- **Rhythm tightens where it was loose** (card grids step down one gap step:
  `gap-6`/`gap-5` → `gap-4`/`gap-3`) and **opens up between sections**
  (`pt-20` on the storefront bands).
- **Feed card (Library `/prompts`)**: the card reads top-to-bottom like a social
  post — creator avatar + handle header, artwork, title/copy, then the Sikka
  price chip anchored **bottom-right**. The saved heart floats top-right over
  the header, which keeps `pr-14` clear for it. Everything else about the card
  (composite-box avatar geometry, no clipped root, rose heart) is unchanged.
- **Prompt detail** is a two-column shell on desktop
  (`lg:grid-cols-[minmax(0,1fr)_360px]`, `min-w-0` left column, sticky right
  rail) and stacks below `lg`.
- **Dashboard** tabs are a segmented settings-style rail (no per-pill border);
  the active tab keeps the ink fill for contrast. Stats are cards, two-up on
  phones.

### P1 (v1.9.1) — the true newsfeed

Founder directive: the two FEED surfaces render as a real vertical newsfeed,
not a catalog grid.

- **`/prompts` (library) and the homepage "Fresh from the library" block are a
  single column at EVERY breakpoint** — `mx-auto grid w-full max-w-3xl
  grid-cols-1 gap-6`, centered on the paper ground. No `sm:grid-cols-2`, no
  `xl:grid-cols-3`, on a feed surface at any width.
- **The homepage carousel/grid dual mode is retired.** That section no longer
  carries a snap rail, a horizontal scroll or `w-[82%]` slides — it is vertical
  scroll only, the same container as the library feed.
- **Feed artwork takes the wide crop.** `x-prompt-card` gained an opt-in
  `large` prop forwarded to `x-prompt-cover`, which moves the cover from the
  plain 16:10 card crop to **16:9** (and steps the generated-banner type up).
  Only the two feed surfaces pass it; every other card keeps 16:10.
- **The feed closes with a pagination door** ("Load more prompts" →
  `/prompts`). Nothing is appended in place — an honest link, never a fake
  infinite scroll.
- The card's internal reading order (identity → artwork → copy → price chip),
  the `rounded-2xl` root and every S4 token are unchanged. The multi-column
  grids that remain elsewhere (image gallery, category tiles, matching
  creators, packs, membership plans) are deliberate: those are walls of small
  tiles, not the feed.

### F1/F2/F3 (v1.9.2) — sales truth, the profile menu and the social replica

- **F1 — one sales truth (data, not paint).** A sale is a **paid order line
  naming a listing**, on either rail. The legacy `prompts.sales_count` column
  still exists but is **dead data** — nothing writes it, and NOTHING may read
  it (it is what made "0 sales" possible after real sales). Read sales through
  `OrderItem::paidSalesCountForCreator()` / `paidSalesCountForPrompt()` or
  `withCount('paidSales')`.
- **F2 — the lightbox is a real modal.** Full-viewport ink scrim with
  `backdrop-blur-sm` (**founder-sanctioned exception to the "no glass/blur"
  rule**, and only here), a `max-w-screen-md` content box, artwork capped at
  `max-h-[80vh]` with `object-contain`, and a visible Close control pinned to
  the modal's **top-right**. The avatar menus (hero + navbar) both carry a
  **My Profile** link to `/creators/{username}`.
- **F3 — the feed is a social replica, not a catalog grid.** One component,
  two post shapes:
  - **IMAGE / VIDEO listings → the Instagram post.** Identity header (`p-3`),
    **edge-to-edge artwork** (`x-prompt-cover feed`: square on phones, 4:5 from
    `md`, ink/5 ground), caption (`p-4`), then the action bar.
  - **TEXT / AGENTIC / SKILL listings → the Facebook text post.** Identity
    header (`p-4`), the copy as the post body (`whitespace-pre-wrap
    text-base leading-relaxed`, split at 500 characters behind **See more**),
    then the action bar. **No artwork** — the copy IS the post.
  - **Card shell:** `rounded-xl border border-ink/5 bg-paper shadow-sm`. The
    older `rounded-2xl bg-white` shell survives ONLY on the tile walls
    (image gallery, category/pack/plan tiles) — the feed is its own shape.
  - **Action bar** (both shapes): `border-t border-ink/5 px-4 py-2`, Like ·
    Comment on the left, Share · Save on the right. **Share** is real (Web
    Share API → clipboard → honest toast). **Save** is the bookmark heart (the
    H4 contract, unchanged). **Like/Comment are honest stubs** — no likes
    backend exists, so they are labelled spans that say "coming soon" on
    hover, never dead buttons.
  - The Sikka price chip stays on every post (the one buy signal); NPR never
    appears on a feed card.

## Tokens (Tailwind v4 `@theme` in `core/resources/css/app.css`)

- `paper` `#faf8f3` — page ground (light world). Deep variant `paper-deep` `#f0eee6`.
  **S4 (v1.9.0): warmed from `#fafaf7` to the founder-authorised `#faf8f3`.**
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

- Headings: **Space Grotesk** (`font-display`), loaded via Google Fonts CDN
  (self-host when brand assets are procured). Tight tracking (-0.02 to -0.04em),
  bold, sentence case. *Italic* marks the emphasized phrase in hero headings.
  **S4 (v1.9.0): every `h1`–`h4` defaults to this face** (a `@layer base` rule
  in `app.css`); utilities still override it.
- Mono: **JetBrains Mono** (`font-mono`) — the face the Google Fonts request and
  `app.css` actually load; version labels, chips, prompt bodies,
  small eyebrow rows. Mono is for code/data/labels only, never body copy.
- Body: **Inter** (`font-sans`) via the same CDN request, at the 16px browser
  baseline. S4 (v1.9.0) — Inter carries body copy, Space Grotesk carries
  headings.

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
- **Prompt cards (F3 v1.9.2 social post — supersedes the S4/P1 card):**
  `rounded-xl border border-ink/5 bg-paper shadow-sm`, hover lift
  (`hover:-translate-y-0.5` + `shadow-card-hover`). Reading order: identity
  header → artwork (image/video posts only) → copy/caption → Sikka price chip
  → action bar. The two feed surfaces render them **full-width in a centered
  `max-w-3xl` single column** (P1 v1.9.1, still binding).
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
- Currency renders as NPR paisa (integer) — never floats; `money_npr` is the
  sole NPR renderer.
- **Sikka is a credit, not a currency (v1.8.0).** The copy says "Sikka
  credits" — never "Sikka currency"; a cash-out is labeled "withdraw
  earnings". Amounts are integers with no decimal point, rendered ONLY
  through `<x-sikka>` (`SikkaFormat::render()`); no ₨/Rs/$ glyph ever sits
  inside the icon or the price chip.

## Sikka unit mark — v1.8.0 addendum

- **The `{{ }}` mint mark is Sikka's identity.** It is the ONE sanctioned
  mark for the credit and `<x-sikka>` is its only renderer. First mention
  per page pairs the mark with the word "Sikka" (`:word="true"`); later
  mentions may be mark + number. App surfaces render the color mark at
  ≥ 20px (it reads on both worlds); mail headers and ink/plain-text
  fallbacks use the mono variant.
- **Brand asset, not code — ships by upload.** Admin → Brand → "Sikka unit
  mark" writes `sikka-icon-path` / `sikka-icon-mono-path` via
  SettingsService. The founder's approved art is uploaded on each install;
  it is never bundled inside a release zip, and nobody should hunt for a
  binary in the artifact. Ingest is the `sikka` variant of
  `ImageUploadService`: PNG/WebP only, alpha preserved (blending OFF +
  save-alpha ON), downscaled to ≤512px, JPEG refused. With no mark
  uploaded, `<x-sikka>` falls back to an honest mono bordered "Sikka"
  chip — zero broken images.
- **Do-not list:** no recoloring, no stretching (square box,
  `object-contain`), no fiat glyphs, no glow/shadow decoration, and never
  as a UI accent — it is a unit mark, not a button. No new color token:
  the mint is the art's own pigment, not a theme token.
- **Sanctioned motion: none on the icon itself.** The frame
  `frame-anim-spin|pulse|shine` keyframes remain the only decorative
  motion in the system (still gated behind `prefers-reduced-motion`).

## Conflict policy

The legacy zinc-dark look **remains valid inside the dark world** (dashboard,
admin, checkout). When touching a dark-world page, keep its existing zinc
palette; do not half-migrate it to paper. When creating a public/catalog page,
use the paper world. Mixed pages are a defect — pick the world the route
belongs to (see the table above).
