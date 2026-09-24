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
| `public_html/update.php` | One-file web updater — token-protected; maintenance mode → migrate → caches → asset sync → back online |
| `build-release.php` | Builds `dist/promptvellum-upload.zip` (core + vendor + built assets + docroot files). `--no-zip` stages the tree for CI FTP mirroring |

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
