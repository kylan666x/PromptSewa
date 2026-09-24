# PromptSewa — Architecture

> How the system is designed and, more importantly, **why** — with the cPanel
> constraints that shaped every decision. Product requirements: `prd.md`.

## 1. Deployment Topology

```
/home/CPANELUSER/
├── core/                        ← entire Laravel app (NOT web-accessible)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/                 ← runtime: logs, cache, sessions, uploads
│   ├── vendor/
│   ├── .env                     ← secrets, unreachable by HTTP
│   └── public/                  ← Laravel's docroot (build assets live here)
└── public_html/                 ← cPanel's ONLY web-visible directory
    ├── .htaccess                ← routes everything into the docroot front controller
    ├── index.php                ← front controller; requires the sibling core/ app
    ├── .user.ini                ← PHP-FPM/LiteSpeed per-dir limits
    ├── install.php              ← one-file web installer (deleted after install)
    └── update.php               ← one-file token-protected web updater
```

**Why:** cPanel gives you exactly one docroot (`public_html`). Rather than
fighting it, we keep the app *outside* it and use two tiny files as a bridge.
The framework, `.env`, and `storage/` become physically unreachable by HTTP —
a security property, not a trick. The docroot front controller boots the
sibling `core/` app directly (works on Apache and LiteSpeed with plain
`mod_rewrite`), and static build assets are copied into the docroot by the
installer/updater so they serve without rewrites. See `deploy/public_html/`
for the actual files and `docs/DEPLOYMENT.md` for the step-by-step upload.

## 2. Stack Decisions & Rationale

| Decision | Rationale (cPanel reality) |
| --- | --- |
| Laravel 12 skeleton (PHP ≥ 8.2) | PRD specified Laravel 11, but every 11.x release is blocked by known security advisories; Laravel 12 uses the same slim skeleton, requires the same PHP, and receives fixes. |
| `database` sessions/queue/cache | No Redis/Memcached daemons exist on cheap shared hosting. The DB is already there, is backed up by the host, and survives reboots. |
| Cron-driven queue (`queue:work --stop-when-empty` inside the scheduler) | No Supervisor, no long-running processes, no `queue:restart` bookkeeping. The single cPanel cron (`* * * * * php artisan schedule:run`) drains jobs every minute. |
| MySQL FULLTEXT via **Laravel Scout `database` driver** (committed default) | Meilisearch cannot run on shared hosting. Scout's `database` engine runs FULLTEXT `MATCH()` against the denormalized `search_text` column in production and transparently falls back to `LIKE` on SQLite in dev/tests — one config value (`SCOUT_DRIVER`), zero app-code change to move to Meilisearch in Phase 4. Controllers never write raw `MATCH()`/`LIKE`. |
| Denormalized counters (`download_count`, `upvotes`, …) | Listing pages must not run `COUNT(*)` over joins on a shared box. Counters are bumped transactionally by events/jobs. |
| Immutable `prompt_versions` rows | Git-like history (PRD §3.3) and future PR-diffing depend on append-only snapshots. |
| Blade + Tailwind (compiled locally) + Alpine.js | No SPA framework → no server Node.js. Assets are hashed by Vite locally, uploaded once, cached hard by `.htaccess`. The post-UI-001 form kit, prompt viewer, and detail-page interactions are Alpine components in `resources/js/app.js`. |
| Settings-driven branding (`settings` table via `SettingsService`) | Logo, favicon, site name, tagline and contact emails are runtime data, not code — the founder manages them from Admin → Brand without a deploy. Views receive them from a single view composer on `components.app-layout`. |
| Local `storage/app/public` via symlink, or B2 (S3-compatible) | cPanel disk is small and slow; B2 is the cheapest escape hatch, and the S3 adapter requires only `league/flysystem-aws-s3-v3` — no extra daemons. |

## 3. Data Model (v1 core)

```
users
  id, name, email, password, role*, xp*, banner_path, avatar_path,
  bio, payout_handle, promoted_to_creator_at, deleted_at
  * indexed — role drives RBAC checks on every request, xp drives leaderboards

categories
  id, parent_id →categories.id (nullable, self-FK), name, slug (unique),
  icon, type_scope (nullable: text|image|video — scoped categories are only
    offered for that prompt type in the create/edit forms),
  position, is_active  |  index (is_active, position)

prompts                        ← the marketplace LISTING
  id, user_id →users.id (CASCADE), category_id →categories.id (NULL),
  forked_from_prompt_id →prompts.id (NULL, self-FK — fork lineage),
  title, slug (unique), description, search_text (FULLTEXT),
  license_tier personal|commercial, price_cents,
  type text|image|video (drives form context + detail layout),
  cover_image_path (nullable — cards/detail render a deterministic
    placeholder when NULL), visibility public|private,
  download_count, fork_count, upvotes, downvotes (denormalized, indexed),
  status draft|pending|published|rejected, timestamps, soft-deletes
  indexes: (status, created_at)  (category_id, status)  (user_id, status)

prompt_versions                ← immutable content SNAPSHOTS (append-only)
  id, prompt_id →prompts.id (CASCADE), version_number (unique per prompt),
  body (the sellable content, with {{variable}} tokens), changelog,
  variables (JSON), tags (JSON),
  recommended_tools (JSON — chips rendered on the detail page),
  audience ("Perfect for" line), tips (JSON — ordered usage tips),
  user_id →users.id (author of this version — differs from owner on merged PRs),
  status draft|pending|published|rejected, timestamps
  indexes: unique (prompt_id, version_number), (prompt_id, created_at)
```

Later tables (per PRD): `reviews`, `votes`, `badges`, `user_badges`,
`forum_threads/posts`, `api_keys` (encrypted), `affiliate_links`.

### Public creator profiles

`GET /creators/{user}` (`creators.show`) renders a user's public identity:
name, role badges, bio, and stats (published prompt count, total sales,
join date) plus their paginated **published-only** catalog via
`Prompt::publicListing()`. Soft-deleted (banned) accounts 404. Creator
chips on prompt cards and the prompt detail page link here — every
displayed creator name is clickable.

## 3.1 Money Model (Sprint 2)

Currency is **NPR**; every monetary column is `*_paisa` BIGINT (1 NPR = 100
paisa). All arithmetic is integer-based — floats never touch money. The
commerce layer is deliberately layered:

```
products (1:1 with prompts: pricing, compare-at, status)
orders (buyer, totals, idempotency_key, status machine)
  └─ order_items (snapshot of product/prompt/price at purchase time)
payments (gateway attempts: eSewa, manual proofs)
license_grants (entitlements issued AFTER payment confirmation)
wallets → wallet_transactions (IMMUTABLE ledger, insert-only)
```

Design rules enforced in `AGENTS.md`: ledger rows are never updated or
deleted; balances are derived from the ledger with a cached column updated
only inside the appending transaction under `lockForUpdate()`; revenue
splits (creator 80% / platform 20%) are two ledger entries against the same
order reference; every webhook validates its HMAC signature before any
write and is idempotent on `[gateway, gateway_transaction_id]`.

## 3.2 Portability Gates (Sprint 1 verdict)

The cPanel pivot must not leak into domain code:

| Concern | Abstraction | Phase 4 migration path |
| --- | --- | --- |
| Search | Laravel Scout (`Prompt::search()`) | `SCOUT_DRIVER=meilisearch` |
| Queue | Serializable, idempotent queued jobs | `QUEUE_CONNECTION=redis` + Horizon |
| Storage | `Storage::disk('public'/'private')` | `FILESYSTEM_DISK=s3` (B2) |
| Vector search | Deferred — never hacked into MySQL | Postgres (pgvector) or external API |

## 4. Versioning & Forking Flow (PRD §3.3)

- **Edit** a prompt → new `prompt_versions` row (`version_number` = max + 1,
  enforced unique per prompt). Old versions are never mutated — this is
  exactly how `PromptEditController` behaves, and the listing's public page
  always renders `latestVersion`.
- **Create** a prompt → v1 snapshot committed in the same transaction as
  the listing row; every new listing enters `pending` review status.
- **Fork** → new `prompts` row with `forked_from_prompt_id` set; its v1
  copies the parent's latest `prompt_versions` snapshot. `fork_count` bumps
  on the parent.
- **Pull request** → a `prompt_versions` row with `status=pending` authored by
  a non-owner on the *original* prompt; the owner publishes it (bumping
  `version_number`) or rejects it.

## 5. Request → Response on Shared Hosting

```
Browser → Apache (public_html/.htaccess)
        → core/public/index.php
        → Laravel kernel
        → session read (users table / sessions table)
        → route → Controller → Eloquent (MySQL) → Blade render
        → response (Vite-hashed static assets served directly by Apache)
```

Anything slow (emails, LLM calls, payout calculations) is dispatched to the
`database` queue and drained by the next cron tick — at worst ~60s of
latency, which is acceptable for this class of work on shared hosting.

## 6. Security Model

- RBAC roles (`member|creator|moderator|admin`) on `users.role`, checked by
  a small hierarchy helper (`User::isAtLeast()`), `App\Policies\PromptPolicy`
  (view/update/delete — owner or moderator), and Laravel gates.
- Secrets/LLM keys: `Crypt::encryptString()` before persistence (APP_KEY is
  the KMS).
- `display_errors` forced off in production (`bootstrap/app.php`).
- `.env`, `vendor/`, `storage/` live outside the docroot by construction.
- Standard Laravel CSRF on all state-changing routes.
- Prompt-body gating is server-side only: paid listings render a locked
  teaser publicly; the full body is sent to the browser solely for the
  owner, moderators, and viewers with an active `license_grants` row
  (`PromptController::canViewFullBody()`).

## 7. Observability on a Budget

- Single rotating log in `core/storage/logs/` (cPanel file manager readable).
- Cron failures email `DEPLOY_ALERT_EMAIL` (`emailOutputOnFailure` in the
  schedule definition).
- `GET /up` health route for uptime monitors (free tiers exist).
