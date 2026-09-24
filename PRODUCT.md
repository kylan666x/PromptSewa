# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Buyers and creators carry equal weight (confirmed by the founder): the
experience must serve someone discovering, testing, and buying prompts just
as well as someone authoring, versioning, and selling them. Other confirmed
audiences from `prd.md`: teams/agencies centralizing an internal prompt
vault, moderators, and site admins. Guests can browse everything public.

## Product Purpose

PromptSewa is a premium prompt-library & marketplace: discover, test,
buy, sell, fork, and pull-request AI prompts — with git-like version
control for prompt content. Success means an active two-sided marketplace:
creators monetize tiered licenses, buyers get battle-tested prompts with a
built-in playground to try them.

## Positioning

Version control for AI prompts — fork/branch/pull-request semantics over
immutable prompt snapshots, plus a playground that runs prompts against
LLM APIs with the user's own encrypted keys. A generic marketplace clone
could not truthfully copy the git-like history/fork/PR model.

## Operating Context

- Deployed on **cheap shared cPanel hosting (Apache or LiteSpeed)** — this
  is a product constraint, not just an ops detail: everything must work
  without SSH, Docker, Redis, Supervisor, or server-side Node.
- Marketplace currency is **NPR (Nepalese rupees)**; payments via eSewa /
  manual proofs. Money is handled as integer paisa.
- Queues/sessions/cache all run on the `database` driver; one MySQL DB.
  Background work runs off a single 1-minute cPanel cron.

## Capabilities and Constraints

- Marketplace with tiered licensing (Personal vs Commercial), creator
  dashboards, wallet ledger, affiliate links.
- Prompt Playground: users test prompts against LLM APIs with their own
  API keys (encrypted at rest; never stored plaintext).
- Git-like versioning: append-only `prompt_versions`, forks, pending
  pull-requests to the original creator.
- Prompt types: `text | image | video` — type drives the authoring form,
  categories, and detail layout. Bodies use `{{variable}}` tokens buyers
  fill before use.
- Gamification: XP, badges, leaderboards, creator profiles.
- Community: forum, reviews, votes, curated blog.
- Search via Laravel Scout `database` driver (MySQL FULLTEXT in
  production, LIKE on SQLite locally); semantic/vector search is deferred
  to a later phase and must not be hacked into MySQL.
- Hard constraints (from root `AGENTS.md`, non-negotiable): no Docker,
  Redis, Memcached, Supervisor, or server-side Node; financial records are
  insert-only; grants only after verified payment; secrets encrypted via
  `Crypt::encryptString()`.
- Frontend is Blade + Tailwind + Alpine.js only — no SPA framework.
- **Undecided product facts:** eSewa production credentials; whether free
  tier exists beyond per-prompt pricing; forum scope for v1.

## Brand Commitments

- Name: **PromptSewa** (confirmed binding).
- Identity today: the in-repo look — amber-on-near-black palette, "PV"
  rounded badge mark, Tailwind-based components. No external logo, brand
  guide, or asset files exist yet.

## Evidence on Hand

- 26-prompt demo catalog (local seeder only, `core/database/seeders/DemoContentSeeder.php`)
  across 12 categories with version history — for evaluation, never
  seeded on production.
- Working storefront, prompt authoring, and version detail flows
  (UI-001/UI-002 shipped). Commerce core (orders, license grants, NPR
  paisa money) is implemented; wallet ledger and eSewa checkout are next.
- No real testimonials, customers, press, or benchmarks exist — none may
  be fabricated.

## Product Principles

1. **The prompt body is the product.** Version history, variables, and
   license-gated rendering are first-class, not chrome around a listing.
2. **Two-sided fairness.** Buyer trust (gated content, verified payment)
   and creator upside (80/20 split, analytics) are designed together.
3. **Cheap-host parity.** Every feature must work on a $3/mo cPanel box;
   if it needs a daemon, it gets redesigned.
4. **Money is sacred.** Integer paisa, insert-only ledgers, idempotent
   everywhere — an accounting bug is a launch blocker.

## Accessibility & Inclusion

**WCAG 2.1 AA is the required standard** (confirmed by the founder):
contrast ratios, full keyboard navigation, visible focus states, semantic
HTML, and accessible names on interactive elements are auditable
requirements, not aspirations.
