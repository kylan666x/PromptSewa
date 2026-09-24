# PromptVellum — deploy/public_html

Files that get uploaded **into your cPanel account's `public_html/` directory**
(nothing else belongs there). The Laravel application lives one level up, in
`/home/CPANELUSER/core/`, unreachable by HTTP. All of these run identically on
Apache and LiteSpeed.

| File          | Purpose                                                                   |
| ------------- | ------------------------------------------------------------------------- |
| `.htaccess`   | Routes requests to the docroot `index.php`; maps `/storage`, `/build` into `core/`; blocks dotfiles |
| `index.php`   | Front controller — boots the hidden `core/` app; 503s with an install.php link if not installed |
| `.user.ini`   | cPanel PHP-FPM/LiteSpeed per-directory limits (uploads, memory, exec time) |
| `install.php` | **One-file web installer** — preflight checks, DB test, `.env`, migrations, admin user, asset copy; self-locks & self-deletes |
| `update.php`  | **One-file web updater** — token-protected; maintenance mode → migrate → caches → asset sync |

See [`docs/DEPLOYMENT.md`](../../docs/DEPLOYMENT.md) for the full flow.

**Release builds** (`promptvellum-upload.zip` containing `core/` +
`public_html/`): run `php deploy/build-release.php`, or download the artifact
from the GitHub Actions *Release / Deploy* workflow.
