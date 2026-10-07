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

## H4-block (v1.7.4 — saved-heart truth: parity + the fetch contract)

| Item | Status | Locking test(s) |
|---|---|---|
| H4 ROOT CAUSE (browser-reproduced, NOT CSRF): the toggle POST returned **200** and the row was written, but the heart rendered `aria-pressed="false"` after a refresh — only the library grid passed `:saved` into `x-prompt-card`; every other surface fell through to the component's `false` default and the optimistic flip was undone on reload | ✅ | `SavedHeartParityTest::the heart pre-flips on the home fresh grid after a refresh` (the repro, now the lock) |
| H4 pre-flip parity on EVERY heart surface: home fresh grid, library grid, library search, creator profile grid, prompt detail | ✅ | `SavedHeartParityTest::every heart surface pre-flips: home, library, search, creator profile, detail` |
| H4 per-prompt parity on one page (the bookmarked card is rose, its neighbour is not) + one viewer never sees another's state | ✅ | `SavedHeartParityTest::on a grid, exactly the bookmarked card shows the filled icon`, `…one viewer never sees another viewer bookmarked state` |
| H4 the set costs ONE query per request, not one per card | ✅ | `SavedHeartParityTest::the parity set costs ONE query per request, not one per card` |
| H4 fetch contract: `credentials: same-origin` + `X-CSRF-TOKEN` read from `<meta name="csrf-token">` (never invented) + `X-Requested-With: XMLHttpRequest` + `Accept: application/json`; the layout serves the meta tag | ✅ | `SavedHeartParityTest::the served layout carries the CSRF meta token the fetch reads`, `…the served heart markup carries the fetch header wiring` (built-asset string tokens, because Pest never runs `fetch()`) |
| H4 any non-OK reverts the optimistic flip AND shows a rose toast — silent lies are banned (419 gets the session-expired copy) | ✅ | `SavedHeartParityTest::…carries the fetch header wiring` (`.ok` / `===419` / `Save failed` in the built JS) + `every heart surface tells the user when a save failed` (grid AND detail — the detail button used to fail in silence) |
| H4 the flip toggles PRESENCE, not utility classes: two mutually exclusive icons via `x-show`, server-cloaked with `x-cloak` so the pre-Alpine paint is truthful. A static SSR pair + an Alpine `:class` pair on ONE element left both pairs in the class attribute and the cascade picked the winner — browser-observed: rose but still an OUTLINE (`fill-none` beat `fill-current`) | ✅ | `SavedHeartParityTest::a saved heart ships the filled icon visible and the outline cloaked`, `…an unsaved heart ships the outline visible and the rose icon cloaked`, and the built JS must NOT contain `heartClass` |
| H4 the detail save button's LABEL is server-rendered too (an `x-text`-only label is empty until Alpine boots) | ✅ | `SavedHeartParityTest::an unsaved heart ships the outline visible and the rose icon cloaked` (`>Save for later<`) |
| H4 viewer state is a container singleton PLUS a forget-per-request middleware — a long-lived worker or the test client would otherwise serve the PREVIOUS viewer's hearts | ✅ | `SavedHeartParityTest` (the cross-request regression it fixed), `ForgetPerRequestState` in the `web` group |
| H4 guests get a login link, never a heart button; the Saved tab lists only the viewer's bookmarks and renders no heart | ✅ | `SavedHeartParityTest::guests get a login link, never a heart button`, `…the Saved tab lists the viewer bookmarks and nobody else` |
| H4 browser gate (release-blocking, 4th "passes tests, fails browser"): verified under `artisan serve` — home/library/creator/detail pre-flip after a hard refresh, tap → **200** + filled rose heart, untap → outline, blanked CSRF token → **419** + visible rose toast + revert | ✅ | manual browser gate + the served-HTML locks above; Pest bypasses CSRF by design and can never see this class |
| Suite | ✅ | 470 passed / 14,018 assertions |

---

# PromptSewa QA Matrix — v1.7.5 "Frame Composite Box" (supersedes v1.7.4 G1 geometry)

## F-block (v1.7.5)

| Item | Status | Locking test(s) |
|---|---|---|
| R0 REPRO: the v1.7.4 hero served a saffron squircle painted by the WRAPPER (`rounded-3xl border-4 border-paper bg-saffron` merged from `creators/show.blade.php`), with the photo 88px at (+4,+4) and the overlay in a non-square 88×109 letterboxed box | ✅ | reproduced in the browser with the founder's own `Abyssal.png`; now permanently impossible — `UserAvatarGeometryTest::the profile hero declares a size prop and nothing else` |
| R1 wrapper = `relative inline-block isolate` + a size class from the prop, with NO rounding / background / border / overflow | ✅ | `FrameTruthTest::the wrapper is a bare composite box: isolate, a size class, nothing else` |
| R1 frame layer = `pointer-events-none absolute inset-0 z-10 size-full object-contain`, rendered only when equipped | ✅ | same test + `FrameTruthTest::the hero route serves the avatar with zero geometry overrides` |
| R1 photo/badge layer = `absolute overflow-hidden rounded-full`, inset to the frame's hole when framed, `inset-0` when frameless; badge shares the box | ✅ | `FrameTruthTest::the photo inset is computed per frame from its own hole`, `…a frameless avatar fills its box with zero frame nodes` |
| R1 the v1.7.4 negative inset and the v1.7.3-hotfix inside-clip are GONE from every view | ✅ | `UserAvatarGeometryTest::the v1.7.4 negative-inset frame geometry is gone from every view` |
| R2 all 13 call sites swept; the hero is `size="xl"` with zero geometry overrides (rendered-HTML, not source) | ✅ | `UserAvatarGeometryTest::no x-user-avatar call site carries geometry attributes`, `…the profile hero declares a size prop and nothing else` |
| R2 the component also strips geometry tokens before merging (ban is defence-in-depth, not the only wall) | ✅ | `FrameTruthTest::the wrapper is a bare composite box…` (wrapper classes asserted clean per surface) |
| R3 `frames.hole_percent` (tinyint unsigned, default 62, existing rows backfilled) | ✅ | `FrameTruthTest::the hole tolerance is one pair of constants and the model enforces it` |
| R3 photo inset computed per frame: 62 → 19%, 38 (Abyssal) → 31%, 70 → 15% | ✅ | `FrameTruthTest::the photo inset is computed per frame from its own hole` |
| R3 bounds 35–70 / default 62 in ONE place; model throws out of range; admin form renders `min`/`max`/`value` from the constants | ✅ | `FrameTruthTest::the hole tolerance is one pair of constants and the model enforces it`, `…the admin frame form renders the hole input from the same constants` |
| R3 an admin can set a per-frame hole; an out-of-range value is refused and the stored value survives | ✅ | `FrameTruthTest::an admin can set a per-frame hole and an out-of-range value is refused` |
| R4 the typeahead mirror uses the SAME composite tokens, with the inset pre-computed server-side (`frame_inset`) | ✅ | `FrameTruthTest::the typeahead mirror uses the same composite tokens as the component` |
| R4 every preset (xs/sm/md/lg/xl) drives the wrapper size from the prop alone | ✅ | `AvatarFrameCompositionTest::the size prop alone drives the wrapper size on every preset` |
| R4 browser gate: hero (112px) + card (24px) report frame centre offset 0.00/0.00 and photo centre offset 0.00/0.00 | ✅ | manual browser gate; Pest cannot measure layout — the arithmetic is asserted in the component tests above |
| R4 card roots + admin panel stay un-clipped (no longer load-bearing for frames; kept as a trap-free default, cover still clips) | ✅ | `FrameTruthTest::card roots and the admin panel stay un-clipped (harmless, but kept)` |
| ARCH REVIVAL: `tests/Arch` registered in `phpunit.xml` — the suite now runs `BladeFormVerbTest`, `MigrationDropGuardTest`, `NoBladeLeakTest`, `UserAvatarGeometryTest` | ✅ | `artisan test --list-tests` shows `Tests\Arch\…`; §6.49 watch-out |
| ARCH REVIVAL fallout fixed: navbar impersonation-bar escaped-brace leak (was rendering literally to staff) | ✅ | `NoBladeLeakTest::no blade view contains escaped-brace leaks or raw arrows` |
| ARCH REVIVAL fallout fixed: two unguarded destructive DDL calls in `up()` (121000 `dropUnique`, 000100 `dropForeign` ×2) — the v1.4.2 incident class | ✅ | `MigrationDropGuardTest::every destructive DDL call in a migration up() is existence-guarded` (+ new `SchemaInspector::hasForeignKey()`) |
| Release | ✅ | chip v1.7.5 (config single source + builder `APP_VERSION`); suite 480 passed / 14,094 assertions; `ReleaseHygieneTest` builds the real zip |

## I-block (v1.7.5 — fresh-install artifact)

| Item | Status | Locking test(s) |
|---|---|---|
| Fresh-install zip is a DIFFERENT artifact from the update zip: ships production `vendor/`, compiled `public/build`, the docroot allow-list and the storage/bootstrap skeleton | ✅ | `InstallArtifactTest::the install artifact exists and is a self-contained installable tree` |
| Production `vendor/` (`--no-dev`): no pest/phpunit/mockery/fakerphp/phpstan on a public host | ✅ | `InstallArtifactTest::the install artifact ships no secrets, no host-local state and no dev surface` |
| Ships NO `.env` (install.php writes it with a fresh APP_KEY), NO `public_html/.update-token` (install.php generates a random one — the repo's token would be a working update panel) | ✅ | same test |
| Ships no host-local state (`bootstrap/cache` contents, `storage/**` runtime, `database.sqlite`) and no dev surface (`tests/`, `phpunit.xml`, `node_modules`) | ✅ | same test |
| The builder FAILS ITS BUILD on an audit violation and asserts the required members exist (v1.4.4 lesson) — it caught the dev SQLite leaking in via the pre-composer tree copy | ✅ | `InstallArtifactTest::the install builder keeps its audit rules and its version in lockstep` |
| Builds are REPRODUCIBLE: staged mtimes are pinned, so the published SHA-256 is verifiable (unstamped builds hashed differently for identical content) | ✅ | `InstallArtifactTest::the install builder keeps its audit rules and its version in lockstep` + two consecutive builds hashed `0043f489…` |
| **Installer bug fixed:** the wizard created the admin without `username` (`NOT NULL` since v1.4.1), so every fresh install died at step 5 — handle now derived + collision-checked, and reported in the log | ✅ | `InstallArtifactTest::install.php creates the admin WITH a username (users.username is NOT NULL since v1.4.1)` |
| The installer's seeding log no longer claims success when D4 refuses the seeders in production | ✅ | `InstallArtifactTest::install.php never claims seeding succeeded when D4 refuses it in production` |
| **End-to-end proof (manual): the artifact was extracted to a clean dir, served with `php -S`, and the real wizard driven over HTTP** — `.env` written, migrations run, admin created `@site-owner`, `storage:link`, caches, assets copied to the docroot, fresh 32-char token written, installer self-locked, home + login **200** with correct titles, `frames.hole_percent` present with default 62 | ✅ | manual gate (a browser/HTTP install cannot be asserted by Pest); invariants above are automated |
| Release | ✅ | `dist/promptsewa-1.7.5-install.zip` — 6,545 entries, 7.92 MB, hygiene CLEAN, **reproducible** (two builds byte-identical), SHA-256 `0043f489c5c3632bb53888ea551d7ee0e5f767ca80f913ea77177b11c6bce70b` |

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

---

# PromptSewa QA Matrix — v1.7.6 "Auth & Mail"

## A-block (v1.7.6)

| Item | Status | Locking test(s) |
|---|---|---|
| A1 forgot-password form renders in the paper world, `noindex, follow` | ✅ | `PasswordResetTest::the request form renders in the paper world with the noindex guard` |
| A1 request sends a mail whose link REALLY resets the password (old fails, new passes) | ✅ | `PasswordResetTest::requesting a reset sends mail whose link really changes the password` |
| A1 the REAL mail pipeline hands a rendered message to the transport; the token is read out of the actual payload | ✅ | `PasswordResetTest::the real mail pipeline hands a rendered reset mail to the transport` (array transport, not a facade fake) |
| A1 unknown address lands on the identical notice — the form is not an account oracle | ✅ | `PasswordResetTest::an unknown address lands on the same notice — the form is not an account oracle` |
| A1 expired (61 min) token refused; garbage token refused; both leave the old password intact | ✅ | `PasswordResetTest::an expired token is refused and the password is untouched`, `a garbage token is refused` |
| A1 the link is single use (replay changes nothing) | ✅ | `PasswordResetTest::requesting a reset sends mail whose link really changes the password` (replay assertion) |
| A1 reset enforces the SAME floor as signup (8+, not-common, confirmed) — one shared `PasswordPolicy::rule()` | ✅ | `PasswordResetTest::the reset enforces the same password floor as signup` |
| A1 6 requests/minute then **429** — the form is not a mail cannon | ✅ | `PasswordResetTest::six reset requests a minute, then 429 — this form is not a mail cannon` |
| A1 guest-only: signed-in users are redirected off all three pages | ✅ | `PasswordResetTest::signed-in users have no business on any of the three pages` |
| A1 a broken mail rail is REPORTED ("we could not send that email"), never swallowed | ✅ | `PasswordResetTest::a broken mail rail is reported, never swallowed` |
| A1 reset mail never carries the new/old password; branded shell (ink bar + saffron button) + plain-text alternative | ✅ | `PasswordResetTest::requesting a reset sends mail whose link really changes the password` (`#f5c518`/`#171715` + text part assertions) |
| A1 all three routes joined the permanent SEO crawl in the same commit | ✅ | `SeoRouteCoverageTest::the permanent route list stays in sync — every named GET route is either asserted or explicitly exempted` |
| A2 eye toggle on every password surface (login, register ×2, reset ×2, admin SMTP) with `aria-pressed` + Show/Hide labels | ✅ | `PasswordVisibilityTest::login, register and the reset form all render the eye toggle` |
| A2 NO password input ever ships a `value` (plaintext-in-HTML ban; the component strips the attribute defensively) | ✅ | same test (regex over every `type="password"` input), `PasswordVisibilityTest::the component refuses to render a value even if a caller passes one` |
| A2 a failed login / rejected registration never echoes the attempted secret back | ✅ | `PasswordVisibilityTest::a failed login never echoes the attempted password back into the page`, `a rejected registration never echoes the attempted password back` |
| A2 the admin SMTP field renders the toggle and shows only the "saved" chip | ✅ | `PasswordVisibilityTest::the admin SMTP password field renders the toggle and never echoes the secret` |
| A3 shipped default is **sendmail** (`env('MAIL_MAILER','sendmail')` + `.env.example`), so auth mail needs zero config | ✅ | `MailConfigTest::the shipped default is sendmail — auth mail must work with zero configuration` |
| A3 with no mail settings saved, `apply()` changes nothing | ✅ | `MailConfigTest::with zero mail settings saved, apply() changes nothing` |
| A3 saved settings land in `config('mail.mailers.smtp.*')` incl. DSN scheme; tls/none leave no stale switch behind | ✅ | `MailConfigTest::saved settings land in the smtp transport, encryption included`, `tls and none map to the right Symfony transport switches — and leave nothing behind` |
| A3 from-address/name fall back to Brand settings | ✅ | `MailConfigTest::the from address falls back to the Brand contact email and site name` |
| A3 SMTP password encrypted at rest (`Crypt`), write-only (empty box keeps it), never served back | ✅ | `MailConfigTest::the SMTP password is encrypted at rest and never served back`, `an empty password box keeps the saved one instead of wiping it` |
| A3 Admin → Email pill in the same commit; moderators/members 403 on page, save and probe | ✅ | `MailConfigTest::the nav pill exists — a settings screen nobody can reach is a missing feature`, `moderators and members are refused the mail settings` |
| A3 "Send test email" goes to the acting admin on the configured rail | ✅ | `MailConfigTest::the probe sends to the acting admin on the configured rail` |
| A3 a failed send shows the exception class + first line and scrubs the password (`•••`) | ✅ | `MailConfigTest::a failed send reports the class and one line — never the password` |
| Suite | ✅ | **511 passed / 14,309 assertions** (chip v1.7.6; Arch suite inside the number — see `--list-tests` proof) |

---

# PromptSewa QA Matrix — v1.7.7 "Bug-Hunt Raid"

## BH-block (v1.7.7)

| Item | Status | Locking test(s) |
|---|---|---|
| R1 every named GET route × 7 fixtures asserted against its gate's outcome (200/302/403/404), zero 500s, one `<title>` per HTML 200, no Blade leaks; a new route cannot skip the sweep silently | ✅ | `RoleSurfaceMatrixTest::every named GET route is in the matrix or exempted with a reason` + the sweep itself (364 responses) |
| R1 BH-R1-01: `/admin/comp-grants` was 200 to moderators while store is admin-only | ✅ | `RoleSurfaceMatrixTest` (moderator row, admin shape; repro first run at `ef2fc01`, fix `f63b55d`) |
| R2 no dead links / dead form actions for five viewer roles: hrefs fetched as the viewer (403/404/405/419/500 fail), form actions verb-spoofed and matched to the role that sees them | ✅ | `DeadLinkScanTest` (2 tests; fix `102c924`) |
| R2 BH-R2-01..05: admin-only pills, purge/adopt panels, approve/reject buttons, save forms, and non-public prompt links rendered for moderators/staff | ✅ | `DeadLinkScanTest` rows (per-role map; no re-appearance when views change) |
| R2 BH-R2-06: `/update.php` matches no Laravel route (docroot script on a real install) | ➖ exempt | `DeadLinkScanTest` exempt map, with reason |
| R3 form round-trip inventory: every served form replayed with browser semantics; completeness scan fails on any un-inventoried Blade form action | ✅ | `FormRoundTripInventoryTest` (8 tests; 44 assertions) |
| R4 edge-state matrix: empty fixtures, overflow/unicode, deleted relations, 30-row pagination, both checkout rails, impersonation chrome | ✅ | `EdgeStateMatrixTest` (5 tests, 442 assertions) |
| R5 BH-001/BH-002: comp-grant picker hid every non-published prompt (root cause `published()` scope); redesigned as a searchable combobox with the full status list | ✅ | `CompGrantPickerTest` (8 tests; repro `82f7c1b` 5 failing/3 passing, fix `2bc402f`) |
| R6 browser gate — 8 flows pass under `artisan serve` (comp grant e2e, impersonate→act→return, ban bounce, reset-mail, frame equip at two hole percents, mobile dock, typeahead seal, manual financial chain → payout settle/reject) | ✅ | browser evidence in `docs/BUG-HUNT-RAID.md` §R6 + the suites below |
| R6 BH-R6-01: manual payment proof form was unreachable (`orders.proof.store` a dead action after reference submit) | ✅ | `ManualPaymentMethodsTest::a pending manual order with a reference still offers the proof upload form (BH-R6-01)` (repro `0337c4f`, fix `28ff793`) |
| R6 financial chain invariants: pack lines credit NO creator; prompt lines credit the creator at 10000−bps; holds/releases are new insert-only ledger rows; approve/settle/reject gated to admin | ✅ | `WalletServiceTest` (both rails, over‑available refused, below-minimum refused, comp grants create zero ledger rows, destination encrypted) + `ManualPaymentMethodsTest::admin approves after proof and grants issue atomically` + `FormRoundTripInventoryTest` payout round-trips |
| Suite | ✅ | **536 passed / 15,896 assertions, 2 skipped** (skips: the two install-artifact tests — the v1.7.7 install zip is not built, they skip by design when absent; Arch suite inside the number — `--list-tests` enumerates **538 tests / 6 Arch**) |

---

# PromptSewa QA Matrix — v1.7.8 "Raid Fallout, Repairs & Notifications"

## F-block (v1.7.8)

| Item | Status | Locking test(s) |
|---|---|---|
| F1 admin-only Finance pill after Payments in the admin nav | ✅ | `OrphanAdminRouteTest::the Finance pill ships for admins after Payments and stays hidden from moderators` |
| F1 BH-R9-01: an orphaned admin door is impossible — every named `admin.*` GET route must appear in the served HTML of an authorized fixture (nav pill, card link, table action) or sit in a reasoned exempt map; stale exemptions fail | ✅ | `OrphanAdminRouteTest::every named admin GET route is linked from a served admin page or exempt with a reason` |
| F2 repro + frame round-trip: equipping/changing a frame through the served form persists and frames hero + cards + navbar at the frame's own hole | ✅ | `AvatarMenuTest::equipping a frame through the served profile form frames hero, cards and navbar` |
| F2 owner-only profile-picture click menu on hero + navbar dropdown — View profile picture (lightbox full-size), Upload / edit picture (`#avatar`), Edit frame (`#avatar-frame`); strangers and guests get no menu | ✅ | `AvatarMenuTest::the owner sees all three profile-picture menu entries on their own profile`, `AvatarMenuTest::strangers get no profile-picture menu — and guests get nothing at all` |
| F2 latent defect: `active_frame_id` not int-cast (string drivers could fail the picker precheck) | ✅ | `AvatarMenuTest::active_frame_id is int-cast so the picker precheck survives string drivers` |
| F2 BH-R9-04: in-test `pv:update` runs wrote testing-flavoured `bootstrap/cache/config.php` (sqlite `:memory:`), poisoning the next dev boot | ✅ | `ReleaseHygieneTest::the suite guardian removes testing bootstrap caches so dev boots stay clean` (+ Pest `afterEach` in `tests/Pest.php`) |
| F3 repro: evaluator chain healthy end-to-end (matching badge + real event → award + XP + feed); the real holes were the missing verified emitter, no `manual` badge option, no descriptions, no backfill path | ✅ | `BadgeCriteriaTest` (5 tests) |
| F3 criterion select offers every `CriterionEvaluator` criterion (manual-only included) with a one-line description each | ✅ | `BadgeCriteriaTest::the served badge form offers every evaluator criterion with a one-line description` |
| F3 criterion set through the served form auto-awards on the real sale event | ✅ | `BadgeCriteriaTest::a criterion set through the served form auto-awards when the sale event fires` |
| F3 `pv:award-scan` backfill (idempotent, unique-constraint deduped) + daily 04:15 schedule + throttled admin-only "Scan now" button on the Badges page | ✅ | `BadgeCriteriaTest::pv:award-scan backfills historical eligibility exactly once`, `BadgeCriteriaTest::the Scan now button is served, admin-only, throttled, and scheduled daily` |
| F3 granting verified awards verified-criterion badges inside the flag-flip transaction; revoking never claws an earned row back | ✅ | `BadgeCriteriaTest::granting verified through the served admin form awards verified badges` |
| F4 orphan-write rule: every POST/PUT/PATCH/DELETE route in the router is reachable from a served Blade form, a documented fetch, or a reasoned exemption — stale exemptions fail | ✅ | `FormRoundTripInventoryTest::every write route is reachable from a served form or documented as a fetch/exemption` |
| F4 (found while building the rule): the form scanner truncated tags at `>` inside quoted attributes, so the pack forms had never been inventoried | ✅ | `FormRoundTripInventoryTest::every route targeted by a Blade form is classified in the form inventory` (+ the fixed extractor in every round-trip test) |
| F5 proofs disk `serve => true` dropped (BH-P3-01) — the framework signed-URL storage routes for proofs are gone; proofs stream ONLY through `orders.proof.show` | ✅ | `FormRoundTripInventoryTest` (framework writes classified), `RoleSurfaceMatrixTest` / `SeoRouteCoverageTest` (framework GETs classified) |
| F5 the buyer's proof note is its own admin desk line and never replaces Method · Reference (new additive `manual_note` column) | ✅ | `ManualPaymentMethodsTest::the proof note is its own admin desk line and never rewrites the reference` |
| F5 (found by the parity gate): the `manual_note` add must not anchor `after()` a column added by a still-pending migration — the v1.4.3 MySQL replay 1054'd | ✅ | `DeployParityAcceptanceTest::v1.4.3-schema database migrates exactly 130000+130100 via pv:update and keeps rows` |
| F6 emitter matrix: each event writes exactly one bell row, for the right user, inside the event transaction; replays write none | ✅ | `NotificationTest` matrix (8 tests: order approve/reject, verified grant/revoke, badge, frame, payout settle/reject, report, comp grant) |
| F6 unread poll JSON shape (count + newest 20, read rows listed but not counted) and throttle 30/min → 429 | ✅ | `NotificationTest::the unread poll returns the count and newest 20 in one JSON shape`, `NotificationTest::the unread poll is throttled at 30 requests per minute` |
| F6 bell in the navbar (desktop + mobile navbar row) with unread count chip; absent for guests; "Mark all read"; owner-only idempotent click-through that marks read before landing on the subject | ✅ | `NotificationTest::the bell is absent for guests and served with its count for signed-in users`, `NotificationTest::per-item mark-read is owner-only, idempotent, and redirects to the subject`, `NotificationTest::mark all read clears the unread count and is idempotent` |
| F6 copy rule: "Notifications", never live-delivery vocabulary (source-locked on the served HTML + both sources); browser gate found click-through links bypassed the mark-read route — fixed pre-ship | ✅ | `NotificationTest::the bell is absent for guests and served with its count for signed-in users` |
| Suite | ✅ | **562 passed / 16,148 assertions, 3 skipped** (skips: two install-artifact tests — no v1.7.8 install zip is built, skip by design; one v1.4.4 incident lock, artifact absent; Arch suite inside the number — `--list-tests` enumerates **565 tests / 6 Arch**) |
| Update artifact | ✅ | `dist/promptsewa-1.7.8-update.zip` — **464 entries** (460 core + 4 docroot), **0.86 MB**, hygiene audit **CLEAN (0 forbidden entries)**, **SHA-256 `6b28fcf308006563a8400d0f0226dbe4acec275a736eaa101724a397c3247074`** (rebuild is byte-identical — reproducible) |

---

# PromptSewa QA Matrix — v1.8.0 "Sikka Economy & Membership"

## S-block (v1.8.0)

| Item | Status | Locking test(s) |
|---|---|---|
| S1 `sikka_transactions` is an append-only ledger: signed BIGINT, UNIQUE idempotency key, `created_at` only; model boot throws on update/delete and the arch suite bans both repo-wide | ✅ | `SikkaLedgerImmutabilityTest::updating a sikka transaction throws`, `SikkaLedgerImmutabilityTest::deleting a sikka transaction throws`, `SikkaLedgerImmutabilityTest::no code path calls update or delete on SikkaTransaction repo-wide` |
| S1 `prompts.price_sikka` is the price of record; the legacy `price_cents` mirror is derived in both directions (S × buy or `intdiv(paisa + buy − 1, buy)`) and free stays free on both sides | ✅ | `SikkaSurfacesTest::the Sikka price mirror is enforced at the DB boundary too` |
| S1 settings respect the documented bounds (kill-switch, buy 50–500, cash-out 10…buy, engagement amounts + cap, cash-out minimum) | ✅ | `SikkaDeskTest::settings respect the documented bounds and persist valid values` |
| S2 balances: spendable is the PLAIN SUM (holds inside), cashoutable filters eligible rows only, both integers under lock | ✅ | `SikkaServiceTest::balances: spendable is the plain SUM (holds inside), cashoutable filters eligible rows only` |
| S2 `spendSikka` is atomic — spend + order paid + license grant + creator credit in ONE transaction; shortfall is 422 with zero trace; the checkout rail is the only call site | ✅ | `SikkaServiceTest::spendSikka pays the order and moves spend + creator credit in one transaction`, `SikkaServiceTest::spendSikka shortfall throws and leaves no trace: no rows, no grants, order still pending`, `SikkaServiceTest::spendSikka has exactly one controller call site — the checkout Sikka rail` |
| S2 unlimited membership bypass: entitlement, zero spend rows, license grant `source = membership_unlimited`; lapsed/non-unlimited members pay normally | ✅ | `SikkaServiceTest::active unlimited membership bypasses the spend: zero rows, grant source membership_unlimited`, `SikkaServiceTest::a lapsed or non-unlimited membership does not bypass the spend` |
| S2 sikka-pack top-up credits `topup` + `topup_bonus` exactly once on double approval (manual and eSewa share the OrderObserver paid transition) | ✅ | `SikkaServiceTest::approving a sikka-pack order credits topup + bonus exactly once`, `SikkaSurfacesTest::the Sikka top-up storefront buys a pack and approval credits it exactly once` |
| S2 formatter: grouped integers, no decimals, no fiat glyph, PSR-4 class — `autoload.files` still hash-locked to `[app/Support/money.php]` | ✅ | `SikkaFormatTest::sikka amounts render as grouped integers with no decimals`, `SikkaFormatTest::sikka rendering never contains a decimal point or fiat glyph`, `ReleaseHygieneTest` (autoload.files hash lock) |
| S3 engagement rewards: publish, rating-received and daily-visit each credit once, spend-only (`cashout_eligible = false`); a re-save never double-fires | ✅ | `EngagementRewardTest::publishing credits one engagement reward, spend-only, and a re-save never double-fires`, `EngagementRewardTest::a rating received credits the prompt creator`, `EngagementRewardTest::a daily visit credits once per day through the web middleware` |
| S3 the same-day cap bounds earning and an emitter amount of 0 turns it off; the kill-switch keeps the ledger empty | ✅ | `EngagementRewardTest::the same-day cap stops further rewards, and the emitter amount can be turned off`, `EngagementRewardTest::the kill-switch keeps the ledger empty, and unknown emitters throw` |
| S3 `pv:stipend-scan` grants every elapsed period once, replays nothing, flips lapsed memberships to expired and stops; stipend-less plans write no rows | ✅ | `StipendScanTest::the scan grants every elapsed period once and a replay grants nothing`, `StipendScanTest::a lapsed membership gets its final period, flips to expired, and stops`, `StipendScanTest::stipend-less plans write no rows and the scan never breaks` |
| S4 cash-out request: hold row + payout row in one transaction, both balances drop; below-minimum and over-cashoutable are 422 with zero trace | ✅ | `SikkaCashoutTest::requesting a withdrawal parks a payout_hold row and lowers both balances`, `SikkaCashoutTest::below-minimum and over-cashoutable requests are 422 with zero trace` |
| S4 machine: approve → settle stores `settled_npr_paisa` once at the rate; reject/cancel releases the hold; legacy NPR wallet payouts untouched | ✅ | `SikkaCashoutTest::approve then settle stores the NPR amount once at the rate; reject and cancel release`, `SikkaCashoutTest::legacy NPR wallet payouts stay on the wallet rail` |
| S4 `settled_npr_paisa` is an exact integer multiple across 200 random amounts × 3 rates (no floats anywhere) | ✅ | `SikkaCashoutTest::settled_npr_paisa is an exact integer multiple across 200 random amounts x 3 rates` |
| S5 membership storefront on the NPR rail: plans list + buy form creates a pending order | ✅ | `MembershipActivationTest::the storefront lists plans and the buy form creates a pending NPR order` |
| S5 approval activates EXACTLY once — membership row, first stipend and the perks (badge/frame/verified) each with one bell row only when created; eSewa rides the same path | ✅ | `MembershipActivationTest::approval activates exactly once: membership row, first stipend and the perks`, `MembershipActivationTest::eSewa settlement rides the same one activation path` |
| S6 authoring form carries the integer Sikka price 0–100000 with a live NPR preview; out-of-range refused | ✅ | `SikkaSurfacesTest::the authoring forms carry the Sikka price input with an NPR preview` |
| S6 cards + detail lead with `<x-sikka>` and keep NPR in parentheses; Free chip unchanged | ✅ | `SikkaSurfacesTest::cards and the detail page lead with Sikka and keep NPR in parentheses` |
| S6 checkout: top-up CTA when short (NPR rails stay visible), Sikka rail payable when funded | ✅ | `SikkaSurfacesTest::the checkout selector offers the top-up CTA when the balance is short` |
| S6 kill-switch sweep: disabled means ZERO Sikka markup on home, library, detail, checkout and earnings; `/sikka` 404s | ✅ | `SikkaSurfacesTest::the kill-switch sweep: disabled means zero Sikka markup on every buyer surface` |
| S6 Admin → Sikka desk: nav pill in the same commit, admins only (moderators 403 everywhere), packs/plans CRUD deactivates instead of deleting once referenced, ledger browser filters by user/type/eligibility | ✅ | `SikkaDeskTest::the desk renders for admins with its pill and 403s moderators everywhere`, `SikkaDeskTest::packs CRUD round-trips and a sold pack deactivates instead of deleting`, `SikkaDeskTest::plans CRUD round-trips the perks picker and referenced plans deactivate`, `SikkaDeskTest::the ledger browser filters by user, type and eligibility` |
| S6 admin grants are spend-only by default; the eligibility flip requires a reason and is audited in the row meta AND the admin log | ✅ | `SikkaDeskTest::admin grants are spend-only by default; the flip is audited in meta and the admin log` |
| S6 every new route is classified (SEO crawl + form inventory) and every write door is reachable from a served form | ✅ | `SeoRouteCoverageTest`, `FormRoundTripInventoryTest` (both extended in the S6 commit) |
| S8 the 11 Sikka migrations join the parity pending list — the v1.4.3 base schema builds without them and `pv:update` migrates the whole batch | ✅ | `DeployParityAcceptanceTest::v1.4.3-schema database migrates exactly 130000+130100 via pv:update and keeps rows` (extended in S1) |
| S9 `sikka` upload variant preserves alpha for PNG + WebP, downscales to ≤512px, refuses JPEG (service AND Brand form) | ✅ | `SikkaIconTest::the sikka variant preserves alpha for PNG and WebP and stays at or below 512px`, `SikkaIconTest::the sikka variant refuses JPEG sources`, `SikkaIconTest::the Brand form ingests both Sikka marks and refuses JPEG with a field error` |
| S9 Admin → Brand shows live preview swatches for both marks on paper AND ink grounds; a save without new files keeps the uploaded art | ✅ | `SikkaIconTest::the served Brand form previews both marks on paper and ink grounds` |
| S9 `<x-sikka>`: color mark when set, mono variant for mail/ink (falling back to color when no mono), honest bordered “Sikka” chip when unset — zero broken images | ✅ | `SikkaIconTest::x-sikka renders the color mark when set, the mono variant for mail, and the chip fallback when unset` |
| S9 integer amounts with no decimal point on card, detail, checkout, earnings and finance surfaces | ✅ | `SikkaIconTest::card, detail, checkout, earnings and finance render integer Sikka amounts through the component` |
| S9 the reset mail header uses the mono mark; with economy off or no mark uploaded it renders nothing (never a broken image) | ✅ | `SikkaIconTest::the reset mail header carries the mono mark, and none when unset or while the economy is off` |
| S9 arch ban: no blade view echoes a raw Sikka amount outside `<x-sikka>` (mirror of the `money_npr` ban); `SikkaFormat::render` lives only in the component | ✅ | `SikkaIconTest::blade views never echo a raw Sikka amount outside the x-sikka component` |
| S9 release note: the founder's art ships by UPLOAD, not in the zip — the artifact carries only the ingest plumbing and the fallback | ✅ | release report `dist/v1.8.0-release-report.md` |
| Suite | ✅ | **613 passed / 17,849 assertions, 3 skipped** (skips: two install-artifact tests — no v1.8.0 install zip is built, skip by design; one v1.4.4 incident lock, artifact absent) — `--list-tests` enumerates **616 tests / 6 Arch** (`BladeFormVerbTest` 1, `MigrationDropGuardTest` 1, `NoBladeLeakTest` 1, `UserAvatarGeometryTest` 3) |
| Update artifact | ✅ | `dist/promptsewa-1.8.0-update.zip` — **505 entries** (501 core + 4 docroot), **0.94 MB**, hygiene audit **CLEAN (0 forbidden entries)**, **SHA-256 `0dd3e0b619113e37207fc3a502bb7240a519031d6040ad8ccaf4dba37c5f19c4`** (rebuild is byte-identical — reproducible) |

## S-block (v1.9.0) — Sikka-only economy, social surface & the S1b extension

> **Supersedes parts of the v1.8.0 S-block above:** the kill-switch is
> retired (no longer "ships OFF"), cards/detail/checkout no longer keep an
> NPR parenthetical, and membership plans are bought with credits instead
> of the NPR rail. The old rows stay as release history.

| Area | Status | Locked by |
|---|---|---|
| S1 the navbar carries the Sikka wallet chip for signed-in users on both viewports (mark + integer, linked to `/dashboard/earnings`); guests get none | ✅ | `SikkaEverywhereTest::the navbar shows the Sikka wallet chip for signed-in users on both viewports` |
| S1 prompt cards, the detail page and checkout price in credits only — zero NPR; the rail shows the top-up CTA when short and the pay door when funded | ✅ | `SikkaEverywhereTest::cards, the detail page and checkout price in Sikka only — no NPR anywhere` |
| S1 the earnings tab is Sikka-only: spendable, cash-out eligible, credits ledger, "Withdraw earnings" — no NPR payout chrome | ✅ | `SikkaEverywhereTest::the earnings tab renders Sikka only — balances, ledger and withdrawals, no NPR`, `EarningsEmptyLedgerTest`, `EmptyLedgerMysqlParityTest` |
| S1 the kill-switch is retired: defaults ON, the desk hides the toggle, migration flips existing rows, the storefront exists with nobody flipping anything | ✅ | `SikkaEverywhereTest::the kill-switch is retired: the economy ships ON and the desk hides the toggle`, `DeployParityAcceptanceTest` (migration in the pending list) |
| S1 the display balance (navbar) equals the locked spendable balance — one ledger, one answer | ✅ | `SikkaEverywhereTest::the display balance equals the locked spendable balance` |
| S1 legacy NPR payout routes have no served form any more and are exempt-with-reason in the inventory, still direct-POST tested | ✅ | `DeadLinkScanTest`, `FormRoundTripInventoryTest`, `CheckoutFlowTest` |
| S2 the avatar menu is exactly two actions; the picture itself opens the lightbox on the hero and in the navbar | ✅ | `AvatarMenuTest` (rewritten to two actions + the direct-click door) |
| S3 `/memberships` is public and reachable from the account menu, the owner-profile action and the Sikka desk "View storefront" link | ✅ | `MembershipAccessTest` (3 tests) |
| S1b `packs.price_sikka` and `membership_plans.price_sikka` are the prices of record; the paisa mirror derives on save (rounding UP for legacy paisa writes so a paid pack never becomes free) | ✅ | `PackLandingV2Test`, `PackLandingAndSeoTest`, `SikkaDeskTest::plans CRUD round-trips the perks picker and referenced plans deactivate` |
| S1b pack grid, pack landing and the admin pack form price in credits; the desk plan form prices in credits | ✅ | `PackLandingV2Test`, `PackLandingAndSeoTest::admin pack form persists tagline and hero copy`, `SikkaDeskTest` |
| S1b the typeahead payload carries a `packs` key (name, prompt count, Sikka price, url) and the served client renders the section | ✅ | `PackSearchTest::the typeahead payload carries packs priced in Sikka`, `PackSearchTest::the served typeahead renders the packs section priced in Sikka only` |
| S1b the mobile dock is exactly Home · Packs · ＋ · Library · Profile with correct hrefs and text-only active state | ✅ | `MobileDockTest` |
| S1b a pack order prices in credits, offers the rail when funded, pays atomically and grants every prompt inside; a short balance gets the top-up CTA and NO money rails | ✅ | `CheckoutFlowTest::a pack order pays with Sikka credits and grants the contents`, `CheckoutFlowTest::a short balance sends the pack buyer to the top-up page, and only a top-up order shows the money rails` |
| S1b a membership plan is paid with credits and activates exactly once (membership row + first stipend + perks); a short balance gets the top-up CTA | ✅ | `SikkaEverywhereTest::packs and memberships price in Sikka only, and a short balance points at the top-up door`, `MembershipActivationTest` |
| S1b the money rails (eSewa / manual transfer) render ONLY on an order carrying a top-up pack — `/sikka` and its checkout are the only NPR surfaces left | ✅ | `CheckoutFlowTest` (both halves), `ManualPaymentMethodsTest`, `MethodKindsAndLabelsTest` |
| S1b the shortfall hint no longer sends buyers to rails that are not on the page, and a credit-rail order is never "Checkout is being configured" | ✅ | `CheckoutFlowTest::a short balance sends the pack buyer to the top-up page…` (asserts both absences) |
| S1b structured data follows the surface: pack and prompt JSON-LD advertise `priceCurrency: SIKKA`; the pack script tag is valid JSON (no Blade `@context` directive leak) | ✅ | `PackLandingV2Test::JSON-LD and the x-seo head survive the redesign` (decodes the tag), `AvatarFrameCompositionTest` |
| S1b the two new migrations join the parity pending list and ride the update batch | ✅ | `DeployParityAcceptanceTest` |
| S1b both zip builders refuse ANY `.sqlite` path (host-local DBs never ship, and the audit can never fail because of one) | ✅ | `ReleaseHygieneTest::the built update zip carries no host-local artifacts and keeps docroot + build` |
| S4 the S4 token set compiles into the shipped stylesheet: `paper #faf8f3`, `--font-sans` Inter, `--font-display` Space Grotesk with an `h1`–`h4` base rule, and the card lift shadows | ✅ | `UiOverhaulTest::the S4 token set compiles into the stylesheet the app serves` (reads `public/build` via the manifest), `…every app surface loads Inter and Space Grotesk from the font CDN` |
| S4 the library ships feed cards in reading order — creator identity header, artwork, Sikka price chip bottom-right — with a `rounded-2xl` unclipped root and no NPR | ✅ | `UiOverhaulTest::the library ships feed cards in reading order: identity, artwork, price` |
| S4 the prompt detail is a two-column shell on desktop (`lg:grid-cols-[minmax(0,1fr)_360px]`, `min-w-0` left column, sticky right rail) and stacks below `lg` with the buy form in the rail | ✅ | `UiOverhaulTest::the prompt detail is a two-column shell on desktop and stacks below lg` |
| S4 the dashboard ships the segmented settings-style tab rail (G3 mobile scroll contract intact, no per-pill border, ink-filled active tab) and card stats two-up on phones | ✅ | `UiOverhaulTest::the dashboard ships the settings-style tab rail and card stats` |
| S4 the storefront opens its section rhythm (hero `pb-20 pt-16 … md:pt-24`, bands `pt-20`) without losing the G3 hero/trending guards | ✅ | `UiOverhaulTest::the homepage opens its section rhythm without losing the mobile guards`, `MobileEngagementPassTest` |
| S4 the overhaul changed no invariant: composite-box avatar geometry, five-slot dock with one saffron fill, Sikka-only buyer surfaces, no off-token colour helper | ✅ | `UiOverhaulTest::the overhaul leaves the composite box, the five-slot dock and Sikka-only pricing alone`, `UserAvatarGeometryTest`, `MobileDockTest`, `FrameTruthTest` |
| S4 the feed-card timestamp clears WCAG 2.1 AA at 11px (`text-ink/60` ≈4.7:1, not `ink/40` ≈2.6:1) | ✅ | surfaced in the final pass; report §1 S4 "Contrast" entry (pre-existing sub-AA greys elsewhere are flagged, not swept) |
| Suite | ✅ | **636 passed / 18,079 assertions / 3 skipped** (skips: two install-artifact tests — no install zip built, by design; one v1.4.4 incident lock, artifact absent) — `--list-tests` enumerates the **6 Arch tests** (`BladeFormVerbTest` 1, `MigrationDropGuardTest` 1, `NoBladeLeakTest` 1, `UserAvatarGeometryTest` 3); evidence `dist/v1.9.0-suite-S4.txt`, `dist/v1.9.0-list-tests.txt` |
| Update artifact | ✅ | `dist/promptsewa-1.9.0-update.zip` — **513 entries** (509 core + 4 docroot), **0.96 MB**, hygiene audit **CLEAN (0 forbidden entries)**, **SHA-256 `b40e36572c7dca34484e808b6f308f01f586a77033700ef12821fe00999b3fb2`** (identical across two consecutive builds — reproducible), 0 `.sqlite` / 0 `.env` entries, all 3 v1.9.0 migrations present; evidence `dist/v1.9.0-zip.txt` (supersedes the pre-S4 `c61488a2…` / 512-entry build) |

## P1-block (v1.9.1) — True Newsfeed Layout

> **Supersedes the v1.9.0 S4 feed rows above:** the library is no longer
> `sm:grid-cols-2 xl:grid-cols-3` and the homepage "Fresh from the library"
> block is no longer a carousel below md. Both are single-column at every
> breakpoint. The S4 tokens, card reading order and all invariants stand.

| Area | Status | Locked by |
|---|---|---|
| P1 the library (`/prompts`) is a TRUE single column at every breakpoint — one full-width post per row on a centered `max-w-3xl` column with `gap-6` air; no `sm:grid-cols-2` / `xl:grid-cols-3` survives on the surface | ✅ | `UiOverhaulTest::v1.9.1: the library and the homepage feed are single-column newsfeeds`, `UiOverhaulTest::the library ships feed cards in reading order: identity, artwork, price` |
| P1 the homepage "Fresh from the library" block uses the same container and the carousel/grid dual mode is retired — no snap rail, no `overflow-x-auto`, no `w-[82%]` slides inside the section (vertical scroll only) | ✅ | `MobileEngagementPassTest::Fresh from the library is a single-column newsfeed at every breakpoint`, `UiOverhaulTest::v1.9.1: the library and the homepage feed are single-column newsfeeds` |
| P1 feed artwork is prominent and keeps its aspect ratio — the feed surfaces pass the new opt-in `large` prop to `x-prompt-cover` for the wide **16:9** crop (the plain card keeps 16:10) | ✅ | `UiOverhaulTest::v1.9.1: … newsfeeds` (`aspect-[16/9]` on both served surfaces) |
| P1 the feed closes with an honest pagination door ("Load more prompts" → `/prompts`) rather than a fake in-place infinite scroll | ✅ | `UiOverhaulTest::v1.9.1: … newsfeeds`, `MobileEngagementPassTest::Fresh from the library is a single-column newsfeed…` |
| P1 no invariant moved: composite-box avatar geometry, the five-slot dock with one saffron centre fill, Sikka-only buyer surfaces, and the card's identity → artwork → copy → price reading order | ✅ | `UiOverhaulTest::the overhaul leaves the composite box, the five-slot dock and Sikka-only pricing alone`, `UserAvatarGeometryTest`, `MobileDockTest` |
| Suite | ✅ | **637 passed / 18,101 assertions / 3 skipped** (same 3 by-design skips as v1.9.0) — `--list-tests tests/Arch` enumerates the **6 Arch tests**; evidence `dist/v1.9.1-suite.txt`, `dist/v1.9.1-list-tests.txt` |
| Update artifact | ✅ | `dist/promptsewa-1.9.1-update.zip` — **513 entries** (509 core + 4 docroot), **0.97 MB**, hygiene audit **CLEAN (0 forbidden entries)**, **SHA-256 `dfdbbc324dab09d547891e24184e22c30deeaa96ed01ea2515bd53a7304b8155`**, 0 `.sqlite` / 0 `.env` entries; evidence `dist/v1.9.1-zip.txt` |
| Browser gate | ✅ | DOM assertions under `artisan serve` + the seeded preview DB at 360px and 1280px on both surfaces — evidence `dist/v1.9.1-browser-gate.txt` (screenshots unavailable: the preview panel reported "no frames / not composited") |

## F-block (v1.9.2) — Social Replica & Sales Fix

> **Supersedes parts of the v1.9.0/v1.9.1 blocks above:** the feed card is
> now the F3 social post (`rounded-xl … bg-paper`, action bar, 16:9 cover
> replaced by the Instagram crop on image listings), the avatar menu has a
> third entry again, and every sales number reads paid order lines instead of
> the dead `prompts.sales_count` column. The P1 single-column contract stands.

| Area | Status | Locked by |
|---|---|---|
| F1 the dashboard **Sales** stat is the real count: a completed Sikka credit purchase moves it from 0 to 1 (it read a column nothing writes, so it was 0 forever) | ✅ | `SalesCountTruthTest::a Sikka credit purchase moves the sales count from 0 to 1 on every surface` |
| F1 the public profile's sale stat, the achievements progress rows and the top-prompts table all read the same truth (no two surfaces disagree) | ✅ | `SalesCountTruthTest` (profile regex + `1/10` row + `1 sales` cell), `AchievementsAndIdentityLinksTest` |
| F1 the count is rail-agnostic — an approved manual NPR order counts exactly like a credit purchase | ✅ | `SalesCountTruthTest::the sales count is rail-agnostic: an approved manual NPR order counts too` |
| F1 comp grants are not sales (no order line, no count) | ✅ | `SalesCountTruthTest::comp grants are not sales`, `CompGrantTest` |
| F1 the legacy `sales_count` column is dead data — no surface trusts it (a phantom 99 reads 0; a real paid line counts while the column still reads 0) | ✅ | `SalesCountTruthTest::the legacy sales_count column is dead data and no surface reads it` |
| F1 the badge/milestone evaluator reads the same truth, so first_sale / sales_10 / sales_50 can finally auto-award | ✅ | `BadgeCriteriaTest`, `FrameTruthTest::criterion evaluator is the single threshold source` |
| F2 the avatar menu carries a **My Profile** entry → `/creators/{username}`, alongside upload/edit and edit frame | ✅ | `ProfileMenuTest` (hero + navbar), `AvatarMenuTest` |
| F2 the lightbox is a responsive modal: full-viewport `bg-ink/80 backdrop-blur-sm` scrim at `z-50`, `max-w-screen-md` content box, artwork capped at `max-h-[80vh]` with `object-contain`, and a visible Close pinned top-right | ✅ | `AvatarLightboxTest` (served markup on both the hero and navbar instances) |
| F3 a text listing renders as a Facebook-style post: identity header, copy as the body (`whitespace-pre-wrap leading-relaxed`), price chip, action bar — and no artwork | ✅ | `SocialFeedReplicaTest::a text prompt renders as a Facebook-style post with the full action bar` |
| F3 an image listing renders as an Instagram-style post: edge-to-edge `aspect-square md:aspect-[4/5]` artwork with an ink/5 ground, caption, same action bar | ✅ | `SocialFeedReplicaTest::an image prompt renders as an Instagram-style post with edge-to-edge art` |
| F3 the action bar ships on every card of every feed surface (Like · Comment | Share · Save), and Save keeps the real bookmark wiring | ✅ | `SocialFeedReplicaTest::the action bar is present on every card on every feed surface`, `…Save keeps the real bookmark wiring…`, `SavedHeartParityTest` |
| F3 Share is a real control (Web Share API → clipboard → honest toast), never a silent button | ✅ | `SocialFeedReplicaTest::Share is a real control wired to the Web Share API with a clipboard fallback` (built-asset contract) |
| F3 Like/Comment stay honest stubs — labelled, explained on hover, never dead buttons and never `href="#"` | ✅ | `SocialFeedReplicaTest::Like and Comment are honest stubs — no dead buttons, no href="#"` |
| F3 long copy is split at 500 characters behind **See more** (and short copy is not decorated with a toggle) | ✅ | `SocialFeedReplicaTest::long copy is split at 500 characters behind a See more control` |
| F3 the feed section still carries no grid/carousel markers: one column at every breakpoint, vertical scroll only | ✅ | `SocialFeedReplicaTest::the feed carries no grid or carousel markers on either feed surface`, `MobileEngagementPassTest` |
| Suite | ✅ | **656 passed / 18,259 assertions / 3 skipped** (exit 0); `--list-tests tests/Arch` = **6 Arch tests**; evidence `dist/v1.9.2-suite-final.txt` (the exact shipped bytes), `dist/v1.9.2-suite.txt`, `dist/v1.9.2-list-tests.txt` |
| Flake ban (Faker apostrophe vs Blade escaping) | ✅ | `FeedTest::an actor name carrying an apostrophe renders escaped, never dropped` + the four `e($name)` assertions in `FeedTest` / `FramesTest`; diagnosis recorded in `dist/v1.9.2-suite-orderflake.txt` |
| Update artifact | ✅ | `dist/promptsewa-1.9.2-update.zip` — **517 entries** (513 core + 4 docroot), **0.98 MB**, hygiene **CLEAN**, **SHA-256 `70d807a30bf16163080777c8bc57a30c3dc595f56eaed79278d2cb18f0da8807`** (identical across two builds), 0 `.sqlite` / 0 `.env`, no new migrations; the archive carries `core/tests/`, so it tracks test edits too; evidence `dist/v1.9.2-zip.txt` |
| Browser gate | ✅ | DOM/computed-style assertions under `artisan serve` + seeded preview DB + a seeded paid sale — `dist/v1.9.2-browser-gate.txt` |
