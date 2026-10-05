<?php

use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\SikkaPack;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * S6 (v1.8.0) — the served surfaces.
 *
 * Locks: the authoring form's Sikka price input (integer 0–100000) with
 * its NPR preview, the dual-price card label (Sikka primary, NPR in
 * parentheses), the detail page's Sikka buy box, the checkout selector +
 * insufficient top-up CTA, the /sikka top-up storefront and its approval
 * credit path, and the kill-switch sweep: while disabled, home, library,
 * card/detail, checkout and earnings carry ZERO Sikka markup. SEO crawl +
 * form inventory keep the new routes classified (their own suites).
 */
function surfacesEnableSikka(): void
{
    app(SettingsService::class)->set('sikka_enabled', '1');
}

test('the authoring forms carry the Sikka price input with an NPR preview', function () {
    surfacesEnableSikka();

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);

    $html = $this->actingAs($creator)->get(route('dashboard.prompts.create'))->assertOk()->getContent();

    expect($html)->toContain('name="price_sikka"')
        ->and($html)->toContain('max="100000"')
        ->and($html)->toContain('Price (Sikka credits)')
        ->and($html)->toContain('Rs. 0.00'); // the live preview seed via money_npr

    // A Sikka price lands as the price of record and derives the mirror.
    $this->actingAs($creator)->post(route('dashboard.prompts.store'), [
        'title' => 'Sikka priced listing probe',
        'description' => 'A listing submitted by the surfaces battery to prove the Sikka price of record lands.',
        'category_id' => Category::factory()->create()->id,
        'type' => Prompt::TYPE_TEXT,
        'body' => 'Write a launch plan for {{product}} with milestones and owners.',
        'tags' => 'launch, planning',
        'recommended_tools' => ['ChatGPT'],
        'price_sikka' => '249',
        'visibility' => Prompt::VISIBILITY_PUBLIC,
    ])->assertRedirect();

    $prompt = Prompt::query()->where('title', 'Sikka priced listing probe')->sole();

    expect($prompt->price_sikka)->toBe(249)
        ->and($prompt->price_cents)->toBe(24_900); // 249 × 100 paisa

    // Out of range refused.
    $this->actingAs($creator)->post(route('dashboard.prompts.store'), [
        'title' => 'Sikka priced listing probe 2',
        'description' => 'A second listing submitted by the surfaces battery to prove the Sikka ceiling is enforced.',
        'category_id' => Category::factory()->create()->id,
        'type' => Prompt::TYPE_TEXT,
        'body' => 'Write a launch plan for {{product}} with milestones and owners.',
        'tags' => 'launch, planning',
        'recommended_tools' => ['ChatGPT'],
        'price_sikka' => '100001',
        'visibility' => Prompt::VISIBILITY_PUBLIC,
    ])->assertSessionHasErrors('price_sikka');
});

test('cards and the detail page lead with Sikka and keep NPR in parentheses', function () {
    surfacesEnableSikka();

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->sikkaPriced(249)->hasVersion()->create([
        'user_id' => $creator->id,
        'status' => Prompt::STATUS_PUBLISHED,
        'visibility' => Prompt::VISIBILITY_PUBLIC,
    ]);

    $html = $this->get(route('prompts.show', $prompt))->assertOk()->getContent();

    expect($html)->toContain('Rs. 249.00')       // NPR in parentheses
        ->and($html)->toContain('Sikka');        // the fallback chip / word pairing

    // The card grid renders the dual label too.
    $library = $this->get(route('library.index'))->assertOk()->getContent();

    expect($library)->toContain('Rs. 249.00')
        ->and($library)->toContain('Sikka');
});

test('the checkout selector offers the top-up CTA when the balance is short', function () {
    surfacesEnableSikka();

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->sikkaPriced(50)->hasVersion()->create([
        'user_id' => $creator->id,
        'status' => Prompt::STATUS_PUBLISHED,
    ]);
    $product = Product::factory()->create(['prompt_id' => $prompt->id, 'price_paisa' => 5_000]);

    $buyer = User::factory()->create();
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => Order::STATUS_PENDING]);
    $order->items()->create([
        'product_id' => $product->id, 'prompt_id' => $prompt->id,
        'price_paisa' => 5_000, 'currency' => 'npr', 'quantity' => 1,
    ]);

    $html = $this->actingAs($buyer)->get(route('checkout.show', $order))->assertOk()->getContent();

    expect($html)->toContain(route('sikka.topup'))       // top-up CTA
        ->and($html)->toContain('Top up Sikka credits')
        ->and($html)->not->toContain(route('checkout.sikka.pay', $order)); // not yet payable

    // Funded → the Sikka rail IS the default door.
    SikkaTransaction::query()->create([
        'user_id' => $buyer->id, 'type' => SikkaTransaction::TYPE_TOPUP, 'amount_sikka' => 100,
        'cashout_eligible' => true, 'idempotency_key' => 'surfaces:fund', 'meta' => [], 'created_at' => now(),
    ]);

    $funded = $this->actingAs($buyer)->get(route('checkout.show', $order))->assertOk()->getContent();

    expect($funded)->toContain(route('checkout.sikka.pay', $order))
        ->and($funded)->toContain('Sikka credits');
});

test('the Sikka top-up storefront buys a pack and approval credits it exactly once', function () {
    surfacesEnableSikka();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $buyer = User::factory()->create();

    $pack = SikkaPack::query()->create([
        'name' => 'Starter pack', 'slug' => 'starter-pack', 'sikka_amount' => 300,
        'bonus_sikka' => 30, 'price_paisa' => 25_000, 'active' => true,
    ]);

    $this->actingAs($buyer)->get(route('sikka.topup'))
        ->assertOk()
        ->assertSee('Starter pack')
        ->assertSee('Rs. 250.00');

    $this->actingAs($buyer)->post(route('checkout.sikka.packs.buy', $pack))->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->sole();

    expect($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->currency)->toBe(Order::CURRENCY_NPR)
        ->and($order->items()->sole()->sikka_pack_id)->toBe($pack->id);

    $this->actingAs($admin)->patch(route('admin.orders.approve', $order))->assertRedirect();
    $this->actingAs($admin)->patch(route('admin.orders.approve', $order))->assertRedirect(); // double approval

    $rows = SikkaTransaction::query()->where('order_id', $order->id)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->sum('amount_sikka'))->toBe(330)
        ->and(Notification::query()->where('user_id', $buyer->id)->where('type', Notification::TYPE_SIKKA_TOPUP)->count())->toBe(1);
});

test('the kill-switch sweep: disabled means zero Sikka markup on every buyer surface', function () {
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->sikkaPriced(249)->hasVersion()->create([
        'user_id' => $creator->id,
        'status' => Prompt::STATUS_PUBLISHED,
        'visibility' => Prompt::VISIBILITY_PUBLIC,
    ]);
    $buyer = User::factory()->create();

    foreach ([
        route('home'),
        route('library.index'),
        route('prompts.show', $prompt),
    ] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)->not->toContain('Sikka')
            ->and($html)->toContain('Rs. 249'); // the legacy NPR label stays
    }

    // The top-up storefront does not exist while disabled.
    $this->get(route('sikka.topup'))->assertStatus(404);

    // Earnings: the Sikka cards + ledger browser never render.
    $earnings = $this->actingAs($buyer)->get(route('dashboard.earnings'))->assertOk()->getContent();
    expect($earnings)->not->toContain('Sikka');

    // Checkout: no Sikka rail on a prompt order.
    $product = Product::factory()->create(['prompt_id' => $prompt->id, 'price_paisa' => 24_900]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => Order::STATUS_PENDING]);
    $order->items()->create([
        'product_id' => $product->id, 'prompt_id' => $prompt->id,
        'price_paisa' => 24_900, 'currency' => 'npr', 'quantity' => 1,
    ]);

    $checkout = $this->actingAs($buyer)->get(route('checkout.show', $order))->assertOk()->getContent();
    expect($checkout)->not->toContain('Sikka');
});

test('the Sikka price mirror is enforced at the DB boundary too', function () {
    surfacesEnableSikka();

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);

    // price_sikka-only write (the record side) → the hook derives the NPR
    // mirror: 77 × 100 paisa.
    $prompt = Prompt::create([
        'user_id' => $creator->id,
        'category_id' => Category::factory()->create()->id,
        'title' => 'Mirror probe listing',
        'slug' => 'mirror-probe-listing',
        'description' => 'A Sikka-priced listing used by the mirror battery.',
        'search_text' => 'mirror probe',
        'license_tier' => Prompt::LICENSE_COMMERCIAL,
        'visibility' => Prompt::VISIBILITY_PUBLIC,
        'type' => Prompt::TYPE_TEXT,
        'status' => Prompt::STATUS_DRAFT,
        'price_sikka' => 77,
    ]);

    expect($prompt->price_cents)->toBe(7_700);

    // A legacy paisa-only write derives the Sikka price with the backfill
    // formula intdiv(paisa + buy − 1, buy).
    $legacy = Prompt::factory()->create(['user_id' => $creator->id, 'price_cents' => 24_950]);

    expect($legacy->price_sikka)->toBe(250)
        ->and(DB::table('prompts')->where('id', $legacy->id)->value('price_sikka'))->toBe(250);

    // Free stays free on both sides.
    $free = Prompt::factory()->create(['user_id' => $creator->id, 'price_cents' => 0]);

    expect($free->price_sikka)->toBe(0)
        ->and($free->price_cents)->toBe(0)
        ->and($free->isFree())->toBeTrue();
});
