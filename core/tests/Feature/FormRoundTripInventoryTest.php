<?php

use App\Models\Badge;
use App\Models\Frame;
use App\Models\ManualPaymentMethod;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\Setting;
use App\Models\ToolLogo;
use App\Models\User;
use App\Models\UserFrameUnlock;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * R3 (v1.7.7 Bug Hunt Raid) — form round-trip inventory.
 *
 * RenderedFormOrderTest proves the verb spoof; this file proves the other
 * half: GET the page, take the SERVER-SERVED fields out of the form, submit
 * them with only the minimum overrides a human would type, and assert
 * persistence or the intended refusal. Payloads are never hand-built.
 *
 * `raidFormInventory()` is the catalog's table, and the completeness test
 * scans every Blade <form> so a new form cannot skip classification.
 */

/**
 * Extract the served fields of the form whose action matches $needle.
 *
 * @return array{verb: string, fields: array<string, string>, files: list<string>, action: string}
 */
function raidFormExtract(string $html, string $needle, ?string $requireVerb = null): array
{
    // Quoted attribute values may contain `>` (e.g. `$pack->exists` in a
    // Blade expression) — consuming whole quoted strings keeps the tag open.
    preg_match_all('/<form\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>(.*?)<\/form>/is', $html, $forms, PREG_SET_ORDER);

    foreach ($forms as $form) {
        $attrs = $form[1];
        $body = $form[2];

        if (! preg_match('/action="([^"]*)"/i', $attrs, $a) || ! str_contains(html_entity_decode($a[1]), $needle)) {
            continue;
        }

        $verb = 'GET';
        if (preg_match('/method="([^"]*)"/i', $attrs, $m)) {
            $verb = strtoupper($m[1]);
        }

        $fields = [];
        $files = [];

        preg_match_all('/<input\b([^>]*)>/i', $body, $inputs, PREG_SET_ORDER);
        foreach ($inputs as $input) {
            $tag = $input[1];
            if (! preg_match('/name="([^"]*)"/i', $tag, $n)) {
                continue;
            }
            $name = $n[1];
            $type = preg_match('/type="([^"]*)"/i', $tag, $t) ? strtolower($t[1]) : 'text';

            if ($type === 'file') {
                $files[] = $name;
                continue;
            }
            if (in_array($type, ['checkbox', 'radio'], true) && ! preg_match('/\bchecked\b/i', $tag)) {
                continue;
            }
            if ($type === 'submit' || $type === 'button') {
                continue;
            }
            $fields[$name] = preg_match('/value="([^"]*)"/i', $tag, $v) ? html_entity_decode($v[1]) : '';
        }

        preg_match_all('/<textarea\b([^>]*)>(.*?)<\/textarea>/is', $body, $areas, PREG_SET_ORDER);
        foreach ($areas as $area) {
            if (preg_match('/name="([^"]*)"/i', $area[1], $n)) {
                $fields[$n[1]] = html_entity_decode(trim($area[2]));
            }
        }

        preg_match_all('/<select\b([^>]*)>(.*?)<\/select>/is', $body, $selects, PREG_SET_ORDER);
        foreach ($selects as $select) {
            if (! preg_match('/name="([^"]*)"/i', $select[1], $n)) {
                continue;
            }

            // Browser semantics: the option carrying `selected` wins, else the
            // first option is what a real submission sends.
            preg_match_all('/<option\b([^>]*)>([^<]*)</i', $select[2], $options, PREG_SET_ORDER);
            $chosen = null;
            foreach ($options as $option) {
                if (preg_match('/\bselected\b/i', $option[1]) || $chosen === null) {
                    $chosen = $option;
                }
                if (preg_match('/\bselected\b/i', $option[1])) {
                    break;
                }
            }

            $fields[$n[1]] = $chosen === null
                ? ''
                : html_entity_decode(preg_match('/value="([^"]*)"/i', $chosen[1], $vv) ? $vv[1] : trim($chosen[2]));
        }

        $effective = $verb;
        if ($verb === 'POST' && isset($fields['_method'])) {
            $effective = strtoupper($fields['_method']);
        }

        if ($requireVerb !== null && $effective !== strtoupper($requireVerb)) {
            continue; // same action URL may host PUT update and DELETE destroy forms
        }

        return ['verb' => $effective, 'fields' => $fields, 'files' => $files, 'action' => html_entity_decode($a[1])];
    }

    throw new RuntimeException("No served form with action containing [{$needle}] found.");
}

/** Submit an extracted form as the given user, with minimum overrides. */
function raidFormSubmit($test, ?User $user, array $form, array $overrides = [], array $fileOverrides = [])
{
    $payload = array_merge($form['fields'], $overrides);

    foreach ($fileOverrides as $name => $file) {
        $payload[$name] = $file;
    }

    $request = $user ? $test->actingAs($user) : $test;

    return match ($form['verb']) {
        'PUT' => $request->put($form['action'], $payload),
        'PATCH' => $request->patch($form['action'], $payload),
        'DELETE' => $request->delete($form['action'], $payload),
        default => $request->post($form['action'], $payload),
    };
}

/**
 * Every route targeted by a Blade `<form action="route('…')">`, with the
 * view files that serve it. Shared by the inventory completeness test and
 * the F4 orphan-write rule, so both read the same source of truth.
 *
 * @return array<string, list<string>> route name => view files
 */
function raidBladeFormTargets(): array
{
    $found = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $content = (string) file_get_contents($file->getPathname());

        // Quoted attribute values may contain `>` (e.g. `$pack->exists` in a
        // Blade expression); `[^>]*` truncated the tag there and silently
        // skipped the pack forms — the F4 rule caught it.
        if (! preg_match_all('/<form\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/is', $content, $tags)) {
            continue;
        }

        // Only the ACTION attribute is inventoried. Routes that appear inside
        // a form body (links, JS data) are not form targets — the first run of
        // this test "found" login/register/prompts.show inside other forms.
        foreach ($tags[1] as $attrs) {
            if (! preg_match('/action="([^"]*)"/i', $attrs, $actionAttr)) {
                continue; // GET form posting to the current URL
            }
            if (! preg_match_all("/route\\(\\s*'([^']+)'/", $actionAttr[1], $routes)) {
                continue; // dynamic action (e.g. the external eSewa gateway form)
            }
            foreach ($routes[1] as $routeName) {
                $found[$routeName][] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    return $found;
}

/**
 * F4 (v1.7.8) — write routes with no `<form>` but a documented fetch.
 * Each entry names the owning test that exercises the endpoint.
 *
 * @return array<string, string>
 */
function raidDocumentedFetches(): array
{
    return [
        'bookmarks.toggle' => 'Alpine bookmarkHeart() fetch on every heart surface — BookmarkTest.',
        'admin.security.test-email' => 'Alpine fetch() (JSON) in admin/security.blade.php — DisposableEmailTest.',
    ];
}

/**
 * F4 (v1.7.8) — write routes with no browser surface by design. Every
 * exemption carries a reason; a stale entry fails the rule.
 *
 * @return array<string, string>
 */
function raidWriteRouteExemptions(): array
{
    return [
        'admin.badges.update' => 'No rendered edit form (badge edits ship as store/destroy only) — audited against the served HTML.',
        'checkout.esewa.verify' => 'eSewa gateway success_url POST — the gateway posts here, never a browser form.',
        'payments.esewa.webhook' => 'External gateway webhook POST — no browser surface; settlement mutations are locked by WalletServiceTest.',
        'storage.local.upload' => 'Framework signed-URL upload route for the local private disk (`serve => true`) — framework-owned, never app-advertised (the F5 proofs fix exposed it: same-URI framework routes shadow each other).',
    ];
}

/** The catalog inventory: every form action route and what locks it. */
function raidFormInventory(): array
{
    return [
        'login.store' => 'AuthTest',
        'register.store' => 'SignupHardeningTest',
        'password.email' => 'PasswordResetTest',
        'password.update' => 'PasswordResetTest',
        'library.index' => 'GET search rail — SearchTest',
        'logout' => 'AuthTest',
        'dashboard.profile.update' => 'SearchPreviewTest (profile update)',
        'dashboard.prompts.store' => 'PromptCreateEditTest',
        'dashboard.prompts.update' => 'PromptCreateEditTest',
        'dashboard.earnings.request' => 'FormRoundTripInventoryTest (R3)',
        'dashboard.earnings.cancel' => 'FormRoundTripInventoryTest (R3)',
        'prompts.rate' => 'AdminFlowsTest',
        'prompts.report.store' => 'PromptReportTest',
        'prompts.versions.restore' => 'CatalogAndVersionTruthTest',
        'checkout.prompts.buy' => 'FormRoundTripInventoryTest (R3)',
        'checkout.packs.buy' => 'CheckoutFlowTest',
        'checkout.manual.submit' => 'ManualPaymentMethodsTest',
        'orders.proof.store' => 'ManualPaymentMethodsTest',
        'admin.users.impersonate' => 'ImpersonationTest',
        'admin.users.role' => 'CheckoutFlowTest (role change)',
        'admin.users.verified' => 'AdminFlowsTest',
        'admin.users.banned' => 'UserBanTest',
        'admin.users.purge.preview' => 'FormRoundTripInventoryTest (R3)',
        'admin.users.purge.run' => 'FormRoundTripInventoryTest (R3)',
        'admin.users.adopt.preview' => 'FormRoundTripInventoryTest (R3)',
        'admin.users.adopt.run' => 'FormRoundTripInventoryTest (R3)',
        'admin.prompts.status' => 'AdminReviewTest',
        'admin.reports.status' => 'AdminFlowsTest',
        'admin.packs.store' => 'AdminFlowsTest',
        'admin.packs.update' => 'AdminFlowsTest',
        'admin.packs.destroy' => 'AdminFlowsTest',
        'admin.tool-logos.store' => 'AdminFlowsTest',
        'admin.tool-logos.update' => 'AdminFlowsTest',
        'admin.tool-logos.destroy' => 'AdminFlowsTest',
        'admin.payments.update' => 'CheckoutFlowTest',
        'admin.brand.update' => 'BrandSettingsTest / BrandLogoTest',
        'admin.security.update' => 'FormRoundTripInventoryTest (R3)',
        'admin.email.update' => 'MailConfigTest',
        'admin.email.test' => 'MailConfigTest',
        'admin.finance' => 'GET ledger filter — FinanceController tests',
        'admin.finance.payouts.approve' => 'FormRoundTripInventoryTest (R3)',
        'admin.finance.payouts.settle' => 'FormRoundTripInventoryTest (R3)',
        'admin.finance.payouts.reject' => 'FormRoundTripInventoryTest (R3)',
        'admin.badges.store' => 'FormRoundTripInventoryTest (R3)',
        'admin.badges.award' => 'GamificationTest',
        'admin.badges.destroy' => 'FormRoundTripInventoryTest (R3)',
        'admin.badges.scan' => 'BadgeCriteriaTest (F3)',
        'admin.frames.store' => 'FormRoundTripInventoryTest (R3)',
        'admin.frames.update' => 'FrameTruthTest',
        'admin.frames.destroy' => 'FormRoundTripInventoryTest (R3)',
        'admin.frames.award' => 'FrameTruthTest',
        'admin.frames.unlocks.revoke' => 'FormRoundTripInventoryTest (R3)',
        'admin.manual-methods.store' => 'ManualPaymentMethodsTest',
        'admin.manual-methods.update' => 'ManualPaymentMethodsTest',
        'admin.manual-methods.destroy' => 'ManualPaymentMethodsTest',
        'admin.orders.approve' => 'CheckoutFlowTest',
        'admin.orders.reject' => 'AdminFlowsTest',
        'admin.comp-grants.store' => 'CompGrantTest',
        'admin.comp-grants.create' => 'GET search rail — this file + R5 test',
        'admin.update.run' => 'AdminFlowsTest / UpdaterFailureRecordTest',
        'impersonation.stop' => 'ImpersonationTest',
    ];
}

test('every route targeted by a Blade form is classified in the form inventory', function () {
    $found = raidBladeFormTargets();

    expect($found)->not->toBeEmpty();

    $unclassified = array_diff(array_keys($found), array_keys(raidFormInventory()));
    expect($unclassified)->toBe([], 'forms target routes missing from the inventory: '.implode(', ', $unclassified));
});

test('every write route is reachable from a served form or documented as a fetch/exemption', function () {
    $formTargets = raidBladeFormTargets();
    $fetches = raidDocumentedFetches();
    $exemptions = raidWriteRouteExemptions();

    // The full write surface, straight from the router: a new POST/PUT/
    // PATCH/DELETE route is unclassified until it names its reachability —
    // the BH-R6-01 class (an advertised action with no reachable door) can
    // no longer ship silently.
    $writeRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
        ->map(fn ($route) => (string) $route->getName())
        ->reject(fn (string $name) => $name === '' || str_starts_with($name, 'generated::'))
        ->unique()
        ->values();

    expect($writeRoutes)->not->toBeEmpty();

    $unclassified = $writeRoutes
        ->reject(fn (string $name) => array_key_exists($name, $formTargets)
            || array_key_exists($name, $fetches)
            || array_key_exists($name, $exemptions))
        ->values();

    expect($unclassified->all())->toBe([], 'write routes with no reachable door — serve a form, document the fetch, or exempt with a reason: '.$unclassified->implode(', '));

    // Stale classifications are as dangerous as missing ones: an exemption
    // for a route that no longer exists would silently rot.
    $stale = collect(array_keys($fetches + $exemptions))
        ->reject(fn (string $name) => $writeRoutes->contains($name))
        ->values();

    expect($stale->all())->toBe([], 'fetch/exemption map entries with no matching write route: '.$stale->implode(', '));
});

test('badge create + delete round-trips through the served forms', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $html = $this->actingAs($admin)->get(route('admin.badges.index'))->assertOk()->getContent();
    $form = raidFormExtract($html, '/admin/badges');

    raidFormSubmit($this, $admin, $form, [
        'name' => 'Raid Roundtrip Badge',
        'slug' => 'raid-roundtrip-badge',
        'description' => 'Created by the R3 round-trip.',
        // criterion comes from the served select (its default is guaranteed valid)
    ])->assertRedirect();

    $badge = Badge::query()->where('slug', 'raid-roundtrip-badge')->sole();

    $html = $this->actingAs($admin)->get(route('admin.badges.index'))->assertOk()->getContent();
    $destroy = raidFormExtract($html, route('admin.badges.destroy', $badge), 'DELETE');

    raidFormSubmit($this, $admin, $destroy)->assertRedirect();

    expect(Badge::query()->whereKey($badge->id)->exists())->toBeFalse();
});

test('frame create + delete and award + revoke round-trip through the served forms', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $member = User::factory()->create();

    $html = $this->actingAs($admin)->get(route('admin.frames.index'))->assertOk()->getContent();
    $form = raidFormExtract($html, '/admin/frames');

    raidFormSubmit($this, $admin, $form, [
        'name' => 'Raid Roundtrip Frame',
        'hole_percent' => 62,
    ], [
        'image' => UploadedFile::fake()->image('raid-ring.png', 128, 128),
    ])->assertRedirect();

    $frame = Frame::query()->where('name', 'Raid Roundtrip Frame')->sole();

    $html = $this->actingAs($admin)->get(route('admin.frames.index'))->assertOk()->getContent();
    $award = raidFormExtract($html, '/admin/frames/award');
    raidFormSubmit($this, $admin, $award, [
        'frame_id' => $frame->id,
        'user_id' => $member->id,
        'reason' => 'R3 round-trip award',
    ])->assertRedirect();

    $unlock = UserFrameUnlock::query()->where('user_id', $member->id)->where('frame_id', $frame->id)->sole();

    $html = $this->actingAs($admin)->get(route('admin.frames.index'))->assertOk()->getContent();
    $revoke = raidFormExtract($html, route('admin.frames.unlocks.revoke', $unlock), 'DELETE');
    raidFormSubmit($this, $admin, $revoke)->assertRedirect();
    expect(UserFrameUnlock::query()->whereKey($unlock->id)->exists())->toBeFalse();

    $html = $this->actingAs($admin)->get(route('admin.frames.index'))->assertOk()->getContent();
    $destroy = raidFormExtract($html, route('admin.frames.destroy', $frame), 'DELETE');
    raidFormSubmit($this, $admin, $destroy)->assertRedirect();
    expect(Frame::query()->whereKey($frame->id)->exists())->toBeFalse();
});

test('payout request + cancel round-trip through the earnings forms', function () {
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    WalletTransaction::query()->create([
        'user_id' => $creator->id,
        'type' => WalletTransaction::TYPE_ADJUSTMENT,
        'amount_paisa' => 100_00,
        'idempotency_key' => 'adjustment:raid-r3',
        'meta' => ['reason' => 'R3 seed'],
        'created_at' => now(),
    ]);
    Setting::query()->updateOrCreate(['key' => 'payout_min_paisa'], ['value' => '2000']);

    $html = $this->actingAs($creator)->get(route('dashboard.earnings'))->assertOk()->getContent();
    $form = raidFormExtract($html, route('dashboard.earnings.request'));
    raidFormSubmit($this, $creator, $form, [
        'amount_npr' => 40,
        'method' => Payout::METHOD_ESEWA_WALLET,
        'destination' => '9800000000',
    ])->assertRedirect();

    $payout = Payout::query()->where('user_id', $creator->id)->sole();
    expect($payout->status)->toBe(Payout::STATUS_REQUESTED);

    $html = $this->actingAs($creator)->get(route('dashboard.earnings'))->assertOk()->getContent();
    $cancel = raidFormExtract($html, route('dashboard.earnings.cancel', $payout));
    raidFormSubmit($this, $creator, $cancel)->assertRedirect();

    expect($payout->refresh()->status)->toBe(Payout::STATUS_CANCELLED);
});

test('checkout buy-prompt round-trips through the served buy form', function () {
    $seller = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->for($seller, 'creator')->withVersion()->create([
        'status' => Prompt::STATUS_PUBLISHED,
        'price_cents' => 24_900,
    ]);
    Product::factory()->create(['prompt_id' => $prompt->id]);

    $buyer = User::factory()->create();

    $html = $this->actingAs($buyer)->get(route('prompts.show', $prompt))->assertOk()->getContent();
    $form = raidFormExtract($html, route('checkout.prompts.buy', $prompt));

    raidFormSubmit($this, $buyer, $form)->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->sole();
    expect($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->items()->count())->toBe(1);
});

test('finance approve + settle + reject round-trip through the served forms', function () {
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    WalletTransaction::query()->create([
        'user_id' => $creator->id,
        'type' => WalletTransaction::TYPE_ADJUSTMENT,
        'amount_paisa' => 200_00,
        'idempotency_key' => 'adjustment:raid-r3-finance',
        'meta' => ['reason' => 'R3 seed'],
        'created_at' => now(),
    ]);
    Setting::query()->updateOrCreate(['key' => 'payout_min_paisa'], ['value' => '2000']);

    $wallet = app(WalletService::class);
    $toApprove = $wallet->requestPayout($creator, 40_00, Payout::METHOD_ESEWA_WALLET, '9800000000');
    $toReject = $wallet->requestPayout($creator, 40_00, Payout::METHOD_ESEWA_WALLET, '9800000000');

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $html = $this->actingAs($admin)->get(route('admin.finance'))->assertOk()->getContent();

    $approve = raidFormExtract($html, route('admin.finance.payouts.approve', $toApprove));
    raidFormSubmit($this, $admin, $approve)->assertRedirect();
    expect($toApprove->refresh()->status)->toBe(Payout::STATUS_APPROVED);

    $html = $this->actingAs($admin)->get(route('admin.finance'))->assertOk()->getContent();
    $settle = raidFormExtract($html, route('admin.finance.payouts.settle', $toApprove));
    raidFormSubmit($this, $admin, $settle)->assertRedirect();
    expect($toApprove->refresh()->status)->toBe(Payout::STATUS_SETTLED);

    $html = $this->actingAs($admin)->get(route('admin.finance'))->assertOk()->getContent();
    $reject = raidFormExtract($html, route('admin.finance.payouts.reject', $toReject));
    raidFormSubmit($this, $admin, $reject, ['note' => 'R3 reject'])->assertRedirect();
    expect($toReject->refresh()->status)->toBe(Payout::STATUS_REJECTED);
});

test('admin security settings round-trip through the served form', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $html = $this->actingAs($admin)->get(route('admin.security.edit'))->assertOk()->getContent();
    $form = raidFormExtract($html, route('admin.security.update'));

    raidFormSubmit($this, $admin, $form, [
        'blocked_domains_extra' => "raid-block.test\nsecond-block.test",
    ])->assertRedirect();

    expect(Setting::query()->where('key', 'blocked_domains_extra')->value('value'))
        ->toBe("raid-block.test\nsecond-block.test");
});

test('purge preview and adopt preview round-trip through the served admin forms', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    ToolLogo::query()->create(['name' => 'R3 Tool', 'is_active' => true, 'position' => 1]);
    ManualPaymentMethod::query()->create(['name' => 'R3 Method', 'kind' => 'other', 'position' => 1, 'active' => true]);

    $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();

    $purge = raidFormExtract($html, route('admin.users.purge.preview'));
    raidFormSubmit($this, $admin, $purge)->assertRedirect()->assertSessionHas('purge_preview');

    $adopt = raidFormExtract($html, route('admin.users.adopt.preview'));
    raidFormSubmit($this, $admin, $adopt)->assertRedirect()->assertSessionHas('adoption_preview');
});
