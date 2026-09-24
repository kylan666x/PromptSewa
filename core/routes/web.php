<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\PackAdminController;
use App\Http\Controllers\Admin\PaymentMethodAdminController;
use App\Http\Controllers\Admin\PromptAdminController;
use App\Http\Controllers\Admin\BrandSettingsAdminController;
use App\Http\Controllers\Admin\ToolLogoAdminController;
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
use App\Http\Controllers\StorefrontController;
use App\Models\Prompt;
use Illuminate\Support\Facades\Route;

// --- Public catalog (UI-001) --------------------------------------------

Route::get('/', [StorefrontController::class, 'index'])->name('home');

// Minimal detail page; the full UI-002 experience (copy button, sanitized
// markdown, metadata sidebar) builds on this route.
Route::get('/prompts/{prompt:slug}', [PromptController::class, 'show'])->name('prompts.show');

// "Report this prompt" — public form, guests allowed, submission throttled.
Route::get('/prompts/{prompt:slug}/report', [PromptReportController::class, 'create'])
    ->name('prompts.report.create')
    ->middleware('throttle:30,1');
Route::post('/prompts/{prompt:slug}/report', [PromptReportController::class, 'store'])
    ->name('prompts.report.store')
    ->middleware('throttle:10,1');

// Public creator profiles — identity, bio and published catalog.
Route::get('/creators/{user}', [CreatorProfileController::class, 'show'])
    ->name('creators.show');

// Search goes through the Scout-backed service; throttle guards the
// public LIKE scans against trivial abuse (UI-001 security note).
Route::get('/prompts', [LibraryController::class, 'index'])
    ->name('library.index')
    ->middleware('throttle:60,1');

Route::get('/categories/{category:slug}', [LibraryController::class, 'category'])
    ->name('library.category');

// --- Static-ish pages + packs -------------------------------------------

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

    Route::get('/checkout/{order}/esewa', [CheckoutController::class, 'esewaPay'])->name('checkout.esewa.pay');
    // eSewa POSTs the signed payload here — never CSRF-protected (gateway
    // has no Laravel session) and must stay reachable after redirects.
    Route::post('/checkout/esewa/verify', [CheckoutController::class, 'esewaVerify'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->name('checkout.esewa.verify');

    Route::post('/checkout/{order}/manual', [CheckoutController::class, 'manualSubmit'])->name('checkout.manual.submit');
});

// --- Admin panel (staff only) --------------------------------------------

Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Prompt moderation: publish / reject from the queue.
    Route::get('/prompts', [PromptAdminController::class, 'index'])->name('prompts.index');
    Route::patch('/prompts/{prompt}/status', [PromptAdminController::class, 'updateStatus'])->name('prompts.status');

    // User management (admin only for role changes).
    Route::get('/users', [UserAdminController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/role', [UserAdminController::class, 'updateRole'])->name('users.role');

    // Abuse reports triage ("Report this prompt").
    Route::get('/reports', [PromptReportAdminController::class, 'index'])->name('reports.index');
    Route::patch('/reports/{report}/status', [PromptReportAdminController::class, 'updateStatus'])->name('reports.status');

    // Payment methods (eSewa credentials encrypted at rest).
    Route::get('/payments', [PaymentMethodAdminController::class, 'edit'])->name('payments.edit');
    Route::put('/payments', [PaymentMethodAdminController::class, 'update'])->name('payments.update');

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

// Legacy path kept working for bookmarks from the previous dashboard card.
Route::get('/dashboard/update', fn () => redirect()->route('admin.update'))
    ->middleware(['auth', 'staff'])
    ->name('dashboard.update');
