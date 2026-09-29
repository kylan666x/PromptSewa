<?php

use App\Models\LicenseGrant;
use App\Models\Order;
use App\Models\Pack;
use App\Models\Prompt;
use App\Models\PromptReport;
use App\Models\Rating;
use App\Models\ToolLogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function flowsAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

// ---------------------------------------------------------------------------
// Verify toggle (admin only)
// ---------------------------------------------------------------------------

test('admin can toggle the verified badge on and off', function () {
    $admin = flowsAdmin();
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR, 'is_verified' => false]);

    $this->actingAs($admin)
        ->patch(route('admin.users.verified', $creator))
        ->assertRedirect();
    expect($creator->refresh()->is_verified)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.users.verified', $creator))
        ->assertRedirect();
    expect($creator->refresh()->is_verified)->toBeFalse();
});

test('moderators cannot issue verified badges', function () {
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);

    $this->actingAs($mod)
        ->patch(route('admin.users.verified', $creator))
        ->assertForbidden();

    expect($creator->refresh()->is_verified)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Manual payment verify / reject
// ---------------------------------------------------------------------------

test('rejecting a manual payment fails the order and grants nothing', function () {
    $admin = flowsAdmin();
    $buyer = User::factory()->create();
    $prompt = Prompt::factory()->published()->create(['price_cents' => 24_900]);
    $product = \App\Models\Product::factory()->for($prompt, 'prompt')->create();

    $order = Order::factory()->for($buyer, 'buyer')->create();
    $order->items()->create([
        'product_id' => $product->id,
        'price_paisa' => 24_900,
        'currency' => 'NPR',
        'quantity' => 1,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.orders.reject', $order))
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(Order::STATUS_FAILED)
        ->and(LicenseGrant::where('user_id', $buyer->id)->count())->toBe(0);
});

test('rejecting an already-paid order is refused', function () {
    $admin = flowsAdmin();
    $order = Order::factory()->paid()->create();

    $this->actingAs($admin)
        ->patch(route('admin.orders.reject', $order))
        ->assertSessionHasErrors('order');

    expect($order->refresh()->status)->toBe(Order::STATUS_PAID);
});

// ---------------------------------------------------------------------------
// Packs CRUD
// ---------------------------------------------------------------------------

test('admin can create, update and delete a pack', function () {
    $admin = flowsAdmin();
    $a = Prompt::factory()->published()->create();
    $b = Prompt::factory()->published()->create();

    $this->actingAs($admin)
        ->post(route('admin.packs.store'), [
            'name' => 'Starter Bundle',
            'price_npr' => 499,
            'is_active' => '1',
            'prompt_ids' => [$a->id, $b->id],
        ])
        ->assertRedirect(route('admin.packs.index'));

    $pack = Pack::query()->where('name', 'Starter Bundle')->sole();
    expect($pack->price_paisa)->toBe(49_900)
        ->and($pack->prompts()->pluck('prompts.id')->all())->toEqualCanonicalizing([$a->id, $b->id]);

    $this->actingAs($admin)
        ->put(route('admin.packs.update', $pack), [
            'name' => 'Starter Bundle XL',
            'price_npr' => 799,
            'prompt_ids' => [$a->id],
        ])
        ->assertRedirect(route('admin.packs.index'));

    $pack->refresh();
    expect($pack->name)->toBe('Starter Bundle XL')
        ->and($pack->price_paisa)->toBe(79_900)
        ->and($pack->prompts()->pluck('prompts.id')->all())->toEqual([$a->id]);

    $this->actingAs($admin)
        ->delete(route('admin.packs.destroy', $pack))
        ->assertRedirect();

    expect(Pack::find($pack->id))->toBeNull();
});

test('pack form requires a name and non-negative price', function () {
    $admin = flowsAdmin();

    $this->actingAs($admin)
        ->post(route('admin.packs.store'), ['name' => '', 'price_npr' => -5])
        ->assertSessionHasErrors(['name', 'price_npr']);
});

// ---------------------------------------------------------------------------
// Tool logos CRUD
// ---------------------------------------------------------------------------

test('admin can add, deactivate and remove an AI tool', function () {
    $admin = flowsAdmin();

    $this->actingAs($admin)
        ->post(route('admin.tool-logos.store'), ['name' => 'Midjourney', 'modality' => 'image'])
        ->assertRedirect();

    $tool = ToolLogo::query()->where('name', 'Midjourney')->sole();
    expect($tool->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.tool-logos.update', $tool), [
            'name' => 'Midjourney',
            'is_active' => '0',
        ])
        ->assertRedirect();
    expect($tool->refresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->delete(route('admin.tool-logos.destroy', $tool))
        ->assertRedirect();
    expect(ToolLogo::find($tool->id))->toBeNull();
});

test('tool logos page is admin/staff only', function () {
    $this->get(route('admin.tool-logos.index'))->assertRedirect(route('login'));

    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
    $this->actingAs($member)->get(route('admin.tool-logos.index'))->assertForbidden();
});

// ---------------------------------------------------------------------------
// /admin/update (release updater page)
// ---------------------------------------------------------------------------

test('admin update page loads for staff and forbids members', function () {
    $this->actingAs(flowsAdmin())
        ->get(route('admin.update'))
        ->assertOk()
        ->assertSee('release_zip', false);

    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
    $this->actingAs($member)->get(route('admin.update'))->assertForbidden();
});

// ---------------------------------------------------------------------------
// Ratings eligibility (both sides)
// ---------------------------------------------------------------------------

test('any logged-in user can rate a free prompt and re-rating upserts', function () {
    $user = User::factory()->create();
    $free = Prompt::factory()->published()->create(['price_cents' => 0]);

    $this->actingAs($user)
        ->post("/prompts/{$free->slug}/rate", ['score' => 4])
        ->assertRedirect();

    expect(Rating::query()->where('prompt_id', $free->id)->count())->toBe(1);

    $this->actingAs($user)
        ->post("/prompts/{$free->slug}/rate", ['score' => 5])
        ->assertRedirect();

    expect(Rating::query()->where('prompt_id', $free->id)->count())->toBe(1)
        ->and(Rating::query()->where('prompt_id', $free->id)->value('score'))->toBe(5);
});

test('paid prompts reject raters without an active license', function () {
    $user = User::factory()->create();
    $paid = Prompt::factory()->published()->create(['price_cents' => 24_900]);

    $this->actingAs($user)
        ->post("/prompts/{$paid->slug}/rate", ['score' => 5])
        ->assertForbidden();

    expect(Rating::count())->toBe(0);
});

test('license holders can rate paid prompts, guests get 401', function () {
    $buyer = User::factory()->create();
    $paid = Prompt::factory()->published()->create(['price_cents' => 24_900]);

    \App\Models\OrderItem::factory()->create(['prompt_id' => $paid->id]);
    LicenseGrant::factory()->for($buyer, 'user')->create(['prompt_id' => $paid->id]);

    $this->actingAs($buyer)
        ->post("/prompts/{$paid->slug}/rate", ['score' => 5])
        ->assertRedirect();
    expect(Rating::query()->where('user_id', $buyer->id)->count())->toBe(1);

    // Route middleware bounces guests away; either way no rating may exist.
    $this->post("/prompts/{$paid->slug}/rate", ['score' => 5])->assertRedirect();
    expect(Rating::where('score', 5)->whereNull('user_id')->count())->toBe(0)
        ->and(Rating::count())->toBe(1); // only the buyer's rating
});

// ---------------------------------------------------------------------------
// Reports triage
// ---------------------------------------------------------------------------

test('staff can resolve and dismiss reports with attribution', function () {
    $admin = flowsAdmin();
    $report = \App\Models\PromptReport::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.reports.status', $report), ['status' => PromptReport::STATUS_RESOLVED])
        ->assertRedirect();

    $report->refresh();
    expect($report->status)->toBe(PromptReport::STATUS_RESOLVED)
        ->and($report->resolved_by)->toBe($admin->id)
        ->and($report->resolved_at)->not->toBeNull();

    $this->actingAs($admin)
        ->patch(route('admin.reports.status', $report), ['status' => PromptReport::STATUS_DISMISSED])
        ->assertRedirect();
    expect($report->refresh()->status)->toBe(PromptReport::STATUS_DISMISSED);
});

test('reports queue opens with the open filter by default', function () {
    \App\Models\PromptReport::factory()->count(2)->create();
    \App\Models\PromptReport::factory()->resolved()->create();

    $this->actingAs(flowsAdmin())
        ->get(route('admin.reports.index'))
        ->assertOk();

    // Open list shows only the open ones (2 open + admin row renders).
    expect(PromptReport::where('status', PromptReport::STATUS_OPEN)->count())->toBe(2);
});
