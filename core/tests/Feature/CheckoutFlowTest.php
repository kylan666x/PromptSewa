<?php

use App\Models\LicenseGrant;
use App\Models\Order;
use App\Models\Pack;
use App\Models\Prompt;
use App\Models\Setting;
use App\Models\SikkaPack;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Services\EntitlementService;
use App\Services\SettingsService;
use App\Services\SikkaService;
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

test('a pack order pays with Sikka credits and grants the contents', function () {
    // The daily-visit reward rides every authenticated request — mute it so
    // the balance arithmetic is exact.
    app(SettingsService::class)->set('engage_daily_sikka', '0');

    // Enable BOTH money rails: they must not appear on a product checkout
    // any more (S1b v1.9.0 — /sikka is the single NPR door).
    app(SettingsService::class)->set('esewa_enabled', '1');
    app(SettingsService::class)->set('manual_payment_enabled', '1');

    $buyer = User::factory()->create();
    $pack = Pack::factory()->create(['price_sikka' => 499, 'is_active' => true]);
    $inPack = Prompt::factory()->published()->count(2)->create();
    $pack->prompts()->sync($inPack->pluck('id'));

    SikkaTransaction::query()->create([
        'user_id' => $buyer->id,
        'type' => SikkaTransaction::TYPE_TOPUP,
        'amount_sikka' => 700,
        'cashout_eligible' => true,
        'idempotency_key' => 'packflow:fund',
        'meta' => [],
        'created_at' => now(),
    ]);

    $this->actingAs($buyer)->post(route('checkout.packs.buy', $pack))->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->sole();

    // The checkout prices in credits and offers the Sikka rail — with the
    // money rails suppressed even though both settings are ON.
    $this->actingAs($buyer)->get(route('checkout.show', $order))
        ->assertOk()
        ->assertSee(route('checkout.sikka.pay', $order), false)
        ->assertSee('Pay with Sikka credits')
        ->assertDontSee('Pay with eSewa')
        ->assertDontSee('Bank / wallet transfer')
        ->assertDontSee('Rs.');

    $this->actingAs($buyer)
        ->post(route('checkout.sikka.pay', $order))
        ->assertRedirect(route('purchases.index'));

    expect($order->refresh()->status)->toBe(Order::STATUS_PAID)
        ->and($order->currency)->toBe(Order::CURRENCY_SIKKA)
        ->and($order->sikka_amount)->toBe(499)
        ->and(LicenseGrant::where('user_id', $buyer->id)->count())->toBe(2)
        ->and(app(SikkaService::class)->spendableAvailable($buyer))->toBe(201);
});

test('a short balance sends the pack buyer to the top-up page, and only a top-up order shows the money rails', function () {
    app(SettingsService::class)->set('engage_daily_sikka', '0');
    app(SettingsService::class)->set('manual_payment_enabled', '1');

    $buyer = User::factory()->create();
    $pack = Pack::factory()->create(['price_sikka' => 499, 'is_active' => true]);
    $pack->prompts()->sync(Prompt::factory()->published()->count(1)->create()->pluck('id'));

    $this->actingAs($buyer)->post(route('checkout.packs.buy', $pack))->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->sole();

    $this->actingAs($buyer)->get(route('checkout.show', $order))
        ->assertOk()
        ->assertSee(route('sikka.topup'))
        ->assertSee('Top up Sikka credits')
        ->assertDontSee(route('checkout.sikka.pay', $order), false)
        ->assertDontSee('Rs.')
        // The shortfall hint must not send the buyer to rails this order
        // does not have, and a credit-rail order is never "being
        // configured" just because the money rails are off.
        ->assertDontSee('use eSewa or a manual transfer below')
        ->assertDontSee('Checkout is being configured');

    // Buying SIKKA itself keeps the money rails: a top-up pack order.
    $sikkaPack = SikkaPack::query()->create([
        'name' => 'Flow top-up pack',
        'slug' => 'flow-top-up-pack',
        'sikka_amount' => 300,
        'bonus_sikka' => 0,
        'price_paisa' => 25_000,
        'active' => true,
    ]);

    $this->actingAs($buyer)->post(route('checkout.sikka.packs.buy', $sikkaPack))->assertRedirect();

    $topUp = Order::query()->where('buyer_id', $buyer->id)->whereKeyNot($order->id)->sole();

    $this->actingAs($buyer)->get(route('checkout.show', $topUp))
        ->assertOk()
        ->assertSee('Bank / wallet transfer')
        ->assertDontSee(route('checkout.sikka.pay', $topUp), false);
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

    app(EntitlementService::class)->fulfill($order);

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

    $row = Setting::query()->where('key', 'esewa_secret_key')->first();

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
