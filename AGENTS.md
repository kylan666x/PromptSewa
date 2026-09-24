# AGENTS.md — AI Agent Operating Guide

> **Scope:** This file governs ALL AI coding agents (Claude Code, Cursor, Copilot,
> Buffy/Codebuff, etc.) working in this repository. Read it fully before touching code.

## Project Identity

**PromptSewa** — a premium prompt-library & marketplace (discover, test, buy,
sell, version-control AI prompts) built for **cheap cPanel shared hosting**.
Full requirements live in `prd.md`; system design in `docs/ARCHITECTURE.md`.

## Prime Directive: cPanel Compatibility

Every change must run on a $3/mo shared host. **NEVER** introduce:

- Docker, Laravel Sail, Vagrant
- Redis, Memcached, or any cache/queue server
- Supervisor / daemons / `queue:work` without `--stop-when-empty`
- Server-side Node.js (assets are compiled locally, deployed as static files)
- Horizontal file writes (multiple docroots), Meilisearch, Elasticsearch
- Any driver from `config/queue.php` or `config/cache.php` other than `database` / `file`

**Allowed stack:** PHP 8.2+, Laravel 12 (same skeleton as 11), MySQL/MariaDB,
Blade + Tailwind (compiled) + Alpine.js, database sessions/queue/cache,
`storage/app/public` or Backblaze B2 for media.

## Design System Rule (UI work)

**Read `DESIGN.md` in the project root before changing ANY UI** (Blade views,
Tailwind styling, components, or frontend copy). It defines the two-world
visual system (paper catalog / dark app), the saffron token, and the
dark-block component language. `core/resources/css/app.css` is the single
Tailwind v4 entry point — all tokens live in its `@theme` block. If DESIGN.md
and the code disagree, fix the discrepancy with the founder before styling.

## Repository Layout

```
/                   repo root — docs, deployment assets, CI
├── prd.md          product requirements (source of truth for features)
├── core/           the Laravel application (whole app lives here)
├── deploy/         cPanel upload assets
│   └── public_html/.htaccess + index.php + .user.ini
└── docs/           architecture, deployment, runbooks
```

The app is developed in `core/` and deployed such that cPanel's docroot
(`public_html/`) contains only the two-ish files from `deploy/public_html/`,
with the full app at `/home/USER/core/`. See `docs/DEPLOYMENT.md`.

## Ground Rules for Agents

1. **Read before writing.** Check `docs/ARCHITECTURE.md` for the design
   decisions behind migrations, drivers, and the deployment layout.
2. **Migrations are append-only history.** Never edit an already-committed
   migration; add a new one. Keep `up()`/`down()` symmetric.
3. **Schema changes** require: migration → model update → factory update →
   feature test. All four or none.
4. **Never commit** `.env`, `vendor/`, `node_modules/`, `public/build/`,
   `public/storage` symlink, or `storage/` runtime contents. `.env.example`
   is the canonical template — update it when adding config.
5. **Tests are mandatory.** `php artisan test` (SQLite in-memory, the same
   `database` queue/session drivers as production) must pass before any
   commit. New features ship with feature tests.
6. **Commit style:** Conventional Commits, atomic, one logical change per
   commit. See `docs/CONTRIBUTING.md`.
7. **Frontend:** Blade templates + Alpine.js for interactivity. Tailwind
   classes only (no custom CSS unless truly unavoidable — the `@theme` tokens
   and the marquee keyframes in `app.css` are the sanctioned exceptions).
   **Read `DESIGN.md` first for any UI change.** Build assets
   locally with `npm run build`; commit source, never `public/build/`.
   **Frontend is Blade + Tailwind + Alpine.js only — never React, Inertia,
   Vue SPA, Next.js or server-side Node rendering** (PAY-002 ruling).
   **Read `DESIGN.md` before any UI work** and follow its tokens
   (`paper`/`ink`/`saffron`/`creak`, Space Grotesk + JetBrains Mono) —
   do not invent off-palette colors or reintroduce the retired dark
   zinc/amber theme. Brand assets (logo, favicon, site name, contact
   emails) are admin-managed via Admin → Brand (`SettingsService`).
8. **Queued work** must be idempotent and tolerate cron-driven execution
   (jobs may run up to a minute late; assume retries).
9. **Secrets** (LLM API keys, payment credentials) are always
   `Crypt::encryptString()`-ed before storage. Never log them.
10. **Ask before:** touching the deployment files in `deploy/`, changing
    `composer.json` constraints, or altering the release process.
11. **Do not commit and push without explicit permission**; commit and
    push is allowed when the founder explicitly asks for it.
12. **Public UI surfaces never show deployment/infrastructure text**
    (cPanel, runbooks, architecture notes, env explanations). That
    content belongs in `docs/` — the marketplace is the product.
13. **Prompt bodies are the product** (post-UI-001 GoP research ruling).
    Creators pick a prompt **type** (`text|image|video`) and the forms,
    placeholders, and category options adapt to it. Bodies use
    `{{variable}}` tokens that buyers fill in before use. Version rows are
    append-only (never mutate an existing `prompt_versions` row), and the
    full body of a paid prompt renders only for the owner, moderators,
    and active license holders — everyone else gets a locked teaser.

## Financial Invariants (Sprint 2+, NON-NEGOTIABLE)

The marketplace handles real money in **NPR**. Violating any of these is an
automatic PR rejection:

1. **Integer paisa only.** 1 NPR = 100 paisa. All monetary columns are
   `*_paisa` BIGINT. `float`/`decimal` math on money is forbidden; use plain
   integer arithmetic (bcmath only when dividing, with explicit scale).
2. **Ledger is insert-only.** `wallet_transactions` rows are immutable:
   no `UPDATE`, no `DELETE`, ever. Balances are the SUM of the ledger; any
   cached `balance_paisa` column is updated strictly inside the same
   `DB::transaction` that appends the row, under `lockForUpdate()`.
3. **Idempotency everywhere.** Checkout, ledger writes, and webhook
   processing take an idempotency key with a UNIQUE constraint. Duplicate
   requests must return the existing record — never duplicate money or grants.
4. **Grants follow payments, never precede them.** Entitlements are created
   only after a webhook/verified status check confirms payment. The frontend
   "success" redirect is NEVER sufficient.
5. **No deletes on financial records.** Orders, payments, proofs, grants,
   ledger rows: state changes only (`refunded`, `rejected`, `revoked`).
6. **Abstraction gates (Sprint 1 verdict):** search goes through Laravel
   Scout (`Prompt::search()`), storage through `Storage::disk()` facades,
   jobs must be serializable/idempotent so a Redis/Horizon swap needs zero
   code changes. Do not hack vector math into MySQL — it lands in Phase 4
   on Postgres/external API. `SCOUT_DRIVER=database` is the committed
   default (LIKE on SQLite, MySQL FULLTEXT in production) — change it in
   env/config only, never by writing raw `MATCH()`/`LIKE` in app code.
