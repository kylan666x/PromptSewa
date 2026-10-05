<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdoptionController;
use App\Http\Controllers\Admin\BadgeAdminController;
use App\Http\Controllers\Admin\BrandSettingsAdminController;
use App\Http\Controllers\Admin\CompGrantController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\FrameAdminController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\MailSettingsAdminController;
use App\Http\Controllers\Admin\ManualPaymentMethodController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\PackAdminController;
use App\Http\Controllers\Admin\PaymentMethodAdminController;
use App\Http\Controllers\Admin\PromptAdminController;
use App\Http\Controllers\Admin\PromptPreviewController;
use App\Http\Controllers\Admin\PromptReportAdminController;
use App\Http\Controllers\Admin\SecurityAdminController;
use App\Http\Controllers\Admin\ToolLogoAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Admin\UserPurgeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CreatorProfileController;
use App\Http\Controllers\Dashboard\EarningsController;
use App\Http\Controllers\Dashboard\PaymentProofController;
use App\Http\Controllers\Dashboard\ProfileController;
use App\Http\Controllers\Dashboard\PromptEditController;
use App\Http\Controllers\Dashboard\PromptFormController;
use App\Http\Controllers\Dashboard\ReleaseUpdateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PackController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\PromptReportController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StorefrontController;
use App\Models\Prompt;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

// --- Public catalog (UI-001) --------------------------------------------

Route::get('/', [StorefrontController::class, 'index'])->name('home');

// Minimal detail page; the full UI-002 experience (copy button, sanitized
// markdown, metadata sidebar) builds on this route.
Route::get('/prompts/{prompt:slug}', [PromptController::class, 'show'])->name('prompts.show');

// Public version history — metadata only (changelog, author, timestamp).
// Bodies/variables never render here for paid prompts (B6).
Route::get('/prompts/{prompt:slug}/versions', [PromptController::class, 'versions'])
    ->middleware('throttle:60,1')
    ->name('prompts.versions');

// T7 (v1.5.0): restore an old snapshot as a new append-only version.
// Owner/moderator only (policy gate inside). Route-model binds by the
// version's numeric PK; controller re-verifies the version belongs.
Route::post('/prompts/{prompt:slug}/versions/{version}/restore', [PromptEditController::class, 'restore'])
    ->middleware('auth')
    ->name('prompts.versions.restore');

// "Report this prompt" — public form, guests allowed, submission throttled.
Route::get('/prompts/{prompt:slug}/report', [PromptReportController::class, 'create'])
    ->name('prompts.report.create')
    ->middleware('throttle:30,1');
Route::post('/prompts/{prompt:slug}/report', [PromptReportController::class, 'store'])
    ->name('prompts.report.store')
    ->middleware('throttle:10,1');

// Community ratings: paid prompts require an active license; free are open
// to any logged-in user. One rating per user per prompt (upsert).
Route::post('/prompts/{prompt:slug}/rate', [RatingController::class, 'store'])
    ->name('prompts.rate')
    ->middleware('auth');

// Public creator profiles — identity, bio and published catalog.
// {creator} binds by the public username (AppServiceProvider) with a
// display-name fallback for legacy accounts.
Route::get('/creators/{creator}', [CreatorProfileController::class, 'show'])
    ->name('creators.show');

// Search goes through the Scout-backed service; throttle guards the
// public LIKE scans against trivial abuse (UI-001 security note).
Route::get('/prompts', [LibraryController::class, 'index'])
    ->name('library.index')
    ->middleware('throttle:60,1');

// JSON typeahead for the navbar search preview (TASK 2) — same throttle
// class as the full search since it hits the same underlying queries.
// T9: JSON endpoint — header-level noindex guard.
Route::get('/search/preview', [SearchController::class, 'preview'])
    ->name('search.preview')
    ->middleware('throttle:60,1');

Route::get('/categories/{category:slug}', [LibraryController::class, 'category'])
    ->name('library.category');

// --- Static-ish pages + packs -------------------------------------------

// T9 (v1.5.0): dynamic sitemap + robots. deploy/public_html has no physical
// robots.txt; the .htaccess catch-all forwards both here.
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::get('/about', [PageController::class, 'about'])->name('pages.about');

Route::get('/packs', [PackController::class, 'index'])->name('packs.index');
Route::get('/packs/{pack:slug}', [PackController::class, 'show'])->name('packs.show');

// --- Minimal auth (navbar links must resolve) ---------------------------

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'create'])->name('register');
    Route::post('/register', [AuthController::class, 'store'])->name('register.store');
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');

    // A1 (v1.7.6): forgot password. 6 attempts a minute per IP — this form
    // hands out a real email, so it must not become a mail cannon. The
    // reset POST is on the broker's own 60s per-account resend throttle.
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])
        ->middleware('throttle:6,1')
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    // A1: the enumeration-proof notice. Deliberately its own path, NOT a
    // third verb on /forgot-password: the POST there must stay a single
    // route (GET renders the form) so no POST/GET pair can be confused.
    Route::get('/forgot-password/sent', [PasswordResetController::class, 'sent'])->name('password.sent');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showForm'])->name('password.reset');
    Route::post('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// --- Creator workspace ---------------------------------------------------

// Interim creator overview — the full dashboard shell arrives in UI-003.
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

// Full profile editing (identity + avatar + banner).
Route::get('/dashboard/profile', [ProfileController::class, 'edit'])
    ->middleware('auth')
    ->name('dashboard.profile.edit');
Route::put('/dashboard/profile', [ProfileController::class, 'update'])
    ->middleware('auth')
    ->name('dashboard.profile.update');

// Type-aware "Add Prompt" flow (God of Prompt pattern).
Route::get('/dashboard/prompts/create', [PromptFormController::class, 'create'])
    ->middleware('auth')
    ->name('dashboard.prompts.create');

Route::post('/dashboard/prompts', [PromptFormController::class, 'store'])
    ->middleware('auth')
    ->name('dashboard.prompts.store');

// Versioned editing: each save commits a new prompt_versions row.
Route::get('/dashboard/prompts/{prompt:slug}/edit', [PromptEditController::class, 'edit'])
    ->middleware('auth')
    ->name('dashboard.prompts.edit');

Route::put('/dashboard/prompts/{prompt:slug}', [PromptEditController::class, 'update'])
    ->middleware('auth')
    ->name('dashboard.prompts.update');

// --- Buyer library + checkout (PAY-001) ---------------------------------

Route::middleware('auth')->group(function () {
    // F6 (v1.7.8): the navbar bell. unread() is the 60s poll (throttled),
    // read-all is the panel button, open() is the mark-read click-through.
    Route::get('/notifications/unread', [NotificationController::class, 'unread'])
        ->middleware('throttle:30,1')
        ->name('notifications.unread');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])
        ->name('notifications.open');

    Route::get('/purchases', [CheckoutController::class, 'purchases'])->name('purchases.index');

    Route::get('/checkout/{order}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/prompts/{prompt:slug}', [CheckoutController::class, 'buyPrompt'])->name('checkout.prompts.buy');
    Route::post('/checkout/packs/{pack:slug}', [CheckoutController::class, 'buyPack'])->name('checkout.packs.buy');

    // T13 (v1.5.0): re-download an owned prompt's full body. Active grant
    // (or ownership) required — same entitlement gate as full-body viewing.
    Route::get('/purchases/prompts/{prompt:slug}/download', [CheckoutController::class, 'redownload'])
        ->name('purchases.download');

    // T13: bookmarks — toggle save state on a prompt. Owner-only, throttled.
    Route::post('/bookmarks/{prompt:slug}', [BookmarkController::class, 'toggle'])
        ->middleware('throttle:30,1')
        ->name('bookmarks.toggle');

    Route::get('/checkout/{order}/esewa', [CheckoutController::class, 'esewaPay'])->name('checkout.esewa.pay');
    // eSewa POSTs the signed payload here — never CSRF-protected (gateway
    // has no Laravel session) and must stay reachable after redirects.
    // M3 (v1.6.0): server-to-server eSewa webhook. OUTSIDE the auth group
    // (eSewa's servers have no session). CSRF-exempt via bootstrap/app.php;
    // the HMAC signature check is the guard. Throttled mildly so a broken
    // client retry loop can't hammer the ledger.
    Route::post('/payments/esewa/webhook', [CheckoutController::class, 'esewaWebhook'])
        ->middleware('throttle:30,1')
        ->name('payments.esewa.webhook');

    Route::post('/checkout/esewa/verify', [CheckoutController::class, 'esewaVerify'])
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->name('checkout.esewa.verify');

    Route::post('/checkout/{order}/manual', [CheckoutController::class, 'manualSubmit'])->name('checkout.manual.submit');

    // S2 (v1.8.0): the Sikka rail — balance IS the verification, so the
    // order is paid on POST. The single controller call site of
    // SikkaService::spendSikka (arch-locked).
    Route::post('/checkout/{order}/sikka', [CheckoutController::class, 'paySikka'])->name('checkout.sikka.pay');

    // C3 (v1.4.4): buyer submits/refreshes the TXN id + screenshot proof on
    // their own pending manual order. Throttled like other public writes.
    Route::post('/orders/{order}/proof', [PaymentProofController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('orders.proof.store');

    // C3: payment-proof images are PRIVATE — served through this controller
    // (owner or staff only), never a raw storage URL. QR codes (public
    // disk) are fine as plain storage URLs; proofs are not.
    Route::get('/orders/{order}/proof', [PaymentProofController::class, 'show'])
        ->name('orders.proof.show');
});

// --- Admin panel (staff only) --------------------------------------------

Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Prompt moderation: publish / reject from the queue.
    Route::get('/prompts', [PromptAdminController::class, 'index'])->name('prompts.index');
    // Moderation preview — full body for staff, grants nothing (replaces
    // the fragile ?preview=1 + referer heuristic).
    Route::get('/prompts/{prompt}/preview', [PromptPreviewController::class, 'show'])->name('prompts.preview');
    Route::patch('/prompts/{prompt}/status', [PromptAdminController::class, 'updateStatus'])->name('prompts.status');

    // User management (admin only for role changes). {user:id} pins the
    // id binding — User::getRouteKey() now returns the public username for
    // creator URLs, and admin action URLs must stay id-stable.
    Route::get('/users', [UserAdminController::class, 'index'])->name('users.index');
    Route::patch('/users/{user:id}/role', [UserAdminController::class, 'updateRole'])->name('users.role');
    Route::patch('/users/{user:id}/verified', [UserAdminController::class, 'toggleVerified'])->name('users.verified');
    Route::patch('/users/{user:id}/banned', [UserAdminController::class, 'toggleBanned'])->name('users.banned');

    // T3 (v1.5.0): admin account switching. start is admin-only (enforced
    // in the controller — the staff group lets moderators in, the 403 comes
    // from ImpersonationController); stop is reachable by the impersonated
    // session itself (the chrome bar links here), so it lives outside the
    // admin prefix.
    Route::post('/users/{user:id}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');

    // T10 (v1.5.0): demo purge panel (dry-run preview + force). Admin-only
    // in the controller; the panel shells pv:purge-demo so logic can't drift.
    Route::post('/users/purge-demo/preview', [UserPurgeController::class, 'preview'])->name('users.purge.preview');
    Route::post('/users/purge-demo/run', [UserPurgeController::class, 'run'])->name('users.purge.run');

    // F3 (v1.5.2): "Apply adoption" panel — dry-run preview + force, shelling
    // pv:adopt-catalog so the runbook logic and UI can't drift. Admin-only
    // in the controller; the founder runs T4 from the browser (no SSH host).
    Route::post('/users/adopt-catalog/preview', [AdoptionController::class, 'preview'])->name('users.adopt.preview');
    Route::post('/users/adopt-catalog/run', [AdoptionController::class, 'run'])->name('users.adopt.run');

    // A5: complimentary grants (press copies, make-goods) — admin only.
    Route::get('/comp-grants', [CompGrantController::class, 'create'])->name('comp-grants.create');
    Route::post('/comp-grants', [CompGrantController::class, 'store'])->name('comp-grants.store');

    // Abuse reports triage ("Report this prompt").
    Route::get('/reports', [PromptReportAdminController::class, 'index'])->name('reports.index');
    Route::patch('/reports/{report}/status', [PromptReportAdminController::class, 'updateStatus'])->name('reports.status');

    // Payment methods (eSewa credentials encrypted at rest).
    Route::get('/payments', [PaymentMethodAdminController::class, 'edit'])->name('payments.edit');
    Route::put('/payments', [PaymentMethodAdminController::class, 'update'])->name('payments.update');

    // C3 (v1.4.4): manual payment methods CRUD (QR + instructions).
    Route::get('/manual-methods', [ManualPaymentMethodController::class, 'index'])->name('manual-methods.index');
    Route::post('/manual-methods', [ManualPaymentMethodController::class, 'store'])->name('manual-methods.store');
    Route::put('/manual-methods/{manualMethod}', [ManualPaymentMethodController::class, 'update'])->name('manual-methods.update');
    Route::delete('/manual-methods/{manualMethod}', [ManualPaymentMethodController::class, 'destroy'])->name('manual-methods.destroy');

    // Packs CRUD.
    Route::get('/packs', [PackAdminController::class, 'index'])->name('packs.index');
    Route::get('/packs/create', [PackAdminController::class, 'create'])->name('packs.create');
    Route::post('/packs', [PackAdminController::class, 'store'])->name('packs.store');
    Route::get('/packs/{pack}/edit', [PackAdminController::class, 'edit'])->name('packs.edit');
    Route::put('/packs/{pack}', [PackAdminController::class, 'update'])->name('packs.update');
    Route::delete('/packs/{pack}', [PackAdminController::class, 'destroy'])->name('packs.destroy');

    // AI tool logos.
    Route::get('/tool-logos', [ToolLogoAdminController::class, 'index'])->name('tool-logos.index');
    Route::post('/tool-logos', [ToolLogoAdminController::class, 'store'])->name('tool-logos.store');
    Route::patch('/tool-logos/{toolLogo}', [ToolLogoAdminController::class, 'update'])->name('tool-logos.update');
    Route::delete('/tool-logos/{toolLogo}', [ToolLogoAdminController::class, 'destroy'])->name('tool-logos.destroy');

    // Brand settings (logo, favicon, contact emails, site name).
    Route::get('/brand', [BrandSettingsAdminController::class, 'edit'])->name('brand.edit');
    Route::put('/brand', [BrandSettingsAdminController::class, 'update'])->name('brand.update');

    // Orders: verify manual payments / view history.
    Route::get('/orders', [OrderAdminController::class, 'index'])->name('orders.index');
    Route::patch('/orders/{order}/approve', [OrderAdminController::class, 'approve'])->name('orders.approve');
    Route::patch('/orders/{order}/reject', [OrderAdminController::class, 'reject'])->name('orders.reject');

    // M5 (v1.6.0): Finance desk — totals, pre-ledger list, payout queue,
    // ledger browser. Admin-only in the controller.
    Route::get('/finance', [FinanceController::class, 'index'])->name('finance');
    Route::post('/finance/payouts/{payout}/approve', [FinanceController::class, 'approvePayout'])->name('finance.payouts.approve');
    Route::post('/finance/payouts/{payout}/settle', [FinanceController::class, 'settlePayout'])->name('finance.payouts.settle');
    Route::post('/finance/payouts/{payout}/reject', [FinanceController::class, 'rejectPayout'])->name('finance.payouts.reject');
    Route::get('/finance/payouts/{payout}/destination', [FinanceController::class, 'payoutDestination'])->name('finance.payouts.destination');

    // G2 (v1.7.0): badge CRUD + manual award (admin, audited).
    Route::get('/badges', [BadgeAdminController::class, 'index'])->name('badges.index');
    Route::post('/badges', [BadgeAdminController::class, 'store'])->name('badges.store');
    Route::put('/badges/{badge}', [BadgeAdminController::class, 'update'])->name('badges.update');
    Route::delete('/badges/{badge}', [BadgeAdminController::class, 'destroy'])->name('badges.destroy');
    Route::post('/badges/award', [BadgeAdminController::class, 'award'])->name('badges.award');
    // F3 (v1.7.8): idempotent award backfill (admin-only in the controller,
    // throttled — a human clicking twice must not queue a second scan).
    Route::post('/badges/scan', [BadgeAdminController::class, 'scan'])->middleware('throttle:6,1')->name('badges.scan');

    // G3 (v1.7.0): frame CRUD.
    Route::get('/frames', [FrameAdminController::class, 'index'])->name('frames.index');
    Route::post('/frames', [FrameAdminController::class, 'store'])->name('frames.store');
    Route::put('/frames/{frame}', [FrameAdminController::class, 'update'])->name('frames.update');

    // W4 (v1.7.3): manual frame award (audited: granted_by + reason) + revoke.
    Route::post('/frames/award', [FrameAdminController::class, 'award'])->name('frames.award');
    Route::delete('/frames/unlocks/{unlock}', [FrameAdminController::class, 'revoke'])->name('frames.unlocks.revoke');
    Route::delete('/frames/{frame}', [FrameAdminController::class, 'destroy'])->name('frames.destroy');

    // T6 (v1.7.3): Admin → Security — bot challenge + disposable blocklist.
    Route::get('/security', [SecurityAdminController::class, 'edit'])->name('security.edit');
    Route::put('/security', [SecurityAdminController::class, 'update'])->name('security.update');
    Route::post('/security/test-email', [SecurityAdminController::class, 'testEmail'])->name('security.test-email');

    // A3 (v1.7.6): Admin → Email — mailer selection, SMTP credentials
    // (write-only, encrypted at rest) and the send-a-test-mail probe.
    Route::get('/email', [MailSettingsAdminController::class, 'edit'])->name('email.edit');
    Route::put('/email', [MailSettingsAdminController::class, 'update'])->name('email.update');
    Route::post('/email/test', [MailSettingsAdminController::class, 'testEmail'])->name('email.test');

    // Release updates via uploaded zip (existing feature).
    Route::get('/update', [ReleaseUpdateController::class, 'form'])->name('update');
    Route::post('/update', [ReleaseUpdateController::class, 'update'])->name('update.run');
});

// T3 (v1.5.0): end an impersonation session. Outside the staff-gated admin
// prefix on purpose — the impersonated session may belong to a plain member,
// and the chrome bar must let the admin driving it return. The controller
// re-verifies the impersonator is still an admin.
Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])
    ->middleware('auth')
    ->name('impersonation.stop');

// G4 (v1.7.0): public community feed (paper world, noindex, cached page 1).
Route::get('/feed', [FeedController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('feed.index');

// M4 (v1.6.0): creator Earnings tab — balance, sales, payout request/cancel.
Route::get('/dashboard/earnings', [EarningsController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard.earnings');
Route::post('/dashboard/earnings/payouts', [EarningsController::class, 'requestPayout'])
    ->middleware('auth')
    ->name('dashboard.earnings.request');
Route::post('/dashboard/earnings/payouts/{payout}/cancel', [EarningsController::class, 'cancelPayout'])
    ->middleware('auth')
    ->name('dashboard.earnings.cancel');

// S4 (v1.8.0): Sikka → NPR withdrawals. Requesting parks a payout_hold row
// (credits are unavailable until released or settled); the admin settles it
// at the cash-out spread in the Finance desk.
Route::post('/dashboard/earnings/sikka', [EarningsController::class, 'requestSikkaPayout'])
    ->middleware('auth')
    ->name('dashboard.earnings.sikka.request');
Route::post('/dashboard/earnings/sikka/{payout}/cancel', [EarningsController::class, 'cancelSikkaPayout'])
    ->middleware('auth')
    ->name('dashboard.earnings.sikka.cancel');

// Legacy path kept working for bookmarks from the previous dashboard card.
Route::get('/dashboard/update', fn () => redirect()->route('admin.update'))
    ->middleware(['auth', 'staff'])
    ->name('dashboard.update');
