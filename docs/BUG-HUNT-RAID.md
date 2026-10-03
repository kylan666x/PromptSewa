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

_(pending)_

## R3 — Form round-trip inventory

_(pending)_

## R4 — Edge-state matrix

_(pending)_

## R5 — BH-001/BH-002 comp grant form redesign

_(pending)_

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
