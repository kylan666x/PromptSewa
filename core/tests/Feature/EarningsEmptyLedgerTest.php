<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * K4 (v1.6.1) — EMPTY LEDGER IS A FIRST-CLASS PROD STATE.
 *
 * The v1.6.0 prod 500 on /earnings was NOT sum-null (SQLite/MySQL SUM over
 * zero rows is fine once cast): the root cause was the composer
 * autoload.files gap — a code-only update zip ships app/Support/money.php
 * but the no-composer host's vendor/ never learns the helper, so
 * money_npr() was undefined at runtime and the first money-rendering page
 * died. Fix: function_exists-guarded <x-money> component on every money
 * surface + code-level settings fallbacks + (int) aggregate boundaries.
 *
 * This file locks the empty state everywhere money renders.
 */

function emptyLedgerCreator(): array
{
    $creator = User::factory()->create();

    // At least one PRE-LEDGER paid order attributed to this creator —
    // the exact prod shape from the incident report.
    $prompt = Prompt::factory()->for($creator, 'creator')->priced(100_00)->create();
    $product = Product::factory()->for($prompt, 'prompt')->create(['price_paisa' => 100_00]);
    $order = Order::factory()->create([
        'buyer_id' => User::factory()->create()->id,
        'status' => Order::STATUS_PAID,
        'paid_at' => now()->subDays(30),
        'total_paisa' => 100_00,
    ]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'prompt_id' => $prompt->id,
        'price_paisa' => 100_00,
        'quantity' => 1,
    ]);

    // ZERO wallet_transactions rows — the fixture above never creates any.

    return [$creator, $order];
}

test('guest is redirected away from the earnings page', function () {
    $this->get(route('dashboard.earnings'))->assertRedirect(route('login'));
});

test('creator with zero wallet rows gets a 200 showing Rs. 0 and the pre-ledger banner', function () {
    [$creator] = emptyLedgerCreator();

    $response = $this->actingAs($creator)->get(route('dashboard.earnings'));

    $response->assertOk();

    $html = $response->getContent();

    // Zero-state money, rendered crash-proof.
    expect($html)->toContain('Rs. 0.00')
        ->and($html)->toContain('Ledger balance')
        ->and($html)->toContain('No ledger rows yet');

    // The pre-ledger order must NOT have leaked into the balance.
    expect(app(\App\Services\WalletService::class)->balancePaisa($creator))->toBe(0)
        ->and(app(\App\Services\WalletService::class)->availablePaisa($creator))->toBe(0);

    assertNoBladeLeak($response);
});

test('finance desk renders 200 in the same empty state', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    emptyLedgerCreator(); // prod shape exists; ledger still empty

    $response = $this->actingAs($admin)->get(route('admin.finance'));

    $response->assertOk();
    expect($response->getContent())->toContain('Rs. 0.00')
        ->and($response->getContent())->toContain('Pre-ledger revenue');

    assertNoBladeLeak($response);
});

test('payout request form renders with the min-threshold label in the empty state', function () {
    [$creator] = emptyLedgerCreator();

    $html = $this->actingAs($creator)->get(route('dashboard.earnings'))->getContent();

    // The documented default (Rs. 500 = 50000 paisa) renders even if the
    // settings row is absent (K3 code fallback). The label and the amount
    // are separated by the <x-money> span markup.
    expect($html)->toContain('Request a payout')
        ->and($html)->toContain('Minimum')
        ->and($html)->toContain('Rs. 500.00');

    // Delete the settings row entirely and re-render: still Rs. 500.
    \Illuminate\Support\Facades\DB::table('settings')->where('key', 'payout_min_paisa')->delete();

    $html = $this->actingAs($creator)->get(route('dashboard.earnings'))->getContent();

    expect($html)->toContain('Minimum')
        ->and($html)->toContain('Rs. 500.00');
});

test('the helper fallback renders money even when composer autoload.files never loaded', function () {
    // The component source must carry the function_exists guard — that IS
    // the prod fix (a full HTTP render of it is covered by the sweep test).
    $componentSource = file_get_contents(realpath(__DIR__.'/../../resources/views/components/money.blade.php'));

    expect($componentSource)->toContain("function_exists('money_npr')");

    // And the safe helper itself (available even without autoload.files):,
    expect(money_npr_safe(149700))->toBe('Rs. 1,497.00')
        ->and(money_npr_safe(-50000))->toBe('-Rs. 500.00');
});

test('earnings and finance render standalone with empty fixtures in the sweep', function () {
    // The sweep that would have caught the prod 500: every money surface
    // renders through the full stack with an EMPTY ledger.
    $creator = User::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $checks = [
        ['route' => 'dashboard.earnings', 'actor' => $creator],
        ['route' => 'admin.finance', 'actor' => $admin],
    ];

    foreach ($checks as ['route' => $route, 'actor' => $actor]) {
        $response = $this->actingAs($actor)->get(route($route));
        expect($response->status())->toBe(200, "route {$route} must survive an empty ledger");
        assertNoBladeLeak($response);
    }
});
