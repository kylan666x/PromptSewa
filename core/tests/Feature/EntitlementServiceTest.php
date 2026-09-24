<?php

use App\Models\LicenseGrant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC #4: a pending order grants nothing
// ---------------------------------------------------------------------------

test('pending orders cannot be fulfilled', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->for($buyer, 'buyer')->create(); // pending

    app(EntitlementService::class)->fulfill($order);
})->throws(DomainException::class);

test('no license grants exist before payment confirmation', function () {
    Order::factory()->has(OrderItem::factory()->count(2), 'items')->create();

    expect(LicenseGrant::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC #5: confirmed payment → grants → access
// ---------------------------------------------------------------------------

test('fulfilling a paid order issues one grant per item', function () {
    $buyer = User::factory()->create();
    $productA = Product::factory()->priced(50_000)->create();
    $productB = Product::factory()->priced(75_000)->create();

    $order = Order::factory()->for($buyer, 'buyer')->paid()->create();
    $order->items()->createMany([
        ['product_id' => $productA->id, 'prompt_id' => $productA->prompt_id, 'price_paisa' => 50_000, 'currency' => 'NPR', 'quantity' => 1],
        ['product_id' => $productB->id, 'prompt_id' => $productB->prompt_id, 'price_paisa' => 75_000, 'currency' => 'NPR', 'quantity' => 1],
    ]);

    app(EntitlementService::class)->fulfill($order);

    expect($order->refresh()->items->map(fn (OrderItem $i) => $i->licenseGrant()->exists()))
        ->each->toBeTrue()
        ->and(LicenseGrant::where('user_id', $buyer->id)->count())->toBe(2)
        ->and(LicenseGrant::query()->whereNotNull('grant_code')->count())->toBe(2);
});

test('fulfillment is idempotent — replay grants nothing extra', function () {
    $order = Order::factory()->paid()->create();
    $product = Product::factory()->priced(50_000)->create();
    $order->items()->create([
        'product_id' => $product->id, 'prompt_id' => $product->prompt_id,
        'price_paisa' => 50_000, 'currency' => 'NPR', 'quantity' => 1,
    ]);

    $service = app(EntitlementService::class);
    $service->fulfill($order);
    $service->fulfill($order); // webhook replay

    expect(LicenseGrant::count())->toBe(1);
});

test('revoked grants remain in the ledger of record', function () {
    $grant = LicenseGrant::factory()->create();
    $grant->revoke();

    expect($grant->refresh()->status)->toBe(LicenseGrant::STATUS_REVOKED)
        ->and($grant->revoked_at)->not->toBeNull()
        ->and(LicenseGrant::count())->toBe(1); // soft-existence: revoked, never deleted
});

// ---------------------------------------------------------------------------
// Required test: authorization — buyers cannot view others' orders/grants
// ---------------------------------------------------------------------------

test('buyers cannot view other buyers orders', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $order = Order::factory()->for($owner, 'buyer')->create();

    expect($intruder->can('view', $order))->toBeFalse()
        ->and($owner->can('view', $order))->toBeTrue();
});

test('buyers cannot view other buyers entitlements', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $grant = LicenseGrant::factory()->for($owner, 'user')->create();

    expect($intruder->can('view', $grant))->toBeFalse()
        ->and($owner->can('view', $grant))->toBeTrue();
});

test('moderators may view any order for dispute support', function () {
    $buyer = User::factory()->create();
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $order = Order::factory()->for($buyer, 'buyer')->create();

    expect($mod->can('view', $order))->toBeTrue();
});
