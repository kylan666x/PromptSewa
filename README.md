# PromptSewa

**Version control for your AI prompts.** A premium prompt-library &
marketplace: discover, test, buy, sell, fork, and pull-request AI prompts —
designed from day one to run on **cheap cPanel shared hosting**.

- 📦 **Marketplace** — buy/sell prompts (eSewa / manual payments), tiered licensing
- 🧪 **Playground** — test prompts against LLM APIs with your own encrypted keys
- 🌿 **Version control** — git-like history, forking, pull-requests for prompts
- 🏆 **Gamification** — XP, badges, leaderboards, creator profiles
- 🔎 **Search** — Laravel Scout `database` driver (MySQL FULLTEXT on shared
  hosting, `LIKE` on SQLite locally); Meilisearch is a config swap away later

## The cPanel Guarantee

| Concern | Solution |
| --- | --- |
| No Docker/Sail/Redis/Memcached/Supervisor | Enforced — see `AGENTS.md` |
| Sessions / Cache / Queue drivers | **`database`** (one MySQL DB does everything) |
| Background work | Single cPanel cron: `* * * * * php artisan schedule:run` |
| Document root | `public_html/` holds 3 tiny files; the app lives in `/home/user/core/` |
| Node.js on server | **Not required** — assets compiled locally, uploaded static |
| **Installing** | Upload one zip → open **`install.php`** in the browser — no SSH, no CLI |
| **Updating** | Upload new zip → `update.php` (token-protected), or full GitHub CI/CD via FTP |

## Current Status

- ✅ **Catalog core** — prompts/categories/versions schema, Scout-backed search
  abstraction, public-visibility contract (drafts/private never leak)
- ✅ **Commerce core (MKT-001)** — orders, order items, NPR paisa money,
  idempotent checkout, license grants with DB-level uniqueness
- ✅ **Storefront (UI-001)** — landing page, prompt library, category pages,
  auth, 22-prompt demo catalog across 12 categories
- ✅ **Prompt authoring & detail** — type-aware create/edit forms
  (text/image/video), git-like versioned edits, copy/variable-fill detail
  pages, license-gated prompt bodies
- ✅ **Public creator profiles** — `/creators/{user}` pages with published
  catalog, sales stats and bio; creator chips on cards/detail link to them
- ✅ **Admin brand management** — site name, tagline, contact/support
  emails, logo and favicon uploadable from Admin → Brand (`SettingsService`)
- 🔜 **Creator dashboard & admin shell (UI-003)** · wallet ledger (FIN-001) ·
  eSewa checkout (PAY-001) · delivery-proof uploads (PAY-002)

## Repository Layout

```
├── prd.md               Product Requirements Document
├── AGENTS.md            Rules for AI coding agents working in this repo
├── core/                The Laravel application (see core/AGENTS.md)
├── deploy/
│   ├── public_html/     The files that go into cPanel's document root,
│   │                    incl. the one-file install.php + update.php tools
│   └── build-release.php  Builds dist/promptsewa-upload.zip (vendor + assets)
├── .github/workflows/   CI (tests) + Release/Deploy (upload.zip artifact, optional FTP push)
└── docs/
    ├── ARCHITECTURE.md  Design decisions & data model
    ├── DEPLOYMENT.md    Step-by-step cPanel deployment runbook (web-installer first)
    └── CONTRIBUTING.md  Development setup & atomic-commit standards
```

## Quick Start (local development)

```bash
cd core
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate            # SQLite out of the box
npm install && npm run dev
php artisan serve              # http://127.0.0.1:8000
```

## Quick Start (production)

See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** — upload the release zip,
open `install.php`, done (no SSH needed). Updating = upload a new zip +
open `update.php`, or let GitHub Actions deploy on tags.

## Stack

PHP 8.2+ · Laravel 12 · MySQL/MariaDB · Blade · Tailwind CSS · Alpine.js ·
Laravel Scout (database driver) · database sessions/queue/cache ·
Apache `.htaccess` · cPanel cron

## License

Proprietary — all rights reserved. (Adjust before public release.)
