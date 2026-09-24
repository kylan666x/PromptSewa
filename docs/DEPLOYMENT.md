# PromptSewa — cPanel Deployment Runbook

> From zero to live on a standard shared-cPanel host (Apache or **LiteSpeed**).
> Total cost: one shared hosting plan + one domain. The **recommended path
> (Option A)** needs no SSH, no Composer and no Node on the server — just
> cPanel's File Manager, MySQL® Databases and one browser visit to
> `install.php`.

## 0. What you need

- cPanel account (PHP 8.2+ selectable in *MultiPHP Manager*)
- One MySQL database + user (created in *MySQL® Databases*)
- The release zip `promptsewa-upload.zip`:
  - **download it from GitHub Actions** (Run *Release / Deploy* workflow →
    download the `promptsewa-upload-*` artifact), or
  - build locally: `php deploy/build-release.php` (needs local PHP + Composer + Node)

The zip already contains `vendor/` and compiled assets — the server needs
**nothing else**.

---

## Option A — Web installer (recommended, ~10 minutes, no SSH)

### A1. Set PHP version & extensions

- *MultiPHP Manager* → PHP **8.2 / 8.3 / 8.4** for the domain.
- *Select PHP Version* → enable: `pdo_mysql`, `mbstring`, `openssl`,
  `fileinfo`, `intl`, `gd`, `zip`, `exif`, `bcmath`, `curl`.

### A2. Create the database

1. *MySQL® Databases* → create DB, e.g. `cpaneluser_promptsewa`.
2. Create user `cpaneluser_pv`, set a strong password.
3. Add the user to the DB with **ALL PRIVILEGES** (migrations need DDL).

### A3. Upload the release

1. Upload `promptsewa-upload.zip` to `/home/CPANELUSER/` (the **home**
   directory, not public_html).
2. Select it in File Manager → **Extract**. You now have:

```
/home/CPANELUSER/
├── core/          ← the whole Laravel app (vendor/, .env templates, …)
└── public_html/   ← index.php, .htaccess, .user.ini, install.php, update.php
```

If `public_html/` already contained files (a default cPanel placeholder),
that's fine — the zip's files overwrite/coexist.

### A4. Run the installer

Open **`https://yourdomain.com/install.php`** in a browser. It will:

1. Check PHP version, all required extensions and writable paths (fix
   anything red via *MultiPHP Manager* / *Select PHP Version*, then reload).
2. Ask for the DB credentials from A2 (host is almost always `localhost`),
   your site URL, an admin name/email/password and optional SMTP.
3. On submit it: tests the DB → writes `core/.env` (with a fresh
   `APP_KEY`; any existing `.env` is backed up) → runs all migrations →
   creates the admin account → builds config/route/view caches → copies
   compiled assets into `public_html/build/` → writes the update token
   (`.update-token`) → locks itself.

No demo/fake content is ever seeded in production.

### A5. Finish

1. Click **Delete install.php now** on the success page (it self-deletes).
2. cPanel → *Cron Jobs* → Once Per Minute:

```cron
* * * * * cd /home/CPANELUSER/core && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Confirm the PHP binary path with `which php` in cPanel Terminal (common:
`/usr/local/bin/php`, `/opt/cpanel/ea-php82/root/usr/bin/php`, or
`/usr/local/lsws/...` on LiteSpeed setups). This one cron drives queues,
the scheduler and nightly housekeeping — no Supervisor.

### A6. Smoke test

1. `https://yourdomain.com/up` → 200 OK.
2. `https://yourdomain.com/` → landing page renders styled.
3. `/prompts?q=email` → search works (MySQL FULLTEXT).
4. Register a user → sessions (`database` driver) work.
5. `/core/…`, `/.env`, `/.update-token` → 404/403 (app + dotfiles are hidden).

---

## Option B — CLI runbook (SSH/Terminal available)

Build locally, then follow the classic path:

```bash
cd core
composer install --no-dev --optimize-autoloader
npm install && npm run build
# upload contents of core/ → /home/CPANELUSER/core/
# upload deploy/public_html/* → /home/CPANELUSER/public_html/
```

Then configure `core/.env` by hand (see the `.env.example` comments; key
settings: `APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION=mysql`,
`SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`,
`SCOUT_DRIVER=database`), and from cPanel Terminal:

```bash
cd /home/CPANELUSER/core
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Set up the same 1-minute cron as A5. The docroot files from
`deploy/public_html/` behave identically to Option A.

---

## Updates

### Path 1 — upload.zip + update.php (works everywhere)

1. Get the new `promptsewa-upload.zip` (Actions artifact or local build).
2. Upload to `/home/CPANELUSER/` → **Extract** (overwrites app files).
3. Open **`https://yourdomain.com/update.php`**, paste the token from
   `public_html/.update-token` (File Manager → *Show Hidden Files*), submit.
4. The updater: maintenance mode on → migrations → config/route/view caches →
   re-syncs `public_html/build` → maintenance mode off.
5. Delete `update.php` afterwards if you prefer (it stays token-protected).

> **Always take a cPanel backup** (*JetBackup* or manual) before updating.

### Path 2 — GitHub CI/CD (hands-free, if your host allows FTP/SFTP)

One-time setup, then every tag `v*` deploys automatically:

1. Repo → *Settings → Secrets and variables → Actions*:
   - **Variable** `CPANEL_DEPLOY_ENABLED = true`
   - **Secrets**: `CPANEL_FTP_HOST` (e.g. `ftp://ftp.yourdomain.com:21`,
     or `sftp://host:22` if SSH is enabled), `CPANEL_FTP_USER`,
     `CPANEL_FTP_PASS`
   - Optional (auto-migrations): variable `UPDATE_URL` =
     `https://yourdomain.com/update.php` and secret `UPDATE_TOKEN` =
     the value of `public_html/.update-token`.
2. Push a tag (`git tag v1.0.1 && git push --tags`).
3. The workflow builds the package, mirrors `core/` + `public_html/` via
   lftp, then triggers `update.php` (migrations + caches) — no manual step.

### Path 3 — cPanel Git™ Version Control (if your host enables it)

1. cPanel → *Git™ Version Control* → clone this repository into
   `/home/CPANELUSER/repos/promptsewa`.
2. After each `git pull` in that UI, copy/merge the tree over `core/` (or
   point a cron at `rsync -a repos/promptsewa/core/ core/`), then open
   `update.php` once.
3. `vendor/` and `public/build/` are **not in git** — build them via the
   Actions artifact (Path 1/2) or locally and upload once; after that,
   `update.php` handles code-only updates cleanly.

---

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| `install.php` shows failing extension checks | *Select PHP Version* → tick the listed extensions, reload. |
| Installer can't connect to MySQL | DB names/users on cPanel are prefixed (`cpaneluser_…`); host is `localhost`; grant ALL PRIVILEGES. |
| 500 with blank page | Check `core/storage/logs/laravel.log`; usually a wrong DB password. |
| "vendor/ not found" | You uploaded a source zip without `vendor/` — use the release artifact from CI or run `deploy/build-release.php`. |
| Assets 404 / unstyled | Run `update.php` once (it re-copies `public_html/build/`), or check `.htaccess` upload (hidden file!). |
| Styles unstyled after update | Stale hashed assets — `update.php` clears `public_html/build/` and re-copies; hard-refresh the browser. |
| Jobs never run | Cron path wrong (`which php`), or `schedule:run` errors — temporarily log output to a file instead of `/dev/null`. |
| `419 CSRF` | `APP_URL` doesn't match the browsing domain (subdomain vs domain). |
| Sessions reset each click | `SESSION_DOMAIN` mismatch or `sessions` table missing (run `update.php`). |
| Updater says invalid token | `public_html/.update-token` missing — recreate it with any long random string via File Manager (Show Hidden Files). |
