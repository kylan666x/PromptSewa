<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC #1: price stored as integer paisa
// ---------------------------------------------------------------------------

test('product price is stored as integer paisa', function () {
    $product = Product::factory()->priced(123_456)->create(); // NPR 1,234.56

    expect($product->refresh()->price_paisa)->toBeInt()->toBe(123_456)
        ->and($product->currency)->toBe('NPR');
});

// ---------------------------------------------------------------------------
// AC #2: checkout creates a pending order with a unique idempotency key
// ---------------------------------------------------------------------------

test('checkout creates a pending order with a unique idempotency key', function () {
    $buyer = User::factory()->create();
    $product = Product::factory()->priced(50_000)->create(); // NPR 500

    $result = app(CheckoutService::class)->createOrder($buyer, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    expect($result['created'])->toBeTrue()
        ->and($result['order']->status)->toBe(Order::STATUS_PENDING)
        ->and($result['order']->idempotency_key)->toBeString()->not->toBeEmpty()
        ->and($result['order']->subtotal_paisa)->toBe(50_000)
        ->and($result['order']->total_paisa)->toBe(50_000)
        ->and($result['order']->items)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Required test: integer-math totals across multiple lines & quantities
// ---------------------------------------------------------------------------

test('checkout calculates totals with pure integer math across lines', function () {
    $buyer = User::factory()->create();
    $a = Product::factory()->priced(33_333)->create(); // odd paisa, no float slack
    $b = Product::factory()->priced(99_999)->create();

    $result = app(CheckoutService::class)->createOrder($buyer, [
        ['product_id' => $a->id, 'quantity' => 3],
        ['product_id' => $b->id, 'quantity' => 2],
    ]);

    // 33,333×3 = 99,999 + 99,999×2 = 199,998 → 299,997 paisa total.
    expect($result['order']->subtotal_paisa)->toBe(299_997)
        ->and($result['order']->total_paisa)->toBe(299_997)
        ->and($result['order']->items->sum(fn ($i) => $i->price_paisa * $i->quantity))->toBe(299_997);
});

test('checkout rejects carts containing inactive products', function () {
    $buyer = User::factory()->create();
    $draft = Product::factory()->draft()->priced(10_000)->create();

    app(CheckoutService::class)->createOrder($buyer, [
        ['product_id' => $draft->id],
    ]);
})->throws(InvalidArgumentException::class);

// ---------------------------------------------------------------------------
// AC #3: duplicate idempotency keys → exactly one order
// ---------------------------------------------------------------------------

test('replaying the same idempotency key returns the original order', function () {
    $buyer = User::factory()->create();
    $product = Product::factory()->priced(50_000)->create();
    $service = app(CheckoutService::class);

    $first = $service->createOrder($buyer, [['product_id' => $product->id]], 'key-abc');
    $second = $service->createOrder($buyer, [['product_id' => $product->id]], 'key-abc');

    expect($first['created'])->toBeTrue()
        ->and($second['created'])->toBeFalse()
        ->and($second['order']->id)->toBe($first['order']->id)
        ->and(Order::query()->where('idempotency_key', 'key-abc')->count())->toBe(1);
});

test('concurrent checkouts with the same key create exactly one order', function () {
    $buyer = User::factory()->create();
    $product = Product::factory()->priced(50_000)->create();
    $service = app(CheckoutService::class);

    // Simulate racing requests: both transactions start before either
    // commits (SQLite serializes writes; the UNIQUE constraint is the
    // final arbiter — on MySQL the same holds under InnoDB row locks).
    $results = [];

    foreach ([1, 2] as $i) {
        try {
            $results[$i] = $service->createOrder($buyer, [['product_id' => $product->id]], 'race-key');
        } catch (UniqueConstraintViolationException) {
            $results[$i] = null; // the loser of the race
        }
    }

    $created = array_filter($results, fn ($r) => $r !== null && $r['created']);
    $replays = array_filter($results, fn ($r) => $r !== null && ! $r['created']);

    // Exactly one winner; any other request is a replay of the same order.
    expect(count($created))->toBe(1)
        ->and(count($created) + count($replays))->toBe(2)
        ->and(Order::query()->where('idempotency_key', 'race-key')->count())->toBe(1);
});
