<?php

use App\Models\LicenseGrant;
use App\Models\ManualPaymentMethod;
use App\Models\Order;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * C2 + C3 (v1.4.4) — manual payment methods v2 + buyer TXN proof.
 *
 * Coverage per directive: method CRUD + ordering + active filter; QR stays
 * PNG; checkout renders only active methods in position order and
 * snapshots the name; proof form validation; owner-only submission;
 * refused once paid; approve-after-proof grants atomically; proofs are
 * PRIVATE (owner/staff controller route, never raw storage URLs); admin
 * sees proof on the order desk.
 */
uses(RefreshDatabase::class);

function mpAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function buyerOrder(User $buyer): Order
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

function proofFile(): UploadedFile
{
    // A real 1x1 PNG so validation + GD both accept it.
    return UploadedFile::fake()->image('proof.png', 10, 10);
}

// ---------------------------------------------------------------------------
// C2 — admin payment settings save (the founder's complaint)
// ---------------------------------------------------------------------------

test('admin updates manual payment instructions and they persist and render at checkout', function () {
    Storage::fake('public');
    $admin = mpAdmin();

    $this->actingAs($admin)
        ->put(route('admin.payments.update'), [
            'payments_enabled' => '1',
            'manual_enabled' => '1',
            'manual_payment_instructions' => 'Send to eSewa 98XXXXXXXX, then submit the TXN id.',
        ])
        ->assertRedirect();

    $settings = app(App\Services\SettingsService::class);
    expect($settings->isOn('manual_payment_enabled'))->toBeTrue('manual toggle did not persist under the runtime key')
        ->and($settings->get('manual_payment_instructions'))->toContain('98XXXXXXXX');

    // And it re-renders in the admin form and on a checkout with no methods
    // configured (legacy fallback panel).
    $this->actingAs($admin)->get(route('admin.payments.edit'))
        ->assertOk()
        ->assertSee('98XXXXXXXX', false);

    $buyer = User::factory()->create();
    $order = buyerOrder($buyer);
    $this->actingAs($buyer)->get(route('checkout.show', $order))
        ->assertOk()
        ->assertSee('Send to eSewa 98XXXXXXXX', false);
});

test('rendered admin payments form submission persists the manual toggle', function () {
    // Rendered-form style: submit exactly the fields the served HTML has
    // (including the hidden active=0 companion), not a hand-built payload.
    Storage::fake('public');
    $admin = mpAdmin();

    $html = $this->actingAs($admin)->get(route('admin.payments.edit'))->getContent();
    expect($html)->toContain('name="manual_enabled"');

    $this->actingAs($admin)->put(route('admin.payments.update'), [
        'manual_enabled' => '1',
        'manual_payment_instructions' => 'Bank: 123-456-789',
    ])->assertRedirect();

    expect(app(App\Services\SettingsService::class)->isOn('manual_payment_enabled'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// C3 — manual payment method CRUD
// ---------------------------------------------------------------------------

test('admin can create, edit and list manual payment methods', function () {
    Storage::fake('public');
    $admin = mpAdmin();

    $this->actingAs($admin)->post(route('admin.manual-methods.store'), [
        'name' => 'eSewa — 98XXXXXX01',
        'instructions' => 'Scan and send, keep the receipt.',
        'position' => '2',
        'active' => '1',
    ])->assertRedirect();

    $method = ManualPaymentMethod::query()->where('name', 'eSewa — 98XXXXXX01')->firstOrFail();
    expect($method->active)->toBeTrue()->and($method->position)->toBe(2);

    $this->actingAs($admin)->put(route('admin.manual-methods.update', $method), [
        'name' => 'eSewa — 98XXXXXX02',
        'instructions' => 'Updated instructions.',
        'position' => '1',
        'active' => '0',
    ])->assertRedirect();

    expect($method->refresh()->name)->toBe('eSewa — 98XXXXXX02')
        ->and($method->active)->toBeFalse()
        ->and($method->position)->toBe(1);

    $this->actingAs($admin)->get(route('admin.manual-methods.index'))
        ->assertOk()
        ->assertSee('eSewa — 98XXXXXX02', false);
});

test('method create validates the name and only admins may manage methods', function () {
    $admin = mpAdmin();
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);

    $this->actingAs($mod)->post(route('admin.manual-methods.store'), [
        'name' => 'Mod Method', 'active' => '1',
    ])->assertForbidden();

    $this->actingAs($admin)->post(route('admin.manual-methods.store'), [
        'instructions' => 'no name',
    ])->assertSessionHasErrors('name');

    expect(ManualPaymentMethod::count())->toBe(0);
});

test('deleting a used method deactivates it; an unused one is hard-deleted', function () {
    Storage::fake('public');
    $admin = mpAdmin();
    $buyer = User::factory()->create();

    $used = ManualPaymentMethod::create(['name' => 'Used Bank', 'active' => true]);
    $fresh = ManualPaymentMethod::create(['name' => 'Fresh Bank', 'active' => true]);

    // An order snapshotting this name (plain string on the order).
    buyerOrder($buyer)->fill(['payment_reference' => 'Used Bank · TXN1'])->save();

    $this->actingAs($admin)->delete(route('admin.manual-methods.destroy', $used))->assertRedirect();
    expect($used->refresh()->active)->toBeFalse('used method must deactivate, never hard-delete');

    $this->actingAs($admin)->delete(route('admin.manual-methods.destroy', $fresh))->assertRedirect();
    expect(ManualPaymentMethod::find($fresh->id))->toBeNull('unused method should hard-delete');
});

test('QR uploads stay PNG on disk even from a JPEG source', function () {
    Storage::fake('public');
    $admin = mpAdmin();

    $this->actingAs($admin)->post(route('admin.manual-methods.store'), [
        'name' => 'Khalti QR',
        'active' => '1',
        'qr' => UploadedFile::fake()->image('qr.jpg', 300, 300),
    ])->assertRedirect();

    $method = ManualPaymentMethod::query()->where('name', 'Khalti QR')->firstOrFail();
    expect($method->qr_path)->not->toBeNull()
        ->and($method->qr_path)->toEndWith('.png', 'QR must be PNG — JPEG blocks kill scannability')
        ->and(Storage::disk('public')->exists($method->qr_path))->toBeTrue();
});

// ---------------------------------------------------------------------------
// C3 — checkout rendering + name snapshot
// ---------------------------------------------------------------------------

test('checkout renders only active methods in position order and snapshots the name', function () {
    Storage::fake('public');
    $buyer = User::factory()->create();
    $order = buyerOrder($buyer);

    ManualPaymentMethod::create(['name' => 'Second', 'position' => 2, 'active' => true]);
    ManualPaymentMethod::create(['name' => 'First', 'position' => 1, 'active' => true]);
    ManualPaymentMethod::create(['name' => 'Hidden', 'position' => 0, 'active' => false]);

    app(App\Services\SettingsService::class)->set('manual_payment_enabled', '1');

    $html = $this->actingAs($buyer)->get(route('checkout.show', $order))->getContent();

    expect(substr((string) strpos($html, 'First'), 0))->not->toBe('')
        ->and(strpos($html, 'First'))->toBeLessThan(strpos($html, 'Second'), 'methods must render in position order')
        ->and($html)->not->toContain('Hidden');

    // Snapshot: the hidden input carries the plain method name, and manual
    // submission stores it on the order.
    $this->actingAs($buyer)->post(route('checkout.manual.submit', $order), [
        'method_name' => 'First',
        'payment_reference' => 'TXN-ABC-123',
    ])->assertRedirect();

    expect($order->refresh()->payment_reference)->toBe('First · TXN-ABC-123')
        ->and($order->payment_method)->toBe('manual');

    // Editing the method later must NOT rewrite order history.
    $method = ManualPaymentMethod::query()->where('name', 'First')->firstOrFail();
    $this->actingAs(mpAdmin())->put(route('admin.manual-methods.update', $method), [
        'name' => 'First Renamed', 'position' => '1', 'active' => '1',
    ])->assertRedirect();

    expect($order->refresh()->payment_reference)->toBe('First · TXN-ABC-123', 'order history must never be rewritten');
});

// ---------------------------------------------------------------------------
// C3 — buyer proof submission
// ---------------------------------------------------------------------------

test('buyer submits TXN id + proof screenshot on their pending manual order', function () {
    Storage::fake('proofs');
    $buyer = User::factory()->create();
    $order = buyerOrder($buyer);
    $order->fill(['payment_method' => 'manual', 'payment_reference' => 'First · TXN-1'])->save();

    $this->actingAs($buyer)
        ->post(route('orders.proof.store', $order), [
            'txn_id' => '9F3K2L8Q',
            'proof' => proofFile(),
            'note' => 'sent from eSewa mobile',
        ])
        ->assertRedirect();

    expect($order->refresh()->manual_txn_id)->toBe('9F3K2L8Q')
        ->and($order->manual_proof_path)->not->toBeNull()
        ->and($order->manual_submitted_at)->not->toBeNull()
        ->and(Storage::disk('proofs')->exists($order->manual_proof_path))->toBeTrue()
        // Submission grants NOTHING — approval is the only grant path.
        ->and(LicenseGrant::where('user_id', $buyer->id)->count())->toBe(0);
});

test('proof form enforces required fields and length limits', function () {
    Storage::fake('proofs');
    $buyer = User::factory()->create();
    $order = buyerOrder($buyer);
    $order->fill(['payment_method' => 'manual'])->save();

    $this->actingAs($buyer)->post(route('orders.proof.store', $order), [])
        ->assertSessionHasErrors(['txn_id', 'proof']);

    $this->actingAs($buyer)->post(route('orders.proof.store', $order), [
        'txn_id' => str_repeat('x', 101),
        'proof' => proofFile(),
    ])->assertSessionHasErrors('txn_id');

    expect($order->refresh()->manual_txn_id)->toBeNull();
});

test('only the owner can submit or view a proof', function () {
    Storage::fake('proofs');
    $buyer = User::factory()->create();
    $other = User::factory()->create();
    $order = buyerOrder($buyer);
    $order->fill(['payment_method' => 'manual'])->save();

    $this->actingAs($other)
        ->post(route('orders.proof.store', $order), ['txn_id' => 'HACK', 'proof' => proofFile()])
        ->assertForbidden();

    $this->actingAs($buyer)
        ->post(route('orders.proof.store', $order), ['txn_id' => 'OK-1', 'proof' => proofFile()])
        ->assertRedirect();

    $this->actingAs($other)->get(route('orders.proof.show', $order))->assertForbidden();

    // A true guest (auth guard flushed — actingAs state would otherwise
    // leak into the next request) is bounced to login by the auth middleware.
    $this->app->make('auth')->guard('web')->forgetUser();
    $this->get(route('orders.proof.show', $order))->assertRedirect(route('login'));

    // Proof lives on the PRIVATE disk (storage/app/proofs, outside the
    // public/storage symlink) — a public-disk existence check must fail.
    expect(Storage::disk('public')->exists($order->refresh()->manual_proof_path))->toBeFalse('proof file must never live on the public disk');
});

test('proof submission is refused once the order is paid or failed', function () {
    Storage::fake('proofs');
    $buyer = User::factory()->create();
    $order = buyerOrder($buyer);
    $order->fill(['payment_method' => 'manual', 'status' => Order::STATUS_PAID])->save();

    // Owner check passes but the pending check refuses (400) — either way
    // the submission is dead on arrival and nothing is stored.
    $this->actingAs($buyer)
        ->post(route('orders.proof.store', $order), ['txn_id' => 'LATE-1', 'proof' => proofFile()])
        ->assertStatus(400);

    expect($order->refresh()->manual_txn_id)->toBeNull();
});

test('re-submission replaces the proof file and deletes the orphan', function () {
    Storage::fake('proofs');
    $buyer = User::factory()->create();
    $order = buyerOrder($buyer);
    $order->fill(['payment_method' => 'manual'])->save();

    $this->actingAs($buyer)->post(route('orders.proof.store', $order), [
        'txn_id' => 'V1', 'proof' => proofFile(),
    ])->assertRedirect();

    $firstPath = $order->refresh()->manual_proof_path;
    expect($firstPath)->not->toBeNull();

    $this->actingAs($buyer)->post(route('orders.proof.store', $order), [
        'txn_id' => 'V2', 'proof' => proofFile(),
    ])->assertRedirect();

    $order->refresh();
    expect($order->manual_txn_id)->toBe('V2')
        ->and($order->manual_proof_path)->not->toBe($firstPath)
        ->and(Storage::disk('proofs')->exists($firstPath))->toBeFalse('orphaned old proof must be deleted from disk')
        ->and(Storage::disk('proofs')->exists($order->manual_proof_path))->toBeTrue();
});

test('admin approves after proof and grants issue atomically; admin desk shows the proof', function () {
    Storage::fake('proofs');
    $admin = mpAdmin();
    $buyer = User::factory()->create();
    $order = buyerOrder($buyer);
    $order->fill(['payment_method' => 'manual'])->save();

    $this->actingAs($buyer)->post(route('orders.proof.store', $order), [
        'txn_id' => 'FINAL-9', 'proof' => proofFile(),
    ])->assertRedirect();

    // Staff (moderator) can view the private proof; admin desk renders it.
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $this->actingAs($mod)->get(route('orders.proof.show', $order))->assertOk();

    $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk();

    $this->actingAs($admin)->patch(route('admin.orders.approve', $order))->assertRedirect();

    expect($order->refresh()->status)->toBe(Order::STATUS_PAID)
        ->and($order->manual_txn_id)->toBe('FINAL-9')
        ->and(LicenseGrant::where('user_id', $buyer->id)->count())->toBe(1);
});
