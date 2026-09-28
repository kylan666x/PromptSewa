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
demo creators, the bulk catalog (240 prompts) and the flagship
JustShipItAI account.

Demo logins:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@promptsewa.test` (some older local DBs: `admin@promptvellum.test`) | `password` |
| Flagship creator | `justshipitai@gmail.com` | `JustShipIt!2026` |
| Creators | `bibek@` · `maya@` · `dorje@promptsewa.test` | `password` |

## Tests & Quality

```bash
php artisan test        # Pest feature suite, in-memory SQLite (102 tests / 416 assertions)
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
├── Models/                      Prompt, PromptVersion, Category, Product, Rating, …
├── Policies/                    PromptPolicy (view/update/delete)
└── Services/
    ├── CheckoutService          eSewa + manual payments
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

- Money is integer paisa end-to-end; never floats.
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
