# PromptSewa QA Matrix — v1.4.2 "Leak Extermination & Admin Hardening"

Every admin + user flow, its status, and the Pest test that locks it.
Suite: `cd core && php artisan test` → **194 passed, 844 assertions** (2026-09-28).

Run the matrix: `php artisan test` — every row below is covered by at least one
named test; if a test regresses, the row goes red and the release blocks.

## Review & moderation (S1 + S2)

| Flow | Status | Locking test(s) |
|---|---|---|
| Review queue approve (PATCH spoof, pending → published) | ✅ | `AdminReviewTest::approve via the PATCH-spoofed admin form transitions pending to published` |
| Review queue reject | ✅ | `AdminReviewTest::reject stores the rejected status without a reason column requirement` |
| Review actions moderator-only (member → 403, guest → login) | ✅ | `AdminReviewTest::review actions are moderator-only — plain members get 403`, `guests are redirected away from the review queue` |
| 405 regression lock (form POST without spoof) | ✅ | `AdminReviewTest::posting without the method spoof is rejected (405 regression lock)` + `Arch\BladeFormVerbTest::every blade form verb matches its target route` |
| Moderation preview route (staff sees full paid body on pending prompt) | ✅ | `AdminPreviewTest::moderator sees the full paid body of a pending prompt via the preview route` |
| Preview member 403 / guest redirect | ✅ | `AdminPreviewTest::plain members are forbidden from the moderation preview`, `guests are redirected to login from the moderation preview` |
| Preview grants zero licenses (paywall invariant) | ✅ | `AdminPreviewTest::previewing creates zero license grants — paywall invariant` + `AdminReviewTest::approval does not create entitlements — paywall invariant intact` |
| Old `?preview=1` + referer sniff dead | ✅ | `AdminPreviewTest::the old referer-sniffing preview param no longer unlocks paid bodies` |
| Branded 401/403/404/405 error pages | ✅ | `AdminReviewTest` (405 path) + manual visual check |

## Users & roles

| Flow | Status | Locking test(s) |
|---|---|---|
| Role change (admin-only, valid transitions) | ✅ | `CheckoutFlowTest::only admins can change user roles` |
| Self-demotion guard | ✅ | `CheckoutFlowTest::an admin cannot change their own role` |
| Verified badge toggle on/off | ✅ | `AdminFlowsTest::admin can toggle the verified badge on and off` |
| Verify toggle moderator-proof | ✅ | `AdminFlowsTest::moderators cannot issue verified badges` |
| Admin panel access ladder (guest/member/moderator) | ✅ | `CheckoutFlowTest::guests are redirected from the admin panel`, `members cannot open the admin panel`, `moderators can open the admin panel` |

## Payments & orders

| Flow | Status | Locking test(s) |
|---|---|---|
| Manual payment approve → grants issued atomically | ✅ | `CheckoutFlowTest::approving a manual pack order grants every published prompt inside` |
| Manual payment reject → order failed, zero grants | ✅ | `AdminFlowsTest::rejecting a manual payment fails the order and grants nothing` |
| Reject on non-pending order refused | ✅ | `AdminFlowsTest::rejecting an already-paid order is refused` |
| Pending orders cannot be fulfilled | ✅ | `EntitlementServiceTest::pending orders cannot be fulfilled` |
| Fulfillment idempotency | ✅ | `EntitlementServiceTest::fulfillment is idempotent — replay grants nothing extra` |
| eSewa secrets encrypted at rest | ✅ | `CheckoutFlowTest::payment secrets are encrypted at rest` |
| Manual reference gated on setting | ✅ | `CheckoutFlowTest::manual payment reference submission is gated on the setting` |
| Checkout integer math + idempotency keys | ✅ | `CheckoutServiceTest::checkout calculates totals with pure integer math across lines`, `checkout creates a pending order with a unique idempotency key`, `replaying the same idempotency key returns the original order`, `concurrent checkouts with the same key create exactly one order` |
| Buyer isolation (orders / entitlements / checkouts) | ✅ | `CheckoutFlowTest::buyers cannot view other buyers orders / entitlements / open other buyers checkouts` |

## Packs

| Flow | Status | Locking test(s) |
|---|---|---|
| Pack create → update → delete (admin CRUD) | ✅ | `AdminFlowsTest::admin can create, update and delete a pack` |
| Pack validation (name, price) | ✅ | `AdminFlowsTest::pack form requires a name and non-negative price` |
| Pack purchase → pending order with pack line | ✅ | `CheckoutFlowTest::buying a pack creates a pending order with a pack line` |
| Pack approval grants every member prompt | ✅ | `CheckoutFlowTest::approving a manual pack order grants every published prompt inside` |
| Pack fulfillment skips owned prompts | ✅ | `CheckoutFlowTest::pack fulfillment skips prompts the buyer already owns` |

## Brand & appearance

| Flow | Status | Locking test(s) |
|---|---|---|
| Brand save (site name, tagline, emails + validation) | ✅ | `BrandSettingsTest::admins can update site name, tagline and contact emails`, `brand update validates emails and required site name` |
| Brand form renders current values | ✅ | `BrandSettingsTest::admins can view the brand settings form with current values` |
| Transparent PNG logo keeps alpha (never JPEG) | ✅ | `BrandLogoTest::brand form accepts a transparent PNG logo and rejects JPEG logos`, `logo variant preserves alpha — transparent PNG stays PNG, never JPEG` |
| Logo downscales to ≤768px | ✅ | `BrandLogoTest::logo variant downscales to at most 768px wide` |
| Mark variant alpha-preserved | ✅ | `BrandLogoTest::mark variant behaves like logo — alpha preserved on the public disk` |
| Navbar: logo without duplicate wordmark; fallback badge | ✅ | `BrandLogoTest::navbar shows the uploaded logo without a duplicate wordmark at desktop`, `navbar falls back to badge + wordmark when no logo is uploaded` |
| Re-save keeps existing files when no new upload | ✅ | `BrandSettingsTest::submitting without new uploads keeps the existing brand files` |
| Brand access control | ✅ | `BrandSettingsTest::guests cannot open the brand settings page`, `non-admin members are forbidden from brand settings` |

## Tool logos & updater

| Flow | Status | Locking test(s) |
|---|---|---|
| Tool add / deactivate / remove | ✅ | `AdminFlowsTest::admin can add, deactivate and remove an AI tool` |
| Tool logos access control | ✅ | `AdminFlowsTest::tool logos page is admin/staff only` |
| `/admin/update` page loads for staff, 403 for members | ✅ | `AdminFlowsTest::admin update page loads for staff and forbids members` |
| Updater zip pipeline (extract → migrate → caches) | ✅ | exercised by deploy/build-update-zip.php + cPanel update.php smoke (manual) |

## Prompt lifecycle

| Flow | Status | Locking test(s) |
|---|---|---|
| Prompt create (version + product, validation) | ✅ | `PromptCreateEditTest::store creates a pending prompt with an initial version and a product`, `store validates required fields and tool limits` |
| Prompt edit (prefill, ownership policy) | ✅ | `PromptCreateEditTest::owners open a prefilled edit form`, `non-owners cannot open the edit form`, `moderators can edit any prompt through the policy` |
| New version on save, old versions immutable | ✅ | `PromptCreateEditTest::saving an edit appends a new version and never mutates old ones` |
| Pricing/product sync both directions | ✅ | `PromptCreateEditTest::edit keeps pricing and the product in sync in both directions` |
| Free prompts created without product | ✅ | `PromptCreateEditTest::free prompts are created without a product` |

## Visibility & paywall

| Flow | Status | Locking test(s) |
|---|---|---|
| Draft/private/pending/rejected/hidden never public | ✅ | `PromptVisibilityTest::draft prompts never appear…`, `private published prompts never appear…`, `pending and rejected prompts never appear publicly`, `hidden prompts are excluded from scout search results` |
| 404 for private/draft/soft-deleted creators | ✅ | `PromptVisibilityTest::guests receive 404 for private and draft prompt detail pages`, `CreatorProfileTest::soft-deleted creators return 404` |
| Paid teaser vs full body (owner/license holder/staff) | ✅ | `PromptVisibilityTest::guests see the full body of free prompts but only a teaser on paid ones`, `owners and license holders see the full paid body`, `a published paid prompt stays locked for staff on the public page` |
| No grants before payment confirmation | ✅ | `CheckoutFlowTest::no license grants exist before payment confirmation` |
| Revoked grants stay in ledger | ✅ | `EntitlementServiceTest::revoked grants remain in the ledger of record` |

## Profile & identity (S4)

| Flow | Status | Locking test(s) |
|---|---|---|
| Profile edit renders `@method('PUT')` + username field | ✅ | `SearchPreviewTest::profile edit form renders the method spoof and username field` |
| Profile update saves valid username | ✅ | `SearchPreviewTest::profile update saves a valid username` |
| Username cannot be cleared (required) | ✅ | `SearchPreviewTest::username cannot be cleared once set (required since v1.4.1)` |
| Username validation (length, alpha_dash, unique case-insensitive) | ✅ | `SearchPreviewTest::username rejects non alpha-dash characters and long values`, `username must be unique`, `username uniqueness is enforced case-insensitively at signup` |
| Signup requires username ≥4 chars | ✅ | `SignupHardeningTest::signup requires a username of at least 4 characters` |
| Taken / non-alpha-dash handles rejected | ✅ | `SignupHardeningTest::signup rejects taken usernames and non alpha-dash handles` |
| Valid signup, handle lowercased | ✅ | `SignupHardeningTest::valid signup succeeds and stores the handle lowercased` |
| Password floor (≥8, common list, name/email containment) | ✅ | `SignupHardeningTest::common passwords are rejected at signup`, `passwords containing the name or email local-part are rejected` |
| Password meter renders, no plaintext value | ✅ | `SignupHardeningTest::the register form renders the meter scaffolding and never a plaintext password value` |
| Strength heuristic tiers | ✅ | `SignupHardeningTest::password strength heuristic scores tiers correctly` |
| Backfill migration (all users get handles, collisions suffixed) | ✅ | `SignupHardeningTest::every user ends up with a handle after the backfill migration`, `slugs are made unique on collision` |
| `users.username` NOT NULL after migration | ✅ | `SignupHardeningTest::the username column is NOT NULL after migration` |
| Creator URLs by username + name fallback | ✅ | `CreatorProfileTest::creator profile resolves by username`, `creator profile falls back to the display name when no username is set`, `creator route() helper generates the username URL` |

## Reports & ratings

| Flow | Status | Locking test(s) |
|---|---|---|
| Report submission (guest + member, validation) | ✅ | `PromptReportTest::guests can submit a report with a contact email`, `report submission validates reason and minimum message length`, `logged-in members are linked to their report automatically` |
| Report access control (hidden prompts) | ✅ | `PromptReportTest::guests can open the report form for a public prompt`, `hidden prompts return 404 for the report form`, `reporting respects prompt ownership for private listings` |
| Triage resolve/dismiss with attribution | ✅ | `AdminFlowsTest::staff can resolve and dismiss reports with attribution` |
| Ratings: free prompts open to logged-in users, upsert | ✅ | `AdminFlowsTest::any logged-in user can rate a free prompt and re-rating upserts` |
| Ratings: paid prompts require active license | ✅ | `AdminFlowsTest::paid prompts reject raters without an active license` |
| Ratings: license holders can rate, guests bounced | ✅ | `AdminFlowsTest::license holders can rate paid prompts, guests get 401` |

## Search & discovery

| Flow | Status | Locking test(s) |
|---|---|---|
| Typeahead preview (JSON, stop words, throttle, blank state) | ✅ | `SearchPreviewTest::search preview returns prompts and creators as JSON`, `search preview strips natural language stop words`, `search preview is throttled like the full search`, `search preview returns empty arrays for a blank query`, `search preview enforces the max term length` |
| Full search (scout service, trimming, max length) | ✅ | `SearchTest::search finds published public prompts through the scout service`, `search term is trimmed before matching`, `search validation rejects terms over the max length`, `normalizeQuery strips stop words and collapses whitespace` |
| Empty/blank search states | ✅ | `SearchTest::empty search shows the polished empty state`, `blank search lists prompts newest first` |
| Storefront + category pages | ✅ | `StorefrontTest::homepage shows storefront copy and stats`, `homepage shows prompt cards for seeded public prompts`, `CategoryPageTest::category page shows only prompts in that category`, `category pages render via route model binding with slug key`, `unknown category slug returns 404`, `inactive categories are not browsable` |
| Creator profile privacy + bylines | ✅ | `CreatorProfileTest::guests can view a public creator profile with their published prompts`, `creator profile does not leak drafts or private prompts`, `PromptVisibilityTest` card/byline tests |

## v1.4.2 B-series (stability block)

| Flow | Status | Locking test(s) |
|---|---|---|
| B1 `/admin/update` renders standalone (no ambient `$errors`) | ✅ | `StaffViewStandaloneRenderTest::dashboard update view renders standalone without ambient globals`, `…with a log and failure state` |
| B1 update pipeline clears stale compiled views before view:cache | ✅ | `pv:update` runs view:clear → view:cache (UpdateFromRelease), parity-verified 200 under APP_DEBUG=false + config/route/view:cache |
| B1 every admin/dashboard page renders clean (no exception, no leak) | ✅ | `StaffViewStandaloneRenderTest::every admin page renders clean for staff`, `key dashboard pages render clean for their owners` |
| B2 no `@{{` or raw U+2192 anywhere in views | ✅ | `Arch\NoBladeLeakTest::no blade view contains escaped-brace leaks or raw arrows` (mutation-checked) |
| B2 rendered pages contain no Blade echo leaks | ✅ | `assertNoBladeLeak` in Pest.php, applied across staff/admin/detail/profile pages |
| B2/B4 handles render via x-user-handle on every identity surface | ✅ | `CreatorProfileTest::guests can view a public creator profile…` (`@justshipitai` + mono class), `prompt cards link…never braces`, `prompt detail page…never braces` |
| B2 typeahead JSON carries real handles | ✅ | `SearchPreviewTest::search preview returns prompts and creators as JSON with real handles` |
| B3 banner fit-to-box (centered both axes, ≤3 lines, word-boundary ellipsis) | ✅ | `PromptCoverTypographyTest::long title truncates with an ellipsis at a word boundary`, `banner text block never overflows the viewBox bottom`, `short title renders untruncated and horizontally centered` |
| B3 deterministic palette retained | ✅ | `PromptCoverTypographyTest::banner keeps the deterministic palette` |
| B6 public version history (metadata only, changelogs listed oldest→newest) | ✅ | `PromptVersionsPageTest::guests see the versions link and the history page`, `paid prompt history is metadata only — no body fragments leak` |
| B6 Edit owner-only; moderators use preview; admin queue links preview | ✅ | `PromptVersionsPageTest::unrelated member sees versions but zero edit links`, `owner sees the edit link; moderators do not`, `admin review queue links to the preview route…` |

## v1.4.2 A-series (hardening block)

| Flow | Status | Locking test(s) |
|---|---|---|
| A1 admin version chip reflects the running release | ✅ | `config('app.version')` single source; chip renders on admin overview (`StaffViewStandaloneRenderTest::every admin page renders clean` sweeps it) |
| A2 no hardcoded form actions (route-registry bypass) | ✅ | `Arch\BladeFormVerbTest` A2 rule (eSewa gateway variable exempt) |
| A3 admin surface audit documented + access ladder swept | ✅ | `docs/ADMIN-AUDIT.md`; `AdminSurfaceTest::guests bounce…`, `members are forbidden…`, `admins pass on every admin GET route`, `moderators are forbidden on admin-only action routes` |
| A4 ban/unban toggle (admin only, never self) | ✅ | `UserBanTest::admin can ban and unban an account`, `moderators cannot ban accounts`, `an admin cannot ban their own account` |
| A4 banned users locked out on next request (web group) | ✅ | `UserBanTest::banned users are logged out on their next request`, `banning keeps content but blocks access`, `banned middleware runs before the staff gate too` |
| A5 comp grants (admin-only, idempotent, audited) | ✅ | `CompGrantTest::admin issues a comp grant that unlocks the paid prompt`, `comp grants are idempotent while active`, `moderators cannot issue comp grants`, `revoking a comp keeps the ledger row` |
| A5 comp = purchased paywall equivalence | ✅ | `CompGrantTest::comp access matches purchased access on the paywall` |
| A6 rendered HTML carries CSRF + method spoof in order | ✅ | `RenderedFormOrderTest` (7 tests: profile, prompt edit, brand, payments, packs, review/reports, tool logos + ban forms) |

## Release engineering

| Flow | Status | Locking test(s) |
|---|---|---|
| Demo seeder (credible, idempotent, no body leaks) | ✅ | `DemoContentSeederTest::demo seeder produces a credible marketplace library`, `demo seeder is idempotent when prompts already exist`, `seeded prompt bodies survive seeding intact and hidden bodies never leak` |
| Money formatting (integer paisa, no float math) | ✅ | `PromptMoneyFormatTest::price labels render integer NPR without float math`, `zero price renders the free label`, `large prices group thousands without decimals` |
| Blade verb guard (arch test) | ✅ | `Arch\BladeFormVerbTest::every blade form verb matches its target route` |
| Zip build + SHA-256 report | ✅ | manual — see handoff §7 post-check rows |
