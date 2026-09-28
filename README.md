<div align="center">

<img src="https://img.shields.io/badge/🧵_PromptSewa-v1.3.0-F5C518?style=for-the-badge&labelColor=171715" alt="PromptSewa"/>

# PromptSewa

### *Version control for your AI prompts*

**Discover · Test · Buy · Sell** battle-tested AI prompts — with git-like version history, licensing, and eSewa payments built in. Built for Nepal, priced in NPR.

[![Laravel](https://img.shields.io/badge/Laravel_12-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP_8.2+-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Alpine.js](https://img.shields.io/badge/Alpine.js-3-77C1CB?style=flat-square&logo=alpinedotjs&logoColor=white)](https://alpinejs.dev)
[![Tests](https://img.shields.io/badge/Tests-102_passing-16A34A?style=flat-square)](#testing)
[![License](https://img.shields.io/badge/License-MIT-F5C518?style=flat-square)](LICENSE)

[Features](#-features) · [Quick Start](#-quick-start) · [Deployment](#-cpanel-deployment) · [Architecture](#-architecture) · [Docs](#-documentation)

</div>

---

## 📖 What is PromptSewa?

A full prompt marketplace — think **GitHub meets Gumroad for AI prompts**:

- **Creators** publish versioned prompts (text / image / video types) with changelogs, tags, recommended tools and usage tips
- **Buyers** purchase with eSewa (or manual payment verification) and receive lifetime license grants — every future version included
- **Admins** moderate the review queue, issue verified badges, manage packs, brands and payment gateways

Every prompt is *versioned like git*: edits append immutable `prompt_versions` rows with changelogs, never overwrite.

---

## ✨ Features

### 🛍 Marketplace
| | |
|---|---|
| 🔍 **Unified search** | Prompts **and creators** in one search bar (Scout-backed, never raw LIKE) |
| 🖼 **Image gallery** | PromptPlum-style visual wall — covers full-bleed, prompt snippet visible on the card, one-click copy for free prompts |
| 🎨 **Generated cover banners** | Every text prompt gets a beautiful SVG title banner (its own name typeset on a deterministic brand gradient) — no upload needed |
| 📦 **Packs** | Bundle prompts at a bundle price |
| ⭐ **Ratings** | 1–5 stars — free prompts rateable by any logged-in user, paid prompts only by verified buyers |

### 👤 Creators
| | |
|---|---|
| ✅ **Verified badges** | Saffron seal, site-wide, issued by admins only |
| 🖼 **Profile uploads** | Avatar + cover banner with automatic GD compression & re-encode |
| 📝 **Full profile editing** | Facebook-style: identity, bio, photos — live preview link |
| 💰 **NPR pricing** | Free → Rs. 50,000, paisa-accurate integer math, automatic product sync |

### 🛡 Trust & safety
| | |
|---|---|
| 🔒 **Paywall integrity** | Paid prompt bodies render ONLY for owners and buyers — **staff included**. Moderation preview works via admin panel link only |
| 🚩 **Reports** | One-click prompt reporting with admin triage queue |
| 🪪 **RBAC** | member → creator → moderator → admin hierarchy, self-demotion guard |

### 🧑‍💼 Admin panel
Review queue (publish/reject) · Users & roles · Verified badge issuance · Orders & manual payment verification · Packs CRUD · Payment gateway settings (eSewa credentials encrypted at rest) · Brand settings (logo, favicon, site name) · AI tool logos · **One-click self-update from a zip**

### 🏠 Pages & polish
Branded error pages (403/404/419/429/500/503) · Mobile-first navbar with hamburger drawer · Game-feel buttons (arcade press) · X/Facebook-style creator profiles with cover banners

---

## 🚀 Quick Start

```bash
git clone https://github.com/kylan666x/PromptSewa.git
cd PromptSewa/core

composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
touch database/database.sqlite

php artisan migrate --seed     # seeds 269 demo prompts + users
php artisan storage:link
php artisan serve              # → http://127.0.0.1:8000
```

**Demo logins** (after seeding):

| Role | Email | Password |
|---|---|---|
| Admin | `admin@promptsewa.test` | `password` |
| Flagship creator | `justshipitai@gmail.com` | `JustShipIt!2026` |
| Creators | `bibek@` / `maya@` / `dorje@promptsewa.test` | `password` |

> The seeders are **idempotent** — safe to run repeatedly; existing data is never touched.

---

## 🖥 CPanel Deployment

PromptSewa is engineered for **$3/mo shared cPanel hosting** — the prime directive:

> No SSH, no Node, no Composer, no Redis, no Supervisor on the host. Apache *and* LiteSpeed. `exec()` assumed disabled.

```
/home/CPANELUSER/
├── core/           ← Laravel app (vendor/, .env, storage/) — NOT web-visible
└── public_html/    ← index.php, .htaccess, install.php, update.php
```

**First install:** upload `dist/promptsewa-*-install.zip` → extract → create MySQL DB + user (ALL PRIVILEGES) → visit `install.php`.

**Updates:** build a code-only zip (`php deploy/build-update-zip.php`) — it contains `core/` **plus a `public_html/` allow-list** (`update.php`, `index.php`, `.htaccess`, `.user.ini`, so docroot fixes self-deliver; never `install.php` or `.update-token`) — then either:
- upload it at `https://<domain>/update.php` (token-protected), or
- upload through **Admin → Update** (`/admin/update`)

The pipeline: maintenance mode → extract → migrate → idempotent seed → caches → asset sync → live. Your `.env`, database and token are preserved.

---

## 🏗 Architecture

```
core/
├── app/
│   ├── Console/Commands/UpdateFromRelease.php   ← pv:update pipeline
│   ├── Http/Controllers/                        ← Storefront, Dashboard, Admin
│   ├── Http/Requests/PromptFormRequest.php      ← shared create/edit validation
│   ├── Models/                                  ← Prompt, PromptVersion, User, Rating…
│   └── Services/
│       ├── CheckoutService.php                  ← eSewa + manual payments
│       ├── EntitlementService.php               ← license grants
│       ├── ImageUploadService.php               ← GD compress & re-encode
│       └── PromptSearchService.php              ← Scout search + creators
├── database/seeders/                            ← idempotent demo + bulk catalog
└── resources/views/                             ← Blade + Alpine components
deploy/
├── public_html/                                 ← docroot + installer + updater
├── build-release.php                            ← full install zip
└── build-update-zip.php                         ← code-only update zip
```

**Key invariants** (break these and something important breaks):

1. `prompt_versions` is append-only — edits create new rows, never mutate
2. Paid bodies render only with an active `LicenseGrant` — staff included
3. `@js()` inside `x-data` always uses **double-quoted** attributes (single quotes truncate)
4. No `use` statements inside Blade `@php` blocks of anonymous components
5. All uploads pass through `ImageUploadService` (GD re-encode strips metadata & downscales)
6. PUT forms carry `@method('PUT')` next to `@csrf` — a missing spoof directive turns every submit into a 405
7. `core/public/index.php` (local `artisan serve` front controller) and `deploy/public_html/index.php` (cPanel docroot variant) look similar but are **not** interchangeable

---

## 📚 Documentation

| Doc | Contents |
|---|---|
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | System design deep-dive |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | cPanel deployment, end to end |
| [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md) | Dev workflow & conventions |
| [`DESIGN.md`](DESIGN.md) | Visual design system (ink · saffron · paper) |
| [`PRODUCT.md`](PRODUCT.md) | Product spec & roadmap |
| [`handoff.md`](handoff.md) | **Engineering handoff for the next maintainer** |

---

## 🧪 Testing

```bash
cd core
php artisan test        # Pest — 102 tests, 416 assertions, all green
```

Covers: storefront rendering, search, category pages, checkout flow (eSewa + manual), entitlements, prompt visibility, reports, brand settings, seeder integrity.

---

## 🤝 Contributing

PRs welcome — run the test suite first, follow the existing Blade/PHP conventions (they're commented in-place), and keep the cPanel prime directive in mind: everything must run on the cheapest shared hosting on earth.

---

<div align="center">

**PromptSewa** — *Built in Nepal 🇳🇵 for the prompt economy.*

[⬆ back to top](#promptsewa)

</div>
