# BUG-HUNT RAID — v1.7.7 catalog

> The raid's single record of truth. Every bug gets a row here, every row
> carries its locking test and the commits that fix it. Counts by severity
> are finalised in R8.

## R0 — Freeze & baseline

- **Branch:** `raid/v1.7.7`, cut from the v1.7.6 release commit `5550630`
  (`feat(auth): v1.7.6 "Auth & Mail" — a recovery path that can actually mail`).
  **NOTE:** the directive says "branch from the v1.7.6 tag"; **no `v1.7.6` tag
  exists** — locally or on `origin` (tags stop at `v1.7.2`). The release commit
  on `main` is the artifact of record; the missing tag is a release-hygiene
  finding for R7/R8 (retagging is a release-process action → mediator).
- **Baseline suite:** `php artisan test` → **511 passed / 14,309 assertions**
  (164.99s). Matches the v1.7.6 handoff exactly.
- **Baseline hygiene:** `deploy/build-update-zip.php` → **446 entries**
  (442 core + 4 docroot), **hygiene audit: CLEAN (0 forbidden entries)**,
  SHA-256 `8c56f0da80b35c440bfd89f9616736e23319393b3d17dac08d14f3f804bfc1ba`
  — byte-reproduced from the v1.7.6 handoff.
- **Baseline `--list-tests`:** **511 tests enumerated, 6 of them Arch**
  (`grep -F 'Tests\Arch'`): `BladeFormVerbTest` (1), `MigrationDropGuardTest` (1),
  `NoBladeLeakTest` (1), `UserAvatarGeometryTest` (3). The §6.49 gate passes.

### Environment note

- PHP 8.4.25 at `/c/php/bin` (not on PATH); Laravel 12.69.2; Windows + Git Bash.
- The `--list-tests` list is printed to **stderr** — grepping stdout yields a
  false "0 Arch tests" (this raid's first near-miss). Use `2>&1` or `2>/dev/null`
  consistently.

## R1 — Route × role matrix

- **Test:** `core/tests/Feature/RoleSurfaceMatrixTest.php` — 3 tests, **1,049 assertions**.
  Data-driven over the route registry: every named GET route must be in the
  matrix or in the exempt map **with a reason** (a new route cannot skip the
  sweep silently). **52 named GET routes × 7 fixtures = 364 responses**, each
  asserted against its gate's promised outcome (200/302/403/404), zero 500s,
  exactly one `<title>` per HTML 200, no `{{ $` leaks, no raw escaped braces.
  Distinct client IPs per fixture so `throttle:6,1` on the password broker
  does not poison the seventh sweep (the probe's 429 was a harness artifact,
  not a product bug).
- **Findings:**
  - **BH-R1-01 (P1, fixed)** — `/admin/comp-grants` served **200 to
    moderators** while its store endpoint is admin-only (§6.36 class: a
    write-gated feature with an ungated read door). Locked by the matrix
    (`admin` shape, moderator 403) and fixed with the same `abort_unless`
    in `CompGrantController::create`. Fix commit below.
  - **BH-P3-01 (P3, hardening)** — `config/filesystems.php` sets
    `'serve' => true` on the `proofs` disk, so Laravel registers a public
    `GET storage/{path}` route (`storage.proofs`, plus a PUT upload route)
    with no app middleware. It is **not an open leak**: the disk has private
    visibility, so `ServeFile` 404s in production without a valid signed URL.
    But the app's contract is that proofs stream *only* through
    `orders/{order}/proof`; the unused route is surface a future editor could
    mistake for a serving path. Exempted with a reason in the matrix;
    removal flagged for the mediator (config change).
- **Route-registry catch:** the completeness assertion found `storage.proofs`
  on its first run — the probe had silently skipped it (no URL resolver).
  That is the sweep earning its keep.

## R2 — Dead-link & dead-action scan

- **Test:** `core/tests/Feature/DeadLinkScanTest.php` — 2 tests. For every
  surface the R1 matrix renders (5 viewer roles): every internal `<a href>`
  is resolved and fetched as the viewer (403/404/405/419/500 all fail), and
  every `<form action>` is resolved, verb-checked (POST + `_method` spoof)
  and checked against a `raidDeadLinkFormViewers()` map — the role the form
  is shown to must be allowed to submit it. Unclassified form routes fail;
  classified-but-never-rendered routes fail unless declared out of sweep.
- **Harness trap recorded:** `route()` emits absolute URLs, so the first
  scan silently skipped every link (leading-slash check) and reported all
  forms as unencountered. The normalizer now accepts app-host URLs — noted
  because "zero failures from a scan that matched nothing" is the exact
  silent-lie class this raid exists to kill.
- **Findings (all fixed in this raid):**
  - **BH-R2-01 (P1)** — admin-layout nav pills for **admin-only** doors
    (comp grants, badges, frames, security, email) rendered for moderators →
    five dead links, each 403. Fixed: admin-only pills hidden for non-admins.
  - **BH-R2-02 (P1)** — Admin → Users purge + catalog-adoption panels shown
    to moderators; both POST to admin-only routes. Fixed: panels admin-only,
    staff see an honest notice.
  - **BH-R2-03 (P1)** — Admin → Orders approve/reject buttons shown to
    moderators; both PATCH routes are admin-only. Fixed: buttons admin-only,
    moderators see "Awaiting admin decision".
  - **BH-R2-04 (P1)** — Admin → Payments / Brand / Manual methods save forms
    rendered for moderators while the controllers refuse them. Fixed: write
    forms admin-only (read access stays staff, per ADMIN-AUDIT), notices
    otherwise.
  - **BH-R2-05 (P1)** — Admin → Prompts linked `prompts.show` for every row;
    a non-published prompt 404s **even for staff** (public route is
    published+public or owner). Fixed: non-public rows link to the
    moderation preview route — the §6.17 rule.
  - **BH-R2-06 (P2, exempt) ** — `/update.php` link on Admin → Update matches
    no Laravel route (it is the docroot script that exists on a real
    install). Exempted with a reason.
- **Fixture depth added (shared with R1):** badge + frame + frame unlock +
  manual method + approved payout + an unpaid-rails order, so every per-row
  form shape renders and is classified — the completeness check now proves
  the map is not stale.

## R3 — Form round-trip inventory

- **Test:** `core/tests/Feature/FormRoundTripInventoryTest.php` — 8 tests,
  44 assertions. `raidFormExtract()` reads the SERVED form (action, verb,
  `_method` spoof, inputs/selects/textareas — browser semantics: checked
  boxes only, first option when none is `selected`) and submits it with the
  minimum overrides a human would type. `raidFormInventory()` is the table
  below, and the first test scans every Blade `<form>` **action attribute**
  and fails on any route missing from the inventory, so new forms cannot
  ship unclassified.
- **New round-trips shipped here** (all previously untested through the
  served HTML): badge create/delete, frame create/delete + award/revoke,
  payout request/cancel, buy-prompt, finance approve/settle/reject, admin
  security save, purge/adopt preview.
- **Harness notes (recorded so the next engineer doesn't re-learn them):**
  1. actions render as absolute URLs (`http://127.0.0.1:8123/...`) — match on
     the path;  2. update (PUT) and delete (DELETE) share one action URL —
     `raidFormExtract()` takes a required-verb filter, or it submits the
     wrong form;  3. the first inventory scan matched `route()` calls inside
     form **bodies** (links, JS) and "found" login/register/prompts.show —
     only the `action` attribute counts.

### Inventory (view form → action route → verb → locked by)

| View | Action | Verb | Locked by |
|---|---|---|---|
| auth/login | login.store | POST | AuthTest |
| auth/register | register.store | POST | SignupHardeningTest |
| auth/forgot-password | password.email | POST | PasswordResetTest |
| auth/reset-password | password.update | POST | PasswordResetTest |
| navbar / storefront | library.index | GET | SearchTest |
| navbar / profile | logout | POST | AuthTest |
| dashboard/profile | dashboard.profile.update | PUT | SearchPreviewTest |
| dashboard/prompts/create | dashboard.prompts.store | POST | PromptCreateEditTest |
| dashboard/prompts/edit | dashboard.prompts.update | PUT | PromptCreateEditTest |
| dashboard/earnings | dashboard.earnings.request | POST | **FormRoundTripInventoryTest (new)** |
| dashboard/earnings | dashboard.earnings.cancel | POST | **FormRoundTripInventoryTest (new)** |
| prompts/show | prompts.rate | POST | AdminFlowsTest |
| prompts/report | prompts.report.store | POST | PromptReportTest |
| prompts/versions | prompts.versions.restore | POST | CatalogAndVersionTruthTest |
| prompts/show | checkout.prompts.buy | POST | **FormRoundTripInventoryTest (new)** |
| packs/show | checkout.packs.buy | POST | CheckoutFlowTest |
| checkout/show | checkout.manual.submit | POST | ManualPaymentMethodsTest |
| _payment-proof-form | orders.proof.store | POST | ManualPaymentMethodsTest |
| admin/users | admin.users.impersonate | POST | ImpersonationTest |
| admin/users | admin.users.role | PATCH | CheckoutFlowTest |
| admin/users | admin.users.verified | PATCH | AdminFlowsTest |
| admin/users | admin.users.banned | PATCH | UserBanTest |
| admin/users | admin.users.purge.preview / .run | POST | **FormRoundTripInventoryTest (new, preview)** |
| admin/users | admin.users.adopt.preview / .run | POST | **FormRoundTripInventoryTest (new, preview)** |
| admin/prompts | admin.prompts.status | PATCH | AdminReviewTest |
| admin/reports | admin.reports.status | PATCH | AdminFlowsTest |
| admin/packs / pack-form | admin.packs.store / .update / .destroy | POST/PUT/DELETE | AdminFlowsTest |
| admin/tool-logos | admin.tool-logos.store / .update / .destroy | POST/PATCH/DELETE | AdminFlowsTest |
| admin/payments | admin.payments.update | PUT | CheckoutFlowTest |
| admin/brand | admin.brand.update | PUT | BrandSettingsTest / BrandLogoTest |
| admin/security | admin.security.update | PUT | **FormRoundTripInventoryTest (new)** |
| admin/email | admin.email.update / .test | PUT/POST | MailConfigTest |
| admin/finance | admin.finance (filter) | GET | FinanceController tests |
| admin/finance | admin.finance.payouts.approve / .settle / .reject | POST | **FormRoundTripInventoryTest (new)** |
| admin/badges | admin.badges.store / .award / .destroy | POST/DELETE | **FormRoundTripInventoryTest (new: store/destroy)** / GamificationTest (award) |
| admin/frames | admin.frames.store / .update / .destroy / .award / .unlocks.revoke | POST/PUT/DELETE | **FormRoundTripInventoryTest (new: store/destroy/revoke)** / FrameTruthTest (update/award) |
| admin/manual-methods | admin.manual-methods.store / .update / .destroy | POST/PUT/DELETE | ManualPaymentMethodsTest |
| admin/orders | admin.orders.approve / .reject | PATCH | CheckoutFlowTest / AdminFlowsTest |
| admin/comp-grants | admin.comp-grants.create (search) / .store | GET/POST | **R5 tests** / CompGrantTest |
| dashboard/update | admin.update.run | POST | AdminFlowsTest / UpdaterFailureRecordTest |
| navbar (impersonating) | impersonation.stop | POST | ImpersonationTest |

- Every other form in the tree targets none of these? No — the completeness
  test is the guard: **every** `<form action="{{ route(...) }}">` in
  `resources/views` is above, or the suite fails.

## R4 — Edge-state matrix

- **Test:** `core/tests/Feature/EdgeStateMatrixTest.php` — 5 tests,
  **442 assertions**. Contexts rendered:
  - **empty fixture** — library (`No prompts found`), packs (`No packs are
    live yet`), feed (`The feed is quiet`), purchases (`No purchases yet.`),
    dashboard (`No prompts yet`), creator profile (`Nothing published yet`),
    plus a 24-URL blank-panel/500 sweep across storefront, dashboard and
    every admin pill. Finance must print a real `Rs. 0` on an empty ledger.
  - **overflow/unicode/deleted relations** — a 200-char title (validation
    caps at 160; the renderer must not care), Devanagari bio (asserted
    byte-preserved in the body), a force-deleted version author
    (`Former creator` chip), and a user whose equipped frame was deleted
    (`nullOnDelete` → frameless, no 500).
  - **30-row pagination** — page 2 of library, admin prompts/users/reports/
    orders and purchases (31 rows each).
  - **checkout both rails** — with manual + eSewa enabled, the checkout page
    shows both rails and `checkout.esewa.pay` really builds the gateway
    redirect (200/302, never 403).
  - **impersonation** — home, library, dashboard, purchases, creator profile
    all render the chrome bar (`Acting as`, the target `@handle`, `Return to
    my account`) with no identity leak or 500.
- **Result: no new defects.** Every listed state rendered honest copy; the
  two locked truths are the empty-ledger `Rs. 0` and the deleted-relation
  fallbacks (both were pre-existing strengths).
- **Harness traps recorded (in-file comments):** (1) Pest `toContain($needle,
  $message)` treats the message as a second NEEDLE — that produced a
  phantom "lost its empty-state copy" failure for a page that had the copy;
  (2) the test client does not carry session cookies between requests, so
  impersonation state is re-seeded per request with `withSession`; (3) the
  chrome bar puts the handle inside `<strong>`, so the assertion checks the
  parts.

## R5 — BH-001/BH-002 comp grant form redesign (founder-mandated)

### BH-001/BH-002 — reproduce, then root cause

- **Repro test:** `core/tests/Feature/CompGrantPickerTest.php` committed
  failing at `82f7c1b` — 5 failing / 3 passing. The passing three
  (idempotency, mandatory reason, moderator 403) were held green through
  the redesign by design.
- **ROOT CAUSE (the query that filters/limits the prompt list):**
  `CompGrantController::create()` builds the picker with
  `Prompt::query()->published()`, and the `published()` scope is
  `where('status', self::STATUS_PUBLISHED)` — **every draft / pending /
  rejected prompt is invisible**, which is exactly "cannot see all of my
  prompts". `store()` re-applied the same scope
  (`Prompt::query()->published()->findOrFail()`), so a draft id was not
  grantable even when posted directly (404). The plain stacked `<select>`
  over 277 rows was the second half of the complaint.

### The redesign

- **`x-searchable-picker`** (`resources/views/components/searchable-picker
  .blade.php`) — a reusable Alpine combobox: server-rendered options (each
  with `data-value` / `data-chip` / `data-search`), client-side visibility
  filter, ArrowUp/Down + Enter + Escape keyboard handling, selected value
  as a chip with a clear button, a real hidden input the form submits, and
  a `max-h-56` option list so 277 rows can never stretch the page.
- **`comboboxPicker`** in `resources/js/app.js` — the one new Alpine data
  component; no new dependencies. `npm run build` recompiled the bundle.
- **`admin/comp-grants.blade.php`** — two-column grid (recipient | prompt)
  with the reason textarea beneath and ONE submit row; option text carries
  the title, the status chip, and the price through `<x-money>` (money_npr
  format, e.g. `Rs. 249.00`). The users list is capped at 300 with the
  server-side Find form retained for the rest of the table.
- **`CompGrantController`** — serves the FULL prompt list (every status,
  ordered by title, id/title/status/price) and drops the `published()`
  scope from both create() and store(). Validation, admin gating,
  idempotency and the audit trail are unchanged.

### Locks (all in `CompGrantPickerTest`, 8 tests)

1. every prompt id present in the combobox payload (draft + published +
   pending);
2. **277 prompts render once** — every id in the served HTML,
   `role="option"` count ≥ 277;
3. option text carries title + status chip + `money_npr` price (`Rs. 249.00`,
   not the legacy `priceLabel`);
4. a draft and a published prompt are both grantable by an admin;
5. idempotent at the HTTP boundary (double submit → one grant + error);
6. reason still mandatory;
7. moderator 403 on page and store;
8. served structure: no `<select name="prompt_id">`, two-up
   `sm:grid-cols-2` grid, `role="combobox"` + `role="listbox"`, bounded
   `max-h` list, exactly one submit button in the grant form.

## R6 — Browser gate

_(pending)_

## R7 — Fix & lock log

_(pending)_

## R8 — Release

_(pending)_

## Bug ledger (severity summary)

| ID | Surface | Severity | Symptom | Reproduce | Fix | Status |
|----|---------|----------|---------|-----------|-----|--------|
| BH-R1-01 | Admin → Comp grants (read door) | P1 | Page served 200 to moderators while the store endpoint 403s them | `RoleSurfaceMatrixTest` (moderator row, admin shape); probe: `admin.comp-grants.create \| moderator \| 200` | `abort_unless(isAdmin)` in `CompGrantController::create` | Fixed — see R7 log |
| BH-P3-01 | `proofs` disk `serve => true` | P3 | Unused signed-URL framework route (`storage.proofs`) exists alongside the owner/staff proof route | `RoleSurfaceMatrixTest` completeness (exempt with reason); `ServeFile` source | Proposed: drop `serve => true` from the proofs disk (mediator: config) | Open (hardening) |
| BH-R2-01 | Admin nav pills (moderator view) | P1 | Five admin-only pills rendered for moderators, each a 403 | `DeadLinkScanTest` first run: `moderator \| admin.dashboard \| href /admin/badges → 403` (+4) | Hide admin-only pills for non-admins (`admin-layout`) | Fixed — see R7 log |
| BH-R2-02 | Admin → Users purge/adopt panels | P1 | Forms visible to moderators, POSTs 403 | `DeadLinkScanTest`: `moderator \| admin.users.index \| form POST /admin/users/purge-demo/run → limited to admin` | `@if(isAdmin)` panels + notice | Fixed — see R7 log |
| BH-R2-03 | Admin → Orders approve/reject | P1 | Buttons visible to moderators, PATCHes 403 | `DeadLinkScanTest` (2 rows) | Admin-only buttons; moderators see "Awaiting admin decision" | Fixed — see R7 log |
| BH-R2-04 | Payments / Brand / Manual methods forms | P1 | Save forms visible to moderators, PUT/POST 403 | `DeadLinkScanTest`: `moderator \| admin.payments.edit \| form PUT /admin/payments → limited to admin` (+2) | Write forms admin-only; staff read stays (ADMIN-AUDIT) | Fixed — see R7 log |
| BH-R2-05 | Admin → Prompts title links | P1 | Non-public prompt rows linked the public route → 404 for staff | `DeadLinkScanTest`: `moderator \| admin.prompts.index \| href /prompts/… → 404` | Non-public rows link the moderation preview | Fixed — see R7 log |
| BH-R2-06 | Admin → Update `/update.php` link | P2 | Matches no Laravel route (dev); exists on a real install | `DeadLinkScanTest` first run | Exempt with reason (docroot script) | Exempted |
| BH-001/BH-002 | Admin → Comp grants prompt picker | P1 (founder) | `published()` scope hid every non-published prompt and blocked granting one; stacked select over 277 rows | `CompGrantPickerTest` failing at `82f7c1b` (5 failures) | Full prompt list + `x-searchable-picker` combobox; `published()` dropped from create+store | Fixed — see R7 log |
