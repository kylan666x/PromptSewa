# PromptSewa — Engineering Handoff

**Prepared:** 2026-09-30 · **Current version:** v1.5.2
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

### v1.7.7 — Bug-Hunt Raid (this release)

Full-catalog raid (R0–R8) on branch `raid/v1.7.7`, cut from the v1.7.6
release commit `5550630`. Every finding carries a failing repro commit, a
fix commit, and a locking test — the single record of truth is
[docs/BUG-HUNT-RAID.md](docs/BUG-HUNT-RAID.md).

- **R0 — freeze & baseline.** Suite 511 passed / 14,309 assertions; update
  zip byte-reproduced from the v1.7.6 handoff (SHA-256 `8c56f0da…`).
  **Hygiene finding: no `v1.7.6` tag exists** (local or origin — tags stop
  at v1.7.2); `main`'s release commit is the artifact of record. Retagging
  is a release-process action → mediator.
- **R1 — route × role matrix.** `RoleSurfaceMatrixTest`: every named GET
  route × 7 fixtures (364 responses) asserted against its gate's promised
  outcome, zero 500s, exactly one `<title>` per HTML 200, no Blade leaks,
  distinct IPs so throttles can't poison the sweep. Found **BH-R1-01 (P1)**
  — `/admin/comp-grants` served **200 to moderators** while its store
  endpoint is admin-only; fixed with `abort_unless(isAdmin)` (`f63b55d`).
  Also caught `storage.proofs` (see BH-P3-01 below).
- **R2 — dead-link & dead-action scan.** `DeadLinkScanTest` resolves every
  internal `href` and every form action in each surface **as the role that
  sees it**. Found five P1s, all fixed in `102c924`: admin-only nav pills
  rendered for moderators (5 dead 403 links), Users purge/adopt panels,
  Orders approve/reject buttons, Payments/Brand/Manual-methods save forms,
  and admin Prompts title links 404ing for staff on non-public rows.
  `/update.php` exempted with a reason (docroot script).
- **R3 — form round-trip inventory.** `FormRoundTripInventoryTest` reads
  the **served** form (action, verb spoof, browser semantics for
  inputs/selects/checkboxes) and submits it; a completeness scan fails the
  suite if any Blade form action is missing from the inventory. New
  round-trips: badge create/delete, frame CRUD + award/revoke, payout
  request/cancel, buy-prompt, finance approve/settle/reject, security save,
  purge/adopt preview.
- **R4 — edge-state matrix.** `EdgeStateMatrixTest`: empty fixtures,
  200-char title, Devanagari bio, deleted relations (`nullOnDelete`),
  30-row pagination, checkout with both rails, impersonation chrome across
  5 pages — **no new defects**; every state rendered honest copy.
- **R5 — comp-grant form redesign (BH-001/BH-002, founder-mandated).**
  Root cause: `CompGrantController` used the `published()` scope for the
  picker, so drafts/pending/rejected prompts were invisible and not
  grantable — "cannot see all of my prompts". Fix (`2bc402f`): full prompt
  list (all statuses) + a new reusable `x-searchable-picker` Alpine
  combobox (keyboard nav, chips, bounded list over 277 rows, no new
  dependencies), two-column editor, money via `money_npr`. Repro locked at
  `82f7c1b` (5 failing / 3 passing).
- **R6 — browser gate** (real clicks/keys under `artisan serve`; screenshot
  capture unavailable in this environment, so every step is DOM/a11y +
  server state). Eight flows PASS: comp grant end-to-end, impersonate → act
  → return, ban → login bounce, reset-mail (enumeration-proof notice, weak
  password, bogus token; reset deliberately not completed on shared
  fixtures), frame equip at two hole percents (19% / 31% insets = exactly
  (100−hole)/2), mobile dock tabs at 390×844, typeahead verified seal, and
  the **financial chain**: manual rail ON + method + pack (Rs. 599 = two
  Maya prompts) + buyer (Dorje) checkout via manual reference + proof +
  admin approve + creator payout request → reject/release → approve/settle,
  ledger verified insert-only end to end. Found **BH-R6-01 (P1)**: the
  proof upload form was a dead action (rendered only for "manual + no
  reference"); repro `0337c4f`, fixed `28ff793` — pending manual orders keep
  the form.
- **R7 — fix & lock log.** All fixed findings re-verified: the six R1–R6
  lock files → **40 passed / 1,678 assertions**.
- **R8 — release.** Chip v1.7.7 in `core/config/app.php` **and both
  builders** in lockstep; QA-MATRIX BH-block with locking test names;
  `npm run build` + `view:clear` before zipping. Final suite **536 passed /
  15,896 assertions** (2 skipped: the two install-artifact tests, because
  the v1.7.7 **install** zip is not built yet — they skip by design when
  the artifact is absent, while the source-level version-lockstep test
  still ran; Arch suite inside the number). `dist/promptsewa-1.7.7-update.zip` — **452 entries**
  (448 core + 4 docroot), **0.84 MB**, hygiene audit **CLEAN (0 forbidden
  entries)**, **SHA-256 `16a1cbe83874f3520d74911ad227795a14aaa1500c08aa46a02e903d98860dc3`**
  (rebuild is byte-identical — reproducible).
- **Arch-suite proof (`--list-tests`, the §6.49 release gate).** **538**
  tests enumerated, **6** of them Arch: `BladeFormVerbTest` (1),
  `MigrationDropGuardTest` (1), `NoBladeLeakTest` (1),
  `UserAvatarGeometryTest` (3) — all discovered, none fiction.
- **Deliberately left open (mediator calls).** **BH-P3-01**: `proofs` disk
  sets `serve => true`, registering an unused signed-URL `storage.proofs`
  route alongside the owner/staff proof route — not a leak (private
  visibility 404s without a signature), but surface worth removing in
  `config/filesystems.php`. The **missing `v1.7.6` tag**. And a copy/UX
  note: submitting a proof with a non-empty note replaces the visible
  `payment_reference` with that note (by design, no money effect).

### v1.7.6 — Auth & Mail (previous release)

- **A1 — forgot password, complete flow.** `GET/POST /forgot-password` (`throttle:6,1`), `GET /forgot-password/sent`, `GET/POST /reset-password/{token}`, all inside the existing `guest` group. Tokens come from Laravel's Password broker against the baseline `password_reset_tokens` table (no new migration), 60-minute expiry from `config/auth.php`. Three paper-world views (`auth/forgot-password`, `auth/check-email`, `auth/reset-password`), all with `x-seo` noindex, all added to the permanent SEO crawl **in the same commit** (`SeoRouteCoverageTest` map + the POST exemptions). The reset form honours `captcha_form_reset` through `<x-captcha form="reset"/>` (`BotChallengeService::FORMS` already listed `reset`).
  - **Enumeration-proof**: an unknown address lands on the *identical* "Check your email" page as a real one, and `Notification::assertNothingSent()` locks it.
  - **The floor is shared with signup.** The name/email containment + common-password rule was a 25-line closure living inside `AuthController::store`; copying it into the reset flow is how a reset ends up accepting a password signup would refuse. It now lives once in `App\Support\PasswordPolicy::rule($request, $name)` and both surfaces call it.
  - **A broken rail is reported, never swallowed**: the broker persists the token, then the notification throws (no sendmail / refused relay). The controller logs the exception class and surfaces "We could not send that email just now" instead of a cheerful redirect to "check your inbox".
- **A1 — branded mail.** `User::sendPasswordResetNotification()` is overridden to send `App\Notifications\ResetPasswordNotification`, which returns `App\Mail\ResetPasswordMail` — a Mailable carrying `mail/auth/reset-password.blade.php` (inline-CSS table layout, ink `#171715` header bar, saffron `#f5c518` button, raw URL as a fallback line) plus a plain-text alternative `mail/auth/reset-password-text.blade.php`. Returning a **Mailable** (rather than a `MailMessage` with a view) matters: `Mailable::send()` hands the mailer a *view name*, which `MailFake` silently drops, so a notification built any other way is **invisible to `Mail::fake()`** — see the lock below.
- **A2 — the view-password toggle.** New `resources/views/components/password-input.blade.php`: two mutually exclusive `x-show` svgs (eye / eye-off — never one svg with a class getter, per §6.45), `aria-pressed` bound to the state, `aria-label` swapping "Show password"/"Hide password", static `type="password"` for no-JS with `x-bind:type` for the toggle, and a focus ring on the button. It replaces the raw inputs on **login, register (both fields), reset (both fields) and the Admin → Email SMTP password**. There is **no profile password-change form in this app** (profile edit is name/username/avatar/frame), so that clause is a no-op — noted here rather than quietly skipped.
  - **Plaintext-in-HTML ban**: the component renders **no `value` on a password field, ever** — `{{ $attributes->except('value') }}` strips it defensively, so a future call site passing `value=` still ships nothing. `PasswordVisibilityTest` regexes every `<input type="password">` in the served HTML of each surface and fails on a `value` attribute.
- **A3 — the mail rail, admin-configurable.** `config/mail.php` now defaults to **`env('MAIL_MAILER', 'sendmail')`** (Laravel ships `log`, which silently discarded every reset link and made the page a lie). `.env.example` gained the `MAIL_*` block with sendmail defaults and the babal.host presets in the comment. At boot, `AppServiceProvider` calls `MailConfigService::apply()` — a **no-op when no `mail_*` settings row exists**, one query, wrapped in try/catch so a fresh install running `migrate` before the table exists is unaffected. `Mail::purge()` after every save so the probe uses what was just typed.
  - **Encryption → DSN scheme**, because Laravel 12 dropped the `encryption` key: `ssl` ⇒ `smtps` (implicit TLS, the babal.host 465 default), `tls` ⇒ `smtp` + `require_tls`, `none` ⇒ `smtp` + `auto_tls=0`. **Both switches are always written** — setting only the relevant one left the previous value behind, and an admin switching `tls → none` would keep `require_tls` and get a connection refused forever (locked by a test).
  - **Admin → Email** (`/admin/email`, moderators 403 on the page, the save and the probe), shipping with its nav pill in the same commit per §6.36. Mailer select, SMTP fields with `mail.babal.host` / `465` / `ssl` placeholders, **write-only** password (`SettingsService::SECRET_KEYS`, encrypted at rest, never echoed — an empty box keeps the saved one), from-address/name falling back to Brand, and **Send test email** to the acting admin. A failed send flashes the exception **class basename + first line only**, with the mailbox password string-scrubbed to `•••`.
  - **Silent-lie fix found while doing A3**: `app-layout` rendered `session('success')` and nothing else, so the `error` flash that `RejectBannedUsers` has been setting since v1.7.3 was **never displayed** — a suspended account bounced home with no message, and the mail probe's honest failure report would have been invisible too. A rose `role="alert"` toast now sits beside the green one.
- **A4 — release.** Chip v1.7.6 in `core/config/app.php` **and both builders** in lockstep; QA-MATRIX A-block with locking test names; `npm run build` + `view:clear` before zipping. Suite **511 passed / 14,309 assertions** (was 485 / 14,130 — the Arch suite is inside that number, proved below). `dist/promptsewa-1.7.6-update.zip` — a superset of v1.7.5: **446 entries** (442 core + 4 docroot), **0.81 MB**, hygiene audit **CLEAN (0 forbidden entries)**, **SHA-256 `8c56f0da80b35c440bfd89f9616736e23319393b3d17dac08d14f3f804bfc1ba`**. Fresh-install artifact `dist/promptsewa-1.7.6-install.zip` — **6,560 entries, 7.95 MB**, hygiene audit **CLEAN**, **SHA-256 `942a453d5020556a9229ece7168da22aff79465a63a21321f1ccfd5f034dc304`** (built with the v1.7.5 mtime pinning, so it stays reproducible).
- **Arch-suite proof (`--list-tests`, the §6.49 release gate).** `php artisan test --list-tests` enumerates **511** tests, of which six are the Arch guards — `Tests\Arch\BladeFormVerbTest`, `Tests\Arch\MigrationDropGuardTest`, `Tests\Arch\NoBladeLeakTest` and `Tests\Arch\UserAvatarGeometryTest` (3 locks) — all discovered, none fiction.
- **Locks**: `PasswordResetTest` (10), `PasswordVisibilityTest` (5), `MailConfigTest` (11). Suite **511 passed / 14,309 assertions** (was 485 / 14,130).
- **Found and fixed in passing — a flaky factory.** `UserFactory` derived handles from a faker name (`Str::slug($name).'-'.NNNN`); a long name produced a **34-character username that the app's own 30-char rule rejects on every profile save**. Any test round-tripping a factory handle failed at random, depending on which name Faker rolled. The handle is now truncated inside the factory, so the suite no longer has a coin-flip.
- **A note for the next engineer on "mail works" tests.** `Mail::assertSent()` cannot see notification mail in Laravel 12: `MailChannel` passes a Mailable to `Mailable::send()`, which calls `$mailer->send($this->buildView(), …)` — a **view name** — and `MailFake::sendMail()` records only `instanceof Mailable`. A notification can therefore be silently broken while every `Mail::fake()` assertion passes. `PasswordResetTest` reads the **array transport** instead (`phpunit.xml` pins `MAIL_MAILER=array`) and extracts the reset token from the real composed message; that lock cannot pass unless the whole pipeline works.

### v1.7.5 — Frame Composite Box (supersedes v1.7.4 G1 geometry)

- **FRESH-INSTALL ARTIFACT (`deploy/build-install-zip.php` → `dist/promptsewa-1.7.5-install.zip`).** The update zip is code-only because a live host already has `vendor/` and a `.env`; a NEW server has neither, so the install zip ships the app + a **production `vendor/`** (`composer install --no-dev --optimize-autoloader` — no pest/phpunit/mockery/fakerphp/phpstan on a public host) + compiled `public/build` + the docroot allow-list (`index.php`, `.htaccess`, `.user.ini`, `install.php`, `update.php`, `README.md`) + the storage/bootstrap directory skeleton. **6,545 entries, 7.92 MB, hygiene audit CLEAN. SHA-256 `0043f489c5c3632bb53888ea551d7ee0e5f767ca80f913ea77177b11c6bce70b` (reproducible: two consecutive builds are byte-identical, so that hash is verifiable).** The builder **fails its own build** on a violation (the v1.4.4 lesson) and additionally asserts the required members are present — it already caught the dev `database.sqlite` sneaking in through the pre-composer tree copy.
  - **Never in this zip:** `.env` (install.php writes it with a fresh APP_KEY), `public_html/.update-token` (install.php generates a random one — shipping this repo's token would hand a working update panel to anyone reading the repo), `bootstrap/cache` contents, `storage/**` runtime, `tests/` + `phpunit.xml`, `node_modules`.
  - **Reproducible by design:** every staged file's mtime is pinned to the release date before zipping. Without that, `copy()` stamped each entry with the build time and two builds of *identical* content hashed differently — a published SHA-256 nobody could reproduce, which turns “is this the audited artifact?” into an unanswerable question. Rebuild to confirm the hash before shipping.
  - **The artifact was installed end-to-end as proof**, not just built: extracted to a clean directory, served over `php -S`, and the **real wizard** driven over HTTP — `.env` written, migrations run, admin created, `storage:link`, caches, assets copied to the docroot, fresh token written, installer self-locked, and the home + login pages returning **200** with correct SEO titles.
- **FRESH-INSTALL BUG FIXED — the wizard could never complete since v1.4.1.** `install.php` created the admin with `User::create([...])` and **no `username`**, which has been `NOT NULL` since v1.4.1 (§6.14): every fresh install died at step 5 with `SQLSTATE[23000] NOT NULL constraint failed: users.username`. It never surfaced because install.php had not been run since before that migration. The handle is now derived from the admin name (slug + numeric suffix on collision — the same rule as the v1.4.1 backfill) and the log line reports it. Verified live: `Admin account created: owner@example.com (@site-owner)`.
- **The installer's seeding log no longer lies.** install.php writes `APP_ENV=production`, and D4 makes `DemoContentSeeder` / `JustShipItAISeeder` / `BulkCatalogSeeder` **hard-refuse** there (they only warn and return 0) — yet the log claimed "Demo content seeded (categories, prompts, JustShipItAI profile)" unconditionally. It now reports the refusal and the real row counts (`Demo seeders REFUSED in production (D4) — expected. 0 prompts, 4 categories present.`), which is the same §6.45 "silent lies are banned" rule that governs the bookmark fetch.
- **Locks**: `InstallArtifactTest` (5) — the artifact's required members and runtime skeleton, its forbidden set (secrets/host-local/dev surface), the installer's username fix, the honest seeding branch, and the builder's audit rules + version lockstep.

- **R0 — what the founder's screenshot actually was (reproduced, not guessed).** Served HTML + browser measurement of a framed creator profile at v1.7.4, using the founder's own `Abyssal.png` through the real upload path. (a) **The saffron squircle was painted by layer 1, the WRAPPER** — `creators/show.blade.php` merged `size-24 rounded-3xl border-4 border-paper bg-saffron text-4xl shadow-card-hover sm:size-28` onto it through `$attributes->merge()`. (b) **That same hero call site was the only source of rounding/background/size overrides** (plus `group-hover/creator:bg-saffron` on the two card call sites and a hand-rolled `-inset-[8%]` typeahead mirror). Measured geometry: wrapper border box 96×96 with a 4px border; the photo clipper `size-full` resolved to the PADDING box 88px at **(+4,+4)**; the overlay's four insets over-constrained a replaced `<img>` into a **non-square 88×109 box** that `object-contain` letterboxed. Three coordinate systems — ring and photo hanging off the top-left of the tile, exactly the founder's screenshot.
- **R1 — THE COMPOSITE BOX (4th geometry ruling; supersedes v1.7.4 G1 and the v1.7.3-hotfix geometry).** `x-user-avatar` is wrapper (`relative inline-block isolate` + a size class from the prop, nothing else — no rounding, background, border, overflow or caller geometry) → frame (`pointer-events-none absolute inset-0 z-10 size-full object-contain`, only when equipped) → photo/badge (`absolute overflow-hidden rounded-full` at `style="inset:{(100−hole)/2}%"` when framed, `inset-0` when frameless, with `size-full object-cover` and the initials badge in the same box). The v1.7.4 negative inset and the v1.7.3-hotfix `inset-0`-inside-clip are both deleted. Nothing protrudes, so the ancestor-clip contract stops being load-bearing for frames (card roots/panels stay un-clipped anyway — harmless, and the cover still needs its own clip).
- **R2 — caller sweep + permanent ban.** All 13 `x-user-avatar` call sites swept; the hero is now `size="xl"` (a prop, `size-24 sm:size-28` internally) with **zero** geometry overrides, and the two card call sites dropped their `group-hover/creator:bg-*`. New `tests/Arch/UserAvatarGeometryTest` fails the suite on any `<x-user-avatar>` tag carrying `rounded-`/`size-`/`bg-`/`border`/`overflow-` attributes, asserts the hero declares a size prop and nothing else, and asserts no negative-inset token survives in any view. The component also strips those tokens before merging, so the ban is not the only defence.
- **R3 — per-frame hole tolerance.** `frames.hole_percent` (tinyint unsigned, default 62, existing rows backfilled by the column default) + `Frame::HOLE_MIN/HOLE_MAX/HOLE_DEFAULT`, `Frame::holePercent()` and `Frame::photoInsetPercent()`. Admin → Frames gained a "Centre hole %" number input whose `min`/`max`/`value` are rendered **from those constants**, and the model throws on an out-of-range save. **Range decision (mediator-approved): 35–70, not the proposed 55–70** — the founder's Abyssal ring measures a **37.5%** disc (192px at 258,254), so the original floor would have refused the founder's own art at upload. It is stored at 38.
- **R4 — tests.** `FrameTruthTest` G1 block rewritten as the composite battery (bare wrapper, per-frame inset at 62/38/70, badge shares the box, constants + model enforcement, admin form renders the bounds, admin can set a hole and out-of-range is refused, hero route serves zero geometry overrides, typeahead mirror tokens + `frame_inset`, frameless renders `inset-0` with zero frame nodes); `AvatarFidelityTest` + `AvatarFrameCompositionTest` moved to the new tokens. Browser-verified: hero 112px box and card 24px box both report **frame centre offset 0.00/0.00 and photo centre offset 0.00/0.00**.
- **ARCH SUITE REVIVED (found while doing R2).** `tests/Arch` was **never registered in `phpunit.xml`**, so `BladeFormVerbTest`, `MigrationDropGuardTest` and `NoBladeLeakTest` had never run once — three guards this handoff cites as permanent repo-wide enforcement. Registering the suite immediately found real damage: a **live user-facing leak** in the navbar (the impersonation chrome bar rendered the escaped-brace expression literally to staff) and **two genuinely unguarded destructive DDL calls in `up()`** (the `121000` license-grants `dropUnique`, driver-checked but never existence-checked — the very migration behind the v1.4.2 prod incident — and two `dropForeign` calls in the baseline `000100`). All fixed; `SchemaInspector::hasForeignKey()` was added so a `dropForeign` can be honestly guarded. The guard was also **scoped to `up()`** — 16 of its 20 findings were `down()`-only drops, and the house culture is append-only (`pv:update` never rolls back).
- **Release facts**: suite **480 passed / 14,094 assertions** (the Arch suite is now inside that number). `dist/promptsewa-1.7.5-update.zip` — a **superset** of v1.7.4 (packs landing v2, the mobile engagement pass, H4 heart truth and the composite box all ride along; the founder deploys once): **427 entries** (423 core + 4 docroot), hygiene audit **CLEAN (0 forbidden entries)**, 0.78 MB. **SHA-256:** `8a792219749c42feabf99c98d8f9879a6bc84a52e54c25061a24b64b9b18ef35`. Built after `npm run build` and `view:clear`, so the compiled assets carry the composite-box classes.


### v1.7.4 — Frame Outside the Circle + Commerce Polish (this release)

- **G1 frame-outside-circle geometry (P0; supersedes BOTH prior rulings)**: `x-user-avatar` is now three layers — the WRAPPER is `relative inline-block isolate` + the caller's size and **never** carries `overflow-hidden` (it owns the stacking context only); an inner CLIPPER `size-full overflow-hidden rounded-full` wraps the photo/initials and is the only place the circle exists; the FRAME overlay is `pointer-events-none absolute z-10 object-contain` pulled outward by a size-keyed inset (**xs/sm `-inset-[8%]`, md/lg `-inset-[12%]`** — a 12% ring would swallow a 20px avatar). The v1.7.3-hotfix `inset-0` inside-clip is DELETED, as is the v1.7.2 `-inset-1` ring. Frame art spec: 512×512 PNG, ring may run to the canvas edges, transparent centre hole 55–70% (the ring overlapping the photo's outer annulus is the intended look).
- **G1 ancestor clip audit — three real violations found and fixed**: (1) BOTH card roots (`prompt-card`, `image-prompt-card`) carried `overflow-hidden` for cover bleed even though `x-prompt-cover` already clips itself — removed from the roots, cover keeps it; (2) the navbar account pill wrapped the avatar in `size-8 overflow-hidden rounded-full`, which would have truncated the ring — removed; (3) the admin users table sat inside an `overflow-hidden rounded-2xl` panel, clipping every avatar in the table — the panel now carries its own rounding and the `<thead>` takes `rounded-t-2xl`. Verified non-violations: the typeahead row's overlay is a sibling of the clipping span (never clipped), and the profile hero/banner are siblings.
- **G1 tests rewritten/extended**: FrameTruthTest gained the geometry battery (wrapper isolate + no clip, clipper owns the circle, per-size inset tokens, card-root/pill/admin-panel clip audit, frameless avatar renders ZERO overlay nodes) and AvatarFidelityTest gained three surface tests. The empty-URL lock and the byte-identical animated lane are unchanged — the animation class now rides on the protruding overlay.
- **G2 pack landing v2**: ink dark hero band (display name, tagline, three real stat chips) → sticky buy card (saffron mono price, struck individual sum, emerald savings in integer paisa) → dark contents card grid (type icon, category mono, price chip / emerald Free) → honest "what your purchase includes" checklist (licence FACTS only: personal/commercial, no resale, newest published version, one grant per account, no seat transfer) → FAQ answering real policy → related packs row. **Every number is computed from the database** in `PackController::show`: the prompt count, `AVG(score)` over the pack's real ratings ("No ratings yet" when empty), and the integer-paisa savings — a pack dearer than its contents prints NO savings claim. Product/Offer JSON-LD and the full x-seo head are retained. Voice rule honoured: the inspiration contributed layout only — no fabricated counts, testimonials or urgency banners, and `PackLandingV2Test` asserts those phrases never appear.
- **G3 mobile engagement pass** (CSS scroll-snap only, zero dependencies, desktop unchanged via `md:` guards): homepage hero type scale steps down (`text-3xl → sm:text-5xl → md:text-6xl`), ONE primary CTA with the secondary demoted to a text link on phones, trending chips + category tiles become keyboard-reachable snap rails, "Fresh from the library" becomes a carousel (`w-[82%]` per card) that is the same DOM as the `md:` grid, the stats band becomes three mono chips, the gallery is 2-col 4:5, section headers keep right-aligned arrow links. Dashboard: tab pills scroll horizontally below md (active pill keeps its ink fill; the "New prompt" CTA stays outside the rail because it is an action, not a tab), stat cards 2-col with the first card spanning full width so sparklines stay readable.
- **H4 saved-heart truth (P0, rides v1.7.4) — the fourth “passes tests, fails browser”**: browser-first repro under `artisan serve` (CSRF is ACTIVE there; Pest skips it by design) as a plain member. Observed: `POST /bookmarks/molestiae-expedita-sed-iste-neque-2663` → **200**, body `{saved:…}`, and the `bookmarks` row WAS written — but the heart rendered `aria-pressed="false"` on the next refresh. **Not a 419 at all**: a pre-flip parity gap. Only the library grid passed `:saved` into `x-prompt-card`; home, the image gallery, search results, the creator profile grid and the detail page all fell through to the component's `false` default, so the optimistic Alpine flip was quietly undone by the next page load. Fix: `App\Support\BookmarkedIds` (a per-request set of the viewer's bookmarked prompt ids, bound as the `bookmarked.ids` singleton in `AppServiceProvider`, ONE query per request instead of one per card) is asked by the card itself, so no surface can forget to pass the state. `x-prompt-card` now takes `saved => null` and resolves its own; the library dropped its per-row `Bookmark::isSaved()` call.
- **H4 the fetch contract (silent lies are banned)**: `bookmarkHeart` now sends `credentials: 'same-origin'`, `X-CSRF-TOKEN` read from `<meta name="csrf-token">` (null-safe — never invented), `X-Requested-With: XMLHttpRequest` and `Accept: application/json`; on ANY non-OK response it **reverts** the optimistic flip and shows a rose `role="status"` toast (419 → “Save failed — your session expired. Reload and try again.”; anything else → “Save failed — try again”). Previously a non-OK only reverted if `.json()` threw and said nothing at all, so a failed save looked exactly like a successful one. The toast ships on BOTH heart surfaces — the detail button was verified failing in silence in the browser.
- **H4 the flip toggles PRESENCE, never utility classes**: the saved and unsaved hearts are two mutually exclusive `<svg>`s toggled with `x-show`, with `x-cloak` applied by the SERVER to the inactive one (truthful before Alpine boots, and correct with JS off). H4's first fix put the SSR pair and Alpine’s `:class` pair on the SAME element — after a tap both pairs sat in the class attribute and the winner was decided by Tailwind's stylesheet order, not by the author: **observed in the browser, the heart turned rose but stayed an OUTLINE** (`fill-none` beat `fill-current`). `heartClass` is deleted from `app.js` and a test bans its return.
- **H4 per-request state must be forgettable**: `bookmarked.ids` is a container singleton (fine on PHP-FPM, wrong on Octane/a queue worker/the test client — all three would serve the PREVIOUS request's viewer state), so `App\Http\Middleware\ForgetPerRequestState` drops it on the way in, appended to the `web` group in `bootstrap/app.php`.
- **H4 locks**: `SavedHeartParityTest` (12 tests) — per-surface pre-flip, per-prompt parity on one page, cross-viewer isolation, the served CSRF meta tag, string-level assertions on the BUILT asset for the header wiring (Pest never executes `fetch()`), the collision-free icon contract, the label parity, a per-surface failure toast, guests, and the one-query budget. The browser check is now a release gate for any Alpine mutation.
- **Release facts**: suite **470 passed / 14,018 assertions**. `dist/promptsewa-1.7.4-update.zip` — **425 entries** (421 core + 4 docroot), hygiene audit **CLEAN (0 forbidden entries)**, 0.77 MB. **SHA-256:** `3f9d85e206ff3420d384928315a2ba66b49c1041ca5265ee3eca14405979613b` (this zip SUPERSEDES the earlier v1.7.4 artifact `a0cd9853…`, which shipped G1–G4 without H4 — do not upload that one). Built after `npm run build`, so the compiled assets carry the snap-rail utilities AND the hardened bookmark fetch.

### v1.7.3 — Frame Truth, Heart Truth & Bot Gates

- **W1 frame surface parity** (REVERSES v1.7.0 cards-clean ruling): `x-user-avatar` now accepts `:frame` and renders the overlay (`pointer-events-none aria-hidden`, sizes xs–lg, circle geometry everywhere) on ALL surfaces — prompt cards both variants, image-gallery cards, library grid, feed actors, versions author, purchases rows, admin users table, navbar dropdown, dock You, typeahead (JSON `frame_url`); profile hero keeps its sanctioned v1.7.2 ring. Feed/eager loads carry `creator.activeFrame` / `actor.activeFrame` everywhere (incl. FeedController). DESIGN.md v1.7.3 addendum documents both geometry + overlay rulings.
- **W2 alpha truth**: `ImageUploadService` sets `imagealphablending(false)` + `imagesavealpha(true)` before EVERY save (and on the downscale canvas — blending OFF during the copy so source alpha REPLACES, not composites); WebP sources keep WebP output (never routed through JPEG-flattening). Pixel tests lock transparent corners for frame/badge/qr PNG and WebP sources.
- **W3 animated frames**: GIF/WebP animated uploads pass through byte-identical (≤512×512 header-parsed, ≤2 MB); static GIFs are rejected (GD re-encode destroys frames). `frame-anim-{spin|pulse|shine}` keyframes ship in app.css behind `prefers-reduced-motion: no-preference`; `Frame.animation` selects per-frame classes.
- **W4 criteria & awarding**: `frames.criterion` + `user_frame_unlocks` UNIQUE(user_id,frame_id) (`2026_09_30_200000`); `CriterionEvaluator` is the single threshold source; FrameAdminController award/revoke (admin-only, idempotent); profile picker refuses locked frames (ink lock chip); locked-frame + grant-unlock tests green.
- **W5 heart truth parity**: creators/show gets owner-only Dashboard + Edit profile buttons (`data-testid="profile-dashboard"` / `profile-edit`) at all breakpoints; strangers see neither.
- **T1–T4 bot challenge**: `BotChallengeService` (null | turnstile | recaptcha_v3 drivers; per-form enables, register on by default); server-side-only verification (`siteverify`, timeout 10 + 1 retry), v3 score+action gate, fail-CLOSED on transport error (`captcha_fail_open` flips); secret encrypted at rest (`SECRET_KEYS`); `x-captcha` component renders zero markup when inactive; enforced at register/login, prompt create, report.
- **T5–T6 disposable mail + Security desk**: `NotDisposableEmail` (exact+subdomain, case-insensitive) on signup; ~172-domain bundled list + admin textarea extension (`disposable.domains` singleton); Admin → Security page (nav pill 🛡 Security) edits provider/keys/per-form enables/blocklist + "test an address" endpoint; moderators 403.
- **T7 tests**: `FrameTruthTest` (18), `BotChallengeTest` (14), `DisposableEmailTest` (11).
- **Release facts**: suite **428 passed / 13,852 assertions**. `dist/promptsewa-1.7.3-update.zip` — **420 entries** (416 core + 4 docroot), hygiene audit **CLEAN**, 0.75 MB. **SHA-256:** `237581c9a5c946874473715100d20a2c1165f7c9d8b99ad2eba9af145c612b25`.

### Post-check (v1.7.3) — after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Admin → Overview version chip | `v1.7.3` | `config('app.version')` |
| 2 | Any prompt card / feed row / admin users table | creator frame overlay renders, circle geometry, `pointer-events-none` | `FrameTruthTest::the frame overlay renders on every avatar surface` |
| 3 | Upload an animated GIF as a frame | stored byte-identical (hash equal), plays on the profile hero | `FrameTruthTest::animated gif uploads pass through byte-identical` |
| 4 | Admin → Frames: grant an unlock / set criterion | unlock persists, idempotent; user can equip; picker shows lock chip otherwise | `FrameTruthTest::manual award persists…`, `…locked frame…` |
| 5 | Admin → Security: set turnstile keys, save, reload | secret still shows "saved" without echoing; register enforces | `BotChallengeTest::the captcha secret is encrypted at rest…`, `…register is refused…` |
| 6 | Admin → Security: test `x@mailinator.com` / `x@gmail.com` | blocked (matched mailinator.com) / ok | `DisposableEmailTest::the admin test-email endpoint…` |
| 7 | Founder: re-upload the four frame PNGs post-deploy | transparent corners survive (no black boxes) | `FrameTruthTest::stored frame corner alpha…` rows |
| 8 | `cd core && php artisan test` | see release facts | whole suite |

### v1.7.3-hotfix — Prompt body ceiling 4,000 → 50,000 chars + hard-forced circle avatars + frame stacking (superseded by v1.7.4)

- **H1 validation**: `PromptFormRequest::MAX_BODY_CHARS = 50000` (single source); the `body` rule is `max:` bound to the constant; both blades render `maxlength` FROM the same constant — the HTML cap and the server rule can never drift.
- **H1 storage**: migration `2026_09_30_210000_widen_prompt_versions_body_to_longtext` widens prod's `prompt_versions.body` from TEXT (65,535 BYTES — a 50k-char multibyte/Devanagari body would have SILENTLY TRUNCATED on MySQL strict mode) to LONGTEXT NULL, with the explicit MySQL `MODIFY` belt-and-suspenders path. SQLite is a no-op semantics-wise. NOT added to the DeployParity pending-list — `prompt_versions` is not a pending-migration table.
- **H1 UX**: a character counter sits under the prompt-body textarea on create AND edit (`{n} / 50,000 chars`), initialized from the textarea's value and debounced on input — no promptForm changes needed; the form stays submittable with JS off (server `maxlength` caps input; the served initial count is server-rendered).
- **Research baseline for 50,000**: PromptBase truncates prompts at ~2,000 (apps 1,000) — the old 4,000 was already ahead of the marketplace peer; GPT-4.1-class chat ~8k chars/message; Claude ~40k tokens (~100k+ chars) per message. 50,000 chars ≈ 12.5k words / ~100KB — a sane guardrail well inside every mainstream model's message budget.
- **Tests**: `PromptBodyCeilingTest` (6 tests, rendered-route + POST round-trip only): create/edit serve the 50k `maxlength` + counter (no `maxlength="4000"` remains); 30k-char body accepted now; >50k rejected; under-4k still fine; long edit persists in full. NOTE: global TrimStrings middleware strips trailing whitespace from POST inputs — test bodies must not end in a space.
- **H2 circle-everywhere avatars (post-deploy hotfix directive)**: `x-user-avatar` hard-forces the geometry — the wrapper ALWAYS carries `rounded-full overflow-hidden` (no conditional shape, no caller-override: the library squircle class is retired), the avatar `<img>` and the initials badge are `rounded-full` themselves, and the frame overlay is `pointer-events-none absolute inset-0 size-full rounded-full object-contain` (transparent centre lets the picture show through) — replacing the old `-inset-1` ring layering. Frames must ship as 512×512 PNGs with a transparent centre. This SUPERSEDES the v1.7.2 hero-squircle addendum for geometry; overlay parity is unchanged.
- **H2 eager-load gaps closed**: `CreatorProfileController` (hero + its card grid), `PromptController::versions` (`versions.author.activeFrame`), admin `UserAdminController`, the purchases grants query, and the dashboard feed events all carry `activeFrame` — a missing relation means a silently absent overlay, not an error.
- **H3 frame stacking & URL (post-deploy hotfix 2, the "buried frame")**: the wrapper now carries `isolate` (own stacking context) and the overlay `z-10` (`absolute inset-0 z-10 rounded-full object-contain pointer-events-none`) so it can NEVER paint behind the picture or leak z-index onto a neighbour. The overlay src comes from a new `Frame::url` accessor (the single resolved public URL) instead of a hand-rolled `Storage::disk('public')->url($frame->image_path)` per view. NOTE the column is `image_path`, NOT `path`, and `User` has **no** `avatar_url` accessor — the directive's snippet used both, so it was adapted rather than copied verbatim.
- **H3 debug-border REFACTORED into a test**: the directive suggested rendering a red border when the frame URL is empty. Shipping debug markup is worse than the bug, so the empty-URL case is instead LOCKED — `FrameTruthTest::the frame url accessor resolves a usable public URL (the buried-frame repro)` asserts the accessor matches the public disk URL, is non-empty, that a row with no image yields `''` (and renders no overlay), and that the served markup carries the resolved src + `z-10` + `isolate`.
- **H3 cPanel cache gotcha — already handled, now locked**: `pv:update` runs `config:cache` (via the `['config:cache','route:cache']` loop) then `view:clear` THEN `view:cache`; a separate `config:clear` is unnecessary because `config:cache` rewrites the file (and would fight the D3 reload that reads it back). The ORDER is now locked by `ReleaseHygieneTest::the update pipeline rebuilds config and clears stale compiled views` so a refactor cannot silently drop it.
- **H2 verified already-correct**: `FrameAdminController::award` is `firstOrCreate(['user_id','frame_id'])` (idempotent, audited via `granted_by` + mandatory `reason`); the profile picker disables locked frames with the ink lock chip and unlocked ones save to `users.active_frame_id` with a server-side 422 on locked equips. No change needed.
- **Artifact**: `dist/promptsewa-1.7.3-hotfix-update.zip` — **420 entries** (416 core + 4 docroot), hygiene audit **CLEAN**, 0.75 MB. **SHA-256:** `874567171b08dba4f82a4471bcca7c3224f9695d69336cc3a01efbe461f9b6de` (SUPERSEDES `a6675488…` and `d7150acb…` — this zip includes v1.7.3 + H1 body ceiling + H2 circle geometry + H3 stacking/url). Built with `npm run build` first so the compiled assets are current. Builder `APP_VERSION = '1.7.3-hotfix'` for this zip only, restored to `1.7.3` afterwards — `ReleaseHygieneTest` resolves the artifact via `config('app.version')`.
- **Migration note**: `2026_09_30_210000_widen_prompt_versions_body_to_longtext` is guard-safe (`Schema::hasColumn` + MySQL-driver branch) and applies cleanly over the v1.4.3 parity schema — `DeployParityAcceptanceTest` proves the replay. On prod a Pending row needs only a plain `php artisan migrate`; no rollback.
- **Suite**: 430 passed / 13,865 assertions (full suite, v1.7.3 + all three hotfixes together).
- **DESIGN.md**: the v1.7.2 hero-squircle line is now marked SUPERSEDED and a circle-everywhere ruling added (transparent-centre 512×512 frames, `isolate` + `z-10`, never the `-inset-1` ring).

### v1.7.2 — The Type Selector, For Real (this release)

- **A1 server-rendered type cards**: create + edit Section 1 renders FIVE REAL radio inputs (`name="type"`: text · image · video · agentic · skill) from `$typeContexts` — mono label + one-line description per card, card visual on a `peer-checked:` chain. Create defaults to text; edit pre-selects the STORED type, `checked` in the served HTML, not JS. No JavaScript required for the radios to be visible and submittable. The old `name="type_radio"` template-looped radios and the hidden `:value="type"` mirror are GONE.
- **A2 type-driven adaptive regions (Alpine enhancement, server truth)**: guidance box ships the TYPE_CONTEXTS copy server-side and Alpine swaps it (`x-text="guidance"`); category `<select>` options are server-rendered real `<option>` nodes — each scoped option carries its own `x-show="type === '…'"` re-scope gate (delivered in double-quoted attributes per the v1.0 truncation rule); section-3 chip label ships server-side + swaps; cover-upload block appears for image (zero-JS truth: `form:has(input[name=type][value=image]:checked)` show/hide rule on `data-cover-only` in app.css + the x-show enhancement; edit shows the current-cover preview + remove toggle); tool-wall chips keep the `data-modality` gate (`modality === type || 'any'`). Server-side validation remains the source of truth: category must belong to the chosen type (or be universal) — new `PromptFormRequest` rule; tools modality ∈ {type, any}; cover prohibited on non-image types.
- **A2/A3 cover-on-switch (founder decision)**: switching an EXISTING prompt to image type now REQUIRES a cover (`Rule::when($this->isImageTypeSwitch(), ['required'])`). Create with image stays optional. The two existing PromptCreateEditTest edit-flow fixtures were re-typed to image so they are not type switches.
- **A3 rendered-route tests**: `AuthoringTypeSelectorTest` (11 tests) — create serves five radios + text checked; edit of a stored type serves it checked; tool chips carry `data-modality` + the gate expression; cover block served + hidden without JS (asserted through the BUILT stylesheet resolved via the Vite manifest, since the `:has()` rule lives in the compiled asset the route serves); edit of an image prompt serves cover preview + remove toggle; POST create with type=image + a PNG cover → stored prompt has type image and a cover path on the public disk; POST create with an image-modality tool on type=text → validation error; PUT edit text→image without cover → error; with cover → switch persists; mismatched category rejected. **No assertion in this file touches a component file's contents.** The v1.7.1 P2 battery in `SavedHeartAndTypeSelectorTest` was retired to a supersession note (its radio assertions targeted the old template contract).
- **A4 sweep of the unverified v1.7.1 items** — each stated: (a) saved heart = filled rose on cards/detail/Saved tab, outline unsaved — LANDED in v1.7.1, re-verified green this release (`SavedHeartAndTypeSelectorTest` + `BookmarkTest`); (b) manual-method kind select in admin, kind icon + "Scan to pay" QR at checkout, Admin→Payments "Manual methods" card link — LANDED in v1.7.1, re-verified green (`MethodKindsAndLabelsTest` + `ManualPaymentMethodsTest`); (c) hero avatar geometry ANSWERED IN WRITING — DESIGN.md v1.7.2 addendum sanctions the SQUIRCLE + FRAME RING for large hero surfaces (circular stays for small identity badges).
- **Release facts**: suite **379 passed / 13,698 assertions**. `dist/promptsewa-1.7.2-update.zip` — **405 entries** (401 core + 4 docroot), hygiene audit **CLEAN**, 0.72 MB. **SHA-256:** `cd158b694fd53ac73ed10079a47656199c93cabc618395a13ec2338f45b7530a`.

### Post-check (v1.7.2) — after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Admin → Overview version chip | `v1.7.2` | `config('app.version')` |
| 2 | Open `/dashboard/prompts/create` with JS DISABLED | five type radio cards visible, text pre-checked, all fields submittable; cover block hidden | `AuthoringTypeSelectorTest::create serves five real type radios…` |
| 3 | Select the Image radio (JS on) | guidance copy, category options, section-3 chip and tool wall all re-scope; cover block appears | same file (gate expression + Alpine contract) |
| 4 | Open `/dashboard/prompts/{image-prompt}/edit` | image radio checked in served HTML; current-cover preview + remove toggle present | `AuthoringTypeSelectorTest::edit serves the stored type checked…`, `…::editing an image prompt serves the cover block…` |
| 5 | Edit a TEXT prompt → switch type to Image, no cover → Save | validation error on cover_image; with a cover attached the switch persists | `…::editing text to image without a cover is a validation error`, `…::editing text to image with a cover passes…` |
| 6 | Submit a text prompt with an image-only tool (e.g. Midjourney) | server validation error (tool modality) | `…::posting create with an image-modality tool on type text…` |
| 7 | `cd core && php artisan test` | 379 passed, 13,698 assertions | whole suite |

### v1.7.1 — Saved Heart, Type Picker, Method Kinds + Gamification Doors, SEO Crawl, Identity Links

- **P1 saved hearts**: prompt-card + detail hearts render saved state as filled rose (`text-rose-600 fill-current`) vs unsaved outline — `bookmarkHeart.heartClass` Alpine getter keeps view and state in sync; DESIGN.md addendum documents the rose semantic token.
- **P2 type selector**: create + edit prompt forms get radio cards (`type_radio`) that gate the tool chips by modality — `toolEntries` Alpine getter (name→modality map), chips with `:data-modality` show when the modality matches the selected type, the tool is `any`, or already selected (selection never silently vanishes on type switch).
- **P3 method kinds**: `manual_payment_methods.kind` (`2026_09_30_190000`, default `other` backfill; KINDS = bank / esewa / other), validated via `Rule::in`, kind select + QR helper ("PNG/WebP, transparency preserved, ≤4 MB") on the admin form, kind chip in the admin list, per-method kind icon + bordered "Scan to pay" QR block at checkout.
- **P4 labels**: Admin Overview revenue sublabel "paid orders · incl. pre-ledger"; Finance desk chip "ledger gross · post-cutover" — kills the founder's double-count confusion between the two desks.
- **P5 avatar fix**: `x-user-avatar` rewritten — size lives on the OUTER wrapper, inner badge is `size-full rounded-[inherit]`, and a caller-supplied `size-*` class drops the default preset. Fixes the saffron-block hero bug (a hard-coded size colliding with the caller's).
- **P1b gamification doors**: Badges/Frames nav pills in `admin-layout` for admins. **Real gap found and fixed: `BadgeAdminController::index` + `FrameAdminController::index` had NO staff gate — moderators got 200 on an admin-only surface.** Both now `abort_unless($request->user()?->isAdmin(), 403)`; locked by a moderator-403 test.
- **P2b achievements surfaces**: creator profile gains an Achievements section (badge grid + Lv chip + "No badges yet — publish your first prompt to earn one." empty state) between stats and prompts; dashboard gains an Achievements tab with real progress bars (first_publish / first_sale / sales_10 / sales_50 / verified; top_rated is staff-judged, shown as such).
- **P3b SEO crawl**: `SeoRouteCoverageTest` — a full guest/member/owner/admin crawl asserting EXACTLY ONE `<title>` per named GET route with the expected subject, PLUS a permanent route-list sync test (any new named GET route fails the suite until it's mapped in `seoExpectedSubjects()` or added to the exempt list). The crawl itself found and fixed 3 headless pages: prompt create ("Add a new prompt", noindex), prompt edit ("Edit: title"), software update.
- **P4b identity links**: mobile-dock You slot → public profile (`creators.show`); navbar dropdown entries carry `data-testid="nav-view-profile"` / `nav-edit-profile`; profile You-menu gains a "View profile" row.
- **P6 packs parity (evidence)**: the founder's "packs page is the same" complaint does NOT hold for SEO structure — the packs index had a full `x-seo` head ("Prompt packs") and the pack landing carried Product/Offer JSON-LD (from v1.5.0 T8) all along; the full crawl walked both and passed. Both facts are now LOCKED by `AvatarFrameCompositionTest` so they can't regress silently. If the complaint meant visual sameness between packs index and library, that's a design follow-up, not a defect.
- **Test collateral fixed during the full-suite run**: `SeoRouteCoverageTest` global helpers renamed (`seoMember()`/`seoAdmin()`) to stop colliding with `AdminReviewTest::member()`; `DeployParityAcceptanceTest` pending-list extended with `190000` (ships in the same pv:update batch as 130000+130100 — batch count 2→3) plus a `kind` column assertion.
- **Release facts**: suite **372 passed / 13,663 assertions**. `dist/promptsewa-1.7.1-update.zip` — **404 entries** (400 core + 4 docroot), hygiene audit **CLEAN**, 0.71 MB. **SHA-256:** `0a10b5a436d82d64efdcc82cb7cbbc7eefaa69e95b425fdf7625349518591a18`.

### Post-check (v1.7.1) — after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Admin → Overview version chip | `v1.7.1` | `config('app.version')` |
| 2 | `php artisan migrate:status` on prod | `2026_09_30_190000_add_kind_to_manual_payment_methods` newly Ran (alongside 130000+130100) | `DeployParityAcceptanceTest` |
| 3 | Save a prompt, revisit the card + detail | heart renders filled rose; clicking toggles outline | `SavedHeartAndTypeSelectorTest`, `BookmarkTest` |
| 4 | Create-prompt form: switch type radio | tool chips re-gate by modality; selected tools never disappear | `SavedHeartAndTypeSelectorTest` |
| 5 | Admin → Manual methods: set kind on a method; open checkout | kind chip in admin list; per-method kind icon + "Scan to pay" QR block at checkout | `MethodKindsAndLabelsTest`, `ManualPaymentMethodsTest` |
| 6 | Log in as a MODERATOR, hit `/admin/badges` and `/admin/frames` | 403 both | `MethodKindsAndLabelsTest::moderators are 403 on badges and frames management` |
| 7 | Log in as an ADMIN → sidebar | Badges + Frames pills visible after Comp grants | `MethodKindsAndLabelsTest::the admin nav carries Badges and Frames pills` |
| 8 | Creator profile + dashboard Achievements tab | badge grid / earned wall + progress bars render | `AchievementsAndIdentityLinksTest` |
| 9 | Mobile dock → You slot | navigates to the public profile, not the edit form | `MobileDockTest`, `AchievementsAndIdentityLinksTest` |
| 10 | Page `<title>` spot-check: /dashboard/prompts/create, /dashboard/prompts/edit | "Add a new prompt" / "Edit: …" (no more blank titles) | `SeoRouteCoverageTest` (full crawl) |
| 11 | `cd core && php artisan test` | 372 passed, 13,663 assertions | whole suite |

### v1.7.0 — Community & Gamification

- **G1 PNG-for-everything**: `ImageUploadService` gains `badge` (512px) and `frame` (512px) alpha-preserving variants — PNG/WebP only, **JPEG sources rejected outright** (these variants exist to carry transparency; flattening a JPEG onto a black box is worse than a clear rejection) — plus `pack_hero` (1600px q82). Same alpha logic as the QR variant.
- **G2 achievements**: `badges` (slug unique, criterion enum: first_publish / first_sale / sales_10 / sales_50 / verified / top_rated) + `user_badges` (UNIQUE(user,badge) IS the idempotency gate). `GamificationService::awardBadge` is the single award choke point — double-fire insert hits the unique constraint and is caught+ignored. XP: +50 publish, +100 sale, +25 rating received, +200 badge earned (granted by the UserBadgeObserver so manual and auto awards pay identically — the service must NOT also grant, or a double-count ships). `levelForXp()` is a pure threshold fn; "Lv N" mono chip on profiles and the feed. Admin badge CRUD + manual award with mandatory reason (audited).
- **G3 frames**: `frames` + `users.active_frame_id` (nullOnDelete). `x-user-avatar` renders the ring via an absolute, pointer-events-none, aria-hidden overlay. Ruled surfaces: profile hero, navbar dropdown (desktop + mobile rows), feed actor avatars — **prompt cards stay clean by ruling** (asserted by occurrence count in FramesTest). Frame picker in profile edit; admin CRUD.
- **G4 feed**: `feed_events` (type enum, actor, morph subject, meta, dedupe_key). Written ONLY by the G2 observers inside the triggering transaction — never from controllers. Stable dedupe keys (prompt_published is once per prompt LIFETIME; milestones once per threshold). Public `/feed` (paper world): paginated 20/page, page-1 file-cached 5 min, banned actors excluded at query level, noindex. Dashboard Feed tab: own events + global milestones.
- **G5 analytics**: `prompts.views_count` + `prompt_daily_stats` (UNIQUE(prompt,day) — upserts allowed here; views are stats, never money). View counting on public detail: non-owner/non-staff, once per session-hour per prompt. `paid_at` set inside `settleOrder`. Creator Stats tab: SVG sparklines (Blade, zero deps) for 30-day views/sales/rating + top-5 table; Admin Overview: revenue/orders/users/reports series. Series file-cached 10 min; EMPTY SERIES IS FIRST-CLASS — dense 30-point arrays of zeros, never sparse/null.
- **G6 tests**: GamificationTest (6), FeedTest (5), FramesTest (5), AnalyticsTest (8) — 24 new. Suite: **343 passed / 13,444 assertions**.
- **Release facts**: `dist/promptsewa-1.7.0-update.zip` — **398 entries** (394 core + 4 docroot), hygiene audit **CLEAN**, 0.7 MB. **SHA-256:** `451d4098ea44f456cdaf5e1a535482dfb36fbafc5f89707ebb6edad378d091ea`.

### Post-check (v1.7.0) — after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Admin → Overview version chip | `v1.7.0` | `config('app.version')` |
| 2 | Fresh sale → creator's badge wall + XP chip | first_sale/first_publish badges awarded; Lv chip advances | `GamificationTest` |
| 3 | Creator profile with a frame equipped | ring overlay on the hero avatar; NOT on prompt cards | `FramesTest` |
| 4 | `/feed` as a guest | 200, noindex, actor avatars + badges + Lv chips, real-number milestones | `FeedTest` |
| 5 | Creator Stats tab with zero data | 200; sparklines render flat baselines; "No published prompts yet" | `AnalyticsTest` |
| 6 | Admin Overview with zero orders | 200; all four 30-day series render Rs. 0.00 | `AnalyticsTest` |
| 7 | Admin → Badges: manual award with reason | audited row (awarded_by + reason); duplicate refused | `GamificationTest` |
| 8 | Upload a JPEG as a badge/frame | rejected with a clear message | `FramesTest` |
| 9 | `cd core && php artisan test` | 343 passed, 13,444 assertions | whole suite |

### v1.6.1 — Empty-Ledger Hardening (P0 hotfix)

- **Root cause of the prod /earnings 500 (K1, confirmed by repro + log + zip forensics): NOT sum-null.** A fresh creator with zero wallet rows rendered 200 on local SQLite before any change. The actual failure: v1.6.0 added `autoload.files` to composer.json for `app/Support/money.php`, but the update zip is code-only (no vendor/) and the cPanel host cannot run `composer dump-autoload` — prod's `vendor/composer/autoload_files.php` predates the helper, so **`money_npr()` was undefined at runtime** and the first money-rendering page died. The zip builder's composer.lock guard is dead code in this checkout (baseline zip `promptsewa-main-1-0-0.zip` absent from dist/), so nothing warned.
- **K2 boundary hardening**: every money aggregate crosses the service edge as `(int)` (SUM over zero rows is NULL — the class remains a 500 until cast); all 14 money echoes in earnings/finance views now render through the crash-proof `<x-money>` component (`function_exists('money_npr')` guard + identical inline fallback), so money renders correctly even on a vendor/ that never learned the helper.
- **K3 settings fallbacks in code**: `commission_bps` absent → 2000; `payout_min_paisa` absent → 50000; `ledger_started_at` absent → 2026-09-30. A migration/seeder gap is now cosmetic, never fatal.
- **K4 tests (7 new)**: `EarningsEmptyLedgerTest` — guest redirect; zero-rows 200 with Rs. 0.00 + "No ledger rows yet"; finance desk 200 in the same state; payout form renders the Rs. 500 min label with the settings row present AND deleted; the autoload-gap simulation; and the standalone sweep of both money surfaces with empty fixtures — the sweep that would have caught the prod 500.
- **K5 MySQL parity**: `EmptyLedgerMysqlParityTest` — migrated a throwaway MySQL DB, verified balance/available = 0, commission fallback = 2000, and both surfaces render 200 with Rs. 0.00 on MySQL SUM semantics. Ran (not skipped) locally.
- **K6 release**: version chip `v1.6.1`; QA-MATRIX K-block; suite **319 passed / 13,336 assertions**; `npm run build` before zip.
- **Release facts**: `dist/promptsewa-1.6.1-update.zip` — **373 entries** (369 core + 4 docroot), hygiene audit **CLEAN**, 0.66 MB. **SHA-256:** `6820c5bb9963714aecf7483a695869be79ed5ce7d40fa61e6531d09311228b03`.

### Post-check (v1.6.1) — after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Admin → Overview version chip | `v1.6.1` | `config('app.version')` |
| 2 | **`/dashboard/earnings` as a user with ZERO wallet rows (the prod-500 state)** | **200, Rs. 0.00 cards, payout form with Minimum Rs. 500.00, "No ledger rows yet"** | `EarningsEmptyLedgerTest` |
| 3 | Admin → Finance desk on the empty ledger | 200, Rs. 0.00 totals, pre-ledger banner intact | same |
| 4 | A priced purchase → approve → Earnings | credit renders via `<x-money>` regardless of vendor state | full suite |
| 5 | `cd core && php artisan test` | 319 passed, 13,336 assertions (parity runs when MySQL reachable) | whole suite |

### v1.6.0 — Money Core

**H-series (v1.5 loose ends, shipped in this zip):**
- **H1 count semantics**: public profile stat = PUBLISHED listings only (relabeled "published prompts"); admin Users column = "All prompts" (total). Locked by `CreatorProfileCountTest` (3). Reconciliation recorded, not "fixed": 270 published pre-adoption vs 268+1 after — the delta is a single non-published (draft/pending/rejected) listing among the 276 adopted rows; comment lives in `CreatorProfileController` + the test.
- **H2 version authorship resilience**: `prompt_versions.user_id` FK was `cascadeOnDelete` — after adoption, a demo purge would have CASCADE-DELETED the entire version history of all 276 listings. Migration `2026_09_30_160000` rebuilds the table: user_id now NULLABLE with `nullOnDelete`. Deleted/missing authors render the honest "Former creator" mono chip — never a crash. Locked by `VersionAuthorshipResilienceTest` (3, including a full `pv:purge-demo` round-trip).

**M-series (Money Core):**
- **M1 schema**: `wallet_transactions` (insert-only: signed `amount_paisa` BIGINT, `idempotency_key` UNIQUE, meta json, created_at ONLY — no updated_at; FKs restrictOnDelete) + `payouts` (state machine requested→approved→settled / rejected|cancelled; `destination_encrypted` = Crypt::encryptString). Settings: `commission_bps` (2000, cap 5000), `payout_min_paisa` (50000), `ledger_started_at` cutover marker.
- **M2 WalletService is THE choke point**: `balancePaisa` = SUM(ledger) with open holds added back; `availablePaisa` = plain SUM (the −hold row is already inside it — do NOT subtract again); `settleOrder(order, source)` = paid-guard + grants + per-line credits in ONE transaction, idempotency key `sale:{order_id}:{item_id}`. Exactly TWO controller call sites (OrderAdminController::approve + CheckoutController eSewa path), locked by arch test.
- **M3 eSewa rail**: webhook `POST /payments/esewa/webhook` (CSRF-exempt via bootstrap, signature-verified, masked info log, last-webhook line in admin). Callback+webhook double-delivery credits once. Sandbox mode swaps merchant code + secret (`EPAYTEST`), NEVER skips signature verification. Admin → Payments gains the sandbox card + last-webhook info.
- **M4 withdrawals**: creator Earnings tab (`/dashboard/earnings`): balance, available, lifetime credits, sales (comp-free by construction), payout history + request form (min-threshold, ≤ available → else 422). Request inserts withdrawal_hold (−X) + payout row in one transaction; cancel → cancelled + withdrawal_release (+X).
- **M5 Finance desk** (Admin → Finance): gross paid / creator credits / platform net (derived, never stored) post-cutover; pre-ledger orders listed READ-ONLY with the honest banner (the Rs. 1,497 is visible, never backfilled); payout queue (approve = no ledger row, settle = no ledger row, reject = release row); ledger browser (user/type/date filters); destination decrypts ONLY in the staff-gated route, masked until "Reveal".
- **M6 battery**: 20 new tests — double-webhook single credit; callback+webhook; manual approve atomic; hold/release/settle state machine; over/under 422; commission property across 1,200 random values × 6 bps settings (12,005 assertions); float-ban + raw-paisa-echo arch tests; ledger mutation boot-throw + repo-wide arch ban; comp-no-rows; earnings isolation; destination encrypted at rest; pre-ledger exclusion.
- **M7**: `money_npr(paisa)` helper (`app/Support/money.php`, composer autoload files) is the single money renderer; QA-MATRIX M-block added; release facts below.

- **Release facts**: `dist/promptsewa-1.6.0-update.zip` — **370 entries** (366 core + 4 docroot), hygiene audit **CLEAN**, 0.66 MB, built after `npm run build`. **SHA-256:** `7345cb637aa976b967c91df231dd4436bfce24cb81b300a6c4c42946f50a9901`. Suite: **312 passed / 13,300 assertions**.

### Post-check (v1.6.0) — run in order after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | `php artisan migrate:status` | `2026_09_30_160000_make_prompt_versions_user_id_nullable` + `2026_09_30_170000_create_wallet_transactions_and_payouts` Ran | migrations |
| 2 | Admin → Overview version chip | `v1.6.0` | `config('app.version')` |
| 3 | Manual approve a paid order → creator Earnings tab | grant issued AND `sale_credit` row visible; balance = gross × (10000−bps)/10000 | `WalletServiceTest::manual approval settles…` |
| 4 | Creator requests payout → available drops | `withdrawal_hold` row (−X); available = balance − X | `WalletServiceTest::payout hold reduces…` |
| 5 | Admin rejects the payout → available restored | `withdrawal_release` row (+X); NO row ever updated | same + immutability tests |
| 6 | Admin settles an approved payout | zero new ledger rows (the hold was the debit) | `WalletServiceTest::settling a payout…` |
| 7 | eSewa sandbox round-trip (creds present) or simulated-signature test | callback+webhook double-delivery credits ONCE | `WalletServiceTest::a double webhook…`, `callback and webhook…` |
| 8 | H1: creator profile vs admin Users | profile shows published count; Users table shows total (276) | `CreatorProfileCountTest` |
| 9 | H2: open `/prompts/{adopted-slug}/versions` | 200, authors render; after any author deletion: "Former creator" chip | `VersionAuthorshipResilienceTest` |
| 10 | Comp grant → Finance desk ledger | zero new rows | `WalletServiceTest::comp grants…` |
| 11 | Admin → Finance pre-ledger banner | pre-cutover orders listed read-only, "no wallet rows by design" | `WalletServiceTest::pre ledger orders…` |
| 12 | `cd core && php artisan test` | 312 passed, 13,300 assertions | whole suite |

### v1.5.2 — founder self-serve adoption panel (this release)

- **F3 "Apply adoption" panel** — Admin → Users gains a second ops panel (below Demo purge, `AdoptionController`): Preview plan (dry run) + Apply adoption, shelling `pv:adopt-catalog` exactly like the purge panel shells `pv:purge-demo` — the runbook logic and the UI can never drift. Admin-only (moderators 403); force goes through a JS confirm. The founder can now run T4 on prod from the browser (no SSH on the cPanel host) and paste the Users-table screenshot showing `@promptsewa = 276`.
- **Tests**: 3 new (`AdoptionPanelTest`: admin-only preview/run, dry-run writes nothing, force adopts + idempotent second run, panel renders both forms). Suite: **286 passed / 1237 assertions**.
- **Release facts**: `dist/promptsewa-1.5.2-update.zip` — **355 entries** (351 core + 4 docroot allow-list), hygiene audit **CLEAN (0 forbidden entries)**, 0.63 MB. **SHA-256:** `f26e08b410f846a18b538c35e42bdfe2352fcabd0801e8c85de3ac9c007f2c84`.

### Post-check (v1.5.2) — run in order after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Admin → Overview version chip | `v1.5.2` matches the deployed release | `config('app.version')` single source |
| 2 | Admin → Users → Apply catalog adoption → Preview plan (dry run) | ink terminal block lists candidate owners + total (e.g. `276 → @promptsewa`); Users table unchanged | `AdoptionPanelTest::adoption panel preview is admin-only and writes nothing` |
| 3 | Apply adoption (confirm dialog) | success flash; every candidate prompt now owned by `@promptsewa`; packs/orders/grants untouched; version history keeps original authors | `AdoptionPanelTest::adoption panel run is admin-only and applies the adoption`, `CatalogAndVersionTruthTest` |
| 4 | Apply adoption again | clean no-op success (idempotent) | same |
| 5 | Users-table screenshot | `@promptsewa` shows 276 prompts (founder's Part-4 deliverable) | manual |
| 6 | Moderators on the panel | 403 on both preview and run | same tests |
| 7 | `cd core && php artisan test` | 286 passed, 1237 assertions | whole suite |

### v1.5.1 — SEO wiring & typeahead badges (this release)

- **F1 the "missing meta" bug root cause**: `x-seo` rendered its `<title>`/`<meta>` tags WHEREVER the page invoked the component — inside the body slot — while the layout served its own hardcoded `<title>` from `<head>`. The live server was therefore serving the brand-only title on every page regardless of the per-page component. Fix: the component now `@push('head')`es ALL its output, so tags land inside `<head>` no matter where `<x-seo/>` sits; the layout's hardcoded `<title>` and `name="description"` are DELETED (locked by a layout-file scan test); every route class now renders x-seo (dashboard, purchases, profile, report, checkout, esewa redirect, admin pages via `x-admin-layout`, all error pages).
- **F2 typeahead badges**: the JSON carried `badge: official|verified|none` since v1.5.0, but the Alpine dropdown never rendered it. The creators section now renders the official blue circle (`#1d9bf0`) or the saffron seal (`#EAB308`) inline (x-if gated on the badge field), sized for the compact row.
- **T4 executed on dev**: `pv:adopt-catalog --force` adopted all 276 prompts to `@promptsewa` (bibek/maya/dorje/justshipitai now childless).
- **Tests**: 7 new (SeoLayoutTest 7). Suite: 283 passed / 1221 assertions.

### v1.5.0 — Official Identity, Taxonomy & Version Truth

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
27. **The wallet ledger is INSERT-ONLY — enforced at model boot, not just convention (v1.6.0).** `WalletTransaction::updating()` and `::deleting()` throw RuntimeException; a repo-wide arch test bans ->update(/->delete( in any file touching the model. Balances are SUM(amount_paisa) under lockForUpdate() — there is NO cached balance column and none may be added. `availablePaisa` is the PLAIN SUM (the −hold row is already inside it); `balancePaisa` adds open holds back. Do not "simplify" either formula — the hold row being inside the SUM is the whole design.
28. **`WalletService::settleOrder` is the ONLY credit path — exactly two controller call sites (v1.6.0).** OrderAdminController::approve (manual rail) and the CheckoutController eSewa path share it; the arch test fails on a third. Per-line idempotency key `sale:{order_id}:{item_id}` + the paid-state guard make callback+webhook double-delivery credit once. Pack lines credit NOBODY (packs have no owner — platform revenue by definition); changing that is a founder-level contract change.
29. **Pre-ledger revenue is labeled, never backfilled (v1.6.0).** Paid orders before the `ledger_started_at` setting render read-only in the Finance desk with the "no wallet rows by design" banner. Never write wallet rows for them; never let them leak into balances.
30. **eSewa secrets (live AND sandbox) are Crypt::encryptString'd at rest via SettingsService::SECRET_KEYS (v1.6.0).** Sandbox mode swaps merchant code + secret but NEVER skips signature verification. The webhook is CSRF-exempt — signature verification IS its guard; keep the exemption narrow in bootstrap/app.php. Payout destinations decrypt ONLY inside staff-gated controller code; views show masked text until the reveal fetch.
31. **All money renders through `money_npr(paisa)` (v1.6.0).** Raw paisa echoes in views are banned by arch test. No float casts, no round(), no non-intdiv division in WalletService — the property test locks credit+platform == gross across random values.
33. **Feed events emit from observers INSIDE the triggering transaction — never from controllers (v1.7.0).** Prompt/Order/UserBadge/Rating/Pack observers are the single emission point; the feed event commits or rolls back with the event. Dedupe keys are STABLE (prompt_published once per prompt lifetime; milestones once per threshold) — a key including updated_at re-emits on every save.
34. **Frames/badges are content images: alpha-preserved variants only (v1.7.0; PARITY REVERSED in v1.7.3).** JPEG sources are rejected outright for badge/frame uploads (G1) — never silently flattened. **v1.7.3 frame surface parity REVERSES the v1.7.0 cards-clean ruling: the frame overlay now renders on EVERY avatar surface (hero, prompt cards both variants, image-gallery cards, navbar dropdown, dock You, feed actors, typeahead rows, versions author, purchases rows, admin users table) and avatar geometry is CIRCLE-EVERYWHERE — do not reintroduce per-surface shapes.** Animated GIF/WebP frames/badges take the W3 byte-identical pass-through lane (≤512×512, header-parsed); static GIFs are rejected (GD re-encode would destroy frames).
36. **A feature without a nav entry is a missing feature (v1.7.1).** v1.7.0 shipped admin badge/frame CRUD with NO navigation path — the surfaces existed but no admin could reach them without typing URLs. New admin surfaces MUST ship their nav pill in the SAME commit (`admin-layout` pills, ordered after Comp grants), and staff-gated indexes MUST gate explicitly (`abort_unless($request->user()?->isAdmin(), 403)`) — the badge/frame indexes silently served 200 to moderators because a policy covered the write routes but nobody checked the read route. Both gaps are now locked by tests.
37. **SEO coverage is self-enforcing (v1.7.1).** `SeoRouteCoverageTest` permanently syncs the route list: every named GET route must appear in `seoExpectedSubjects()` or the exempt list, or the suite fails. When you add a route, extend BOTH — the map for the crawl's expected `<title>` subject, or the exempt list (JSON, downloads, POST-only). Guest/member/owner/admin fixtures live in the crawl itself; don't duplicate world-building per test.
38. **New migrations that touch a table created by a PENDING migration must join the parity pending-list (v1.7.1).** `DeployParityAcceptanceTest` builds the v1.4.3 schema by running every migration EXCEPT a hard-coded pending list — a later migration depending on a pending table explodes the build. When pv:update will ship several migrations in one batch, add each to `$pending` and bump the batch-count assertion. Version helpers in test files must be uniquely named repo-wide (global functions collide when the full suite loads) — prefix test helpers (e.g. `seoMember()`), don't reuse `member()`/`admin()`.
39. **Adaptive authoring regions are asserted on the rendered create/edit routes — component-level assertions are banned for route-facing UI (fourth occurrence of the exists-but-not-included class).** v1.7.1's type-selector tests passed while asserting a Blade template's source; the served page could have drifted silently. v1.7.2's `AuthoringTypeSelectorTest` hits the routes themselves: served HTML for radio `checked` state, gate expressions and cover markup; real POST/PUT round-trips for validation. Built-asset contracts (the zero-JS `:has()` rule) are asserted through the Vite manifest, never by guessing the hash. When you add route-facing UI, the test asserts what the ROUTE serves — not what a file contains.
40. **CAPTCHA secrets are encrypted at rest and verification fails CLOSED (v1.7.3).** `captcha_secret` lives in `SettingsService::SECRET_KEYS` (Crypt::encryptString) — the admin form is write-only (empty input keeps the saved secret). Verification is server-side only (`siteverify` POST; the client token is never trusted); a transport error REJECTS unless `captcha_fail_open` is explicitly on. reCAPTCHA v3 additionally requires score ≥ min AND action == form name. The `x-captcha` component renders ZERO markup when no provider is active or the form's enable is off. New enforcement points: register/login (AuthController), prompt create (PromptFormController), report (PromptReportController) — locked by `BotChallengeTest`.
41. **The disposable-domain list is bundled + admin-extended (v1.7.3).** `config/disposable-domains.php` ships ~172 curated domains; the admin extends at runtime via `blocked_domains_extra` (newline/comma textarea). The merged set resolves through the `disposable.domains` singleton — controllers never hardcode lists. Matching is exact-domain OR subdomain, case-insensitive (`NotDisposableEmail`); the admin "test an address" endpoint (`admin.security.test-email`) checks the same merged list. Changing the bundled config does NOT invalidate the singleton mid-request — it's resolved once per request lifetime.
42. **Animated uploads are byte-identical — the hash-equality test is the contract (v1.7.3).** The W3 lane stores the upload's raw bytes (`file_get_contents` → `Storage::put`); no GD re-encode touches it. Size gating is header-parsed (`getimagesize` ≤512×512), animation detection is dependency-free (GIF 0x2C separator count; WebP ANIM chunk scan). If you ever "optimize" the lane with an encode step, `FrameTruthTest::animated gif uploads pass through byte-identical` fails by design — that is the point.
43. **Frame geometry is frame-outside-circle (v1.7.4): isolate wrapper WITHOUT overflow-hidden, circular photo clipper inside, negative-inset overlay on top. Both prior variants — v1.7.2's un-isolated `-inset` ring and v1.7.3-hotfix's `inset-0` inside-clip — are superseded; do not reintroduce either.** Three layers, in order: wrapper (`relative inline-block isolate` + size, never clips) → clipper (`size-full overflow-hidden rounded-full`, the ONLY circle) → overlay (`pointer-events-none absolute z-10 object-contain` at xs/sm `-inset-[8%]`, md/lg `-inset-[12%]`). Because the ornament protrudes, **no ancestor of a framed avatar may carry `overflow-hidden`** — card roots that clipped for cover bleed moved the clip onto the cover element itself; tight wrappers (navbar pill, admin table panel) dropped it. A clipping ancestor silently truncates the frame with no error, so this is locked by rendered-HTML assertions on the served markup, never by reading a Blade file. Frame art: 512×512 PNG, transparent centre hole 55–70%, ring may run to the canvas edges.
44. **Commerce copy must be true before it is pretty (v1.7.4).** The pack landing's numbers are computed in `PackController::show` (real prompt count, `AVG(score)` over real ratings, integer-paisa savings) and the page degrades honestly ("No ratings yet", "no published prompts yet", and NO savings line when the pack is dearer than its contents). The FAQ and checklist answer policy that the code actually enforces (latest-version downloads, per-account grant, no resale, eSewa + manual proof approval). Do not add urgency banners, seat counts, testimonials or "trusted by" copy — `PackLandingV2Test` asserts those phrases never appear.
43. **Validation ceilings and storage columns must be widened in the SAME release (v1.7.3-hotfix).** A 4,000-char validation max masked a TEXT column underneath: `prompt_versions.body` was `text` (65,535 BYTES) on prod — raising validation alone would have let multibyte bodies silently truncate on MySQL strict mode. When you raise any `max:` bound, check the column type behind it in the SAME commit (migration 2026_09_30_210000 did the widening). Same lesson for the client cap: render `maxlength` from the SAME PHP constant as the validation rule (`PromptFormRequest::MAX_BODY_CHARS`) — two hand-copied numbers always drift.

45. **State-mutating Alpine fetches must send the CSRF meta token, revert on non-OK, and be browser-verified under `artisan serve` before release — Pest bypasses CSRF by design and will never catch this class.** This is the **fourth** occurrence of “passes tests, fails browser”, so the browser check is now a **release gate for any Alpine mutation**. The contract: `credentials: 'same-origin'`, `X-CSRF-TOKEN` read from `<meta name="csrf-token">` (null-safe, never invented), `X-Requested-With: XMLHttpRequest`, `Accept: application/json`; and on ANY non-OK, revert the optimistic flip and tell the user in a rose `role="status"` toast. Silent lies are banned — a failed save that looks like a successful one is worse than an error. Lock it with **served-HTML string assertions** (the meta tag, the `x-data` wiring) plus **string assertions on the built asset** for the header tokens; assert CONTRACT tokens (`.ok`, `===419`, `Save failed`), never minified local names.
46. **Optimistic state must be server-rendered, and an optimistic flip must toggle PRESENCE — never utility classes (v1.7.4).** H4's real root cause was that `saved` lived only in Alpine: only one surface passed it in, so every other surface rendered the default and the next refresh undid the flip. Server-render the truth (`aria-pressed` static AND bound, so the served HTML, a no-JS reader and a test all agree). For the visual flip, render **two mutually exclusive elements** with `x-show` + a server-applied `x-cloak` on the inactive one. Do NOT put a server-rendered utility pair and an Alpine `:class` pair on the same element: after a tap both pairs sit in the `class` attribute and the winner is decided by Tailwind's stylesheet order, not by the author — the browser showed a heart that turned rose but stayed an outline. Same rule for `x-text`-only labels (they render empty until Alpine boots) and for any state that only Alpine knows.
47. **A container singleton holding VIEWER state needs a forget-per-request middleware (v1.7.4).** `bookmarked.ids` is a singleton so a grid of cards costs one query per request, which is correct on PHP-FPM and wrong everywhere else: Octane, queue workers and the Laravel test client keep the container between “requests”, so the previous viewer's hearts leak into the next response. `App\Http\Middleware\ForgetPerRequestState` drops it on the way in (appended to the `web` group). Apply the same treatment to any future memo of per-viewer state; never put it in a `static` property.
49. **THE ARCH SUITE MUST BE REGISTERED IN `phpunit.xml` OR IT IS DEAD CODE (v1.7.5 — the most expensive lesson in this file).** `tests/Arch` held three guards this handoff has cited as permanent enforcement since v1.4.1 (`BladeFormVerbTest`, `MigrationDropGuardTest`, `NoBladeLeakTest`) and **none of them had ever run**: `phpunit.xml` only declared Unit and Feature. Every “locked by an arch test” claim about those files was fiction until v1.7.5 wired the suite up — which immediately surfaced a live navbar leak and two unguarded `up()` drops. **When you add a test under `tests/Arch`, verify it appears in `php artisan test --list-tests`** — a passing suite proves nothing about a file the runner never loads. Also note `MigrationDropGuardTest` is scoped to `up()` on purpose: the house culture is append-only and `pv:update` never rolls back, so `down()` drops cannot 1091 a replay.
50. **A component that merges caller classes must own its geometry (v1.7.5).** `x-user-avatar` merged the caller's class list, so one hero call site could paint a `rounded-3xl border-4 bg-saffron` tile around the avatar and suppress the size class — the founder's frame-misalignment incident, the FOURTH geometry ruling. The component now derives size from the `size` prop only, strips `rounded-/size-/bg-/border/overflow-` tokens before merging, and an arch test bans those attributes at every call site. Generalise it: a shared visual component must never let a caller set the properties it positions its own layers against.
51. **One coordinate system per composite (v1.7.5).** The v1.7.4 hero had three: the wrapper's border box (96px, 4px border), the padding box the `size-full` clipper resolved against (88px at +4/+4), and the overlay's over-constrained inset box (88×109 for a SQUARE png — all four insets over-determine a replaced element, so `object-contain` letterboxed the art inside it). Layers that must align share ONE box and inset against it; `inset-0` everywhere plus a per-frame inline inset for the photo. When a bug smells like “it’s off by a few pixels”, measure the boxes in the browser (`getBoundingClientRect`) and compare centres before reading any Blade.
53. **Mail defaults to cPanel Exim (sendmail) so auth mail works with zero config; SMTP credentials live in `SettingsService::SECRET_KEYS` and the admin field is write-only — never mirror secrets into `.env` on prod** (v1.7.6). `config/mail.php` is `env('MAIL_MAILER', 'sendmail')` on purpose: a fresh install must be able to send a password-reset link before anyone has configured anything, and `log` (Laravel's default, which this app shipped with) silently discarded it. Host-level overrides belong in **Admin → Email**, which writes encrypted rows and is applied at boot — putting the same credential in `.env` on prod would defeat the encryption and leave a plaintext secret in a file that is shared over FTP. `mail_password` is in `SECRET_KEYS`: it is encrypted at rest, never rendered back, and an empty submission keeps the stored value. If a host blocks local sendmail, switch the mailer to **smtp** with the mailbox password from the panel and re-send a test from there — do not edit `.env`. The failed-send flash deliberately shows only the exception class + one line with the password scrubbed.
54. **`Mail::fake()` cannot see notification mail in Laravel 12 — assert the array transport instead (v1.7.6).** The channel hands a returned Mailable to `Mailable::send()`, which calls `$mailer->send($this->buildView(), …)` with a **view name**, and `MailFake::sendMail()` records only `instanceof Mailable` — so every notification mail vanishes from the fake. `Notification::fake()` hides render failures (it never renders); the honest lock reads `app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()` and inspects the composed message. Same class of trap as §6.49: a green assertion about a code path the test never really ran.
55. **A full suite run can leave `bootstrap/cache/config.php` pinned to `:memory:` — `artisan serve` then dies with “no such table: sessions” (v1.7.4).** `phpunit.xml` sets `DB_DATABASE=:memory:`, and a test that runs the deploy pipeline re-caches config from that environment. The result is a dev server that 500s on EVERY page while the suite is perfectly green. Before any browser verification, run `php artisan config:clear` (then `migrate --force` + `DemoContentSeeder` if the dev SQLite is empty). Never commit `bootstrap/cache/config.php` (§6.21) — this is the same poison arriving by a different door.

35. **Views are stats, not money — upserts allowed there, never on the ledger/payouts (v1.7.0).** prompt_daily_stats upserts per (prompt, day); wallet_transactions stays insert-only with zero exceptions. View counting excludes owner/staff and dedupes per session-hour via session key. Series are file-cached 10 min (TTL honest); the database-cache store SURVIVES RefreshDatabase — tests must cache()->flush() before asserting empty-data states.
32. **Empty ledger is a first-class prod state — every money aggregate must survive zero rows; SUM-null is a 500 until cast (v1.6.1).** Every SUM crosses the service/controller edge as `(int)`. AND: `autoload.files` helpers (app/Support/money.php) do NOT exist on the host after a code-only update zip — the no-composer host cannot refresh vendor/, so money-rendering views MUST use the `function_exists('money_npr')`-guarded `<x-money>` component (the v1.6.1 prod /earnings 500 root cause). If composer.json autoload changes again, the SAME trap applies to any new helper file: either define a guarded fallback or ship a full release zip. The zip builder's composer.lock guard is dead code unless `deploy/promptsewa-main-1-0-0.zip` exists — restore that baseline or add a content-hash check against composer.json.
22. **MySQL DDL autocommits — never rely on transactions around schema changes (v1.4.3).** An interrupted migration leaves half-applied DDL with NO row in `migrations`; the replay then dies on SQLSTATE 1091 (dropping an object that no longer exists). Every `dropUnique`/`dropIndex`/`dropColumn`/`dropForeign` in a migration MUST be guarded by an existence check from `App\Support\SchemaInspector` (`hasUniqueIndex`/`hasIndex`/`hasColumn`) — `Arch\MigrationDropGuardTest` enforces this repo-wide. To repair a half-migrated schema, add a back-dated repair migration (see 120999) rather than editing a committed migration. If `pv:update` dies, the site STAYS in maintenance mode and `core/storage/logs/update-failed.json` carries the recovery checklist — do NOT simply re-run against a half-state.

## 7. Deploying the current update

### Installing on a NEW server (fresh install, v1.7.5+)

`dist/promptsewa-<version>-install.zip` is a **different artifact** from the update zip — it carries `vendor/` and everything else a bare server lacks. Use it ONLY for a first install; every later change goes through `update.php`.

1. Create the MySQL database + user in cPanel (**ALL PRIVILEGES**). SQLite is offered by the wizard for evaluation only.
2. Upload `promptsewa-1.7.5-install.zip` to `/home/USER/` and **Extract it there**, so you end up with `/home/USER/core/` and `/home/USER/public_html/`. Verify the SHA-256 matches the one in §4 before extracting.
3. Open `https://your-domain.com/install.php` and follow the wizard. It writes `core/.env` with a fresh APP_KEY, runs the migrations, creates your admin account (handle derived from the admin name), runs `storage:link`, rebuilds config/route/view caches, copies the compiled assets into the docroot, writes a **random** `public_html/.update-token`, and then locks itself.
4. **Delete `public_html/install.php`** once it reports success.
5. Add the cron the wizard prints: `cd /home/USER/core && /usr/local/bin/php artisan schedule:run` (confirm your binary path with `which php`).
6. Keep `public_html/.update-token` — `update.php` needs it, and it is the only thing that authorises an update.

**Expect an empty catalogue.** The wizard sets `APP_ENV=production`, and D4 deliberately refuses the demo/bulk seeders there; the install log says so explicitly. Add your categories in Admin → Categories before creating prompts.

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

### Post-check (v1.7.1) — run in order after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Zip contents (builder output) | `entries: 404 \| hygiene audit: CLEAN (0 forbidden entries)` | `ReleaseHygieneTest::the built update zip carries no host-local artifacts…` |
| 2 | `php artisan migrate:status` on prod | exactly 130000 + 130100 + **190000** newly Ran | `DeployParityAcceptanceTest` (parity DB migrates all three in one batch) |
| 3 | Manual methods: set a kind (bank/esewa/other), reload | kind persists, chip shows in the list; checkout shows the kind icon + "Scan to pay" QR block per method | `MethodKindsAndLabelsTest`, `ManualPaymentMethodsTest` |
| 4 | Moderator hits `/admin/badges` + `/admin/frames` | 403 (was silently 200 before v1.7.1) | `AchievementsAndIdentityLinksTest::moderators are 403 on badges and frames management` |
| 5 | Admin sidebar as admin | Badges + Frames pills visible | `AchievementsAndIdentityLinksTest::admin nav shows badges and frames pills` |
| 6 | Page titles on prompt create/edit | "Add a new prompt" / "Edit: …" — never blank | `SeoRouteCoverageTest` (crawl + route-list sync) |
| 7 | Saved-prompt heart on a card + detail | filled rose when saved, outline when not | `SavedHeartAndTypeSelectorTest`, `BookmarkTest` |
| 8 | Admin → Overview shows version chip | `v1.7.1` chip matches the deployed release | `config('app.version')` single source |
| 9 | `cd core && php artisan test` | 372 passed, 13,663 assertions | whole suite |

SHA-256 of `dist/promptsewa-1.7.1-update.zip`: `0a10b5a436d82d64efdcc82cb7cbbc7eefaa69e95b425fdf7625349518591a18` (404 entries; ships P1–P5 + P1b–P4b, the kind migration, compiled `public/build`).

### Post-check (v1.7.6) — run in order after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Zip contents (builder output) | `entries: 446 \| hygiene audit: CLEAN (0 forbidden entries)` | `ReleaseHygieneTest::the built update zip carries no host-local artifacts…` |
| 2 | SHA-256 of the uploaded zip | `8c56f0da80b35c440bfd89f9616736e23319393b3d17dac08d14f3f804bfc1ba` | builder output |
| 3 | `php artisan migrate:status` | **no new migrations this release** — the reset flow reuses the baseline `password_reset_tokens` table | `PasswordResetTest` (round-trips real tokens) |
| 4 | **Admin → Email → Send test email** (the first thing to do) | a green toast naming your address and the rail (`sendmail` by default). No settings needed: the default is now cPanel Exim. If nothing arrives, switch the mailer to **smtp** (`mail.babal.host`, port `465`, encryption `ssl`, username + mailbox password), save, and press the button again | `MailConfigTest::the probe sends to the acting admin on the configured rail` |
| 5 | **Forgot password, end to end on a real account** | “Check your email” notice → mail arrives → the button opens the reset form → a new password logs you in and the old one no longer does | `PasswordResetTest::requesting a reset sends mail whose link really changes the password` |
| 6 | **Screenshot the received reset mail** | ink header bar, saffron button, working link — this image is the proof the rail is alive, and it is what unlocks the v1.7.7 receipts | the mail views themselves |
| 7 | **Login and Register** | the eye toggle on every password field; clicking flips the label to “Hide password”, the icon to eye-off, and reveals the text | `PasswordVisibilityTest::login, register and the reset form all render the eye toggle` |
| 8 | Suspend a test member and log in as them | a **rose** toast now says why (previously it bounced home in silence) | the `error` flash in `app-layout` |
| 9 | `cd core && php artisan test` | **511 passed, 14,309 assertions** — and `Tests\Arch\…` appears in `--list-tests` | whole suite |
| 10 | Admin → Overview | `v1.7.6` chip | `config('app.version')` single source |

### Post-check (v1.7.5) — run in order after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Zip contents (builder output) | `entries: 427 \| hygiene audit: CLEAN (0 forbidden entries)` | `ReleaseHygieneTest::the built update zip carries no host-local artifacts…` |
| 2 | SHA-256 of the uploaded zip | `8a792219749c42feabf99c98d8f9879a6bc84a52e54c25061a24b64b9b18ef35` | builder output |
| 3 | `php artisan migrate:status` | `2026_10_02_230000_add_hole_percent_to_frames` newly **Ran**; every existing frame now reads `hole_percent = 62` | the migration's column default backfills; `FrameTruthTest::the hole tolerance is one pair of constants…` |
| 4 | Open a framed creator profile (the acceptance reference) | the ring is CONCENTRIC with the photo, no saffron squircle, no offset — measure it: frame and photo centre offsets are both 0.00/0.00 in DevTools | `FrameTruthTest` (arithmetic) + the browser gate |
| 5 | Admin → Frames → edit the founder's Abyssal row, set Centre hole % to `38`, save | the profile still lines up; an out-of-range value (e.g. 12) is refused with a validation error | `FrameTruthTest::an admin can set a per-frame hole and an out-of-range value is refused` |
| 6 | Every avatar surface: profile hero, both card variants, gallery, library, navbar pill, dock You, feed, typeahead row, versions, purchases, admin users table | all concentric; nothing clipped | `UserAvatarGeometryTest`, `AvatarFidelityTest`, `AvatarFrameCompositionTest` |
| 7 | Navbar **impersonation** bar (impersonate a member as admin) | it reads "Acting as @handle" — NOT a raw escaped-brace expression | `NoBladeLeakTest` |
| 8 | `cd core && php artisan test` | 480 passed, 14,094 assertions — and `Tests\Arch\…` appears in `--list-tests` | whole suite |
| 9 | Admin → Overview | `v1.7.5` chip | `config('app.version')` single source |

### Post-check (v1.7.4) — run in order after the update.php pipeline finishes

| # | Check | Expected | Locked by |
|---|---|---|---|
| 1 | Zip contents (builder output) | `entries: 425 \| hygiene audit: CLEAN (0 forbidden entries)` | `ReleaseHygieneTest::the built update zip carries no host-local artifacts…` |
| 2 | SHA-256 of the uploaded zip | `3f9d85e206ff3420d384928315a2ba66b49c1041ca5265ee3eca14405979613b` — if it matches the OLD `a0cd9853…` you are uploading a G-only build without H4 | builder output |
| 3 | Log in as a plain member, open the home grid, hard-refresh (F5) | every heart you saved is FILLED ROSE; your unsaved cards are outlines — on home, the library, search results, the creator grid AND the detail page | `SavedHeartParityTest` (5 surfaces) |
| 4 | Click an unsaved heart, watch the Network tab | `POST /bookmarks/{slug}` → **200**, and the heart fills IMMEDIATELY (filled, not just coloured) | browser gate — Pest cannot execute `fetch()` |
| 5 | Click it again | back to the outline; a refresh still shows the outline (the row is gone) | same |
| 6 | Blank `<meta name="csrf-token">` in DevTools, click a heart | `POST` → **419**, the heart snaps back and a rose toast reads “Save failed — your session expired. Reload and try again.” — on the card AND on the detail page | browser gate + `SavedHeartParityTest::every heart surface tells the user when a save failed` |
| 7 | Open a creator profile with a framed avatar and a prompt card | the frame ring is COMPLETE — nothing clips it (no ancestor `overflow-hidden`) | `FrameTruthTest::no overflow-hidden ancestor clips the protruding frame` |
| 8 | Open a populated pack landing | real prompt count, real `AVG(score)` (or “No ratings yet”), savings only when the contents really cost more; dark hero/contents/buy card | `PackLandingV2Test` (10) |
| 9 | Phone width (<768px) home + dashboard | hero scales down, one primary CTA, snap rails/carousel, 2-col gallery, scrolling tab row, full-width sparklines | `MobileEngagementPassTest` (10) |
| 10 | Admin → Overview | `v1.7.4` chip | `config('app.version')` single source |
| 11 | `cd core && php artisan test` | 470 passed, 14,018 assertions | whole suite |

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
| v1.6.0 (2026-09-30) | — incident-free release | n/a | Money Core shipped with the full M6 gate green (312 passed / 13,300 assertions); zero live incidents recorded |
| v1.7.1 (2026-09-30) | Admin badge/frame index pages served 200 to MODERATORS — an admin-only surface with no read gate (found pre-release by the P1b door work, never exploited) | indexes relied on the write-route policy only; no explicit admin gate on the read route | v1.7.1 (abort_unless isAdmin on both indexes + moderator-403 test + nav pills so admins can actually reach them) |
| v1.7.2 (2026-09-30) | Founder: uploaded frame PNGs rendered with BLACK corners on cards | GD encoded the final image with blending still ON — alpha composited onto the canvas default (black); the save-time flags pair was missing (see §9) | v1.7.3 (W2 alpha truth: blending OFF + save-alpha ON before EVERY encode + on the downscale canvas; pixel tests lock corner alpha) |
| v1.7.5 (2026-10-02) | **Framed avatar rendered as a saffron squircle with the ring and photo hanging off its top-left** (founder screenshot). Follow-on: the founder's own `Abyssal.png` could not have been drawn correctly by ANY single geometry (its transparent hole is 37.5%, the hard-coded art assumed 55–70) | Two independent causes. (1) **Caller class merge**: `creators/show.blade.php` passed `size-24 rounded-3xl border-4 border-paper bg-saffron` into `x-user-avatar`, which merged it onto the wrapper — so the squircle was the WRAPPER painting itself, and the 4px border split the box a reader sees from the padding box the photo resolved against. (2) **Split coordinate systems**: the overlay's four negative insets over-constrained a square `<img>` into an 88×109 box that `object-contain` letterboxed. 4th geometry ruling on this component (v1.7.2 squircle → v1.7.3-hotfix inside-clip → v1.7.4 protrusion → v1.7.5 composite box) | v1.7.5 (composite box: wrapper/frame/photo in ONE box + `x-cloak`-free `inset-0` frame + per-frame `frames.hole_percent` 35–70 default 62, Abyssal at 38 + geometry-merge ban arch test + `size="xl"` hero; see §6.49–§6.51) |
| v1.7.5 (2026-10-02) | **The impersonation chrome bar showed staff a raw escaped-brace expression instead of the handle**; `tests/Arch` had never run at all | `tests/Arch` was missing from `phpunit.xml`'s testsuites, so all three arch guards were dead code — the leak this one exists to prevent shipped and stayed invisible because the suite could not see it | v1.7.5 (Arch suite registered; leak fixed with `'@'.…` inside a plain echo; two unguarded `up()` DDL calls fixed — `SchemaInspector::hasForeignKey()` added — and the guard scoped to `up()` since down() never runs) |
| v1.7.5 (2026-10-02) | **The whole arch suite was fiction.** Three guards this handoff cites as permanent repo-wide enforcement (`BladeFormVerbTest`, `MigrationDropGuardTest`, `NoBladeLeakTest`) had never executed a single assertion, because `phpunit.xml` declared only the Unit and Feature testsuites. Every “locked by an arch test” claim about those files was untrue until v1.7.5 added the testsuite — and registering it immediately found a live navbar leak plus two unguarded destructive `up()` DDL calls | Test discovery is driven by `<testsuite>` entries, not by directory convention: a `tests/Arch` folder is not a suite unless `phpunit.xml` says so. The cost of the discovery is the lesson — see §6.49 | v1.7.5 (Arch testsuite registered; damage fixed; **`--list-tests` is now a release gate**, see §6.49) |
| v1.7.6 (2026-10-02) | **“Forgot password” did not exist anywhere** — no route, no controller, no view — so a locked-out user had no way back in; and `MAIL_MAILER` defaulted to `log`, meaning any mail the app did send was written to a log file while the UI promised “check your inbox” | The feature was never built (only the baseline `password_reset_tokens` table and the `config/auth.php` broker block were in place), and the Laravel mail default (`log`) is correct for a scaffold and wrong for a product that owes the user a recovery path | v1.7.6 (A1 complete broker-backed flow + branded mail + honest failure reporting; A2 eye toggle; A3 `sendmail` default + Admin → Email; see §6.53–§6.54) |
| v1.7.6 (2026-10-02) | **Every `error` flash since v1.7.3 was invisible.** `app-layout` rendered `session('success')` and nothing else, so a suspended account was bounced to the home page with no explanation — and the new mail probe's honest failure report would have been swallowed the same way | The flash key was written by controllers but never read by a view; nothing asserted on the rendered layout for it | v1.7.6 (rose `role="alert"` toast beside the green one; the same §6.45 “silent lies are banned” rule that governs the bookmark fetch) |
| v1.7.4 (2026-10-02) | **Saved heart lost its state on every refresh** (home, image gallery, search, creator profile, detail). Invisible to Pest: every functional test posts the toggle and asserts the row, and CSRF is skipped in tests, so nothing ever looked at what the ROUTE rendered. Browser-first repro under `artisan serve` as a plain member: `POST /bookmarks/{slug}` → **200** with the row written, then `aria-pressed="false"` after F5 — the toggle worked, the RENDER lied. Follow-on defect found only by looking at computed styles after a tap: the heart turned rose but stayed an OUTLINE (the SSR pair and Alpine's `:class` pair fought in the cascade), and the detail save button reverted a 419 in complete silence | `saved` lived only in Alpine — only the library grid passed `:saved` into `x-prompt-card`, so every other surface rendered the component's `false` default and the next page load undid the optimistic flip. Two secondary causes: the fetch sent no `X-CSRF-TOKEN` (419 was one expired session away, and non-OK responses were swallowed) and the failed-save toast existed on the card only | v1.7.4-H4 (`BookmarkedIds` per-request parity set + `ForgetPerRequestState`; `saved` server-rendered everywhere; hardened fetch contract with revert + rose toast on every heart surface; collision-free `x-show` icon pair; `SavedHeartParityTest` 12 locks; browser check is now a release gate — see §6.45–§6.46) |
| v1.7.3 (2026-10-02) | Prompt body capped at 4,000 chars — creators of long system/agentic prompts hit the wall (feature request); latent prod risk: `prompt_versions.body` was still TEXT (65,535 BYTES) on techadda_main, so any future ceiling raise without a column widen would silently truncate multibyte bodies | validation-only ceiling from v1.0; the 2026_09_29 snapshot migration's `change()` had only taken effect on SQLite dev — MySQL prod never got the LONGTEXT | v1.7.3-hotfix (MAX_BODY_CHARS 50,000 constant + TEXT→LONGTEXT migration in the SAME release + rendered-route ceiling tests; see §9 watch-out 43) |
| v1.7.7 (2026-10-04) | Admin → Comp grants served **200 to moderators** while its store endpoint was admin-only — a write-gated feature with an ungated read door (found pre-release by the raid's route × role matrix, never exploited) | only `store()` carried the admin gate; nothing swept read doors per role | v1.7.7 (BH-R1-01: `abort_unless(isAdmin)` + `RoleSurfaceMatrixTest`; repro/fix `ef2fc01`/`f63b55d`) |
| v1.7.7 (2026-10-04) | Moderators saw **seven dead admin actions** — admin-only nav pills (5, each a 403), Users purge/adopt panels, Orders approve/reject buttons, Payments/Brand/Manual-methods save forms | write-route policies were enforced server-side but never mirrored into the views; nothing swept dead links/forms per role | v1.7.7 (BH-R2-01..05: role-aware rendering + `DeadLinkScanTest`; `a5fd871`/`102c924`) |
| v1.7.7 (2026-10-04) | Founder: **“I cannot see all of my prompts”** in the comp-grant picker — drafts/pending/rejected prompts invisible and un-grantable | `published()` scope applied to both the picker and the store lookup — a filter nobody intended as a gate became one (same class as the v1.4.4 key mismatch) | v1.7.7 (BH-001/BH-002: full-status list + `x-searchable-picker` combobox; `82f7c1b`/`2bc402f`) |
| v1.7.7 (2026-10-04) | **Buyers could not upload a payment proof** — the form rendered only for “manual + no reference”, a state `checkout.manual.submit` never leaves, so the advertised “upload your payment proof” step was a dead action (`orders.proof.store` unreachable from the served UI) | the checkout Blade's “manual + reference exists” branch pre-empted the branch that included the proof form; no test rendered the page in that state | v1.7.7 (BH-R6-01: the form renders for every pending manual order; repro/fix `0337c4f`/`28ff793`) |

— Prepared by Codebuff. Questions about any section: start from the file map and read the docblocks; every non-obvious decision is commented inline in the code.
