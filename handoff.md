# PromptSewa — Engineering Handoff

**Prepared:** 2026-09-29 · **Current version:** v1.4.5
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
- **Update zip (live site)** — `php deploy/build-update-zip.php` → `dist/promptsewa-<VERSION>-update.zip` (code-only: `core/` **plus a `public_html/` allow-list** — update.php, index.php, .htaccess, .user.ini — since v1.3.0; no vendor). Apply at `https://<domain>/update.php` with the token in `public_html/.update-token`, **or** from the admin panel at `/admin/update`. `install.php` and `.update-token` are never shipped (the live token must stay server-side).
- **Update pipeline** (`core/app/Console/Commands/UpdateFromRelease.php`, command `pv:update`): maintenance mode → extract zip over `core/` (preserving live `.env`, token, storage) → `migrate` → **idempotent `db:seed`** (added v1.1) → config/route/view caches → asset sync into docroot → `storage:link` → maintenance off.

**Warning:** `deploy/public_html/.update-token` is committed to the public GitHub repo. Regenerate it on the server after install (random hex in `public_html/.update-token`, web-invisible dotfile).

## 4. Release history highlights

### v1.5.0 — Official Identity, Taxonomy & Version Truth (this release)

- **T1 official house account**: `pv:official-account` creates/flags `@promptsewa` (admin, verified, `users.is_official`), idempotent, production-safe. Profile/settings edits of the official account are admin-only (moderators 403) — enforced in `ProfileController` for both direct login AND impersonated sessions.
- **T2 two-badge system**: `x-verified-badge` renders the official blue circle (`--color-official #1d9bf0`, the single sanctioned token addition) whenever `is_official`; saffron seal remains the verified badge. Typeahead JSON carries `badge: official|verified|none`.
- **T3 admin impersonation**: `impersonations` table (session log — start/end rows; NOT a ledger), `POST /admin/users/{id}/impersonate` + `POST /impersonation/stop`, chrome bar in the navbar, `ResolveImpersonation` middleware with stale-driver self-heal. Nesting refused; self-switch = closed no-op row.
- **T4 catalog adoption**: `pv:adopt-catalog --dry-run|--force` reassigns demo/bulk-seeded prompts to the official account (dry-run output delivered; force awaits architect authorization). Packs/orders/grants untouched; `prompt_versions.user_id` keeps honest authorship history.
- **T5/T6 five types + tool modality**: `text|image|video|agentic|skill` end-to-end (consts, validation, TYPE_CONTEXTS, banner palettes, type-scoped categories). `tool_logos.modality` (text/image/video/agentic/skill/any) + TaxonomySeeder (28 tools, slug-idempotent, production-safe). `recommended_tools` now validates against the active registry with modality ∈ {type, any} — the founder's live-edit error (arbitrary tool names accepted, logo rendering broken) is reproduced and locked by test.
- **T7 version truth**: `prompt_versions` gains nullable `body` (longText)/`variables`/`tools` snapshots. Every edit appends a FULL snapshot; published listings stay published on save; open reports flip to pending on live edit; "Restore this version" appends a copy ("Restored from vN"), never mutates history. History page: snapshots visible to owner/staff/active license holders only; pre-v1.5.0 rows show the honest "Snapshot not captured before v1.5.0" chip — bodies are NEVER fabricated (backfill writes variables/tools only).
- **T8 pack landing**: `packs.tagline/hero_copy`, public landing renders them with Product JSON-LD. **Incidental fix**: any populated pack page was a 500 (ambiguous `created_at` in the belongsToMany window function) — found by the new landing test, fixed with a qualified `orderByDesc('prompts.created_at')`.
- **T9 SEO**: `x-seo` component (title/description/canonical/OG/Twitter/robots) on home, library, categories, prompt detail (+ Product JSON-LD), creator profiles (+ ProfilePage), packs, versions (canonical → prompt detail), auth (noindex), about. Dynamic cached sitemap.xml (published public surfaces only) + robots.txt with Sitemap line.
- **T10 absorbed E-series**: Admin → Users → Demo purge panel (dry-run preview + force; shells `pv:purge-demo` so runbook logic can't drift); `update.php` stray-SQLite scan extended to `core/` and docroot top level (warn-only).
- **T11 mobile brand mark**: the founder's screenshot bug — `app-layout` never forwarded `brand-mark-path`, so mobile showed neither mark nor logo. Fixed + locked by regex assertions on both responsive rows.
- **T12 bottom dock**: `x-mobile-dock` (exactly 5 slots, ink ground, saffron on the center CTA ONLY, ≥44px targets, aria-labels, focus rings, safe-area padding, `pb-24 md:pb-0` body wrapper, z-40 under the typeahead). **The burger drawer is RETIRED** — category chips are a horizontal scroll row on the library (all breakpoints); Packs/About/Dashboard/Admin/Logout live in the profile "You" menu (Admin row gated server-side).
- **T13 purchases library + bookmarks**: orders expand to items with license-state chips (active/revoked) and pack rows listing their granted prompts; re-download streams the full body gated by the same active-grant check as the paywall (owner or active grant; never staff storefront access). `bookmarks` table + `POST /bookmarks/{prompt}` toggle (throttled, idempotent, 404 on invisible prompts); heart on cards + detail (Alpine optimistic flip, pre-flipped server-side); dashboard Saved tab.
- **Tests**: 43 new (OfficialIdentityTest 4, ImpersonationTest 5, CatalogAndVersionTruthTest 8, PackLandingAndSeoTest 5, MobileDockTest 9, PurchasesTest 5, BookmarkTest 5, BrandLogoTest +2). Suite: 276 passed / 1198 assertions.

### v1.4.5 — release hygiene

- **D1 zip hygiene**: `build-update-zip.php` permanently excludes host-local artifacts — `bootstrap/cache/*`, `storage/**` (runtime), `public/storage`, `.env*`, `*.sqlite` (`.gitignore` placeholders excepted) — and runs a build-time audit that FAILS the build on any forbidden entry or missing docroot allow-list/compiled assets. The v1.4.4 zip's violation (dev `config.php` shipped) is locked by an incident test.
- **D2 pre-boot purge**: `update.php` deletes `bootstrap/cache/config.php|routes*.php|packages.php|services.php` in plain PHP after extract, before boot — a poisoned cache can never survive an upload. `pv:update` runs the same purge as step 0 (admin-panel path).
- **D3 in-process config refresh**: after `config:cache`, `pv:update` re-reads the freshly written cache into the live repository so later steps (route:cache, view:cache, seeds) act on host paths, never boot-time paths.
- **D4 production seeder gate**: `DemoContentSeeder`, `BulkCatalogSeeder` and `JustShipItAISeeder` HARD-refuse when `APP_ENV=production` — the from-zero seed path on prod is structurally impossible.
- **D9 stray-SQLite detector**: `update.php` warns in the summary if any `*.sqlite` sits under `core/database/` after extract (evidence is never deleted).
- **D5 demo-account remediation**: `pv:purge-demo --dry-run|--force` (admin-gated, idempotent, logs every id) + `docs/RUNBOOK-DEMO-PURGE.md`. Unused demo identities hard-delete; money-adjacent ones ban+rename (financial invariant: never delete).
- **D10 acceptance gate**: `DeployParityAcceptanceTest` — a real MySQL DB at the v1.4.3 schema + sample rows; `pv:update` migrates EXACTLY 130000+130100, sample rows untouched, view:cache OK, no failure record. This is the proof that the founder's next deploy repairs the schema instead of replaying the accident.
- **Tests**: 7 new (ReleaseHygieneTest 6, DeployParityAcceptanceTest 1, MySQL-gated). Suite: 233 passed / 1023 assertions.

### v1.4.4 — avatar fidelity & manual payment proof

- **x-user-avatar component** (`components/user-avatar.blade.php`, sizes xs/sm/md/lg): real `avatar_path` photo on every creator-identity surface — prompt cards (both card variants), prompt detail byline, library creators grid, versions page author row, admin users table, navbar dropdown (desktop + mobile), search typeahead JSON (`avatar_url` + `initial`), creator profile hero. Initials badge is the FALLBACK only. Alt always carries the @handle; the verified tick stays a sibling element. Locked by `AvatarFidelityTest` (10 tests).
- **C2 root cause ("can't update manual payment methods")**: the admin form field is `manual_enabled` but the runtime settings key is `manual_payment_enabled` — the save path persisted under the form name, so the toggle and instructions never took effect and the manual panel never activated. `PaymentMethodAdminController::update` now persists under the canonical key. Locked by rendered-form submission tests.
- **Manual payment methods v2**: `manual_payment_methods` table (name, instructions, qr PNG ≤1024px alpha-preserved, position, active) with admin CRUD at `/admin/manual-methods`. Delete = deactivate once any order references the name (snapshot-prefix aware: orders store `NAME · reference`); hard delete only before first use. Checkout renders active methods position-ordered on the dark panel with per-method instructions (escaped + pre-line) and click-to-enlarge QR (Alpine modal, no new deps). Orders snapshot the method NAME — editing a method never rewrites order history.
- **Buyer TXN proof**: `orders.manual_txn_id/manual_proof_path/manual_submitted_at`; pending manual orders get a submit/replace form (txn ≤100 + screenshot ≤8 MB + optional note, `throttle:10,1`). Re-submission deletes the orphaned old file. Submission NEVER grants — approval remains the only grant path. Proofs live on the PRIVATE `proofs` disk (`storage/app/proofs`, outside public/storage) and stream only through `orders.proof.show` (owner or staff). Admin orders desk shows TXN id, submitted-at, and the proof preview before the unchanged approve/reject buttons.
- **Tests**: 23 new (AvatarFidelityTest 10, ManualPaymentMethodsTest 13). Suite: 226 passed / 987 assertions.

### v1.4.3 — migration replay repair

- R1 back-dated `120999` repair normalizes the license_grants preconditions so an interrupted `121000` (MySQL DDL autocommits; migrations row unwritten) replays cleanly instead of dying on SQLSTATE 1091; append-only `121100` drops the leftover unique index on `order_item_id` on BOTH engines (SQLite never dropped it, which silently broke pack fulfillment). R2 `App\Support\SchemaInspector` (SHOW INDEX / PRAGMA) + `Arch\MigrationDropGuardTest` enforce existence-guarded destructive DDL repo-wide. R3 a fatal in `pv:update` keeps maintenance mode ON, writes `core/storage/logs/update-failed.json` with a 4-step recovery checklist + SQLSTATE hints, and the admin/update.php failure screens render the same ops copy. See §6.18.

### v1.4.0 — search & profile UX overhaul

- **Username handles**: nullable unique `users.username` (30 chars, `alpha_dash`). Creator URLs are now `/creators/{username}` — binding is scoped to the `{creator}` parameter in `AppServiceProvider::boot` (username OR name fallback; soft-deleted excluded), so admin `{user:id}` routes are untouched. `User::getRouteKey()` returns handle-or-name, which makes `route('creators.show', $user)` emit the handle URL automatically. Profile edit form gains an @username input (`Rule::unique()->ignore()`, lowercased, clearable → falls back to name URL).
- **AJAX typeahead**: `GET /search/preview` (throttled 60,1) returns 3 prompt + 3 creator hits as JSON. `x-search-preview` Alpine component in the navbar: 300 ms debounced, abortable fetch, Prompts/Creators sections, arrow-key navigation (`bg-saffron/10` active row), Escape closes, Enter falls through to the full-page search.
- **Natural-language search**: `PromptSearchService::normalizeQuery()` strips English stop words ("I want a blog" → "blog") before Scout — the `database` driver's LIKE/FULLTEXT has no NLU. Falls back to the raw term if everything is stripped. Creator search now matches username too.
- **Creator header layout (UI-003)**: only the avatar overlaps the banner; the identity text block has zero negative margins, `pt-3/pt-4` clearance and `truncate` on name/@username.
- **Tests**: 14 new (SearchPreviewTest) — endpoint shape, throttle, 422 length, normalization, username rules, binding. Suite: 116 passed / 465 assertions.

### v1.2.0 — image uploads, profile editing, staff paywall

- **`ImageUploadService`** (`app/Services/ImageUploadService.php`): GD decode-verify → downscale → JPEG re-encode (strips EXIF). Variants: cover 1600px q82 · banner 1600px q80 · avatar 512px q85 · logo 256px q90 · 8 MB ceiling · JPG/PNG/WebP only. All uploads (prompt covers, avatars, banners) go through it — nothing raw ever hits `storage/app/public`.
- **Prompt cover upload** (image-type prompts only, `PromptFormRequest` rules) + **generated SVG title banners** (`components/prompt-cover.blade.php`): when no cover exists, a deterministic 6-palette saffron/bronze gradient banner typesets the title (word-wrapped ≤3 lines) as an SVG data-URI keyed by `prompt->id`.
- **Full profile editing**: `GET/PUT /dashboard/profile` → `Dashboard\ProfileController` (name, bio, avatar, banner, remove toggles). Route name `dashboard.profile.update` is PUT-only.
- **Navbar account dropdown** (desktop avatar menu + mobile drawer profile links).
- **Staff paywall**: `PromptController::show` no longer grants staff free paid bodies; `?preview=1` is honored only when the viewer `isModerator()` AND the referer contains `/admin`. Admin prompts table has a Preview button.
- **`deploy/public_html/update.php` fix**: `extract_release_zip()` returned `'ok|message'` strings while the caller destructured `[$level, $line]` → "Cannot use string as array … on line 362". Fixed to `list<array{0:string,1:string}>` pairs. (See §7 — the v1.2.0 zip was core-only, so live needed one manual cPanel upload of the file; from v1.3.0 every zip ships it.)

### v1.3.0 — three live-incident fixes (this release)

1. **Profile upload 405 (live)** — `dashboard/profile.blade.php` posted a plain POST but the route `dashboard.profile.update` is PUT-only and the form was **missing `@method('PUT')`**. Laravel correctly answered 405 Method Not Allowed. Fixed by adding the spoof directive; verified end-to-end with a real multipart upload (302 → avatar/banner stored). See watch-out §6.10.
2. **Creator name overlapping the cover banner** — in `creators/show.blade.php` the identity row overlaps the banner by `-mt-12/-mt-16`; the name block now carries `pt-2 sm:pt-8` and `min-w-0` so name/badges clear the banner edge while the avatar keeps its X-style overlap.
3. **update.php line-362 fix never reached live** — the v1.2.0 zip was `core/`-only, so `public_html/update.php` on the server stayed old. `build-update-zip.php` now ships the docroot allow-list in every zip (§3), so docroot fixes ride along from now on. v1.3.0 is the first zip that self-delivers the fixed updater.

**Bonus fix:** `core/public/index.php` had been committed as the *cPanel docroot* front controller (looks for a sibling `core/` folder → 503 under `artisan serve`). Restored the real Laravel front controller; the cPanel variant lives only at `deploy/public_html/index.php`. See watch-out §6.9.

### v1.1.0/v1.1.1 — earlier feature set

#### Admin panel updater — 500 fixed
`/admin/update` fat-errored because the view referenced the removed route name `dashboard.update.run`. Fixed to `admin.update.run` (`resources/views/dashboard/update.blade.php`).

#### Verified badge (saffron seal)
- `users.is_verified` (bool, migration `2026_09_27_000100`).
- Issued **only by admins**: Admin → Users → per-row "Verify / ✓ Verified" toggle (`admin.users.verified` route → `UserAdminController::toggleVerified`). No automatic criteria.
- Component `x-verified-badge` (`resources/views/components/verified-badge.blade.php`) with `size` variants xs/md/lg. Renders: prompt cards, image-gallery cards, **prompt detail byline**, creator profiles, admin users table, library search results.
- **Policy:** only `admin@*` and the flagship `justshipitai@gmail.com` are seeded verified. Everyone else is admin-issued — the bulk seeder deliberately does *not* verify demo creators (removed in this release; earlier builds auto-verified them, revoke with one UPDATE if a legacy DB still has them).

#### Creator profile redesign (X/Facebook-style)
`resources/views/creators/show.blade.php`: gradient cover banner (deterministic per user id; swaps to uploaded image when `users.banner_path` is set), avatar overlapping the banner (`users.avatar_path`), large verified badge, role pills, joined date, bio, stats (prompts / sales), "Edit profile" CTA on own profile. **Route now binds by name**: `/creators/{user:name}` (was `/creators/{id}`) — URLs are human-readable, e.g. `/creators/Maya Tamang` → slugified by the browser.

#### Game-feel buttons
`components/button.blade.php` + inline CTAs use chunky offset shadows (`shadow-[0_4px_0_0_#a16207]`) that collapse on `:active` — arcade press feel. Applied to navbar CTAs, buy buttons, error pages, admin actions.

#### Premium locked box fix
The locked teaser on paid prompt detail was an absolutely-positioned overlay; the price pill overflowed the rounded box on narrow screens. Rebuilt as normal flow: teaser (max-h, gradient fade) → lock icon → copy → price pill, all inside `overflow-hidden rounded-2xl bg-ink`.

#### Image prompt gallery (v1.0 ship)
Storefront "Image prompt gallery" section: `x-image-prompt-card` shows the cover visual full-bleed (4:5), category pill overlay, **prompt snippet visible on the card**, one-click copy for free prompts (`promptCopy` Alpine component), price CTA for paid. Library supports `?type=image|video|text` via `PromptSearchService::search($term, $perPage, $type)`.

#### Custom branded error pages
`resources/views/errors/{403,404,419,429,500,503}.blade.php` — brand layout, ink badge with error code, saffron CTAs (Back home / Browse prompts). Laravel picks these up automatically by status code.

#### Mobile navbar rebuild
The old navbar put search, categories and auth links on one flex row — overlapping on small screens. Rebuilt: logo + search row 1 (search full-width on mobile), desktop links hidden on mobile behind a hamburger (`Alpine: mobile/cats` state) opening a drawer with all links + category chips. Desktop behavior unchanged.

#### User search
`PromptSearchService::searchCreators($term)` — name/email LIKE search over users who have ≥1 public prompt, surfaced as a "Creators" card grid above the prompt grid on `/prompts?q=…` (`library.blade.php`).

#### Ratings
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

**Tests:** `php artisan test` — Pest, 102 tests / 416 assertions, all green as of this handoff (re-verified green at v1.3.0). Cover storefront, search, categories, checkout flow, entitlements, reports, brand settings, seeder integrity.

## 6. Known debt / watch-outs (for the senior engineer)

1. **Search is Scout-backed** (`PromptSearchService`); local dev uses the `database` engine. On live MySQL, consider `match against` / Meilisearch if catalog grows past ~10k. Do **not** reintroduce raw LIKE on prompts (Sprint-1 security gate) — the only LIKE in the codebase is the *creator* search on `users`, which is parameterized and bounded.
2. **`Js::from` / `@js` in x-data attributes** must always sit in **double-quoted** HTML attributes. Single quotes truncate the attribute (this was the v1.0 blank-prompt bug) — worth a Blade lint rule or code review checklist item.
3. **Blade `@php` blocks must not contain `use` statements** in views rendered as anonymous components — they compile inside `if ($component->shouldRender())` and PHP forbids `use` there. Fully qualify classes instead (this broke all admin list pages in v1.0).
4. **The demo seeders are idempotent** by design (`DemoContentSeeder` skips when prompts exist, `BulkCatalogSeeder` skips on its marker title, `JustShipItAISeeder` skips per-account). If you change seeded content, bump the marker or write a fresh seeder — do not mutate existing rows.
5. **Compiled assets ship in the update zip** (`public/build` is NOT gitignored in the zip builder). Run `npm run build` before building a release, or the zip ships stale JS/CSS.
6. **`storage/app/public/covers/*` demo SVGs are generated, not committed** (storage is gitignored). Fine in production — creators upload real covers — but a fresh local clone shows gradient placeholders for the gallery until seeded.
7. **eSewa verification** is POST-based and exempt from CSRF (`checkout/esewa/verify`) — it signature-verifies the payload instead. Keep that exemption if you refactor checkout.
8. **Ratings have no moderation/review text yet** — stars only. If you add reviews, add length limits + rate limiting (the report controller's `throttle:10,1` is the pattern) and consider a prompt-report link on reviews.
9. **`core/public/index.php` is the LOCAL front controller** (boots the sibling `../` app, used by `artisan serve`). The cPanel docroot variant that looks for a sibling `core/` lives ONLY at `deploy/public_html/index.php` — they must never be swapped. This mix-up was shipped in v1.0 and silently made `artisan serve` 503 (tests still passed because they bypass the front controller).
10. **Blade forms targeting PUT routes MUST include `@method('PUT')`** next to `@csrf`. v1.2.0's `dashboard/profile.blade.php` omitted it, so submits hit the PUT-only route as plain POST → live 405 Method Not Allowed. Local functional tests that `->post()` the spoofed field caught nothing because the view was never asserted for the hidden method field. When you add an edit form, verify the rendered HTML contains `_method`.
11. **The update zip now ships a docroot allow-list** (`update.php`, `index.php`, `.htaccess`, `.user.ini`) under `public_html/` — v1.3.0. Before that, docroot bugfixes (like the update.php `extract_release_zip()` return-pairs fix) never reached live because the zip was core-only. `install.php` and `.update-token` are deliberately never shipped (the live token must stay server-side).
12. **Blade form verb guard is an arch test** (`tests/Arch/BladeFormVerbTest.php`, v1.4.1). It scans every Blade `<form>` with a `route(...)` action, resolves the route's allowed verbs, and fails CI if (a) a form targets PUT/PATCH/DELETE without the matching `@method` spoof, or (b) a POST form targets a GET-only route. This is the permanent fix for the recurring 405 class (v1.3.0 profile, v1.4.1 admin review queue). New forms are covered automatically — do not add an `->only(...)` exclusion for it.
13. **`CreatorProfileTest` caveat (v1.4.1)**: the 404 for unknown/soft-deleted creators moved from the controller into the route binder (`Route::bind('creator')` in `AppServiceProvider::boot` — Laravel 12 has no `RouteServiceProvider`; the boot binding is the approved equivalent). Tests asserting 404 for a bogus creator slug now exercise the binder, not controller code — a controller refactor that removes the 404 check is safe, but a binder regression will surface in that test first.
14. **`users.username` is NOT NULL since v1.4.1.** The backfill migration (`2026_09_28_110000`) is idempotent (chunked, slug + numeric suffix on collision) and `2026_09_28_110100` enforces the constraint. Any new code path that creates users MUST supply a username (factory does it automatically; bare `User::create` in tests must include it). Profile edit also requires the handle — clearing it is a validation error, not a null-out.
15. **Blade `@{{ }}` renders literal text — banned repo-wide; NoBladeLeakTest enforces.** The v1.4.1 handle rollup shipped six `@{{ }}` escapes that Blade never compiles, and prod rendered raw `{{ $prompt->creator->username ?? ... }}` on every card. Alpine interpolation must use `x-text`/`:attr`; literal `@` is `'@'.$handle` echoed inside a plain `{{ }}`. The arch test also bans raw U+2192 arrows (use `&rarr;`).
16. **Views must render standalone (no ambient shared vars); StaffViewStandaloneRenderTest enforces.** The v1.4.1 prod 500 was `Undefined variable $errors` in dashboard/update — the `@error` directive compiles to `$errors->getBag()` and explodes when the view renders outside the web middleware group (release log pages, artisan contexts, error paths). Never use `@error`/`$errors` in a view without an `isset($errors)` guard, and never rely on `view()->composer` data in a view that can render outside HTTP. The update pipeline now runs `view:clear` immediately before `view:cache` — stale compiled views must never survive an upgrade.
17. **Public history page = metadata only; Edit link is owner-only.** `/prompts/{slug}/versions` exposes labels, changelogs, authors, timestamps — never bodies or variable lists for paid prompts. The detail page's Edit link renders only for the owner; moderators use the admin preview route. Never link `dashboard.prompts.edit` from admin surfaces (the admin dashboard review queue links the preview route).
18. **Creator surfaces render avatars via `x-user-avatar` — initials are a fallback, never the default (v1.4.4).** Any new identity surface (cards, tables, rows, JSON payloads) must use the component (sizes xs/sm/md/lg; extra classes merge through `$attributes`). Alt text always carries `@handle`; the verified tick stays OUTSIDE the img. Typeahead JSON must carry `avatar_url` (null when unset) so the client can fall back — `searchCreators` only returns users with a published prompt, so a bare-user test fixture shows an empty typeahead.
19. **Payment proofs are private — served via owner/staff route, never raw storage URLs (v1.4.4).** Proof screenshots land on the `proofs` disk (`storage/app/proofs`, outside the public/storage symlink) and stream only through `GET /orders/{order}/proof` (`orders.proof.show`, owner or moderator). Never echo a proof path into `Storage::disk('public')->url()`; QR codes (public disk) are fine as plain URLs. Proof submission is throttled (10,1), never grants entitlement, and replaces the file server-side (old file deleted — files are not financial records; the order row keeps one current proof).
20. **Order-history snapshots are write-once strings (v1.4.4).** Checkout stores the payment method NAME as `NAME · reference` on the order. Editing/deactivating a `manual_payment_methods` row must never rewrite it; `isUsedByOrders()` therefore matches the snapshot PREFIX (`name · %`), not bare equality — an exact match would miss every real order and let a referenced method hard-delete, orphaning history.
21. **Bootstrap caches are host-local artifacts — never ship them; update.php purges them pre-boot; prod never runs demo seeders (v1.4.5).** A `bootstrap/cache/config.php` from a dev machine carries dev view paths and a dev DB connection: on the host it poisons the boot AND redirects migrations/seeders to a stray database (the v1.4.4 incident). The zip builder hard-excludes these trees and fails its own audit on violations; `update.php` purges caches pre-boot regardless of zip contents; the demo/bulk/flagship seeders refuse `APP_ENV=production` outright.
22. **Impersonation is a session log, not a ledger (v1.5.0).** `impersonations` rows record start/end of admin switches; `ended_at` updates are allowed (self-switch writes a closed no-op row). The impersonated session has EXACTLY the target's powers — no merged permissions; a stale driver entry self-heals (forgets) on the next request. The chrome bar in the navbar must never be hidden; new staff surfaces must never assume the session user is the real driver — check `session('impersonator_id')` where identity matters (see the official-account profile guard).
23. **The official account's profile is admin-edit-only (v1.5.0).** `ProfileController` refuses when the edited account `is_official` and the acting driver (session user OR impersonator) is not an admin. If you add account-settings surfaces, re-apply this guard — moderators must 403, not get a hidden form.
24. **Version snapshots: never fabricate history (v1.5.0).** Pre-v1.5.0 `prompt_versions` rows have NULL body — the history page shows the "Snapshot not captured before v1.5.0" chip and the backfill writes variables/tools ONLY. Do not backfill bodies from anywhere; do not render snapshot bodies past the paywall gate (owner/staff/active-license check inherited from the detail page). Restores are APPENDS ("Restored from vN") — never in-place copies.
25. **Mobile nav is the bottom dock; the burger is retired (v1.5.0).** New mobile surfaces attach to dock slots (dock component `x-mobile-dock`) or to the profile "You" menu — never a drawer. The dock's saffron fill is reserved for the center CTA; keep tab states to text color. The Admin row in the "You" menu is gated server-side (`@if ($user->isModerator())`), never CSS-hidden. Error/maintenance layouts are exempt from the dock by design.
26. **`recommended_tools` validates against the registry (v1.5.0).** The tool picker is modality-aware; the server rejects names absent from `tool_logos` (or whose modality excludes the prompt type). When seeding demo/test tools, give them `modality = any` or matching type — a text-only tool on an image prompt fails validation (this was the founder's live-edit 500-class bug).
22. **MySQL DDL autocommits — never rely on transactions around schema changes (v1.4.3).** An interrupted migration leaves half-applied DDL with NO row in `migrations`; the replay then dies on SQLSTATE 1091 (dropping an object that no longer exists). Every `dropUnique`/`dropIndex`/`dropColumn`/`dropForeign` in a migration MUST be guarded by an existence check from `App\Support\SchemaInspector` (`hasUniqueIndex`/`hasIndex`/`hasColumn`) — `Arch\MigrationDropGuardTest` enforces this repo-wide. To repair a half-migrated schema, add a back-dated repair migration (see 120999) rather than editing a committed migration. If `pv:update` dies, the site STAYS in maintenance mode and `core/storage/logs/update-failed.json` carries the recovery checklist — do NOT simply re-run against a half-state.

## 7. Deploying the current update

1. Upload `dist/promptsewa-1.4.1-update.zip` (or the newest `promptsewa-*-update.zip`) via cPanel or the `/admin/update` form. The zip contains `core/` and a `public_html/` allow-list (update.php + index.php + .htaccess + .user.ini).
2. Open `https://promptsewa.techadda.com.np/update.php`, paste the token from `public_html/.update-token`, run. NOTE: if live update.php still shows the "Cannot use string as array" error on line 362, upload `deploy/public_html/update.php` manually via cPanel once — after that, every future zip keeps it current.
3. Pipeline merges core/ AND the docroot files, migrates, seeds (idempotent), rebuilds caches, syncs `public_html/build`.
4. Post-check (v1.4.0): type in the navbar search — dropdown shows prompt/creator hits for "I want a blog"; `/creators/{username}` resolves; profile edit at `/dashboard/profile` saves avatar/banner and the new username; creator profile name never collides with the banner; `/admin/update` loads.

### Post-check (v1.4.1) — run in order after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Run `php artisan migrate:status` (or let update.php log) | both `2026_09_28_110000_backfill_usernames` and `2026_09_28_110100_make_username_required` show as Ran | `SignupHardeningTest::every user ends up with a handle after the backfill migration`, `the username column is NOT NULL after migration` |
| 2 | Admin → review queue → Preview on a pending paid prompt | amber "Moderation preview" banner, full body visible, URL `/admin/prompts/{id}/preview`, no `?preview=1` | `AdminPreviewTest` (6 tests) |
| 3 | Approve/reject from the review queue | pending → published / rejected, no 405 | `AdminReviewTest::approve via the PATCH-spoofed admin form transitions pending to published` |
| 4 | Sign up a fresh account | username required (≥4 chars, taken ones rejected), password meter animates, weak/common passwords rejected | `SignupHardeningTest` (10 tests) |
| 5 | Navbar with uploaded logo (desktop + mobile) | desktop shows logo img without duplicate wordmark; mobile shows square mark; no logo → saffron badge + wordmark | `BrandLogoTest` (7 tests) |
| 6 | Upload a transparent PNG logo in Admin → Brand | file stays `.png` (no JPEG re-encode), ≤768px | `BrandLogoTest::brand form accepts a transparent PNG logo and rejects JPEG logos` |
| 7 | Ratings + reports + packs spot-check | buyer can rate paid prompt; staff resolve report; pack CRUD saves | `AdminFlowsTest` (14 tests) |
| 8 | `cd core && php artisan test` | 159 passed, 607 assertions | whole suite |

SHA-256 of `dist/promptsewa-1.4.1-update.zip`: `90dc27e1169331edbcec5892d57648588517c4596c8e488663350869a13f8a40` (295 entries; ships S1–S4 controllers, migrations, 405/401 error pages, compiled `public/build`).

### Post-check (v1.4.5) — run in order after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Zip contents (builder output) | `entries: N \| hygiene audit: CLEAN (0 forbidden entries)` — no `bootstrap/cache/`, `storage/`, `.env*`, `.sqlite` | `ReleaseHygieneTest::the built update zip carries no host-local artifacts…` |
| 2 | update.php run output | `Bootstrap caches purged pre-boot` line; NO stray-SQLite warning unless one actually exists | manual + D2/D9 plain-PHP code |
| 3 | `php artisan migrate:status` on prod | exactly `130000` + `130100` newly Ran; everything else unchanged (v1.4.3 schema preserved) | `DeployParityAcceptanceTest` (MySQL parity) |
| 4 | Prod users table after pipeline | zero `@promptsewa.test` / `@promptvellum.test` / `justshipitai@gmail.com` identities created by seeding | `ReleaseHygieneTest::demo, bulk and flagship seeders hard-refuse in production` |
| 5 | Founder's real data intact | sample row checks in the parity test pattern: row count unchanged, identities intact | `DeployParityAcceptanceTest` |
| 6 | Demo purge (after founder's check-4 list) | `php artisan pv:purge-demo --dry-run` → review → `--force`; report shows deleted vs quarantined ids | `ReleaseHygieneTest::pv:purge-demo dry run writes nothing…` + RUNBOOK |
| 7 | Admin → Overview shows version chip | `v1.4.5` chip matches the deployed release | `config('app.version')` single source |
| 8 | `cd core && php artisan test` | 233 passed, 1023 assertions (parity test skips without MySQL) | whole suite |

### Post-check (v1.4.4) — run in order after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Run `php artisan migrate:status` | `2026_09_29_130000_create_manual_payment_methods_table` and `2026_09_29_130100_add_manual_proof_to_orders_table` show Ran; all v1.4.3 rows (120999/121000/121100) still Ran | `MigrationReplayRepairTest`, `ManualPaymentMethodsTest` |
| 2 | View a prompt card whose creator has a profile picture | real photo in the round badge (NOT initials), alt = @handle, verified tick adjacent | `AvatarFidelityTest` (10 tests) |
| 3 | Admin → Payments: save manual toggle + instructions → reload | checkbox stays checked, instructions persist; legacy instructions panel renders at checkout when no methods are configured | `ManualPaymentMethodsTest::admin updates manual payment instructions…` |
| 4 | Admin → Manual methods: create a method with a QR (PNG), position 0 | listed, active, QR visible; upload survives as `.png` even from a JPEG source | `ManualPaymentMethodsTest::QR uploads stay PNG…` |
| 5 | Buyer checkout (manual enabled, methods exist) | dark panel lists only ACTIVE methods in position order, per-method instructions + QR with click-to-enlarge | `ManualPaymentMethodsTest::checkout renders only active methods…` |
| 6 | Buyer submits TXN + screenshot on a pending manual order; then admin → Orders | TXN id, submitted-at, and proof preview visible on the order desk before unchanged approve/reject; approve grants; proof URL 403s for other buyers and redirects guests to login | `ManualPaymentMethodsTest::admin approves after proof…`, `only the owner can submit or view a proof` |
| 7 | Admin → Overview shows version chip | `v1.4.4` chip matches the deployed release | `config('app.version')` single source |
| 8 | `cd core && php artisan test` | 226 passed, 987 assertions | whole suite |

### Post-check (v1.4.3) — run in order after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Run `php artisan migrate:status` | `2026_09_28_120999_repair_license_grants_preconditions` and `2026_09_28_121100_drop_license_grants_order_item_unique` show Ran; 121000 shows Ran from the earlier interrupted run or now | `MigrationReplayRepairTest` (4 tests) |
| 2 | Site is online after the pipeline | maintenance OFF on success; if it FAILED, site intentionally stays down + `core/storage/logs/update-failed.json` exists with the 4-step checklist | `UpdaterFailureRecordTest::a fatal during pv:update keeps maintenance ON and writes update-failed.json` |
| 3 | Manual pack order with a 3-prompt pack → approve | THREE grants issued (one per prompt), order paid — proves the unique index is gone on MySQL too | `CheckoutFlowTest::approving a manual pack order grants every published prompt inside` |
| 4 | `SHOW INDEX FROM license_grants` (prod) | NO unique index on `order_item_id`; `order_item_id` nullable with `issued_by`/`issue_reason` columns present | `MigrationDropGuardTest` + `MigrationReplayRepairTest` |
| 5 | Admin → Overview shows version chip | `v1.4.3` chip matches the deployed release | `config('app.version')` single source |
| 6 | Admin → update failure surface (optional drill) | failure panel lists "STILL IN MAINTENANCE MODE" + recovery steps; update.php page shows the same ops copy (no vendor/ ghost-hunt text) | `UpdaterFailureRecordTest::update failure screen shows the ops maintenance panel` |
| 7 | `cd core && php artisan test` | 203 passed, 877 assertions | whole suite |

### Post-check (v1.4.2) — run in order after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Run `php artisan migrate:status` | `2026_09_28_120000_add_banned_at_to_users_table` and `2026_09_28_121000_make_license_grants_comp_capable` show Ran | `UserBanTest`, `CompGrantTest` |
| 2 | Open any prompt card / creator profile / prompt detail | real `@handle` renders in mono font — NEVER `{{ $… }}` literal text | `Arch\NoBladeLeakTest`, `CreatorProfileTest` handle assertions |
| 3 | Spot-check served HTML of home, library, prompt detail, creator profile, dashboard profile, admin users | zero occurrences of `{{ $` echo leaks | `assertNoBladeLeak` applied in `StaffViewStandaloneRenderTest`, `PromptVersionsPageTest`, card/detail tests |
| 4 | Open `/admin/update` as staff on prod | 200 with the upload form (works with token absent, cached config) — no 500 | `StaffViewStandaloneRenderTest::dashboard update view renders standalone…`, `AdminFlowsTest::admin update page loads…` |
| 5 | Look at a long-titled prompt's generated banner | title centered on both axes, ≤3 lines, ellipsis at a word boundary, nothing clipped mid-glyph | `PromptCoverTypographyTest` (overflow + ellipsis assertions) |
| 6 | Open a prompt detail as guest and as an unrelated member | "View history" link present; zero edit URLs; history page shows changelogs only (no bodies on paid prompts) | `PromptVersionsPageTest` (5 tests) |
| 7 | Admin → Overview shows version chip | `v1.4.2` chip matches the deployed release | `config('app.version')` single source |
| 8 | Admin → Users: ban a test account | banned user's next request is logged out and bounced; content stays live; admin cannot ban self | `UserBanTest` (6 tests) |
| 9 | Admin → Comp grants: issue one | recipient sees the prompt in their library; ledger row says tier `comp` with issuer + reason | `CompGrantTest` (5 tests) |
| 10 | `cd core && php artisan test` | 194 passed, 844 assertions | whole suite |

## 8. File map (v1.1 → v1.3.0)

```
core/app/Console/Commands/UpdateFromRelease.php   + idempotent db:seed step
core/app/Http/Controllers/Admin/UserAdminController.php   + toggleVerified
core/app/Http/Controllers/RatingController.php    NEW
core/app/Models/Rating.php                        NEW
core/app/Models/Prompt.php                        + ratings()
core/app/Models/User.php                          + is_verified (fillable, cast)
core/app/Services/PromptSearchService.php         + $type param, searchCreators()
core/database/migrations/2026_09_27_000100_add_is_verified_to_users_table.php  NEW
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
core/app/Services/ImageUploadService.php          NEW v1.2 (GD compress & re-encode)
core/app/Http/Controllers/Dashboard/ProfileController.php  NEW v1.2 (profile editing)
core/app/Http/Controllers/Dashboard/PromptEditController.php  + remove_cover
core/app/Http/Requests/PromptFormRequest.php      + cover_image rules (image type only)
core/resources/views/components/prompt-cover.blade.php  NEW v1.2 (SVG generated banners)
core/resources/views/dashboard/profile.blade.php  NEW v1.2 (+ @method('PUT') in v1.3)
core/resources/views/components/navbar.blade.php  account dropdown (v1.2)
core/public/index.php                             RESTORED v1.3 (real Laravel front controller)
deploy/public_html/update.php                     pairs-fix v1.2, shipped in zip since v1.3
deploy/build-update-zip.php                       v1.3.0: ships public_html/ allow-list
```

## 9. Live incident log (for context)

| Date | Symptom | Root cause | Fixed in |
| --- | --- | --- | --- |
| v1.0 era | `/admin/update` 500 | view referenced removed route name `dashboard.update.run` | v1.1.0 |
| v1.2 era | `update.php` "Cannot use string as array on line 362" | `extract_release_zip()` returned strings, caller destructured pairs; zip was core-only so the fix never reached the docroot | repo v1.2.0; delivered live by v1.3.0 zip |
| v1.2 era | 405 Method Not Allowed on `/dashboard/profile` upload | form missing `@method('PUT')` | v1.3.0 |
| v1.2 era | name overlapping cover photo on creator profiles | `-mt` overlap without top padding on the name block | v1.3.0 |
| v1.4.2 (2026-09-28) | SQLSTATE 1091 replaying `121000_make_license_grants_comp_capable` on prod | interrupted 121000 left partial DDL (unique index dropped, migrations row unwritten) — MySQL DDL autocommits, so the replay dropped an already-dropped index; SQLite never dropped the index at all, which later broke pack fulfillment (UNIQUE constraint on `order_item_id`) | v1.4.3 (120999 repair + 121100 guarded drop + SchemaInspector + drop-guard arch test + fatal-path maintenance lock) |
| v1.4.3 (2026-09-29) | Creator avatars never rendered — initials everywhere despite uploads working | identity surfaces hand-rolled initials markup; no shared avatar component existed (the "trivial bug") | v1.4.4 (x-user-avatar on every surface, AvatarFidelityTest) |
| v1.4.4 (2026-09-29) | Prod deploy ran from-zero migrations + dev-path view:cache failure; site served against a stray SQLite | the v1.4.4 update zip carried a dev `bootstrap/cache/config.php` (dev view.paths + dev SQLite connection); extract-before-boot poisoned the host boot; post-run config:cache masked the poison | v1.4.5 (D1 zip exclusion + audit, D2 pre-boot purge, D3 in-process config reload, D4 production seeder gate, D9 stray-SQLite warning, D10 parity acceptance) |
| v1.4.3 (2026-09-29) | Founder: "I cannot update manual payment methods" | key mismatch: form field `manual_enabled` vs runtime key `manual_payment_enabled` — saves landed on a key nothing read (C2) | v1.4.4 (canonical-key persistence + rendered-form tests + manual methods v2) |
| v1.4.5 (2026-09-29) | Mobile navbar showed neither brand mark nor logo (founder screenshot) | `app-layout` never forwarded `brand-mark-path` to `x-navbar` — the mobile mark branch was dead code; the existing test only asserted `md:hidden` somewhere in the page, which passed while mobile showed nothing | v1.5.0 (T11: prop forwarded + regex assertions on BOTH responsive rows) |
| v1.4.5 (2026-09-29) | Any populated pack landing page 500'd | `latest()` inside the `publishedPrompts` eager load emitted a bare `order by created_at` — ambiguous between `prompts` and `pack_prompt` inside the belongsToMany window function | v1.5.0 (qualified `orderByDesc('prompts.created_at')`; landing test renders a populated pack) |
| pre-1.5.0 | Founder live-edit error: prompt saves rejected / tools broke | `recommended_tools` validated as bare strings — arbitrary names slipped in and broke logo rendering; no modality/type coupling | v1.5.0 (T6: registry + modality validation, repro test first) |

— Prepared by Codebuff. Questions about any section: start from the file map and read the docblocks; every non-obvious decision is commented inline in the code.
