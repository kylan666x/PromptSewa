# AGENTS.md — docs/ (architecture, deployment, contributing)

> Scope: the markdown runbooks in this folder. Docs are load-bearing:
> they are the contract between the code, the deploy tooling and whoever
> installs this at 2 a.m. on a shared host.

## Rules

1. **Docs ship with the change.** Any commit touching `deploy/`,
   `.env.example`, composer constraints, or the release process must update
   `DEPLOYMENT.md` (and `ARCHITECTURE.md` if a design decision changed) in
   the same PR.
2. **Two audiences, two paths.** `DEPLOYMENT.md` serves non-technical
   installers (cPanel UI only, no SSH) *and* technical ones (SSH/CLI).
   Keep the web-installer path (`install.php` / `update.php`) as the
   primary documented flow; CLI steps are the alternative, not the default.
3. **No infrastructure text in the product UI** — cPanel/runbook/env
   explanations belong here, never in Blade templates (root AGENTS.md §12).
4. Keep `ARCHITECTURE.md` honest about *why* decisions were made; it is
   the reference agents read before changing migrations or drivers.
5. Financial invariants and the cPanel Prime Directive are restated in
   `AGENTS.md` files at the repo root, `core/`, and `deploy/` — update all
   of them together when a ruling changes.

## Contents

| File | Audience |
| --- | --- |
| `ARCHITECTURE.md` | Engineers — data model, stack rationale, security model |
| `DEPLOYMENT.md` | Installers — first deploy + updates (web installer first, CLI second) |
| `CONTRIBUTING.md` | Contributors — local setup, atomic-commit discipline |
