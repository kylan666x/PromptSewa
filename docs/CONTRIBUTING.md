# Contributing to PromptSewa

Thanks for helping build PromptSewa! This document describes how we work.

## Development Environment

Requirements: **PHP 8.2+** (with `pdo_mysql`, `pdo_sqlite`, `mbstring`,
`openssl`, `fileinfo`, `intl`), **Composer 2**, **Node.js 20+** (local asset
compilation only — Node never runs on the production server).

```bash
git clone git@github.com:misterBSynthesisI/prompt.vellum.git
cd prompt.vellum/core
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # SQLite locally — zero setup + demo data
npm install && npm run dev   # Tailwind + Alpine via Vite
php artisan serve            # http://127.0.0.1:8000
```

Tests run against in-memory SQLite with the **same database queue/session
drivers production uses** — if it passes locally, it exercises production
behavior:

```bash
php artisan test
vendor/bin/pint              # code style (PSR-12-ish, Laravel preset)
```

## The Prime Directive

This app deploys to **cPanel shared hosting**: no Docker, no Redis, no
Memcached, no Supervisor, no server-side Node. Cache, queue, and sessions
all use the `database` driver. If your change needs any of those, redesign
it — see `docs/ARCHITECTURE.md` for the patterns we use instead.

## Commit Discipline (Atomic Commits)

We follow the standards used by large engineering organizations:
**one logical change per commit**, message written for the reader six months
from now, in **Conventional Commits** format:

```
<type>(<scope>): <imperative summary, <= 72 chars>

[optional body: WHY the change was made, wrap at 100 chars]

[optional footer(s): BREAKING CHANGE:, Refs: #123]
```

Allowed types: `feat`, `fix`, `docs`, `refactor`, `perf`, `test`, `build`,
`ci`, `chore`. Scope is the area: `migrations`, `models`, `deploy`,
`scheduler`, `frontend`, `queue`…

Examples of **atomic** commit sets (one feature = several commits, never one
giant "add prompts feature" commit):

```
feat(migrations): add prompts, prompt_versions and categories tables
feat(models): add Prompt, PromptVersion and Category models
test(prompts): cover version chain and fork lineage
```

```
fix(queue): stop worker from lingering past cron window
```

### Rules

1. **Every commit compiles and passes tests.** Bisectability is sacred.
2. **Never mix** a refactor with a behavior change, or feature code with
   documentation. Split them.
3. **Self-review before pushing** — re-read the diff, check for stray
   debugging, secrets, or unrelated churn.
4. **Never commit** `.env`, secrets, `vendor/`, `node_modules/`, or
   `public/build/` output.
5. Large features go through a branch + pull request; tiny fixes may commit
   straight to `main` with a clear message.

## Pull Requests

- Small PRs win. If the diff exceeds ~400 lines, split it.
- Fill in the what/why; link issues with `Refs: #N`.
- CI must be green (PHP lint → tests). Deployment docs updates ship with
  any change that affects `deploy/` or `.env.example`.

## Reporting Bugs

Open an issue with: what you did, what you expected, what happened, and
`php artisan about` output (redact anything sensitive).
