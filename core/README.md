# PromptVellum — `core/`

The Laravel application. Product overview, architecture decisions, and the
cPanel deployment runbook live one level up (`../README.md`, `../docs/`).

## Local Development

Requirements: PHP 8.2+, Composer 2, Node.js 20+ (local asset builds only).

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed     # SQLite out of the box + demo marketplace data
npm install && npm run dev
php artisan serve              # http://127.0.0.1:8000
```

`php artisan migrate:fresh --seed` reloads the demo catalog: 1 admin,
3 creators, 12 categories (with image/video subcategories), 22 published
prompts across text/image/video types, plus draft/pending/private/rejected
listings for verifying visibility.

Demo logins (all `password`):

| Role    | Email                    |
| ------- | ------------------------ |
| Admin   | `admin@promptvellum.test`|
| Creators| `bibek@` · `maya@` · `dorje@promptvellum.test` |

## Tests & Quality

```bash
php artisan test        # feature suite, in-memory SQLite
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
├── Http/Controllers/            storefront, library, auth, detail page
│   └── Dashboard/               create/edit prompt flows (type-aware forms)
├── Http/Requests/               PromptFormRequest (validation + normalization)
├── Models/                      Prompt, PromptVersion, Category, Product, …
├── Policies/                    PromptPolicy (view/update/delete)
└── Services/                    PromptSearchService (Scout wrapper), EntitlementService
resources/
├── js/app.js                    Alpine components (promptForm, promptViewer)
└── views/
    ├── components/              layout, navbar, cards, form kit, code-block
    ├── dashboard/prompts/       create/edit forms
    └── prompts/show.blade.php   public detail page (copy, variables, gating)
```

## Conventions

- Money is integer paisa end-to-end; never floats.
- Search goes through `Prompt::search()` / `PromptSearchService` — no raw
  `LIKE`/`MATCH()` in controllers.
- Editing a prompt appends a new `prompt_versions` row; history is immutable.
- All user-facing content is Blade-escaped; prompt bodies are data, never markup.
