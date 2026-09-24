<?php

use App\Models\LicenseGrant;
use App\Models\Order;
use App\Models\Pack;
use App\Models\Prompt;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Admin panel access control
// ---------------------------------------------------------------------------

test('guests are redirected from the admin panel', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('members cannot open the admin panel', function () {
    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);

    $this->actingAs($member)->get('/admin')->assertForbidden();
});

test('moderators can open the admin panel', function () {
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);

    $this->actingAs($mod)->get('/admin')->assertOk();
});

test('only admins can change user roles', function () {
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $target = User::factory()->create(['role' => User::ROLE_MEMBER]);

    $this->actingAs($mod)
        ->patch(route('admin.users.role', $target), ['role' => User::ROLE_ADMIN])
        ->assertForbidden();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)
        ->patch(route('admin.users.role', $target), ['role' => User::ROLE_CREATOR])
        ->assertRedirect();

    expect($target->refresh()->role)->toBe(User::ROLE_CREATOR);
});

test('an admin cannot change their own role', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->patch(route('admin.users.role', $admin), ['role' => User::ROLE_MEMBER])
        ->assertSessionHasErrors('role');

    expect($admin->refresh()->role)->toBe(User::ROLE_ADMIN);
});

// ---------------------------------------------------------------------------
// Prompt moderation
// ---------------------------------------------------------------------------

test('admins can publish pending prompts', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prompt = Prompt::factory()->create(['status' => Prompt::STATUS_PENDING]);

    $this->actingAs($admin)
        ->patch(route('admin.prompts.status', $prompt), ['status' => Prompt::STATUS_PUBLISHED])
        ->assertRedirect();

    expect($prompt->refresh()->status)->toBe(Prompt::STATUS_PUBLISHED);
});

// ---------------------------------------------------------------------------
// Pack ordering + fulfillment
// ---------------------------------------------------------------------------

test('buying a pack creates a pending order with a pack line', function () {
    $buyer = User::factory()->create();
    $pack = Pack::factory()->create(['price_paisa' => 49_900]);

    $this->actingAs($buyer)
        ->post(route('checkout.packs.buy', $pack))
        ->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->sole();
    expect($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->total_paisa)->toBe(49_900)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->pack_id)->toBe($pack->id);
});

test('approving a manual pack order grants every published prompt inside', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $buyer = User::factory()->create();

    $pack = Pack::factory()->create(['price_paisa' => 49_900]);
    $inPack = Prompt::factory()->published()->count(3)->create();
    $pack->prompts()->sync($inPack->pluck('id'));

    $order = Order::factory()->for($buyer, 'buyer')->create(['total_paisa' => 49_900]);
    $order->items()->create([
        'pack_id' => $pack->id,
        'price_paisa' => 49_900,
        'currency' => 'NPR',
        'quantity' => 1,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.orders.approve', $order))
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(Order::STATUS_PAID)
        ->and(LicenseGrant::where('user_id', $buyer->id)->count())->toBe(3);
});

test('pack fulfillment skips prompts the buyer already owns', function () {
    $buyer = User::factory()->create();
    $pack = Pack::factory()->create();
    $owned = Prompt::factory()->published()->create();
    $fresh = Prompt::factory()->published()->create();
    $pack->prompts()->sync([$owned->id, $fresh->id]);

    // Buyer already has an active grant to one member prompt.
    LicenseGrant::factory()->for($buyer, 'user')->create(['prompt_id' => $owned->id]);

    $order = Order::factory()->for($buyer, 'buyer')->paid()->create();
    $order->items()->create([
        'pack_id' => $pack->id,
        'price_paisa' => 49_900,
        'currency' => 'NPR',
        'quantity' => 1,
    ]);

    app(\App\Services\EntitlementService::class)->fulfill($order);

    $owned = LicenseGrant::where('user_id', $buyer->id)->get();
    expect($owned)->toHaveCount(2)
        ->and($owned->where('prompt_id', $fresh->id)->isNotEmpty())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Settings-driven payment gates
// ---------------------------------------------------------------------------

test('checkout page explains when no payment methods are enabled', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->for($buyer, 'buyer')->create();

    $this->actingAs($buyer)
        ->get(route('checkout.show', $order))
        ->assertOk()
        ->assertSee('Checkout is being configured');
});

test('buyers cannot open other buyers checkouts', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $order = Order::factory()->for($owner, 'buyer')->create();

    $this->actingAs($intruder)
        ->get(route('checkout.show', $order))
        ->assertForbidden();
});

test('manual payment reference submission is gated on the setting', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->for($buyer, 'buyer')->create();

    // Disabled by default.
    $this->actingAs($buyer)
        ->post(route('checkout.manual.submit', $order), ['payment_reference' => 'TXN-123456'])
        ->assertForbidden();

    app(SettingsService::class)->set('manual_payment_enabled', '1');

    $this->actingAs($buyer)
        ->post(route('checkout.manual.submit', $order), ['payment_reference' => 'TXN-123456'])
        ->assertRedirect();

    expect($order->refresh()->payment_reference)->toBe('TXN-123456')
        ->and($order->payment_method)->toBe('manual')
        ->and($order->status)->toBe(Order::STATUS_PENDING); // still needs admin approval
});

// ---------------------------------------------------------------------------
// Secret encryption at rest
// ---------------------------------------------------------------------------

test('payment secrets are encrypted at rest', function () {
    $settings = app(SettingsService::class);
    $settings->set('esewa_secret_key', 'super-secret-hmac-key');

    $row = \App\Models\Setting::query()->where('key', 'esewa_secret_key')->first();

    expect($row->value)->not->toBe('super-secret-hmac-key')
        ->and(str_contains($row->value, 'super-secret'))->toBeFalse()
        ->and($settings->get('esewa_secret_key'))->toBe('super-secret-hmac-key');
});

// ---------------------------------------------------------------------------
// Public pages
// ---------------------------------------------------------------------------

test('about and packs pages render for guests', function () {
    $this->get(route('pages.about'))->assertOk();
    $this->get(route('packs.index'))->assertOk();
});

test('purchases page requires authentication', function () {
    $this->get(route('purchases.index'))->assertRedirect(route('login'));
});
