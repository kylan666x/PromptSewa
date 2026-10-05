<?php

use App\Models\Badge;
use App\Models\Category;
use App\Models\Frame;
use App\Models\ManualPaymentMethod;
use App\Models\Order;
use App\Models\Pack;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\PromptReport;
use App\Models\ToolLogo;
use App\Models\User;
use App\Models\UserFrameUnlock;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * R1 (v1.7.7 Bug Hunt Raid) — the role × route matrix.
 *
 * Every named GET route is rendered as guest / member / creator / moderator /
 * admin / banned / impersonating-admin and asserted against the verb-outcome
 * its gate promises (200/302/403/404), with zero 500s, exactly one <title> on
 * HTML 200s, no `{{ $` leaks and no raw escaped braces.
 *
 * Data-driven over the ROUTE REGISTRY, not over a hand-copied list: the
 * completeness test (below) fails when a new named GET route appears and is
 * not classified here, so a new route cannot silently skip the sweep.
 *
 * Fixtures: one seeded world, one URL resolver per route — bound routes use
 * real rows so the sweep exercises the binder, not a 404 shortcut.
 */

/** The named GET routes that are deliberately outside this sweep. */
function raidMatrixExemptRoutes(): array
{
    return [
        'up' => 'Framework health endpoint (Laravel-generated name, no app session).',
        // F5 (v1.7.8): the proofs disk no longer sets `serve => true`, so the
        // framework `storage.proofs` route (BH-P3-01) no longer exists —
        // proofs stream ONLY through orders/{order}/proof. `storage.local`
        // still registers its framework route.
        'storage.local' => 'Framework signed-URL file route (local private disk).',
        // F6 (v1.7.8): the bell's two non-page GET doors — the JSON poll and
        // the owner-bound mark-read redirect. NotificationTest sweeps their
        // auth gates (guest 302, stranger 403, owner 302) directly.
        'notifications.unread' => 'JSON poll endpoint (auth + throttle:30,1) — NotificationTest.',
        'notifications.open' => 'Mark-read click-through redirect (owner-bound) — NotificationTest.',
    ];
}

/**
 * Expected status per fixture role. Shapes cover most gates; individual
 * routes override with an explicit map when the policy is per-row.
 *
 * @return array<string, int>
 */
function raidMatrixShape(string $shape): array
{
    $statuses = match ($shape) {
        // Storefront/catalog: open to everyone; a banned session is bounced
        // home (the middleware logs it out) before any page renders.
        'public' => ['guest' => 200, 'member' => 200, 'creator' => 200, 'moderator' => 200, 'admin' => 200, 'banned' => 302, 'impersonator' => 200],
        // guest-only auth pages: an authenticated visitor is redirected away.
        'guest' => ['guest' => 200, 'member' => 302, 'creator' => 302, 'moderator' => 302, 'admin' => 302, 'banned' => 302, 'impersonator' => 302],
        // Auth pages: any signed-in account renders.
        'auth' => ['guest' => 302, 'member' => 200, 'creator' => 200, 'moderator' => 200, 'admin' => 200, 'banned' => 302, 'impersonator' => 200],
        // /admin perimeter: staff are in, members 403.
        'staff' => ['guest' => 302, 'member' => 403, 'creator' => 403, 'moderator' => 200, 'admin' => 200, 'banned' => 302, 'impersonator' => 403],
        // admin-only doors inside the perimeter.
        'admin' => ['guest' => 302, 'member' => 403, 'creator' => 403, 'moderator' => 403, 'admin' => 200, 'banned' => 302, 'impersonator' => 403],
        default => throw new RuntimeException("Unknown raid matrix shape: {$shape}"),
    };

    return $statuses;
}

/** Minimal-but-real world so binders resolve for every bound route. */
function raidMatrixWorld(): array
{
    Storage::fake('proofs');

    $category = Category::factory()->create();
    $owner = User::factory()->create(['role' => User::ROLE_CREATOR, 'username' => 'raidmatrixowner', 'name' => 'Raid Matrix Owner']);
    $prompt = Prompt::factory()->for($owner, 'creator')->withVersion()->create([
        'title' => 'Raid Matrix Prompt',
        'category_id' => $category->id,
        'status' => Prompt::STATUS_PUBLISHED,
        'price_cents' => 24_900,
    ]);
    Prompt::factory()->for($owner, 'creator')->draft()->create(['title' => 'Raid Matrix Draft', 'category_id' => $category->id]);
    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $banned = User::factory()->create(['role' => User::ROLE_MEMBER, 'banned_at' => now()]);

    $pack = Pack::factory()->create(['name' => 'Raid Matrix Pack']);
    $pack->prompts()->attach($prompt->id);

    $order = Order::factory()->create(['buyer_id' => $owner->id, 'status' => Order::STATUS_PENDING, 'payment_method' => 'manual']);
    $product = Product::factory()->create(['prompt_id' => $prompt->id]);
    $order->items()->create(['product_id' => $product->id, 'prompt_id' => $prompt->id, 'price_paisa' => 24_900, 'currency' => 'NPR', 'quantity' => 1]);
    Storage::disk('proofs')->put('raid-matrix-proof.png', 'proof');
    $order->forceFill(['manual_proof_path' => 'raid-matrix-proof.png'])->save();

    // A checkout order with NO payment method yet: this is the page that
    // renders the manual-submit rail (the pending manual order above shows
    // the proof form instead). Both rails are matrix surfaces.
    $openOrder = Order::factory()->create(['buyer_id' => $owner->id, 'status' => Order::STATUS_PENDING]);
    $openOrder->items()->create(['product_id' => $product->id, 'prompt_id' => $prompt->id, 'price_paisa' => 24_900, 'currency' => 'NPR', 'quantity' => 1]);

    $payout = Payout::create([
        'user_id' => $owner->id,
        'amount_paisa' => 10_000,
        'status' => Payout::STATUS_REQUESTED,
        'method' => Payout::METHOD_ESEWA_WALLET,
        'destination_encrypted' => Crypt::encryptString('esewa:9800000000'),
        'requested_at' => now(),
    ]);

    PromptReport::factory()->create(['prompt_id' => $prompt->id]);
    ToolLogo::query()->create(['name' => 'Raid Matrix Tool', 'is_active' => true, 'position' => 1]);

    // R2 fixture depth: rows that make the per-row admin forms render, so the
    // dead-action scan can classify update/destroy/revoke/settle shapes too.
    Badge::query()->create(['name' => 'Raid Badge', 'slug' => 'raid-badge', 'criterion' => 'manual', 'is_active' => true]);
    $frame = Frame::query()->create(['name' => 'Raid Frame', 'image_path' => 'frames/raid.png', 'is_active' => true]);
    UserFrameUnlock::query()->create(['user_id' => $owner->id, 'frame_id' => $frame->id, 'source' => 'manual', 'granted_by' => $admin->id, 'reason' => 'raid fixture']);
    ManualPaymentMethod::query()->create(['name' => 'Raid Method', 'kind' => 'esewa', 'instructions' => 'Send the amount.', 'position' => 1, 'active' => true]);
    app(SettingsService::class)->set('manual_payment_enabled', '1');
    Payout::create([
        'user_id' => $owner->id,
        'amount_paisa' => 5_000,
        'status' => Payout::STATUS_APPROVED,
        'method' => Payout::METHOD_ESEWA_WALLET,
        'destination_encrypted' => Crypt::encryptString('esewa:9800000001'),
        'requested_at' => now()->subDay(),
    ]);

    return compact('category', 'owner', 'prompt', 'member', 'mod', 'admin', 'banned', 'pack', 'order', 'openOrder', 'payout');
}

/** Resolve a route name to a URL with real bindings; null = not resolvable here. */
function raidMatrixUrl(string $name, array $w): ?string
{
    return match ($name) {
        'library.category' => route($name, $w['category']),
        'prompts.show', 'prompts.versions', 'prompts.report.create' => route($name, $w['prompt']),
        'creators.show' => route($name, $w['owner']),
        'packs.show' => route($name, $w['pack']),
        'dashboard.prompts.edit' => route($name, $w['prompt']),
        'admin.prompts.preview' => route($name, $w['prompt']),
        'admin.packs.edit' => route($name, $w['pack']),
        'password.reset' => route($name, 'a-token', ['email' => 'nobody@example.test']),
        'checkout.show', 'checkout.esewa.pay' => route($name, $w['order']),
        'orders.proof.show' => route($name, $w['order']),
        'purchases.download' => route($name, $w['prompt']),
        'admin.finance.payouts.destination' => route($name, $w['payout']),
        default => (function () use ($name) {
            if (! Route::has($name)) {
                return null;
            }
            $uri = Route::getRoutes()->getByName($name)?->uri();

            return $uri !== null && ! str_contains($uri, '{') ? route($name) : null;
        })(),
    };
}

/**
 * The registry: every named GET route with its gate shape and whether a 200
 * must carry exactly one <title>.
 *
 * @return array<string, array{url: string, roles: array<string, int>, title: bool}>
 */
function raidMatrixRoutes(array $w): array
{
    $route = fn (string $name, string $shape = 'public', bool $title = true) => [
        'url' => raidMatrixUrl($name, $w),
        'roles' => raidMatrixShape($shape),
        'title' => $title,
    ];

    return [
        // Public storefront.
        'home' => $route('home'),
        'pages.about' => $route('pages.about'),
        'library.index' => $route('library.index'),
        'library.category' => $route('library.category'),
        'prompts.show' => $route('prompts.show'),
        'prompts.versions' => $route('prompts.versions'),
        'prompts.report.create' => $route('prompts.report.create'),
        'creators.show' => $route('creators.show'),
        'packs.index' => $route('packs.index'),
        'packs.show' => $route('packs.show'),
        'feed.index' => $route('feed.index'),
        // S5 (v1.8.0): the membership storefront is public — guests browse the
        // plan catalog, signed-in visitors also see their own memberships.
        'memberships.index' => $route('memberships.index'),
        // S6 (v1.8.0): the Sikka top-up storefront 404s while the kill-switch
        // is off (the shipped default), so EVERY role gets 404 in this world;
        // SikkaSurfacesTest sweeps the enabled state and the kill-switch.
        'sikka.topup' => ['url' => raidMatrixUrl('sikka.topup', $w), 'title' => false, 'roles' => [
            'guest' => 404, 'member' => 404, 'creator' => 404, 'moderator' => 404, 'admin' => 404, 'banned' => 302, 'impersonator' => 404,
        ]],
        'search.preview' => $route('search.preview', 'public', false),
        'robots' => $route('robots', 'public', false),
        'sitemap' => $route('sitemap', 'public', false),

        // Auth (guest-only) and password reset.
        'login' => $route('login', 'guest'),
        'register' => $route('register', 'guest'),
        'password.request' => $route('password.request', 'guest'),
        'password.sent' => $route('password.sent', 'guest'),
        'password.reset' => $route('password.reset', 'guest'),

        // Authenticated app.
        'dashboard' => $route('dashboard', 'auth'),
        'dashboard.profile.edit' => $route('dashboard.profile.edit', 'auth'),
        'dashboard.prompts.create' => $route('dashboard.prompts.create', 'auth'),
        'dashboard.earnings' => $route('dashboard.earnings', 'auth'),
        'purchases.index' => $route('purchases.index', 'auth'),

        // Per-policy routes (the owner fixture is the creator).
        'dashboard.prompts.edit' => ['url' => raidMatrixUrl('dashboard.prompts.edit', $w), 'title' => true, 'roles' => [
            'guest' => 302, 'member' => 403, 'creator' => 200, 'moderator' => 200, 'admin' => 200, 'banned' => 302, 'impersonator' => 403,
        ]],
        'purchases.download' => ['url' => raidMatrixUrl('purchases.download', $w), 'title' => false, 'roles' => [
            'guest' => 302, 'member' => 403, 'creator' => 200, 'moderator' => 403, 'admin' => 403, 'banned' => 302, 'impersonator' => 403,
        ]],
        'orders.proof.show' => ['url' => raidMatrixUrl('orders.proof.show', $w), 'title' => false, 'roles' => [
            'guest' => 302, 'member' => 403, 'creator' => 200, 'moderator' => 200, 'admin' => 200, 'banned' => 302, 'impersonator' => 403,
        ]],
        'checkout.show' => ['url' => raidMatrixUrl('checkout.show', $w), 'title' => true, 'roles' => [
            'guest' => 302, 'member' => 403, 'creator' => 200, 'moderator' => 200, 'admin' => 200, 'banned' => 302, 'impersonator' => 403,
        ]],
        // The unpaid-rails checkout page (no method chosen yet) — same gate.
        'checkout.show.rails' => ['url' => route('checkout.show', $w['openOrder']), 'title' => true, 'roles' => [
            'guest' => 302, 'member' => 403, 'creator' => 200, 'moderator' => 200, 'admin' => 200, 'banned' => 302, 'impersonator' => 403,
        ]],
        // eSewa is OFF by default; the owner is refused 403, not sent off-site.
        'checkout.esewa.pay' => ['url' => raidMatrixUrl('checkout.esewa.pay', $w), 'title' => true, 'roles' => [
            'guest' => 302, 'member' => 403, 'creator' => 403, 'moderator' => 403, 'admin' => 403, 'banned' => 302, 'impersonator' => 403,
        ]],
        // Legacy bookmark path: staff-only redirect to the admin update page.
        'dashboard.update' => ['url' => raidMatrixUrl('dashboard.update', $w), 'title' => true, 'roles' => [
            'guest' => 302, 'member' => 403, 'creator' => 403, 'moderator' => 302, 'admin' => 302, 'banned' => 302, 'impersonator' => 403,
        ]],

        // Admin perimeter — staff shape.
        'admin.dashboard' => $route('admin.dashboard', 'staff'),
        'admin.prompts.index' => $route('admin.prompts.index', 'staff'),
        'admin.prompts.preview' => $route('admin.prompts.preview', 'staff'),
        'admin.reports.index' => $route('admin.reports.index', 'staff'),
        'admin.users.index' => $route('admin.users.index', 'staff'),
        'admin.orders.index' => $route('admin.orders.index', 'staff'),
        'admin.packs.index' => $route('admin.packs.index', 'staff'),
        'admin.packs.create' => $route('admin.packs.create', 'staff'),
        'admin.packs.edit' => $route('admin.packs.edit', 'staff'),
        'admin.payments.edit' => $route('admin.payments.edit', 'staff'),
        'admin.manual-methods.index' => $route('admin.manual-methods.index', 'staff'),
        'admin.brand.edit' => $route('admin.brand.edit', 'staff'),
        'admin.tool-logos.index' => $route('admin.tool-logos.index', 'staff'),
        'admin.update' => $route('admin.update', 'staff'),

        // Admin-only doors (moderators 403).
        'admin.badges.index' => $route('admin.badges.index', 'admin'),
        'admin.frames.index' => $route('admin.frames.index', 'admin'),
        'admin.security.edit' => $route('admin.security.edit', 'admin'),
        'admin.email.edit' => $route('admin.email.edit', 'admin'),
        'admin.finance' => $route('admin.finance', 'admin'),
        // R5: comp grants are admin-only — the read page must 403 moderators
        // (§6.36: a write-gated feature with an ungated read door).
        'admin.comp-grants.create' => $route('admin.comp-grants.create', 'admin'),
        // S6 (v1.8.0): the Sikka desk is an admin-only door — moderators 403.
        'admin.sikka.index' => $route('admin.sikka.index', 'admin'),
        'admin.finance.payouts.destination' => $route('admin.finance.payouts.destination', 'admin', false),
    ];
}

/** The fixture roles, in sweep order. */
function raidMatrixUsers(array $w): array
{
    return [
        'guest' => null,
        'member' => $w['member'],
        'creator' => $w['owner'],
        'moderator' => $w['mod'],
        'admin' => $w['admin'],
        'banned' => $w['banned'],
    ];
}

test('every named GET route is classified in the role matrix or exempt with a reason', function () {
    $routes = raidMatrixRoutes(raidMatrixWorld());
    $exempt = raidMatrixExemptRoutes();

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if (! $name || ! in_array('GET', $route->methods(), true)) {
            continue;
        }
        // Framework-internal generated names (the /up health endpoint) are
        // not app surfaces.
        if (str_starts_with($name, 'generated::')) {
            continue;
        }

        expect(isset($routes[$name]) || isset($exempt[$name]))
            ->toBeTrue("Named GET route [{$name}] is neither in the role matrix nor exempt — classify it.");
    }
});

test('the matrix sweeps every route for the six direct fixture roles', function () {
    $w = raidMatrixWorld();
    $routes = raidMatrixRoutes($w);
    $checked = 0;

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $name => $spec) {
        expect($spec['url'])->not->toBeNull("route [{$name}] has no URL resolver");

        foreach (raidMatrixUsers($w) as $role => $user) {
            // Distinct client IPs per role: the throttle:6,1 on the password
            // broker would otherwise 429 the seventh sweep.
            $this->withServerVariables(['REMOTE_ADDR' => '10.9.'.array_search($role, array_keys(raidMatrixUsers($w)), true).'.7']);

            $response = $user ? $this->actingAs($user)->get($spec['url']) : $this->get($spec['url']);

            $status = $response->getStatusCode();
            expect($status)->toBe(
                $spec['roles'][$role],
                "route [{$name}] as [{$role}] expected {$spec['roles'][$role]}, got {$status}"
            );
            expect($status)->not->toBe(500, "route [{$name}] as [{$role}] returned 500");

            $body = (string) $response->getContent();

            if ($status === 200 && $spec['title'] && str_contains($response->headers->get('Content-Type', ''), 'html')) {
                expect(substr_count($body, '<title>'))->toBe(1, "route [{$name}] as [{$role}] must serve exactly one <title>");
            }

            if ($status === 200) {
                expect($body)->not->toMatch('/@?\{\{\s*\$[A-Za-z_]/', "route [{$name}] as [{$role}] leaks a Blade expression");
            }

            $checked++;
        }
    }

    expect($checked)->toBe(count($routes) * 6);
});

test('an impersonating admin acts with exactly the target powers and never sees a 500', function () {
    $w = raidMatrixWorld();
    $routes = raidMatrixRoutes($w);
    $checked = 0;

    // Admin switches into the plain member.
    $this->actingAs($w['admin'])->post(route('admin.users.impersonate', $w['member']))->assertRedirect();

    foreach ($routes as $name => $spec) {
        $response = $this->get($spec['url']);

        expect($response->getStatusCode())->toBe(
            $spec['roles']['impersonator'],
            "route [{$name}] while impersonating expected {$spec['roles']['impersonator']}, got {$response->getStatusCode()}"
        );

        if ($response->getStatusCode() === 200) {
            $body = (string) $response->getContent();
            expect($body)->not->toMatch('/@?\{\{\s*\$[A-Za-z_]/', "route [{$name}] leaks a Blade expression while impersonating");
        }

        $checked++;
    }

    expect($checked)->toBe(count($routes));
});
