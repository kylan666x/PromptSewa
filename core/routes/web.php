<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\PackAdminController;
use App\Http\Controllers\Admin\PaymentMethodAdminController;
use App\Http\Controllers\Admin\PromptAdminController;
use App\Http\Controllers\Admin\BrandSettingsAdminController;
use App\Http\Controllers\Admin\ToolLogoAdminController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CreatorProfileController;
use App\Http\Controllers\Dashboard\PromptEditController;
use App\Http\Controllers\Dashboard\PromptFormController;
use App\Http\Controllers\Dashboard\ReleaseUpdateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PackController;
use App\Http\Controllers\Admin\PromptReportAdminController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\PromptReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StorefrontController;
use App\Models\Prompt;
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
Route::post('/prompts/{prompt:slug}/versions/{version}/restore', [\App\Http\Controllers\Dashboard\PromptEditController::class, 'restore'])
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
Route::post('/prompts/{prompt:slug}/rate', [\App\Http\Controllers\RatingController::class, 'store'])
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
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [\App\Http\Controllers\SitemapController::class, 'robots'])->name('robots');

Route::get('/about', [PageController::class, 'about'])->name('pages.about');

Route::get('/packs', [PackController::class, 'index'])->name('packs.index');
Route::get('/packs/{pack:slug}', [PackController::class, 'show'])->name('packs.show');

// --- Minimal auth (navbar links must resolve) ---------------------------

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'create'])->name('register');
    Route::post('/register', [AuthController::class, 'store'])->name('register.store');
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
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
Route::get('/dashboard/profile', [\App\Http\Controllers\Dashboard\ProfileController::class, 'edit'])
    ->middleware('auth')
    ->name('dashboard.profile.edit');
Route::put('/dashboard/profile', [\App\Http\Controllers\Dashboard\ProfileController::class, 'update'])
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
    Route::get('/purchases', [CheckoutController::class, 'purchases'])->name('purchases.index');

    Route::get('/checkout/{order}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/prompts/{prompt:slug}', [CheckoutController::class, 'buyPrompt'])->name('checkout.prompts.buy');
    Route::post('/checkout/packs/{pack:slug}', [CheckoutController::class, 'buyPack'])->name('checkout.packs.buy');

    // T13 (v1.5.0): re-download an owned prompt's full body. Active grant
    // (or ownership) required — same entitlement gate as full-body viewing.
    Route::get('/purchases/prompts/{prompt:slug}/download', [CheckoutController::class, 'redownload'])
        ->name('purchases.download');

    // T13: bookmarks — toggle save state on a prompt. Owner-only, throttled.
    Route::post('/bookmarks/{prompt:slug}', [\App\Http\Controllers\BookmarkController::class, 'toggle'])
        ->middleware('throttle:30,1')
        ->name('bookmarks.toggle');

    Route::get('/checkout/{order}/esewa', [CheckoutController::class, 'esewaPay'])->name('checkout.esewa.pay');
    // eSewa POSTs the signed payload here — never CSRF-protected (gateway
    // has no Laravel session) and must stay reachable after redirects.
    Route::post('/checkout/esewa/verify', [CheckoutController::class, 'esewaVerify'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->name('checkout.esewa.verify');

    Route::post('/checkout/{order}/manual', [CheckoutController::class, 'manualSubmit'])->name('checkout.manual.submit');

    // C3 (v1.4.4): buyer submits/refreshes the TXN id + screenshot proof on
    // their own pending manual order. Throttled like other public writes.
    Route::post('/orders/{order}/proof', [\App\Http\Controllers\Dashboard\PaymentProofController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('orders.proof.store');

    // C3: payment-proof images are PRIVATE — served through this controller
    // (owner or staff only), never a raw storage URL. QR codes (public
    // disk) are fine as plain storage URLs; proofs are not.
    Route::get('/orders/{order}/proof', [\App\Http\Controllers\Dashboard\PaymentProofController::class, 'show'])
        ->name('orders.proof.show');
});

// --- Admin panel (staff only) --------------------------------------------

Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Prompt moderation: publish / reject from the queue.
    Route::get('/prompts', [PromptAdminController::class, 'index'])->name('prompts.index');
    // Moderation preview — full body for staff, grants nothing (replaces
    // the fragile ?preview=1 + referer heuristic).
    Route::get('/prompts/{prompt}/preview', [\App\Http\Controllers\Admin\PromptPreviewController::class, 'show'])->name('prompts.preview');
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
    Route::post('/users/purge-demo/preview', [\App\Http\Controllers\Admin\UserPurgeController::class, 'preview'])->name('users.purge.preview');
    Route::post('/users/purge-demo/run', [\App\Http\Controllers\Admin\UserPurgeController::class, 'run'])->name('users.purge.run');

    // A5: complimentary grants (press copies, make-goods) — admin only.
    Route::get('/comp-grants', [\App\Http\Controllers\Admin\CompGrantController::class, 'create'])->name('comp-grants.create');
    Route::post('/comp-grants', [\App\Http\Controllers\Admin\CompGrantController::class, 'store'])->name('comp-grants.store');

    // Abuse reports triage ("Report this prompt").
    Route::get('/reports', [PromptReportAdminController::class, 'index'])->name('reports.index');
    Route::patch('/reports/{report}/status', [PromptReportAdminController::class, 'updateStatus'])->name('reports.status');

    // Payment methods (eSewa credentials encrypted at rest).
    Route::get('/payments', [PaymentMethodAdminController::class, 'edit'])->name('payments.edit');
    Route::put('/payments', [PaymentMethodAdminController::class, 'update'])->name('payments.update');

    // C3 (v1.4.4): manual payment methods CRUD (QR + instructions).
    Route::get('/manual-methods', [\App\Http\Controllers\Admin\ManualPaymentMethodController::class, 'index'])->name('manual-methods.index');
    Route::post('/manual-methods', [\App\Http\Controllers\Admin\ManualPaymentMethodController::class, 'store'])->name('manual-methods.store');
    Route::put('/manual-methods/{manualMethod}', [\App\Http\Controllers\Admin\ManualPaymentMethodController::class, 'update'])->name('manual-methods.update');
    Route::delete('/manual-methods/{manualMethod}', [\App\Http\Controllers\Admin\ManualPaymentMethodController::class, 'destroy'])->name('manual-methods.destroy');

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

// Legacy path kept working for bookmarks from the previous dashboard card.
Route::get('/dashboard/update', fn () => redirect()->route('admin.update'))
    ->middleware(['auth', 'staff'])
    ->name('dashboard.update');
