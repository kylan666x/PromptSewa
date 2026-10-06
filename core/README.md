# PromptSewa — `core/`

The Laravel application. Product overview, architecture decisions, and the
cPanel deployment runbook live one level up (`../README.md`, `../docs/`).

## Local Development

Requirements: PHP 8.2+, Composer 2, Node.js 20+ (local asset builds only).

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed     # SQLite out of the box + demo marketplace data
npm install && npm run build
php artisan serve              # http://127.0.0.1:8000
```

The seeders are **idempotent** (safe to re-run; existing rows untouched):
demo creators, the bulk catalog (every category) and the flagship
JustShipItAI account.

Demo logins:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@promptsewa.test` (some older local DBs: `admin@promptvellum.test`) | `password` |
| Flagship creator | `justshipitai@gmail.com` | `JustShipIt!2026` |
| Creators | `bibek@` · `maya@` · `dorje@promptsewa.test` | `password` |

## Tests & Quality

```bash
php artisan test        # Pest suite, in-memory SQLite (644 tests across 100 files)
vendor/bin/pint         # code style (Laravel preset)
npm run build           # compile CSS/JS — commit source, never public/build/
```

Tests run with the same `database` session/queue drivers production uses,
so a green suite exercises production behavior. New schema/model changes
require migration → model → factory → feature test, all four (see
`../AGENTS.md`).

## Code Map

```
app/
├── Console/Commands/            UpdateFromRelease (pv:update pipeline)
├── Http/Controllers/            storefront, library, auth, detail page
│   ├── Dashboard/               create/edit prompts (type-aware), profile editing
│   └── Admin/                   moderation, users, packs, payments, brand, update
├── Http/Requests/               PromptFormRequest (validation + normalization)
├── Models/                      Prompt, Order/OrderItem, SikkaTransaction, Membership, …
├── Policies/                    PromptPolicy (view/update/delete)
└── Services/
    ├── CheckoutService          order creation, eSewa + manual settlement
    ├── SikkaService             credit rail: top-up, spend, cash-out
    ├── WalletService            NPR ledger: sale credits, payout holds
    ├── MembershipService        plans, 30-day stipends, unlimited_unlock
    ├── GamificationService      XP, badges, criteria thresholds
    ├── EntitlementService       license grants
    ├── ImageUploadService       GD compress & re-encode (covers/banners/avatars/logos)
    └── PromptSearchService      Scout search + creator search
resources/
├── js/app.js                    Alpine components (promptForm, promptViewer)
└── views/
    ├── components/              layout, navbar, cards, form kit, code-block,
    │                            verified-badge, prompt-cover (generated SVG banners)
    ├── creators/show.blade.php  public creator profile (banner + overlapping avatar)
    ├── dashboard/profile.blade.php  avatar/banner editing (@method('PUT')!)
    └── prompts/show.blade.php   public detail page (copy, variables, gating, ratings)
```

## Conventions & Gotchas (regression-critical)

- Money is integer paisa end-to-end; never floats. Sikka credits are whole
  integers.
- `wallet_transactions` / `sikka_transactions` are **insert-only** — the models
  throw on UPDATE or DELETE; balances are SUMs over the ledger under
  `lockForUpdate()`, never a cached column.
- A **sale is a paid order line** naming the listing (`OrderItem::paidSales*`),
  not `prompts.sales_count` — that legacy column has no writer and reads 0.
- Checkout, top-ups, ledger writes and webhooks take an **idempotency key** with
  a UNIQUE constraint; grants are created only after a verified payment.
- Search goes through `Prompt::search()` / `PromptSearchService` — no raw
  `LIKE`/`MATCH()` in controllers.
- Editing a prompt appends a new `prompt_versions` row; history is immutable.
- All user-facing content is Blade-escaped; prompt bodies are data, never markup.
- **Blade forms targeting PUT routes must include `@method('PUT')`** next to
  `@csrf` — omitting it turns every submit into a 405 (shipped once in v1.2.0).
- **`@js()` inside `x-data` must use double-quoted** HTML attributes (single
  quotes truncate the attribute → empty Alpine state).
- **No `use` statements in Blade `@php` blocks of anonymous components** —
  they compile inside `shouldRender()` and PHP fatals; fully qualify instead.
- `public/index.php` here is the **local** front controller for
  `artisan serve`; the cPanel docroot variant lives only in
  `../deploy/public_html/index.php`. Never swap them.
