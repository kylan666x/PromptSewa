<?php

use App\Models\Category;
use App\Models\LicenseGrant;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * R5 (v1.7.7 Bug Hunt Raid) — BH-001/BH-002: the comp grant form.
 *
 * FOUNDER REPRO (recorded before the fix): "cannot see all of my prompts".
 * ROOT CAUSE: `CompGrantController::create()` built the picker with
 * `Prompt::query()->published()` — the `published()` scope is
 * `where('status', STATUS_PUBLISHED)`, so every draft/pending/rejected
 * prompt was invisible in the picker — AND `store()` re-applied the same
 * scope (`Prompt::query()->published()->findOrFail()`), so even a
 * hand-posted draft id 404'd. A plain stacked <select> over 277 rows was
 * the second half of the complaint.
 *
 * The locks below pin the fix: every prompt id in the server-rendered
 * combobox payload, drafts and published prompts both grantable, status +
 * money_npr in the option text, idempotency, moderator 403, and a bounded
 * two-column form (no stacked full-width select taller than the grid).
 */

function raidCompPrompt(int $pricePaisa = 24_900, string $status = Prompt::STATUS_PUBLISHED): Prompt
{
    $owner = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $category = Category::factory()->create();

    return Prompt::factory()->for($owner, 'creator')->create([
        'title' => 'Comp Probe '.fake()->unique()->numberBetween(1, 99999),
        'status' => $status,
        'category_id' => $category->id,
        'price_cents' => $pricePaisa,
    ]);
}

test('the picker serves every prompt id in the combobox payload, including drafts', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $published = raidCompPrompt(status: Prompt::STATUS_PUBLISHED);
    $draft = raidCompPrompt(status: Prompt::STATUS_DRAFT);
    $pending = raidCompPrompt(status: Prompt::STATUS_PENDING);

    $html = $this->actingAs($admin)->get(route('admin.comp-grants.create'))->assertOk()->getContent();

    foreach ([$published, $draft, $pending] as $prompt) {
        expect(str_contains($html, 'data-value="'.$prompt->id.'"'))
            ->toBeTrue("prompt #{$prompt->id} ({$prompt->status}) is missing from the combobox payload");
    }
});

test('all 277 prompts render once into the combobox payload', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $owner = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $category = Category::factory()->create();

    Prompt::factory()->count(277)->for($owner, 'creator')->create([
        'status' => Prompt::STATUS_DRAFT,
        'category_id' => $category->id,
    ]);

    $html = $this->actingAs($admin)->get(route('admin.comp-grants.create'))->assertOk()->getContent();

    $ids = Prompt::query()->pluck('id');
    $missing = $ids->reject(fn ($id) => str_contains($html, 'data-value="'.$id.'"'));

    expect($missing)->toBeEmpty('the combobox payload dropped prompt ids: '.$missing->implode(','));
    expect(substr_count($html, 'role="option"'))->toBeGreaterThanOrEqual(277);
});

test('option text carries the title, a status chip and the money_npr price', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prompt = raidCompPrompt(pricePaisa: 24_900, status: Prompt::STATUS_DRAFT);
    $prompt->forceFill(['title' => 'Devanagari Money Probe'])->save();

    $html = $this->actingAs($admin)->get(route('admin.comp-grants.create'))->assertOk()->getContent();

    expect($html)->toContain('Devanagari Money Probe')
        ->and($html)->toContain('Rs. 249.00') // money_npr, not the legacy priceLabel
        ->and($html)->toContain('draft');
});

test('a draft and a published prompt are both grantable by an admin', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $buyer = User::factory()->create();
    $published = raidCompPrompt();
    $draft = raidCompPrompt(status: Prompt::STATUS_DRAFT);

    $this->actingAs($admin)->post(route('admin.comp-grants.store'), [
        'user_id' => $buyer->id,
        'prompt_id' => $published->id,
        'reason' => 'Published comp for the raid',
    ])->assertRedirect()->assertSessionHas('success');

    $this->actingAs($admin)->post(route('admin.comp-grants.store'), [
        'user_id' => $buyer->id,
        'prompt_id' => $draft->id,
        'reason' => 'Draft comp for the raid',
    ])->assertRedirect()->assertSessionHas('success');

    expect(LicenseGrant::query()->where('user_id', $buyer->id)->count())->toBe(2)
        ->and(LicenseGrant::query()->where('prompt_id', $draft->id)->exists())->toBeTrue();
});

test('granting is still idempotent at the HTTP boundary', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $buyer = User::factory()->create();
    $prompt = raidCompPrompt();

    $payload = ['user_id' => $buyer->id, 'prompt_id' => $prompt->id, 'reason' => 'Double submit'];

    $this->actingAs($admin)->post(route('admin.comp-grants.store'), $payload)->assertRedirect()->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.comp-grants.store'), $payload)->assertRedirect()->assertSessionHasErrors('user_id');

    expect(LicenseGrant::query()->where('user_id', $buyer->id)->count())->toBe(1);
});

test('the reason stays mandatory', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $buyer = User::factory()->create();
    $prompt = raidCompPrompt();

    $this->actingAs($admin)->post(route('admin.comp-grants.store'), [
        'user_id' => $buyer->id,
        'prompt_id' => $prompt->id,
        'reason' => '',
    ])->assertSessionHasErrors('reason');

    expect(LicenseGrant::query()->count())->toBe(0);
});

test('moderators are 403 on the page and the store', function () {
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $buyer = User::factory()->create();
    $prompt = raidCompPrompt();

    $this->actingAs($mod)->get(route('admin.comp-grants.create'))->assertForbidden();
    $this->actingAs($mod)->post(route('admin.comp-grants.store'), [
        'user_id' => $buyer->id,
        'prompt_id' => $prompt->id,
        'reason' => 'mod overreach',
    ])->assertForbidden();

    expect(LicenseGrant::query()->count())->toBe(0);
});

test('the form is a bounded two-column combobox grid, not a stacked mega-select', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    raidCompPrompt();

    $html = $this->actingAs($admin)->get(route('admin.comp-grants.create'))->assertOk()->getContent();

    // The old stacked full-width mega-select is gone.
    expect($html)->not->toContain('<select name="prompt_id"')
        ->and($html)->not->toContain('<select name="user_id"');

    // Both pickers are two-up on one row, each a real hidden input + combobox.
    expect($html)->toContain('sm:grid-cols-2')
        ->and($html)->toContain('name="user_id"')
        ->and($html)->toContain('name="prompt_id"')
        ->and($html)->toContain('role="combobox"')
        ->and($html)->toContain('role="listbox"');

    // The option list is height-bounded (277 rows cannot stretch the page).
    expect(preg_match('/role="listbox"[^>]*\bmax-h-/', $html) === 1
        || preg_match('/\bmax-h-[^"]*"[^>]*role="listbox"/', $html) === 1)
        ->toBeTrue('the option list must carry a max-height so the form fits one screen');

    // A single submit row: exactly one submit button on the page.
    expect(substr_count($html, 'type="submit"'))->toBe(1);
});
