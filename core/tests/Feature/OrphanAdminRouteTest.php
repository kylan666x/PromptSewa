<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Pack;
use App\Models\Payout;
use App\Models\Prompt;
use App\Models\PromptReport;
use App\Models\ToolLogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * F1 (v1.7.8) — no orphaned admin pages (BH-R9-01: the Finance desk shipped
 * in v1.6.0 with routes, controller and tests but no nav pill, so it was
 * reachable only by typing the URL — and nothing could ever catch that).
 *
 * Rule: every named `admin.*` GET route must be referenced by at least one
 * real admin page's served HTML (nav pill, card link, or table action) OR
 * sit in the reasoned exempt map below. A new admin GET route that nobody
 * can click now fails the suite instead of shipping dark.
 */

/** Named admin GET routes that are deliberately not navigable pages. */
function orphanAdminExemptRoutes(): array
{
    return [
        // Fetched by the "Reveal destination" control on the Finance desk:
        // the URL is assembled client-side (`/admin/finance/payouts/${id}/destination`),
        // so no served href/action can carry it. It is an XHR endpoint whose
        // payload/authorization is asserted in the FinanceController tests.
        'admin.finance.payouts.destination' => 'In-page fetch endpoint (URL built client-side); finance tests cover its gate and payload.',
    ];
}

/** Minimal world whose rows make every table action render. */
function orphanAdminWorld(): array
{
    Storage::fake('proofs');

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $category = Category::factory()->create();

    $published = Prompt::factory()->for($creator, 'creator')->withVersion()->create([
        'title' => 'Orphan Sweep Published',
        'category_id' => $category->id,
        'status' => Prompt::STATUS_PUBLISHED,
        'price_cents' => 24_900,
    ]);
    // Non-public rows are what link the moderation preview (R2 fix).
    $draft = Prompt::factory()->for($creator, 'creator')->draft()->create([
        'title' => 'Orphan Sweep Draft',
        'category_id' => $category->id,
    ]);

    $pack = Pack::factory()->create(['name' => 'Orphan Sweep Pack']);
    $pack->prompts()->attach($published->id);

    $order = Order::factory()->create([
        'buyer_id' => $creator->id,
        'status' => Order::STATUS_PAID,
        'payment_method' => 'manual',
        'payment_reference' => 'manual · REF-ORPHAN',
        'paid_at' => now(),
    ]);

    Payout::create([
        'user_id' => $creator->id,
        'amount_paisa' => 10_000,
        'status' => Payout::STATUS_REQUESTED,
        'method' => Payout::METHOD_ESEWA_WALLET,
        'destination_encrypted' => Illuminate\Support\Facades\Crypt::encryptString('esewa:9800000000'),
        'requested_at' => now(),
    ]);

    PromptReport::factory()->create(['prompt_id' => $published->id]);
    ToolLogo::query()->create(['name' => 'Orphan Tool', 'is_active' => true, 'position' => 1]);

    return compact('admin', 'mod', 'creator', 'category', 'published', 'draft', 'pack', 'order');
}

/** Every admin surface an admin is entitled to render. */
function orphanAdminPages(): array
{
    return [
        route('admin.dashboard'),
        route('admin.prompts.index'),
        route('admin.orders.index'),
        route('admin.users.index'),
        route('admin.reports.index'),
        route('admin.packs.index'),
        route('admin.payments.edit'),
        route('admin.manual-methods.index'),
        route('admin.brand.edit'),
        route('admin.tool-logos.index'),
        route('admin.update'),
        route('admin.comp-grants.create'),
        route('admin.badges.index'),
        route('admin.frames.index'),
        route('admin.security.edit'),
        route('admin.email.edit'),
        route('admin.finance'),
    ];
}

/** href/action values from served HTML → route names they resolve to. */
function orphanAdminResolvedNames(string $html): array
{
    preg_match_all('/(?:href|action)="([^"]+)"/', $html, $matches);

    $names = [];

    foreach ($matches[1] as $url) {
        $path = parse_url(html_entity_decode($url), PHP_URL_PATH);

        if ($path === null || $path === '' || $path[0] !== '/') {
            continue; // mailto:, https://elsewhere, anchors
        }

        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (Throwable) {
            continue; // no GET route at this path (POST-only table actions etc.)
        }

        if ($route->getName() !== null) {
            $names[$route->getName()] = true;
        }
    }

    return array_keys($names);
}

test('every named admin GET route is linked from a served admin page or exempt with a reason', function () {
    $w = orphanAdminWorld();

    $linked = [];

    foreach (orphanAdminPages() as $url) {
        $response = $this->actingAs($w['admin'])->get($url);
        $response->assertOk();

        foreach (orphanAdminResolvedNames((string) $response->getContent()) as $name) {
            $linked[$name] = true;
        }
    }

    $adminRoutes = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if ($name !== null && str_starts_with($name, 'admin.') && in_array('GET', $route->methods(), true)) {
            $adminRoutes[$name] = true;
        }
    }

    expect($adminRoutes)->not->toBeEmpty();

    $exempt = orphanAdminExemptRoutes();

    // Stale exemptions must not survive: if the route is gone (or the reason
    // is empty), the map has to be cleaned up.
    foreach ($exempt as $name => $reason) {
        expect(isset($adminRoutes[$name]))->toBeTrue("exempt admin route [{$name}] no longer exists — remove the exemption");
        expect(trim($reason))->not->toBe('', "exempt admin route [{$name}] needs a reason");
    }

    foreach (array_keys($adminRoutes) as $name) {
        if (isset($exempt[$name])) {
            continue;
        }

        expect(isset($linked[$name]))->toBeTrue(
            "admin route [{$name}] is linked from no served admin page — add a nav pill, card link or table action (or a reasoned exemption)."
        );
    }
});

test('the Finance pill ships for admins after Payments and stays hidden from moderators', function () {
    $w = orphanAdminWorld();

    $adminHtml = (string) $this->actingAs($w['admin'])->get(route('admin.dashboard'))->getContent();
    $modHtml = (string) $this->actingAs($w['mod'])->get(route('admin.dashboard'))->getContent();

    expect($adminHtml)->toContain('href="'.route('admin.finance').'"');
    expect($modHtml)->not->toContain('href="'.route('admin.finance').'"');

    $paymentsAt = strpos($adminHtml, 'href="'.route('admin.payments.edit').'"');
    $financeAt = strpos($adminHtml, 'href="'.route('admin.finance').'"');

    expect($paymentsAt)->not->toBeFalse()
        ->and($financeAt)->not->toBeFalse()
        ->and($financeAt)->toBeGreaterThan($paymentsAt, 'the Finance pill must sit after Payments in the admin nav');
});
