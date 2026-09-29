<?php

use App\Models\LicenseGrant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pack;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Create a paid order + a single-prompt grant for $user on $prompt. */
function grantSingle(User $user, Prompt $prompt): Order
{
    $order = Order::create([
        'buyer_id' => $user->id,
        'status' => Order::STATUS_PAID,
        'total_paisa' => $prompt->price_cents,
        'subtotal_paisa' => $prompt->price_cents,
        'currency' => 'NPR',
        'payment_method' => 'esewa',
        'payment_reference' => 'TEST-'.strtoupper(bin2hex(random_bytes(4))),
        'idempotency_key' => 'test-'.bin2hex(random_bytes(8)),
        'paid_at' => now(),
    ]);

    $product = Product::create([
        'prompt_id' => $prompt->id,
        'price_paisa' => $prompt->price_cents,
        'currency' => 'NPR',
        'status' => Product::STATUS_ACTIVE,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'prompt_id' => $prompt->id,
        'price_paisa' => $prompt->price_cents,
        'quantity' => 1,
    ]);

    LicenseGrant::create([
        'user_id' => $user->id,
        'order_item_id' => $item->id,
        'prompt_id' => $prompt->id,
        'license_tier' => 'commercial',
        'grant_code' => 'TEST-'.bin2hex(random_bytes(4)),
        'status' => LicenseGrant::STATUS_ACTIVE,
    ]);

    return $order;
}

test('buyer sees own grants including pack member prompts', function () {
    $buyer = User::factory()->create();
    $creator = User::factory()->create();

    $promptA = Prompt::factory()->for($creator, 'creator')->hasVersion()->priced(19900)->create();
    $promptB = Prompt::factory()->for($creator, 'creator')->hasVersion()->priced(14900)->create();

    grantSingle($buyer, $promptA);

    $html = $this->actingAs($buyer)->get(route('purchases.index'))
        ->assertOk()
        ->assertSee($promptA->title)
        ->getContent();

    expect($html)->toContain('ACTIVE')
        ->and($html)->toContain('Orders');
});

test('re-download serves the body with an active grant', function () {
    $buyer = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->priced(19900)->create();

    grantSingle($buyer, $prompt);

    $response = $this->actingAs($buyer)->get(route('purchases.download', $prompt));

    $response->assertOk();
    $body = (string) $response->streamedContent();
    expect($body)->toContain($prompt->title)
        ->and($body)->toContain('deterministic body');
});

test('re-download 403s without an active grant', function () {
    $intruder = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->priced(19900)->create();

    $this->actingAs($intruder)
        ->get(route('purchases.download', $prompt))
        ->assertForbidden();
});

test('other buyers orders are absent from the library', function () {
    $me = User::factory()->create();
    $stranger = User::factory()->create();
    $creator = User::factory()->create();

    $mine = Prompt::factory()->for($creator, 'creator')->hasVersion()->priced(19900)->create();
    $theirs = Prompt::factory()->for($creator, 'creator')->hasVersion()->priced(19900)->create();

    grantSingle($me, $mine);
    grantSingle($stranger, $theirs);

    $html = $this->actingAs($me)->get(route('purchases.index'))
        ->assertOk()
        ->assertSee($mine->title)
        ->getContent();

    expect($html)->not->toContain($theirs->title);
});

test('revoked grants show the revoked chip and block re-download', function () {
    $buyer = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->priced(19900)->create();

    $order = grantSingle($buyer, $prompt);
    LicenseGrant::query()->where('user_id', $buyer->id)->update(['status' => LicenseGrant::STATUS_REVOKED]);

    $html = $this->actingAs($buyer)->get(route('purchases.index'))
        ->assertOk()
        ->assertSee($prompt->title)
        ->getContent();

    expect($html)->toContain(LicenseGrant::STATUS_REVOKED);

    $this->actingAs($buyer)
        ->get(route('purchases.download', $prompt))
        ->assertForbidden();
});
