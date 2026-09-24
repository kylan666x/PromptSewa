<?php

use App\Models\Category;
use App\Models\Prompt;
use App\Models\User;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('demo seeder produces a credible marketplace library', function () {
    $this->seed(DemoContentSeeder::class);

    expect(User::where('role', User::ROLE_ADMIN)->count())->toBe(1)
        ->and(User::where('role', User::ROLE_CREATOR)->count())->toBe(3)
        ->and(Category::count())->toBe(12)
        ->and(Prompt::query()->publicListing()->count())->toBeGreaterThanOrEqual(20)
        ->and(Prompt::query()->publicListing()->where('type', Prompt::TYPE_IMAGE)->count())->toBeGreaterThanOrEqual(3)
        ->and(Prompt::query()->publicListing()->where('type', Prompt::TYPE_VIDEO)->count())->toBeGreaterThanOrEqual(1)
        ->and(Prompt::query()->publicListing()->where('price_cents', 0)->count())->toBeGreaterThanOrEqual(8)
        ->and(Prompt::query()->publicListing()->where('price_cents', '>', 0)->count())->toBeGreaterThanOrEqual(8)
        // Subcategories carry the type_scope used by the create form.
        ->and(Category::whereNotNull('type_scope')->count())->toBe(4)
        // Hidden seeds for visibility verification:
        ->and(Prompt::where('status', Prompt::STATUS_DRAFT)->count())->toBe(1)
        ->and(Prompt::where('status', Prompt::STATUS_PENDING)->count())->toBe(1)
        ->and(Prompt::where('visibility', Prompt::VISIBILITY_PRIVATE)->count())->toBe(1)
        ->and(Prompt::where('status', Prompt::STATUS_REJECTED)->count())->toBe(1);
});

test('every paid demo prompt has an active NPR product for checkout', function () {
    $this->seed(DemoContentSeeder::class);

    $paid = Prompt::query()->publicListing()->where('price_cents', '>', 0)->get();

    expect($paid->count())->toBeGreaterThanOrEqual(8);

    foreach ($paid as $prompt) {
        expect($prompt->product()->where('status', 'active')->where('currency', 'NPR')->exists())->toBeTrue();
    }
});

test('every seeded prompt has version history with tools and audience metadata', function () {
    $this->seed(DemoContentSeeder::class);

    $prompts = Prompt::query()->publicListing()->with(['latestVersion', 'versions'])->get();

    foreach ($prompts as $prompt) {
        expect($prompt->latestVersion)->not->toBeNull()
            ->and($prompt->latestVersion->toolList())->not->toBeEmpty()
            ->and($prompt->versions->count())->toBeGreaterThanOrEqual(1);
    }

    // At least two prompts demonstrate multi-version history.
    expect($prompts->filter(fn ($p) => $p->versions->count() > 1)->count())->toBeGreaterThanOrEqual(2);
});

test('demo seeder is idempotent when prompts already exist', function () {
    $this->seed(DemoContentSeeder::class);
    $countAfterFirstRun = Prompt::count();

    $this->seed(DemoContentSeeder::class);

    expect(Prompt::count())->toBe($countAfterFirstRun);
});

test('seeded prompt bodies survive seeding intact and hidden bodies never leak', function () {
    $this->seed(DemoContentSeeder::class);

    $public = Prompt::query()->publicListing()->with('latestVersion')->first();
    expect($public->latestVersion->body)->not->toBeEmpty();

    $hidden = Prompt::where('visibility', Prompt::VISIBILITY_PRIVATE)->with('latestVersion')->first();
    expect($hidden->latestVersion->body)->toContain('must never appear');
});
