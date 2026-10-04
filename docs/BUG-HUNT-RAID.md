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

Run under `php artisan serve` (`http://127.0.0.1:8123`) with the admin
session, via the browser panel. Screenshot capture is unavailable in this
environment (`webview not composited`), so every step is verified through
DOM/a11y state plus server state, using real clicks and real key events.

### 1. Comp grant end-to-end — **PASS**

- `/admin/comp-grants` renders the redesigned form: two `role="combobox"`
  pickers (recipient = 4 accounts, prompt = 26 prompts incl. draft,
  rejected, archived), two-column grid, one submit.
- Real click opens the prompt listbox (`display:block`, 26 options, capped
  224px list). Real typing `arch` filters to 3 options and the list stays
  open. Real ArrowDown + Enter picks: chip shows the title, hidden input
  `prompt_id=26`, search box cleared, list closed.
- Recipient picker same: `user_id=3` (Maya Tamang), chip renders.
- Submit → redirect back with flash "Comp grant issued — Maya Tamang now
  owns \"Archived — Twitter Thread Reformatter\" (ledger tier: comp)."
- Ledger row (tinker): `license_grants` #1, user `maya@promptsewa.test`,
  prompt 26, `license_tier=comp`, `issued_by=1`, reason recorded verbatim,
  `status=active`, grant code issued.
- A rejected-status prompt was grantable in the real UI — BH-001/BH-002 is
  dead in the browser, not just in the locks. Clear button works.
- Note: this gate issued one real comp grant in the dev DB; the ledger is
  insert-only by design, so the row is kept.

### 2. Impersonate → act → return — **PASS**

- `/admin/users` → "Switch" on Maya (POST `/admin/users/3/impersonate`)
  → chrome bar "Acting as @maya-tamang — returned session restores Aasha
  Gurung" with a "Return to my account" form.
- Acted as Maya: Alpine bookmark heart on the homepage → `POST
  /bookmarks/follow-up-sequence-that-doesnt-stalk → 200`, `aria-pressed`
  flips; DB row attributes to `maya@promptsewa.test` (not the admin);
  toggling back removes it.
- "Return to my account" (POST `/impersonation/stop`) lands back on
  `/admin/users`, bar gone, admin nav restored.
- Audit row: `impersonations` #1 admin → maya, started 08:20:35, ended
  08:22:31. (This also covers the Alpine `bookmarkHeart` mutation.)

### 3. Ban → login bounce — **PASS**

- Admin → Users → Ban on Dorje: flash "Dorje Lama is now banned.", row
  shows the "Banned — lift" button.
- Incognito tab, login as `dorje@promptsewa.test` → lands on the homepage
  unauthenticated with "This account has been suspended. Contact support if
  you believe this is a mistake." (`RejectBannedUsers` logs the session out
  and bounces to home by design — documented in the middleware).
- Ban lifted: flash "Dorje Lama is now unbanned.", row back to "Ban".

### 4. Reset-mail flow — **PASS**

- `/forgot-password` → submit `maya@promptsewa.test` → `/forgot-password/sent`
  with the enumeration-proof notice ("If that address belongs to an account…").
- Mail (log driver) contains the signed link
  `…/reset-password/<64-hex>?email=maya%40promptsewa.test`.
- The link renders "Choose a new password" with the email prefilled and the
  token hidden.
- Submitting the weak literal `password` is rejected: "That password is too
  common — choose something less guessable" (same on both fields).
- A bogus token rejects on POST: "This password reset token is invalid."
  (the GET form renders regardless — stock Laravel behaviour).
- Deliberately did NOT complete a reset: the policy cannot restore the
  shared fixture password, and changing a documented dev login would break
  other threads. Verified no change: `Hash::check('password')` still true
  and a fresh sign-in as Maya succeeds.

### 5. Frame equip at two hole percents — **PASS**

- Impersonated Maya → `/dashboard/profile`; both frames show "unlocked"
  chips (empty criterion = free for everyone).
- Equipped "x" (hole 62): flash "Profile updated.", both avatars in the
  composite box carry `data-frame-hole="62"` and the circle is
  `inset: 19%` — exactly (100−62)/2.
- Equipped "Abyssal" (hole 38): `data-frame-hole="38"`, circle
  `inset: 31%` (= (100−38)/2), Abyssal art in the frame layer.
- Reset to "None": composite returns frameless (`data-frame-hole`
  absent), flash "Profile updated.". Returned to admin.

### 6. Dock tabs — **PASS**

At 390×844 the mobile dock renders `fixed` with five tabs. Clicked through
all five: Home → `/`, Library → `/prompts`, Add prompt →
`/dashboard/prompts/create`, My library → `/purchases`, You →
`/creators/aasha` (the signed-in account's profile). Every click navigated
and `aria-current="page"` moved to the matching tab (the Add prompt action
tab deliberately has no `aria-current`).

### 7. Typeahead badges — **PASS**

- Verified Bibek through the real admin Users button (flash "Bibek
  Shrestha is now verified ✓").
- Navbar search `bibek` → predictive dropdown row: name, @handle, prompt
  count, his seeded Abyssal frame ring (W1 frame parity), and the saffron
  verified seal (`aria-label="Verified creator"`).
- Unverified him again (flash "Bibek Shrestha is now unverified.") and
  re-ran the search: same row, frame ring still there, **zero** seals —
  the badge is real data, not decoration.

### 8. Financial chain — manual rail → pack → proof → approve → payout — **PASS**

Walked entirely through served pages (admin session in the browser panel,
buyer in an incognito tab as Dorje 4 **so the seller and the approver are
never the same account**). One P1 found and fixed mid-flow (BH-R6-01).

- **Rail setup (admin UI):** `/admin/payments` → _Enable checkout_ +
  _Accept manual payments_ + instructions, saved → `payments_enabled=1`,
  `manual_payment_enabled=1` (canonical runtime key — the C2 lesson holds),
  instructions persisted. `/admin/manual-methods` → method
  **"eSewa — 9800000000"** (kind `esewa`, active, position 0, instructions)
  created with the success flash; 0 → 1 configured.
- **Pack (admin UI):** `/admin/packs/create` → **Creator Growth Pack**,
  Rs. 599, prompts 14 + 18 (both Maya's paid published prompts). The
  storefront page renders the two contents, "Rs. 648" contents value and
  "Save Rs. 49 vs buying individually" — arithmetic checks (399+249=648,
  648−599=49).
- **Buyer checkout:** Dorje → `/packs/creator-growth-pack` → _Buy pack_ →
  order **#1** (Rs. 599) → manual method card shows the method name +
  instructions → reference `TXN-PACK-0001` → flash "Payment reference
  received — an admin will verify it shortly", pending page shows
  `Reference: eSewa — 9800000000 · TXN-PACK-0001`.
- **BH-R6-01 (P1, fixed):** the proof upload form was unreachable — the
  checkout Blade only rendered `_payment-proof-form` for `manual + NO
  reference`, a state `checkout.manual.submit` can never leave behind, so
  the advertised "upload your payment proof" step was a dead action
  (`orders.proof.store`; 0 proof forms / 0 file inputs in the served DOM).
  Repro locked at `0337c4f`, fixed at `28ff793`: every pending manual order
  keeps the form (it previews/replaces an existing proof); decided orders
  keep a read-only preview. After the fix, a real 1×1 PNG was attached and
  submitted through the served form → `manual_txn_id` + `manual_proof_path`
  written, file exists on the **private** `proofs` disk (stored as
  `proofs/….jpg` via the image pipeline).
- **Admin desk:** `/admin/orders` shows per order the method · reference,
  `TXN: …`, `proof Oct 4, 06:40` and the thumbnail served from
  `orders.proof.show` (staff-visible, private disk). Approve buttons render
  for the admin (R2 lock).
- **Two more manual orders** as Dorje: prompt **#3** (Rs. 499, order #2) and
  prompt **#12** (Rs. 299, order #3), references + proofs submitted. Then
  all three approved from the desk:
  - **Pack #1 → 2 commercial grants** to the buyer (prompts 14, 18),
    **zero wallet rows** — pack lines are platform revenue by design
    (`WalletService::creatorIdForItem`); observed, not just asserted.
  - **Orders #2/#3 → Maya credited** `sale:2:2` +Rs. 399.20 and `sale:3:3`
    +Rs. 239.20 (commission 2000 bps; 49900→39920, 29900→23920). Available
    balance Rs. 638.40; grants flow only inside the approval transaction.
  - Buyer library: order rows paid, pack expands to both granted prompts
    with _Re-download_, owned-prompt list shows ACTIVE chips + creator
    attribution.
- **Payout life-cycle (Maya via impersonation → admin desk):** request
  Rs. 500 (min Rs. 500 default) → hold row `withdrawal_hold:1` −Rs. 500,
  available 638.40 → 138.40, request form disables below minimum. Finance
  queue #1 → **Reveal destination** decrypts `9800000000` on the desk →
  **Reject** → flash + `withdrawal_release:1` +Rs. 500, available restored
  to Rs. 638.40, queue clear, payout `rejected` (`decided_by` admin).
  Request #2 → **Approve** (`approved`, "awaiting settlement") → **Mark
  settled** → `settled`, final available Rs. 138.40. Ledger browser shows
  the insert-only trail (`sale:2:2`, `sale:3:3`, `withdrawal_hold:1`,
  `withdrawal_release:1`, `withdrawal_hold:2`) and the derived platform
  net **Rs. 758.60 = 1,397.00 gross − 638.40 credits** (gross matches the
  three paid orders; packs included).
- Note (by design, not a bug): submitting a proof with a non-empty `note`
  replaces the visible `payment_reference` with that note — the method
  snapshot survives in the order's method field/txn line, but the desk
  then shows the note instead of "method · reference". Flagged for the
  mediator as copy/UX, no money effect.

## R7 — Fix & lock log

Every fixed bug: the failing repro commit, the fix commit, the test that now
locks it, and where the lock was proven. "Repro" commits were run failing
before the fix landed (R5/R6) or are the first run of the new sweep (R1/R2).

| ID | Repro commit | Fix commit | Locking test | Verified |
|----|--------------|------------|--------------|----------|
| BH-R1-01 | `ef2fc01` (matrix first run: moderator 200) | `f63b55d` | `RoleSurfaceMatrixTest` — moderator row, admin shape | 40-test lock run, 1,678 assertions green (this raid) |
| BH-R2-01..05 | `a5fd871` (scan first run: 403/404 rows) | `102c924` | `DeadLinkScanTest` — per-viewer href/form sweep | same lock run |
| BH-001/BH-002 | `82f7c1b` (5 failing / 3 passing) | `2bc402f` | `CompGrantPickerTest` — 8 tests (payload, 277 options, draft grant, structure) | same lock run |
| BH-R6-01 | `0337c4f` (proof form absent from served page) | `28ff793` | `ManualPaymentMethodsTest` — reference + upload form on the same page | same lock run |

- **Not fixable in-raid (mediator calls):** **BH-P3-01** — drop
  `serve => true` from the `proofs` disk in `config/filesystems.php`
  (removes the unused `storage.proofs` signed-URL route); config change →
  release process. **BH-R2-06** — `/update.php` exempt (docroot script that
  exists on a real install). **Missing `v1.7.6` tag** — history stops at
  `v1.7.2`; `main`'s release commit `5550630` is the artifact of record.
- **Lock run:** the six R1–R6 lock files together → **40 passed / 1,678
  assertions** (~26 s). Full-suite gate is R8 below.

## R8 — Release

- **Version lockset:** `core/config/app.php` → `'1.7.7'`; both builders in
  lockstep — `deploy/build-update-zip.php` and `deploy/build-install-zip.php`
  (the install builder is held to config by
  `InstallArtifactTest::the install builder pins APP_VERSION to the chip`).
- **Docs:** QA-MATRIX got the v1.7.7 BH-block (every row names its locking
  test); `handoff.md` §4 gained the v1.7.7 release entry and §9 the four
  P1 incident rows (comp-grants read door, moderator dead actions, the
  founder's picker, the unreachable proof form).
- **Build:** `npm run build` + `view:clear` + `config:clear` before zipping
  — bundle `app-BBSwSIP8.js` / `app-BfufQNeY.css` (the R5 combobox build).
- **Full suite (final gate):** `php artisan test` → **536 passed / 15,896
  assertions / 2 skipped** (~203 s). The two skips are the
  `InstallArtifactTest` artifact locks — the v1.7.7 **install** zip is not
  built (both skip by design when the artifact is absent; the
  source-level `the install builder keeps its audit rules and its version
  in lockstep` test **ran and passed**, and the v1.4.4 incident lock ran
  green). Baseline was 511 / 14,309 with none skipped: +27 added locks − 2
  now-skipped install tests = 536 passed. Evidence:
  `dist/raid-final-suite.txt`.
- **Arch proof (§6.49 gate):** `php artisan test --list-tests` → **538 tests
  enumerated, 6 Arch** (`BladeFormVerbTest`, `MigrationDropGuardTest`,
  `NoBladeLeakTest`, `UserAvatarGeometryTest` ×3) — all discovered, none
  fiction.
- **Artifact:** `dist/promptsewa-1.7.7-update.zip` — **452 entries** (448 core
  + 4 docroot), **0.84 MB**, hygiene audit **CLEAN (0 forbidden entries)**,
  **SHA-256 `16a1cbe83874f3520d74911ad227795a14aaa1500c08aa46a02e903d98860dc3`**.
  A second build is byte-identical (16a1cbe8… again) — reproducible, same
  standard as v1.7.6. Entry growth vs v1.7.6's 446: +5 raid test files, +1
  `x-searchable-picker` component.
- **Mediator calls still open:** retag `v1.7.6` (no tag exists — release
  hygiene); BH-P3-01 config change (drop `proofs` disk `serve => true`);
  the proof-note-overwrites-reference copy question (§R6.8).

### Commit trail

| Commit | What |
|---|---|
| `38c1bb8` | R0 baseline freeze |
| `ef2fc01`, `f63b55d` | R1 matrix + BH-R1-01 fix |
| `a5fd871`, `102c924` | R2 dead-link scan + BH-R2-01..05 fix |
| `a7d3527` | R3 form round-trip inventory |
| `8b2918b` | R4 edge-state matrix |
| `82f7c1b`, `2bc402f` | R5 BH-001/002 repro + combobox fix |
| `0337c4f`, `28ff793` | R6 BH-R6-01 repro + proof-form fix |
| _(this commit)_ | R8 — version lockset, catalog, QA-MATRIX, handoff |

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
| BH-R6-01 | Buyer checkout — manual proof upload | P1 | `orders.proof.store` was a dead action: the upload form rendered only for "manual + no reference", a state `checkout.manual.submit` never leaves behind, while checkout copy promises "upload your payment proof" | Repro test at `0337c4f`; served page had 0 proof forms / 0 file inputs; `checkout.show` branch order (`payment_reference` branch wins) | Form renders for every pending manual order (preview/replace when a proof exists); decided orders keep the read-only preview | Fixed — see R7 log |
