# Runbook — Demo Account Purge (v1.4.5, D5)

**When:** once, after the mediator forwards the founder's check-4 list (the
post-v1.4.4 prod identity outputs). **Where:** prod, via cPanel Terminal or
`php artisan tinker`-free one-shot — this is an admin action, never automatic.

**Financial invariant (absolute):** rows touching money are never deleted.
Orders and license grants are financial history. Any demo identity with
orders or grants is **banned + renamed**, not removed.

## 1. Source of truth — who is "demo"

The seeder email list (mirrored in `PurgeDemoAccounts::DEMO_EMAILS`):

| Email | Seeder | Role |
|---|---|---|
| `admin@promptsewa.test` | DemoContentSeeder | admin |
| `bibek@promptsewa.test` | DemoContentSeeder | creator |
| `maya@promptsewa.test` | DemoContentSeeder | creator |
| `dorje@promptsewa.test` | DemoContentSeeder | creator |
| `bibek@promptvellum.test` / `maya@…` / `dorje@…` | legacy-era DemoContent | creator |
| `justshipitai@gmail.com` | JustShipItAISeeder | creator (flagship) |
| `test@example.com` | DatabaseSeeder | member |

> If the founder's check-4 list shows a **real person re-used or purchased
> through** one of these accounts, treat it as financial-linked regardless
> of row counts — quarantine, don't delete.

## 2. Run it

```bash
# Always dry-run first — prints the plan per id, writes nothing:
php artisan pv:purge-demo --dry-run

# Review the output, then apply:
php artisan pv:purge-demo --force
```

The command is idempotent: re-runs report `absent` for already-purged
identities. Every touched id is printed and appended to
`core/storage/logs/purge-demo.log`.

## 3. Decision rules the command applies

| Condition | Action |
|---|---|
| 0 orders, 0 grants, 0 reports, 0 prompts | **HARD DELETE** (`forceDelete`) |
| ≥1 order OR ≥1 grant (money-adjacent) | **BAN + RENAME** (`banned_at` set, name → `[removed demo account] {id}`, username → `removed-demo-{id}`) |
| ≥1 report or ≥1 prompt (moderation/content linkage) | **BAN + RENAME** |
| absent from DB | reported as `absent`, nothing done |

## 4. Why ban+rename instead of delete for linked accounts

- Orders reference `buyer_id`; grants reference `user_id`. Deleting the user
  orphans financial rows and breaks the audit trail (AGENTS.md money
  invariants).
- Banning (A4) locks the account out of every authenticated request while
  keeping content and history readable.
- Renaming frees the display identity so `justshipitai` etc. can never be
  mistaken for a real staff brand.

## 5. Post-run checklist (mediator)

1. `SELECT email, banned_at FROM users WHERE email LIKE '%promptsewa.test%' OR email LIKE '%promptvellum.test%' OR email = 'justshipitai@gmail.com' OR email = 'test@example.com';` — every row either gone or `banned_at` non-null.
2. Order rows referencing purged ids: unchanged and still viewable in the admin desk.
3. `core/storage/logs/purge-demo.log` archived with the release report.
4. Demo/bulk/flagship seeders can no longer recreate these on prod (D4 hard-refuse) — no re-purge will ever be needed.
