# PromptSewa — Engineering Handoff

**Prepared:** 2026-09-28
**Live site:** https://promptsewa.techadda.com.np
**Repo:** https://github.com/kylan666x/PromptSewa (branch `main`)
**Stack:** Laravel 12 · PHP 8.2+ · Blade + Alpine.js 3 · Tailwind CSS (Vite build) · SQLite (dev) / MySQL (prod, cPanel)

---

## 1. What PromptSewa is

A prompt marketplace for the Nepali market (prices in NPR, eSewa payments): creators publish versioned AI prompts (text / image / video types), buyers purchase and re-download, admins moderate everything. Think "git for prompts" — every prompt has version history with changelogs, tags, recommended tools and usage tips.

Core domain models: `User` (roles: member < creator < moderator < admin, plus `is_verified`), `Prompt` (status: draft/pending/published/rejected, visibility: public/private, `price_cents`, `type`), `PromptVersion` (immutable history; latest is live), `Product` (sellable, price in paisa), `Order` + `OrderItem` + `LicenseGrant` (entitlements after payment), `Pack` (bundles), `Category` (type-scoped), `PromptReport` (abuse), `ToolLogo` (logos for "works best in" chips), `Rating` (NEW v1.1), `Setting` (brand/site settings).

## 2. Layout contract (cPanel shared hosting — the prime directive)

Everything must run on $3/mo shared cPanel hosting: **no SSH, no Node, no Composer on the host, no Redis, no Supervisor**. `exec()` is assumed disabled; artisan calls run in-process (`Artisan::call()`).

```
/home/CPANELUSER/
├── core/           ← Laravel app (vendor/, .env, storage/) — NOT web-visible
└── public_html/    ← index.php, .htaccess, .user.ini, install.php, update.php
```

`deploy/public_html/index.php` requires the sibling `core/`. The `.htaccess` maps `/storage`, `/build`, favicon into `../core/` and blocks dotfiles. Both Apache and LiteSpeed must work (all guards wrapped in `<IfModule>`).

## 3. Releases & updates

- **Full install zip** — `php deploy/build-release.php` → `dist/promptvellum-upload.zip` (core + vendor + built assets + docroot). Extract over `/home/USER/`, visit `install.php`.
- **Update zip (live site)** — `php deploy/build-update-zip.php` → `dist/promptsewa-<VERSION>-update.zip` (code-only, no vendor). Apply at `https://<domain>/update.php` with the token in `public_html/.update-token`, **or** from the admin panel at `/admin/update`.
- **Update pipeline** (`core/app/Console/Commands/UpdateFromRelease.php`, command `pv:update`): maintenance mode → extract zip over `core/` (preserving live `.env`, token, storage) → `migrate` → **idempotent `db:seed`** (added v1.1) → config/route/view caches → asset sync into docroot → `storage:link` → maintenance off.

**Warning:** `deploy/public_html/.update-token` is committed to the public GitHub repo. Regenerate it on the server after install (random hex in `public_html/.update-token`, web-invisible dotfile).

## 4. v1.1.0 feature set (this release)

### 4.1 Admin panel updater — 500 fixed
`/admin/update` fat-errored because the view referenced the removed route name `dashboard.update.run`. Fixed to `admin.update.run` (`resources/views/dashboard/update.blade.php`).

### 4.2 Verified badge (saffron seal)
- `users.is_verified` (bool, migration `2026_09_27_000100`).
- Issued **only by admins**: Admin → Users → per-row "Verify / ✓ Verified" toggle (`admin.users.verified` route → `UserAdminController::toggleVerified`). No automatic criteria.
- Component `x-verified-badge` (`resources/views/components/verified-badge.blade.php`) with `size` variants xs/md/lg. Renders: prompt cards, image-gallery cards, **prompt detail byline**, creator profiles, admin users table, library search results.
- **Policy:** only `admin@*` and the flagship `justshipitai@gmail.com` are seeded verified. Everyone else is admin-issued — the bulk seeder deliberately does *not* verify demo creators (removed in this release; earlier builds auto-verified them, revoke with one UPDATE if a legacy DB still has them).

### 4.3 Creator profile redesign (X/Facebook-style)
`resources/views/creators/show.blade.php`: gradient cover banner (deterministic per user id; swaps to uploaded image when `users.banner_path` is set), avatar overlapping the banner (`users.avatar_path`), large verified badge, role pills, joined date, bio, stats (prompts / sales), "Edit profile" CTA on own profile. **Route now binds by name**: `/creators/{user:name}` (was `/creators/{id}`) — URLs are human-readable, e.g. `/creators/Maya Tamang` → slugified by the browser.

### 4.4 Game-feel buttons
`components/button.blade.php` + inline CTAs use chunky offset shadows (`shadow-[0_4px_0_0_#a16207]`) that collapse on `:active` — arcade press feel. Applied to navbar CTAs, buy buttons, error pages, admin actions.

### 4.5 Premium locked box fix
The locked teaser on paid prompt detail was an absolutely-positioned overlay; the price pill overflowed the rounded box on narrow screens. Rebuilt as normal flow: teaser (max-h, gradient fade) → lock icon → copy → price pill, all inside `overflow-hidden rounded-2xl bg-ink`.

### 4.6 Image prompt gallery (v1.0 ship)
Storefront "Image prompt gallery" section: `x-image-prompt-card` shows the cover visual full-bleed (4:5), category pill overlay, **prompt snippet visible on the card**, one-click copy for free prompts (`promptCopy` Alpine component), price CTA for paid. Library supports `?type=image|video|text` via `PromptSearchService::search($term, $perPage, $type)`.

### 4.7 Custom branded error pages
`resources/views/errors/{403,404,419,429,500,503}.blade.php` — brand layout, ink badge with error code, saffron CTAs (Back home / Browse prompts). Laravel picks these up automatically by status code.

### 4.8 Mobile navbar rebuild
The old navbar put search, categories and auth links on one flex row — overlapping on small screens. Rebuilt: logo + search row 1 (search full-width on mobile), desktop links hidden on mobile behind a hamburger (`Alpine: mobile/cats` state) opening a drawer with all links + category chips. Desktop behavior unchanged.

### 4.9 User search
`PromptSearchService::searchCreators($term)` — name/email LIKE search over users who have ≥1 public prompt, surfaced as a "Creators" card grid above the prompt grid on `/prompts?q=…` (`library.blade.php`).

### 4.10 Ratings
- `ratings` table (unique user+prompt, `score` 1–5), `Rating` model, `RatingController@store` (upsert).
- **Eligibility policy:** free prompts → any logged-in user; paid prompts → only buyers holding an `active` `LicenseGrant` (the same entitlement check as the full-body view). Enforced server-side; 403 otherwise.
- UI: star row on prompt detail (below the prompt, above tips). Click a star = submit that score (progressive form, no JS needed). Shows "Your rating — click to change" after rating. Aggregate shows in the byline: `★ 4.5 (12)`.

## 5. Local development

```
cd core
composer install            # or use the committed vendor/ on Windows
npm install && npm run build
php artisan serve           # http://127.0.0.1:8000
php artisan migrate --seed  # seeds demo content + bulk catalog (269 prompts)
```

Logins (local demo): `admin@promptsewa.test` / `password` (local DB may still use old `admin@promptvellum.test` — both exist in some environments). Creators: `bibek@`, `maya@`, `dorje@promptsewa.test`.

**Tests:** `php artisan test` — Pest, 102 tests / 415 assertions, all green as of this handoff. Cover storefront, search, categories, checkout flow, entitlements, reports, brand settings, seeder integrity.

## 6. Known debt / watch-outs (for the senior engineer)

1. **Search is Scout-backed** (`PromptSearchService`); local dev uses the `database` engine. On live MySQL, consider `match against` / Meilisearch if catalog grows past ~10k. Do **not** reintroduce raw LIKE on prompts (Sprint-1 security gate) — the only LIKE in the codebase is the *creator* search on `users`, which is parameterized and bounded.
2. **`Js::from` / `@js` in x-data attributes** must always sit in **double-quoted** HTML attributes. Single quotes truncate the attribute (this was the v1.0 blank-prompt bug) — worth a Blade lint rule or code review checklist item.
3. **Blade `@php` blocks must not contain `use` statements** in views rendered as anonymous components — they compile inside `if ($component->shouldRender())` and PHP forbids `use` there. Fully qualify classes instead (this broke all admin list pages in v1.0).
4. **The demo seeders are idempotent** by design (`DemoContentSeeder` skips when prompts exist, `BulkCatalogSeeder` skips on its marker title, `JustShipItAISeeder` skips per-account). If you change seeded content, bump the marker or write a fresh seeder — do not mutate existing rows.
5. **Compiled assets ship in the update zip** (`public/build` is NOT gitignored in the zip builder). Run `npm run build` before building a release, or the zip ships stale JS/CSS.
6. **`storage/app/public/covers/*` demo SVGs are generated, not committed** (storage is gitignored). Fine in production — creators upload real covers — but a fresh local clone shows gradient placeholders for the gallery until seeded.
7. **eSewa verification** is POST-based and exempt from CSRF (`checkout/esewa/verify`) — it signature-verifies the payload instead. Keep that exemption if you refactor checkout.
8. **Ratings have no moderation/review text yet** — stars only. If you add reviews, add length limits + rate limiting (the report controller's `throttle:10,1` is the pattern) and consider a prompt-report link on reviews.

## 7. Deploying the current update

1. Upload `dist/promptsewa-1.1.0-update.zip` (or the newest `promptsewa-*-update.zip`) via cPanel or the `/admin/update` form.
2. Open `https://promptsewa.techadda.com.np/update.php`, paste the token from `public_html/.update-token`, run.
3. Pipeline migrates the `ratings` + `is_verified` migrations, seeds the bulk catalog (only if missing — existing data untouched), rebuilds caches, syncs `public_html/build`.
4. Post-check: homepage gallery renders, `/admin/update` loads (was 500), `/creators/Maya Tamang` works, verified badges visible on JustShipItAI prompts, star rating works on a free prompt while logged in.

## 8. File map (what changed in v1.1)

```
core/app/Console/Commands/UpdateFromRelease.php   + idempotent db:seed step
core/app/Http/Controllers/Admin/UserAdminController.php   + toggleVerified
core/app/Http/Controllers/RatingController.php    NEW
core/app/Models/Rating.php                        NEW
core/app/Models/Prompt.php                        + ratings()
core/app/Models/User.php                          + is_verified (fillable, cast)
core/app/Services/PromptSearchService.php         + $type param, searchCreators()
core/database/migrations/2026_09_27_000100_add_is_verified_to_users_table.php  NEW
core/app/Services/ImageUploadService.php       NEW (GD compress & re-encode)
core/app/Http/Controllers/Dashboard/ProfileController.php  NEW (profile editing)
core/resources/views/dashboard/profile.blade.php  NEW
core/database/migrations/2026_09_28_000100_create_ratings_table.php            NEW
core/database/seeders/BulkCatalogSeeder.php       NEW (240 prompts, no auto-verify)
core/database/seeders/{DatabaseSeeder,DemoContentSeeder,JustShipItAISeeder}.php
core/resources/views/errors/*.blade.php           NEW (403/404/419/429/500/503)
core/resources/views/components/verified-badge.blade.php  NEW
core/resources/views/components/{navbar,button,prompt-card,image-prompt-card}.blade.php
core/resources/views/creators/show.blade.php      redesigned
core/resources/views/prompts/show.blade.php       badge, big tool logos, locked box, rating widget
core/resources/views/library.blade.php            + creators section
core/resources/views/dashboard/update.blade.php   route-name fix
core/routes/web.php                               + rate route, {user:name} binding
deploy/build-update-zip.php                       version 1.1.0
```

— Prepared by Codebuff. Questions about any section: start from the file map and read the docblocks; every non-obvious decision is commented inline in the code.
