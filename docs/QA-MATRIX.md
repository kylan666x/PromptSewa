# PromptSewa QA Matrix — v1.7.3-hotfix "Prompt Body Ceiling"

## H-block (v1.7.3-hotfix)

| Item | Status | Locking test(s) |
|---|---|---|
| H1 body ceiling 4,000 → 50,000 (`PromptFormRequest::MAX_BODY_CHARS` single source; blade `maxlength` rendered from the SAME constant) | ✅ | `PromptBodyCeilingTest::create serves the body textarea with the fifty thousand char maxlength and counter`, `…::edit serves the same ceiling…` |
| H1 character counter under the prompt-body textarea on create AND edit; works with JS off (server-rendered initial count; maxlength still caps input) | ✅ | same two tests (counter markup served on both routes) |
| H1 30,000-char body (rejected under the old 4,000 rule) now accepted + stored verbatim | ✅ | `PromptBodyCeilingTest::a body between four thousand and fifty thousand chars is accepted now` |
| H1 >50,000 chars → validation error on `body` | ✅ | `PromptBodyCeilingTest::a body over fifty thousand chars is a validation error` |
| H1 storage parity: `prompt_versions.body` TEXT → LONGTEXT (migration `2026_09_30_210000` — MySQL explicit MODIFY path included) so no multibyte truncation | ✅ | `PromptBodyCeilingTest::an edit raising the body past four thousand chars persists in full` + migration run in every RefreshDatabase suite |
| H1 legacy bodies under 4,000 unaffected; parity pipeline (`pv:update`) still green with the new migration in the batch | ✅ | `PromptBodyCeilingTest::a body under the old four thousand ceiling still stores fine`, `DeployParityAcceptanceTest` |

---

# PromptSewa QA Matrix — v1.7.4 "Frame Outside the Circle + Commerce Polish"

## G-block (v1.7.4)

| Item | Status | Locking test(s) |
|---|---|---|
| G1 wrapper is `relative inline-block isolate`, carries NO `overflow-hidden` and NO `rounded-full` | ✅ | `FrameTruthTest::the wrapper isolates but never clips; the clipper owns the circle`, `AvatarFidelityTest::every avatar surface clips the PHOTO, never the frame` |
| G1 inner clipper `size-full overflow-hidden rounded-full` wraps photo/initials | ✅ | same two tests (clipper token asserted on hero, library, home) |
| G1 overlay = `pointer-events-none absolute z-10 object-contain` + size-keyed inset (xs/sm −8%, md/lg −12%) | ✅ | `FrameTruthTest::the overlay protrudes by the size-keyed negative inset` |
| G1 ancestor clip audit: card roots + navbar pill + admin panel do not clip; cover child still clips | ✅ | `FrameTruthTest::no overflow-hidden ancestor clips the protruding frame`, `…the admin users panel does not clip the avatar frames inside it`, `AvatarFidelityTest::the navbar account pill does not clip a framed avatar` |
| G1 typeahead row mirrors the component (hand-rolled Alpine row, same inset rule) | ✅ | `AvatarFidelityTest::the typeahead row renders the frame outside the circle with the same inset rule` |
| G1 frameless avatar = clipper + photo + ZERO overlay nodes; empty-URL lock retained | ✅ | `FrameTruthTest::a frameless avatar renders the clipper and photo with zero overlay nodes`, `…the frame url accessor resolves a usable public URL (the buried-frame repro)` |
| G1 animated frame still byte-identical pass-through, animates on the PROTRUDING overlay, reduced-motion guarded | ✅ | `FrameTruthTest::animated gif uploads pass through byte-identical`, `…an animated frame renders its animation class on the overlay`, `…reduced-motion guard is present in the compiled stylesheet` |
| G2 ink hero band: display name, tagline, real stat chips (count · ★ AVG or "No ratings yet" · future versions) | ✅ | `PackLandingV2Test::the ink hero band carries the pack name, tagline and real stat chips`, `…an unrated pack says No ratings yet and never shows a made-up average`, `…the star figure is the real average over the pack ratings` |
| G2 sticky buy card: saffron mono price, struck individual sum, emerald savings in integer paisa; savings only when real | ✅ | `PackLandingV2Test::savings appear only when the contents really cost more` |
| G2 contents dark card grid (type icon, category mono, price chip / emerald Free) + honest licence checklist (facts only) | ✅ | `PackLandingV2Test::contents render as a dark grid with type icon, category and price`, `…the licence checklist states facts, not superlatives` |
| G2 FAQ answers real policy (versioning, payment rails, refunds) + related packs never self-recommend | ✅ | `PackLandingV2Test::the FAQ answers real policy questions`, `…related packs list real rows and stay off the pack itself` |
| G2 Product/Offer JSON-LD + full x-seo head retained through the redesign | ✅ | `PackLandingV2Test::JSON-LD and the x-seo head survive the redesign`, `PackLandingAndSeoTest` (5) |
| G3 homepage <md: hero type scale steps down, ONE primary CTA + secondary text link | ✅ | `MobileEngagementPassTest::the hero type scale steps down below md`, `…the hero shows one primary CTA with a demoted text link on mobile` |
| G3 trending chips + categories = horizontal scroll-snap rails (keyboard reachable), un-railed from md | ✅ | `MobileEngagementPassTest::the trending chips are a keyboard-reachable snap rail that un-rails on desktop` |
| G3 "Fresh from the library" = snap carousel <md, grid from md up, SAME DOM | ✅ | `MobileEngagementPassTest::Fresh from the library is a carousel below md and a grid from md up` |
| G3 stats band = three mono chips on phones; image gallery 2-col 4:5; right-aligned arrow links | ✅ | `MobileEngagementPassTest::the stats band is three mono chips…`, `…the image gallery is two columns with 4:5 covers on phones`, `…section headers keep their right-aligned arrow links` |
| G3 dashboard <md: tab row horizontal scroll (active pill kept), stat cards 2-col, sparklines full-width | ✅ | `MobileEngagementPassTest::the dashboard tab row scrolls horizontally below md`, `…the dashboard stat cards are two-up on phones and full-width sparklines` |
| G3 CSS scroll-snap only — zero dependencies, no scrollbar hacks, viewport still user-scalable | ✅ | `MobileEngagementPassTest::the engagement pass adds no new dependencies or scrollbar-hiding hacks` |
| G4 chip v1.7.4 (config single source + builder APP_VERSION); suite 458 passed / 13,959 assertions | ✅ | `config('app.version')`; `ReleaseHygieneTest` builds the real zip |

---

# PromptSewa QA Matrix — v1.7.3 "Frame Truth, Heart Truth & Bot Gates"

## W-block (v1.7.3)

| Item | Status | Locking test(s) |
|---|---|---|
| W1 frame overlay renders on EVERY avatar surface (cards both variants, gallery, library, feed actors, versions, purchases, admin users, navbar, dock, typeahead) | ✅ | `FrameTruthTest::the frame overlay renders on every avatar surface`, `…the overlay stays pointer-events-none and aria-hidden everywhere` |
| W1 avatar geometry circle-everywhere (reverses v1.7.0 cards-clean ruling; v1.7.2 hero ring stays for the large hero) | ✅ | `FramesTest::frame overlay renders on every avatar surface including prompt cards`, DESIGN.md v1.7.3 addendum |
| W2 alpha truth: blending OFF + save-alpha ON before every save; transparent corners on frame/badge/qr (PNG + WebP sources) | ✅ | `FrameTruthTest::stored frame corner alpha is fully transparent for a PNG source`, `…survives a WebP source`, `…badge and QR variants keep transparent corners` |
| W3 animated GIF/WebP pass-through byte-identical (≤512×512 header-parsed); static GIF rejected | ✅ | `FrameTruthTest::animated gif uploads pass through byte-identical`, `…oversized animated webp is refused` |
| W3 animation classes gated behind prefers-reduced-motion (compiled stylesheet) | ✅ | `FrameTruthTest::an animated frame renders its animation class on the overlay`, `…reduced-motion guard is present in the compiled stylesheet` |
| W4 criteria & awarding: CriterionEvaluator single source; admin award/revoke idempotent + audited; locked frames refused at equip | ✅ | `FrameTruthTest::criterion evaluator is the single threshold source`, `…manual award persists an audited unlock and is idempotent`, `…a locked frame shows an ink lock chip…`, `…granting an unlock lets the user equip the frame` |
| W4 frames admin carries criterion selects + manual award panel; moderators 403 | ✅ | `FrameTruthTest::admin frames page carries criterion selects…`, `…moderators are 403 on the frames admin and award endpoint` |
| W5 own-profile Dashboard + Edit profile buttons (testids), strangers see neither | ✅ | `FrameTruthTest::own profile shows Dashboard and Edit profile buttons; strangers see neither` |
| W5 Saved heart parity re-verified (filled rose on saved surfaces) | ✅ | `FrameTruthTest::a saved card serves the filled-rose class…`, `…the Saved tab serves filled hearts…` |

## T-block (v1.7.3)

| Item | Status | Locking test(s) |
|---|---|---|
| T1/T2 provider drivers: null pass-through; turnstile; recaptcha_v3 (score ≥ min AND action == form) | ✅ | `BotChallengeTest::service is a pass-through…`, `…turnstile success/failure…`, `…recaptcha v3 requires score and matching action` |
| T2 server-side-only verification; transport error FAILS CLOSED by default; captcha_fail_open flips | ✅ | `BotChallengeTest::transport timeout fails closed by default`, `…fails open when captcha_fail_open is on` |
| T2 per-form enables (register on by default, others opt-in); disabled forms never hit the provider | ✅ | `BotChallengeTest::per-form enablement defaults…`, `…forms without the challenge enabled never hit the provider` |
| T2 secret encrypted at rest; write-only admin input; missing token/secret rejected without a provider call | ✅ | `BotChallengeTest::the captcha secret is encrypted at rest…`, `…missing token or secret is rejected…` |
| T3 x-captcha renders ZERO markup when inactive; turnstile widget when active | ✅ | `BotChallengeTest::the captcha component renders silently…`, `…renders the turnstile widget…` |
| T4 enforcement: register refused on challenge fail, accepted on pass | ✅ | `BotChallengeTest::register is refused when the challenge fails…` |
| T5 disposable gate: bundled ~172 domains; exact + subdomain + case-insensitive; admin-extended list merged | ✅ | `DisposableEmailTest::the bundled disposable-domain list is loaded`, `…blocked domains trigger…`, `…subdomains…`, `…uppercase…`, `…admin-extended…` |
| T5 signup enforcement: disposable refused, subdomain refused, gmail passes | ✅ | `DisposableEmailTest::signup with a disposable address is refused`, `…disposable subdomain is refused but gmail passes` |
| T6 Admin → Security page + nav pill; moderators 403; test-email endpoint reports blocked/ok | ✅ | `BotChallengeTest::the security admin page shows captcha controls…`, `DisposableEmailTest::the admin test-email endpoint…`, `…is admin-only` |

---

# PromptSewa QA Matrix — v1.7.2 "The Type Selector, For Real"

## A-block (v1.7.2)

| Item | Status | Locking test(s) |
|---|---|---|
| A1 create: five SERVER-RENDERED type radio cards (Text · Image · Video · Agentic · Skill), mono label + one-line description, text checked in served HTML, zero JS needed | ✅ | `AuthoringTypeSelectorTest::create serves five real type radios with the text one checked` |
| A1 edit: stored type pre-selected — `checked` in the SERVED html, not JS | ✅ | `AuthoringTypeSelectorTest::edit serves the stored type checked in the HTML, not via JS` |
| A2 guidance box: server-rendered TYPE_CONTEXTS copy + Alpine `x-text` swap on change | ✅ | `AuthoringTypeSelectorTest::create serves five real type radios…` (served guidance text), Alpine swap in `promptForm` |
| A2 category re-scope: server-rendered `<option>` nodes, per-option `x-show` gate; server truth = category belongs to type (or universal) | ✅ | `AuthoringTypeSelectorTest::category options are server-rendered and scoped per type in the markup`, `…::a category scoped to another type is rejected server-side` |
| A2 section-3 chip label: server-rendered + Alpine swap | ✅ | rendered-route contract in `AuthoringTypeSelectorTest` (chip ships with the served type label) |
| A2 cover block: appears for image type; zero-JS `:has()` show/hide in the built CSS + `x-show` enhancement; current-cover preview + remove toggle on edit | ✅ | `AuthoringTypeSelectorTest::cover block is served inside the form and hidden for non-image types without JS`, `…::editing an image prompt serves the cover block, preview and remove toggle` |
| A2 tool wall: chips carry `data-modality` + the gate expression (modality ∈ {type, any}); server truth mirrors | ✅ | `AuthoringTypeSelectorTest::tool chips carry data-modality and the modality gate expression`, `…::posting create with an image-modality tool on type text is a validation error` |
| A3 create POST: type=image + PNG cover → stored type image + cover on the PUBLIC disk (GD re-encoded) | ✅ | `AuthoringTypeSelectorTest::posting create with type image and a png cover stores both` |
| A3 edit POST: text→image WITHOUT cover → validation error on `cover_image` (founder decision: cover required on the switch) | ✅ | `AuthoringTypeSelectorTest::editing text to image without a cover is a validation error` |
| A3 edit POST: text→image WITH cover → passes, switch persists | ✅ | `AuthoringTypeSelectorTest::editing text to image with a cover passes and persists the switch` |
| A3 discipline: NO component-file assertions in the battery — every assertion hits the served route HTML/POST round-trip | ✅ | whole `AuthoringTypeSelectorTest` file (grep: no `view(…)`,`File::get` on blades) |
| A4(a) saved heart = filled rose (cards/detail/Saved tab), outline when unsaved — LANDED in v1.7.1, re-verified green this release | ✅ | `SavedHeartAndTypeSelectorTest` (3 heart tests), `BookmarkTest` |
| A4(b) manual methods kind (bank/esewa/other): admin select, kind icon + "Scan to pay" QR at checkout, Admin→Payments "Manual methods" card link — LANDED in v1.7.1, re-verified green | ✅ | `MethodKindsAndLabelsTest`, `ManualPaymentMethodsTest` |
| A4(c) hero avatar geometry answered IN WRITING: squircle + ring sanctioned by DESIGN.md addendum (v1.7.2); circular stays for small badges | ✅ (docs) | DESIGN.md "Hero avatar geometry (v1.7.2 addendum)" + `AvatarFrameCompositionTest` (composition rules unchanged) |
| A5 version chip v1.7.2 (config single source + builder APP_VERSION); suite 379 passed / 13,698 assertions | ✅ | `config('app.version')`; `ReleaseHygieneTest` builds the real zip |

---

# PromptSewa QA Matrix — v1.7.1 "Saved Heart, Type Picker, Method Kinds" + "Gamification Doors, SEO Crawl, Identity Links"

## P-block (v1.7.1)

| Item | Status | Locking test(s) |
|---|---|---|
| Hearts: saved state renders filled rose on card + detail; unsaved outline | ✅ | `SavedHeartAndTypeSelectorTest::a pre-flipped saved heart carries the filled-rose class and aria-pressed on cards`, `…::an unsaved heart ships the outline state`, `…::the detail page heart ships filled-rose when saved and outline when not`, `BookmarkTest` |
| Type selector: radio cards gate tool chips by modality (any always visible; selected tools never vanish) | ✅ | `SavedHeartAndTypeSelectorTest::create form renders all five type radio cards`, `…::image-modality tool chips carry the gate attribute and x-show expression`, `…::edit form pre-selects the stored type`, `…::category options are type-scoped in the Alpine payload`, `RenderedFormOrderTest` |
| Manual method kind column: default 'other' backfill, validated via Rule::in, persisted | ✅ | `MethodKindsAndLabelsTest::the kind column backfills legacy rows to other`, `…::methods persist an explicit kind`, migration `2026_09_30_190000_add_kind_to_manual_payment_methods` |
| Checkout: per-method kind icon + "Scan to pay" QR block | ✅ | `MethodKindsAndLabelsTest::checkout renders the kind icon and Scan-to-pay QR block per active method`, `…::legacy fallback renders when zero methods exist` |
| Admin manual-methods: kind select + QR helper text + kind chip in list | ✅ | `MethodKindsAndLabelsTest::methods persist an explicit kind`, `ManualPaymentMethodsTest` |
| Admin Overview "paid orders · incl. pre-ledger" + Finance "ledger gross · post-cutover" chip | ✅ | `MethodKindsAndLabelsTest::admin overview and finance desk keep their two distinct revenue truths` |
| Gamification doors: Badges/Frames nav pills for admins; badge/frame index 403 for moderators | ✅ | `MethodKindsAndLabelsTest::the admin nav carries Badges and Frames pills for admins`, `…::moderators are 403 on badges and frames management` |
| Creator achievements section (badges grid + Lv chip + empty state) | ✅ | `AchievementsAndIdentityLinksTest::a badged profile renders the Achievements section between stats and prompts`, `…::an unbadged profile shows the honest empty state` |
| Dashboard Achievements tab: earned wall + progress bars (verified, staff-judged top_rated) | ✅ | `AchievementsAndIdentityLinksTest::the dashboard achievements tab shows the earned wall and real progress rows` |
| Identity links: dock You → public profile; navbar/profile "View profile" rows | ✅ | `AchievementsAndIdentityLinksTest::the dock You slot links to the public profile for authed users`, `…::the desktop dropdown keeps distinct view and edit profile entries` (testids `nav-view-profile`/`nav-edit-profile`), `…::the profile page hosts the You menu with a view-profile row`, `MobileDockTest` |
| SEO: every named GET route has exactly one `<title>` with expected subject | ✅ | `SeoRouteCoverageTest::every named GET route renders exactly one x-seo <title>…` (full crawl, 122 assertions) |
| SEO: route-list sync — new named GET routes fail the suite until mapped or exempted | ✅ | `SeoRouteCoverageTest::the permanent route list stays in sync — every named GET route is either asserted or explicitly exempted` |
| SEO: previously headless pages now titled (prompt create/edit, software update) | ✅ | crawl covers `dashboard.prompts.create` ("Add a new prompt", noindex), `dashboard.prompts.edit` ("Edit: …"), `admin.update` ("Software update") |
| Avatar component: size on wrapper, no saffron-block overlap, preset dropped when caller sizes | ✅ | `AvatarFrameCompositionTest::no-frame hero with a photo renders one full-size img and zero saffron placeholder nodes`, `…::with-frame hero renders photo plus ring`, `…::preset sizes still apply when no caller size class is present` |
| Packs parity: index full head + landing Product/Offer JSON-LD + dock on both | ✅ | `AvatarFrameCompositionTest::packs index carries full x-seo head tags`, `…::pack landing carries full x-seo and Product/Offer JSON-LD`, `…::the mobile dock renders on both pack surfaces` |
| Parity deploy: v1.4.3 DB + pv:update runs 130000+130100+190000, kind column lands | ✅ | `DeployParityAcceptanceTest::v1.4.3-schema database migrates exactly 130000+130100+190000…` |
| Version chip 1.7.1 in config + builder; zip 404 entries, hygiene CLEAN | ✅ | `ReleaseHygieneTest` (builds real zip via `deploy/build-update-zip.php`) |

---

# PromptSewa QA Matrix — v1.7.0 "Community & Gamification"

## G-block (v1.7.0)

| Item | Status | Locking test(s) |
|---|---|---|
| Badge/frame alpha-preservation through the pipeline | ✅ | `FramesTest::frame uploads preserve alpha through the image pipeline` |
| JPEG → badge/frame rejected outright | ✅ | `FramesTest::jpeg sources are rejected for badge and frame variants` |
| Badge award idempotent under double-fire (UNIQUE user+badge) | ✅ | `GamificationTest::badge award is idempotent under double-fire` |
| Manual award audited (awarded_by + reason), duplicate refused | ✅ | `GamificationTest::manual award is audited with awarder and reason` |
| XP thresholds + level chip pure function (Lv 1–10) | ✅ | `GamificationTest::xp thresholds and level chip are pure functions of xp` |
| Publish → +50 XP, badge award, feed emission, no double-emit on re-save | ✅ | `GamificationTest::publishing pays xp and awards first_publish with a feed event` |
| Rating received → +25 XP to creator | ✅ | `GamificationTest::rating received pays the creator 25 xp` |
| Level chip renders in the feed | ✅ | `GamificationTest::level chip renders on the public feed` |
| Feed renders for guests + noindex | ✅ | `FeedTest::the feed renders for guests and is noindex` |
| Banned actors excluded at query level | ✅ | `FeedTest::banned actors are excluded at query level` |
| Feed paginates 20/page | ✅ | `FeedTest::the feed paginates at 20 per page` |
| Dashboard Feed tab: own events + global milestones only | ✅ | `FeedTest::the dashboard feed tab shows own events plus global milestones` |
| Pack creation emits pack_created | ✅ | `FeedTest::pack_created events render in the public feed` |
| Frame overlay on exactly the four ruled surfaces, never prompt cards | ✅ | `FramesTest::frame overlay renders on the four ruled surfaces and never on prompt cards` |
| Frame select/clear in profile edit; delete falls users back safely | ✅ | `FramesTest::users can select and clear a frame…`, `deleting a frame falls users back…` |
| Owner/staff views excluded; guest counted | ✅ | `AnalyticsTest::owner and staff views are excluded from counting` |
| Session-hour view dedupe | ✅ | `AnalyticsTest::views dedupe once per session-hour per prompt` |
| Daily stat upsert: one row per prompt/day, incremented | ✅ | `AnalyticsTest::the daily stat upserts one row per prompt per day` |
| Empty series = dense 30 points, renders clean (sparkline component) | ✅ | `AnalyticsTest::series builders return dense 30-point arrays…`, `sparkline renders an empty series as a clean baseline` |
| Admin overview renders with zero orders | ✅ | `AnalyticsTest::admin overview renders with zero orders` |
| Creator Stats tab zero-data render | ✅ | `AnalyticsTest::creator stats tab renders clean with zero data` |

---

# PromptSewa QA Matrix — v1.6.1 "Empty-Ledger Hardening" (hotfix)

## K-block (v1.6.1 hotfix — prod /earnings 500)

| Item | Status | Locking test(s) |
|---|---|---|
| Guest redirected from /earnings | ✅ | `EarningsEmptyLedgerTest::guest is redirected away from the earnings page` |
| Zero wallet rows → 200 with Rs. 0 + pre-ledger banner | ✅ | `EarningsEmptyLedgerTest::creator with zero wallet rows gets a 200 showing Rs. 0…` |
| Finance desk 200 in the same empty state | ✅ | `EarningsEmptyLedgerTest::finance desk renders 200 in the same empty state` |
| Payout form renders with min-threshold label (settings row present AND absent) | ✅ | `EarningsEmptyLedgerTest::payout request form renders with the min-threshold label…` |
| money_npr undefined at runtime (composer autoload gap) → views still render | ✅ | `EarningsEmptyLedgerTest::the helper fallback renders money…` (function_exists guard on <x-money>) |
| Standalone sweep of both money surfaces with empty fixtures | ✅ | `EarningsEmptyLedgerTest::earnings and finance render standalone with empty fixtures in the sweep` |
| MySQL parity of the empty-ledger scenario | ✅ (skips without MySQL) | `EmptyLedgerMysqlParityTest::earnings and finance survive an empty ledger on MySQL` |
| Settings fallbacks in code (commission_bps absent → 2000) | ✅ | `EmptyLedgerMysqlParityTest` + `WalletServiceTest::commission respects the admin-editable bps setting` |
| Commission property test re-run unchanged | ✅ | `CommissionPropertyTest` (unchanged, 12,005 assertions) |

---

# PromptSewa QA Matrix — v1.6.0 "Money Core"

## Money Core (M-block, v1.6.0)

| Item | Status | Locking test(s) |
|---|---|---|
| Double-webhook single credit | ✅ | `WalletServiceTest::a double webhook credits exactly once` |
| Callback + webhook both delivered → single credit | ✅ | `WalletServiceTest::callback and webhook double delivery credits once` |
| Manual approve grants + credits atomically | ✅ | `WalletServiceTest::manual approval settles the order, grants licenses and credits the creator in one transaction` + `CheckoutFlowTest` regression rows |
| Hold reduces available; reject/cancel restores; settle never double-debits | ✅ | `WalletServiceTest::payout hold reduces available and reject or cancel releases it`, `settling a payout inserts no further ledger rows` |
| Over-available / under-min payout → 422 | ✅ | `WalletServiceTest::over available payout is refused` / `payout below the minimum threshold is refused` |
| Commission integer property (200 random values) | ✅ | `CommissionPropertyTest::credit plus platform share equals gross across 200 random paisa values` |
| No floats in WalletService (arch) | ✅ | `CommissionPropertyTest::walletservice contains no float casts or float functions` |
| Ledger immutability — model boot throws | ✅ | `WalletLedgerImmutabilityTest::updating a wallet transaction throws`, `deleting one throws` |
| Ledger immutability — repo-wide arch ban | ✅ | `WalletLedgerImmutabilityTest::no code path calls update or delete on WalletTransaction repo-wide` |
| Comp grants create zero ledger rows | ✅ | `WalletServiceTest::comp grants create zero ledger rows` |
| Earnings isolation between creators | ✅ | `WalletServiceTest::creator earnings are isolated to their own ledger rows` |
| Destination encrypted at rest | ✅ | `WalletServiceTest::payout destination is encrypted at rest and decrypts for staff` |
| Pre-ledger orders excluded from balances, present in desk | ✅ | `WalletServiceTest::pre ledger orders never appear in balances but appear in the finance desk` |
| money_npr is the single money renderer (arch) | ✅ | `CommissionPropertyTest::views never echo raw paisa values` |
| Only the admin-approval + eSewa paths call settleOrder | ✅ | `WalletServiceTest::settleorder has exactly two controller call sites` |
| eSewa webhook CSRF-exempt + signature-verified | ✅ | `WalletServiceTest::the esewa webhook is csrf exempt and signature verified` |
| Sandbox swaps credentials but never skips verification | ✅ | `WalletServiceTest::sandbox mode swaps the merchant code but signature verification stays on` |
| H1 count semantics (profile published vs admin all) | ✅ | `CreatorProfileCountTest` (3 tests) |
| H2 version authorship resilience | ✅ | `VersionAuthorshipResilienceTest` (3 tests) |

---

# PromptSewa QA Matrix — v1.4.5 "Release Hygiene" (historical)

Every admin + user flow, its status, and the Pest test that locks it.
Suite: `cd core && php artisan test` → **233 passed, 1023 assertions** (2026-09-29; parity test skips without MySQL).

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

## v1.4.5 D-series (release hygiene block)

| Flow | Status | Locking test(s) |
|---|---|---|
| D1 update zip carries no host-local artifacts (bootstrap/cache, storage/**, .env*, *.sqlite) and keeps docroot allow-list + public/build | ✅ | `ReleaseHygieneTest::the built update zip carries no host-local artifacts and keeps docroot + build` |
| D1 incident lock: the v1.4.4 zip IS in violation of the rule | ✅ | `ReleaseHygieneTest::the v1.4.4 zip is confirmed in violation of the D1 rule` |
| D2+D3 poisoned-cache recovery: stale config.php purged, config reloaded in-process, view:cache succeeds on host paths, failure record absent | ✅ | `ReleaseHygieneTest::pv:update purges bootstrap caches and reloads config so view:cache succeeds with poisoned paths` |
| D4 demo/bulk/flagship seeders HARD-refuse in production (zero demo rows possible) | ✅ | `ReleaseHygieneTest::demo, bulk and flagship seeders hard-refuse in production` |
| D4 local seeding unchanged | ✅ | `ReleaseHygieneTest::local env seeding is unchanged` |
| D5 pv:purge-demo dry-run writes nothing; force hard-deletes unused, bans+renames money-linked (financial invariant) | ✅ | `ReleaseHygieneTest::pv:purge-demo dry run writes nothing; force hard-deletes unused and bans linked` + `docs/RUNBOOK-DEMO-PURGE.md` |
| D10 parity acceptance: v1.4.3-schema MySQL + sample rows → pv:update migrates exactly 130000+130100, rows intact, view:cache OK, no failure record | ✅ (MySQL-gated) | `DeployParityAcceptanceTest::v1.4.3-schema database migrates exactly 130000+130100 via pv:update and keeps rows` |

## v1.4.4 C-series (avatar fidelity + manual payment proof block)

| Flow | Status | Locking test(s) |
|---|---|---|
| C1 real avatar renders on every creator surface (cards, byline, library grid, versions, admin users, navbar, typeahead JSON, creator hero) | ✅ | `AvatarFidelityTest::prompt card shows the real photo…`, `prompt detail byline renders the avatar image`, `library creators grid…`, `versions page author row…`, `admin users table…`, `navbar dropdown header…`, `typeahead JSON carries the avatar url…`, `creator profile hero uses the shared component` |
| C1 initials fallback when avatar_path is null; alt always carries the @handle; no empty src ever | ✅ | `AvatarFidelityTest::prompt card falls back to the initials badge…` + shared `assertAvatarSurface` helper |
| C2 admin payment settings save persists (manual_enabled → manual_payment_enabled key mismatch fixed) | ✅ | `ManualPaymentMethodsTest::admin updates manual payment instructions and they persist and render at checkout`, `rendered admin payments form submission persists the manual toggle` |
| C3 manual method CRUD + validation + admin-only | ✅ | `ManualPaymentMethodsTest::admin can create, edit and list manual payment methods`, `method create validates the name and only admins may manage methods` |
| C3 used method deactivates, unused hard-deletes (snapshot-prefix aware) | ✅ | `ManualPaymentMethodsTest::deleting a used method deactivates it; an unused one is hard-deleted` |
| C3 QR uploads stay PNG (alpha preserved, scannable) | ✅ | `ManualPaymentMethodsTest::QR uploads stay PNG on disk even from a JPEG source` |
| C3 checkout renders only active methods in position order; name snapshotted; later edits never rewrite history | ✅ | `ManualPaymentMethodsTest::checkout renders only active methods in position order and snapshots the name` |
| C3 buyer proof submission (TXN ≤100 + screenshot ≤8 MB + note), throttled | ✅ | `ManualPaymentMethodsTest::buyer submits TXN id + proof screenshot on their pending manual order`, `proof form enforces required fields and length limits` |
| C3 owner-only submit + owner/staff-only proof viewing; guests → login | ✅ | `ManualPaymentMethodsTest::only the owner can submit or view a proof` |
| C3 proofs are PRIVATE — private `proofs` disk, controller-served, never on the public disk | ✅ | `ManualPaymentMethodsTest::only the owner can submit or view a proof` (public-disk existence assertion) |
| C3 submission refused once paid; grants nothing (money invariant) | ✅ | `ManualPaymentMethodsTest::proof submission is refused once the order is paid or failed`, `buyer submits…` (zero grants assertion) |
| C3 re-submission replaces proof + deletes the orphan file | ✅ | `ManualPaymentMethodsTest::re-submission replaces the proof file and deletes the orphan` |
| C3 approve-after-proof grants atomically; admin desk shows TXN + proof preview | ✅ | `ManualPaymentMethodsTest::admin approves after proof and grants issue atomically; admin desk shows the proof` |

## v1.4.3 R-series (migration replay repair block)

| Flow | Status | Locking test(s) |
|---|---|---|
| R1 repair replay is a strict no-op on a healthy recorded schema | ✅ | `MigrationReplayRepairTest::fresh DB: repair no-ops and 121000 stands applied`, `recorded post-state: repair is a strict no-op (schema hash unchanged)` |
| R1 repair normalizes a simulated interrupted-121000 half-state | ✅ | `MigrationReplayRepairTest::simulated partial state: repair normalizes so 121000 can complete` |
| R1 repair fails LOUD on duplicate order_item_id (never silently corrupts) | ✅ | `MigrationReplayRepairTest::repair fails loud when duplicate order_item_id blocks the unique index` |
| R1 unique index on order_item_id dropped on BOTH engines (pack fulfillment safe) | ✅ | `CheckoutFlowTest::approving a manual pack order grants every published prompt inside` + migration `2026_09_28_121100_drop_license_grants_order_item_unique` (SchemaInspector-guarded) |
| R2 destructive DDL requires an existence guard (arch test, mutation-checked) | ✅ | `Arch\MigrationDropGuardTest::every destructive migration operation is guarded by an existence check` |
| R3 fatal during pv:update keeps maintenance ON + writes update-failed.json | ✅ | `UpdaterFailureRecordTest::a fatal during pv:update keeps maintenance ON and writes update-failed.json` |
| R3 successful update writes no failure record | ✅ | `UpdaterFailureRecordTest::a successful pv:update does not write update-failed.json` |
| R3 failure screen carries the ops panel (maintenance notice + 4 recovery steps + SQL-state hint) | ✅ | `UpdaterFailureRecordTest::update failure screen shows the ops maintenance panel`, `successful update screen shows no ops panel` |
| R3 update.php ops copy replaces the vendor/ ghost-hunt text | ✅ | manual — `deploy/public_html/update.php` failure card now lists the recovery checklist |

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
| Zip build + SHA-256 report + D1 content assertion | ✅ | `ReleaseHygieneTest::the built update zip carries no host-local artifacts…` + builder self-audit (`hygiene audit: CLEAN`) — see handoff §7 post-check rows |

## v1.5.0 T-series (Official Identity, Taxonomy & Version Truth + T11–T13 mobile)

| Flow | Status | Locking test(s) |
|---|---|---|
| T1 official house account (idempotent command) | ✅ | `OfficialIdentityTest::pv:official-account creates the house account idempotently` |
| T1 official-account profile edit guard (admins only) | ✅ | `OfficialIdentityTest::the official account profile can only be edited by admins` |
| T2 official badge wins over verified seal (every surface) | ✅ | `OfficialIdentityTest::the official badge wins over the verified seal on every surface` |
| T2 typeahead badge field (official/verified/none) | ✅ | `OfficialIdentityTest::typeahead carries the badge field with official winning` |
| T3 impersonation start/stop + session restore | ✅ | `ImpersonationTest::admin can switch into an account and return` |
| T3 member/moderator 403; self-switch no-op row; nesting refused; stale self-heal | ✅ | `ImpersonationTest::members and moderators cannot start…`, `self-switch writes a closed no-op row…`, `nested impersonation is refused`, `a stale impersonation entry self-heals` |
| T4 catalog adoption (dry-run writes nothing; force moves; idempotent) | ✅ | `CatalogAndVersionTruthTest::pv:adopt-catalog dry-run writes nothing and force moves prompts` |
| T5/T6 tool registry modality validation (founder-error repro) | ✅ | `CatalogAndVersionTruthTest::tool registry validates modality against prompt type` |
| T6 TaxonomySeeder idempotent + production-shaped | ✅ | `CatalogAndVersionTruthTest::taxonomy seeder is idempotent and production-shaped` |
| T7 edit appends snapshotted version; published stays published | ✅ | `CatalogAndVersionTruthTest::editing a published prompt appends a snapshotted version and keeps it published` |
| T7 open report → pending on live edit | ✅ | `CatalogAndVersionTruthTest::an open report flips to pending after a live edit` |
| T7 restore = append-only copy ("Restored from vN") | ✅ | `CatalogAndVersionTruthTest::restore appends a copy as the newest version without mutating history` |
| T7 honesty chip; snapshots never leak past the paywall | ✅ | `CatalogAndVersionTruthTest::versions page shows snapshots to the owner and honesty chips for pre-v1.5.0 rows` |
| T7 backfill writes variables/tools, NEVER fabricates bodies | ✅ | `CatalogAndVersionTruthTest::backfill writes variables/tools onto latest rows and never fabricates bodies` |
| T8 pack landing (tagline/hero/JSON-LD) | ✅ | `PackLandingAndSeoTest::pack landing renders tagline, hero copy and Product JSON-LD` |
| T8 admin pack form persists tagline/hero | ✅ | `PackLandingAndSeoTest::admin pack form persists tagline and hero copy` |
| T8 fix: pack contents query (ambiguous created_at 500) | ✅ | Same two tests — any populated pack page renders (was a 500 before v1.5.0) |
| T9 SEO head on public pages + JSON-LD | ✅ | `PackLandingAndSeoTest::public pages carry canonical, OG tags and JSON-LD` |
| T9 auth noindex; versions canonical → prompt detail | ✅ | `PackLandingAndSeoTest::auth pages are noindex and versions canonical points at the prompt` |
| T9 sitemap (public surfaces only) + robots Sitemap line | ✅ | `PackLandingAndSeoTest::sitemap and robots respond with public surfaces only` |
| T10 demo purge panel (dry-run preview + force, admin-only) | ✅ | Panel shells `pv:purge-demo` (D5 runbook logic — no drift); controller 403s non-admins |
| T11 mobile brand mark + desktop logo rows | ✅ | `BrandLogoTest::mobile navbar renders the square brand mark, desktop the full logo (T11)`, `mobile navbar shows the saffron badge fallback…` |
| T12 bottom dock (5 slots, hrefs per audience) | ✅ | `MobileDockTest::the dock renders exactly five slots with correct hrefs for a guest`, `…for an authed member` |
| T12 saffron ONLY on center CTA; aria-current; no burger anywhere | ✅ | `MobileDockTest::saffron fill appears only on the center CTA…`, `the active tab carries aria-current`, `no burger button survives anywhere…` |
| T12 category chips row; profile "You" menu (server-gated Admin) | ✅ | `MobileDockTest::category chips render on the library page`, `the profile page hosts the You menu with server-gated admin row and logout` |
| T13 purchases library (license chips, pack expansion) | ✅ | `PurchasesTest::buyer sees own grants including pack member prompts`, `revoked grants show the revoked chip and block re-download` |
| T13 re-download gated by active grant; cross-user isolation | ✅ | `PurchasesTest::re-download serves the body with an active grant`, `re-download 403s without an active grant`, `other buyers orders are absent from the library` |
| T13 bookmarks (toggle idempotent, invisible-prompt 404) | ✅ | `BookmarkTest::bookmark toggle saves and unsaves idempotently`, `a user can never touch another user bookmark state — 404 on invisible prompts` |
| T13 Saved tab + card heart pre-flip + detail save button | ✅ | `BookmarkTest::the saved tab lists bookmarked prompts`, `the prompt card heart is pre-flipped…`, `the detail page shows the save button with the correct state` |
| Suite | ✅ | 276 passed / 1198 assertions |

## v1.5.1 F-series (SEO wiring & typeahead badges)

| Flow | Status | Locking test(s) |
|---|---|---|
| F1 x-seo output lands inside `<head>` via @stack (the missing-meta bug: tags rendered in the body slot before v1.5.1) | ✅ | `SeoLayoutTest::seo tags never render in the body`, `the homepage serves a dynamic title and og:title inside <head>` |
| F1 single `<title>`, dynamic subject-first (homepage, prompt detail, creator profile) | ✅ | `SeoLayoutTest::the homepage serves a dynamic title…`, `the prompt detail page title leads with the prompt subject`, `the creator profile serves a ProfilePage title and og tags` |
| F1 no hardcoded `<title>`/description in any layout file | ✅ | `SeoLayoutTest::no hardcoded brand-only title tag survives in any layout` |
| F2 typeahead dropdown renders official (blue circle) + verified (saffron seal) badges from the JSON badge field | ✅ | `SeoLayoutTest::the typeahead dropdown template renders official and verified badge markup` (data gate from `OfficialIdentityTest::typeahead carries the badge field…`) |
| F2 badge SVG on identity surfaces | ✅ | `SeoLayoutTest::official badge SVG appears in the served page for the official creator card` |
| Suite | ✅ | 283 passed / 1221 assertions |
