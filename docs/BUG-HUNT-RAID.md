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

_(pending)_

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
