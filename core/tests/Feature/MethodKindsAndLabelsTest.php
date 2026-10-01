<?php

use App\Models\ManualPaymentMethod;
use App\Models\Order;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * P3 + P4 (v1.7.1) — method kinds, checkout QR block, admin doors,
 * revenue label honesty.
 */
uses(RefreshDatabase::class);

function kindsAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function kindsBuyerOrder(User $buyer): Order
{
    $prompt = Prompt::factory()->published()->create();
    $order = Order::factory()->for($buyer, 'buyer')->create(['total_paisa' => 50_000]);
    $order->items()->create([
        'prompt_id' => $prompt->id,
        'price_paisa' => 50_000,
        'currency' => 'NPR',
        'quantity' => 1,
    ]);

    return $order;
}

// ---------------------------------------------------------------------------
// P3 — kind migration + backfill
// ---------------------------------------------------------------------------

test('the kind column backfills legacy rows to other', function () {
    // Simulate a legacy row created before the migration added defaults:
    // refresh the schema knowledge by direct insert, then run the backfill
    // assertion on a fresh row missing kind.
    $method = ManualPaymentMethod::query()->create(['name' => 'Legacy Bank', 'active' => true]);

    // The migration's DB default backfills the column; re-read from the
    // database (the in-memory model doesn't know DB defaults).
    $method->refresh();
    expect(in_array($method->kind, ManualPaymentMethod::KINDS, true))->toBeTrue()
        ->and($method->kind)->toBe(ManualPaymentMethod::KIND_OTHER);
});

test('methods persist an explicit kind', function () {
    $admin = kindsAdmin();

    $this->actingAs($admin)->post(route('admin.manual-methods.store'), [
        'name' => 'Nabil Bank',
        'kind' => 'bank',
        'active' => '1',
    ])->assertRedirect();

    $this->actingAs($admin)->post(route('admin.manual-methods.store'), [
        'name' => 'eSewa Wallet',
        'kind' => 'esewa',
        'active' => '1',
    ])->assertRedirect();

    expect(ManualPaymentMethod::query()->where('name', 'Nabil Bank')->value('kind'))->toBe('bank')
        ->and(ManualPaymentMethod::query()->where('name', 'eSewa Wallet')->value('kind'))->toBe('esewa');

    // Invalid kinds are rejected by validation.
    $this->actingAs($admin)->post(route('admin.manual-methods.store'), [
        'name' => 'Bad Kind',
        'kind' => 'paypal',
        'active' => '1',
    ])->assertSessionHasErrors('kind');
});

// ---------------------------------------------------------------------------
// P3 — checkout per-method cards: kind icon + Scan-to-pay QR block
// ---------------------------------------------------------------------------

test('checkout renders the kind icon and Scan-to-pay QR block per active method', function () {
    Storage::fake('public');
    $buyer = User::factory()->create();
    $order = kindsBuyerOrder($buyer);

    $qr = UploadedFile::fake()->image('qr.png', 200, 200);
    $bankQr = UploadedFile::fake()->image('bank-qr.png', 200, 200);

    ManualPaymentMethod::query()->create([
        'name' => 'Nabil Bank',
        'kind' => 'bank',
        'instructions' => 'Transfer to 123-456-789, keep the slip.',
        'qr_path' => app(App\Services\ImageUploadService::class)->store($bankQr, 'qr'),
        'active' => true,
    ]);

    $esewa = ManualPaymentMethod::query()->create([
        'name' => 'eSewa — 98XXXX',
        'kind' => 'esewa',
        'instructions' => "Scan the QR.\nSend the amount.\nSubmit the TXN id below.",
        'active' => true,
    ]);
    $esewa->update(['qr_path' => app(App\Services\ImageUploadService::class)->store($qr, 'qr')]);

    app(App\Services\SettingsService::class)->set('manual_payment_enabled', '1');

    $html = $this->actingAs($buyer)->get(route('checkout.show', $order))->getContent();

    // Kind icon contract: eSewa wordmark glyph and the bank glyph path.
    expect($html)->toContain('>eSewa</span>')
        ->and($html)->toContain('M12 21v-8.25') // bank building glyph path
        // "Scan to pay" bordered block, one per QR-bearing method.
        ->and(substr_count($html, 'Scan to pay'))->toBe(2)
        // Instructions render escaped with pre-line.
        ->and($html)->toContain('whitespace-pre-line')
        ->and($html)->toContain('Scan the QR.');
});

test('legacy fallback renders when zero methods exist', function () {
    $buyer = User::factory()->create();
    $order = kindsBuyerOrder($buyer);

    app(App\Services\SettingsService::class)->set('manual_payment_enabled', '1');
    app(App\Services\SettingsService::class)->set('manual_payment_instructions', 'Global legacy instructions.');

    $html = $this->actingAs($buyer)->get(route('checkout.show', $order))->getContent();

    expect($html)->toContain('Global legacy instructions.')
        ->and($html)->not->toContain('Scan to pay');
});

// ---------------------------------------------------------------------------
// P3 — admin Payments manager card
// ---------------------------------------------------------------------------

test('the admin payments page shows the manual-methods manager link', function () {
    $admin = kindsAdmin();

    ManualPaymentMethod::query()->create(['name' => 'One', 'active' => true]);
    ManualPaymentMethod::query()->create(['name' => 'Two', 'active' => false]);

    $html = $this->actingAs($admin)->get(route('admin.payments.edit'))->getContent();

    expect($html)->toContain('Manual methods')
        ->and($html)->toContain('2 configured')
        ->and($html)->toContain(route('admin.manual-methods.index'));
});

// ---------------------------------------------------------------------------
// P1b — admin doors: Badges + Frames nav pills
// ---------------------------------------------------------------------------

test('the admin nav carries Badges and Frames pills for admins', function () {
    $admin = kindsAdmin();

    $html = $this->actingAs($admin)->get(route('admin.dashboard'))->getContent();

    expect($html)->toContain(route('admin.badges.index'))
        ->and($html)->toContain(route('admin.frames.index'))
        // Pill labels render after the icon span, with a newline between.
        ->and($html)->toContain('</span>Badges')
        ->and($html)->toContain('</span>Frames');
});

test('moderators are 403 on badges and frames management', function () {
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);

    $this->actingAs($mod)->get(route('admin.badges.index'))->assertForbidden();
    $this->actingAs($mod)->get(route('admin.frames.index'))->assertForbidden();

    $admin = kindsAdmin();
    $this->actingAs($admin)->get(route('admin.badges.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.frames.index'))->assertOk();
});

// ---------------------------------------------------------------------------
// P4 — revenue label honesty
// ---------------------------------------------------------------------------

test('admin overview and finance desk keep their two distinct revenue truths', function () {
    $admin = kindsAdmin();

    $overview = $this->actingAs($admin)->get(route('admin.dashboard'))->getContent();
    expect($overview)->toContain('paid orders · incl. pre-ledger');

    $finance = $this->actingAs($admin)->get(route('admin.finance'))->getContent();
    expect($finance)->toContain('ledger gross · post-cutover');
});
