# AGENTS.md — deploy/ (cPanel upload assets & release tooling)

> Scope: everything under `deploy/` — the files that make PromptVellum
> installable and updatable on shared cPanel / LiteSpeed hosting without SSH.

## Contents

| File | Purpose |
| --- | --- |
| `public_html/index.php` | Docroot front controller; requires the sibling `core/` app (auto-detects `core` inside `public_html` too) |
| `public_html/.htaccess` | Routes everything to docroot `index.php`; maps `/storage`, `/build`, favicon, robots.txt into `../core/`; blocks dotfiles |
| `public_html/.user.ini` | LiteSpeed/PHP-FPM per-directory limits (uploads, memory, execution time) |
| `public_html/install.php` | One-file web installer — preflight, DB test, `.env` generation, migrations, admin user, asset copy, self-locking + self-deleting |
| `public_html/update.php` | One-file web updater — token-protected; maintenance mode → migrate → caches → asset sync → back online. Accepts zips containing `core/` AND `public_html/` entries (merges each to its place). Returns `list<array{0:string,1:string}>` [level, line] log pairs — do not regress to string concat |
| `build-release.php` | Builds `dist/promptsewa-upload.zip` (core + vendor + built assets + docroot files). `--no-zip` stages the tree for CI FTP mirroring |
| `build-update-zip.php` | Builds the code-only `dist/promptsewa-<VERSION>-update.zip`: `core/` **plus a `public_html/` allow-list** (`update.php`, `index.php`, `.htaccess`, `.user.ini` — so docroot fixes reach live). Never ships `install.php` or `.update-token`. Warns if `composer.lock` drifted from the 1.0.0 baseline (code-only zip cannot update `vendor/`) |

## Prime Directive (restated)

Everything here must run on $3/mo shared cPanel hosting: **no Docker, no
Redis, no Supervisor, no server-side Node, no SSH requirement**. The
installer/updater boot Laravel **in-process** (`$kernel->call()`) because
`exec()` is disabled on most shared hosts. Apache *and* LiteSpeed must both
work — no webserver-specific PHP APIs, and `.htaccess` guards everything
with `<IfModule>`.

## Security Rules

1. `install.php` refuses to run when `core/.env` contains an `APP_KEY`
   (or the `installed.lock` file exists) and offers to delete itself.
2. `update.php` requires the random token in `public_html/.update-token`
   (a dotfile — web-invisible). The token alone authenticates (CI/CD posts
   it without a session); it is never displayed on-screen by the installer.
3. Never print secrets (DB passwords, tokens) in installer/updater output.
4. `.env` is written with `chmod 0600` where the filesystem allows it.
5. The update zip may only embed the four allow-listed docroot files —
   NEVER `install.php` (post-install servers must not re-run it) and NEVER
   `.update-token` (the live token must never transit the repo).
6. **Front-controller variants must never be swapped:**
   `deploy/public_html/index.php` (cPanel docroot, boots a sibling `core/`)
   and `core/public/index.php` (real Laravel front controller for
   `artisan serve`) look similar but are not interchangeable — mixing them
   up 503s local serving. v1.3.0 fixed exactly this swap. The update zip
   ships the docroot variant to `public_html/` only.

## Layout Contract

```
/home/CPANELUSER/
├── core/           ← Laravel app (vendor/, .env, storage/) — NOT web-visible
└── public_html/    ← index.php, .htaccess, .user.ini, (install/update.php), build/
```

`index.php` and the two PHP tools auto-detect `core/` as a sibling of
`public_html/` first, then as a child — keep both paths working.

## Building a Release

```bash
# Windows (this workstation):
C:\PHP\bin\php.exe deploy\build-release.php
# CI (Linux): php deploy/build-release.php
```

Requires `php.exe` on the PATH-or-full-path with `ext-zip`; Composer and
npm are auto-detected for vendor/ and public/build/ when missing.
The zip extracts as `core/` + `public_html/` directly over `/home/USER/`.
