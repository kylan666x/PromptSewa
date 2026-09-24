<?php

use App\Models\Category;
use App\Models\LicenseGrant;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function validPromptPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Cinematic Product Photography Frames',
        'description' => 'Midjourney-ready prompts for dramatic product shots with studio rim lighting.',
        'category_id' => Category::factory()->create()->id,
        'type' => 'image',
        'body' => "Photorealistic product photograph of {{product}}.\n\n85mm, f/1.8, rim light, cinematic mood.",
        'tags' => 'midjourney, product, photography',
        'recommended_tools' => ['Midjourney', 'DALL-E'],
        'audience' => 'E-commerce sellers',
        'tips' => "Name the surface material explicitly\nRun variations at 4:5",
        'price_npr' => '499',
        'visibility' => 'public',
        'changelog' => '',
    ], $overrides);
}

function createCreator(): User
{
    return User::factory()->create(['role' => User::ROLE_CREATOR]);
}

// --------------------------------------------------------------- create flow

test('guests are redirected from the create form', function () {
    $this->get(route('dashboard.prompts.create'))->assertRedirect(route('login'));
});

test('create form renders type contexts and categories for creators', function () {
    $category = Category::factory()->create(['name' => 'Image Generation', 'type_scope' => 'image']);

    $this->actingAs(createCreator())
        ->get(route('dashboard.prompts.create'))
        ->assertOk()
        ->assertSee('Add a new prompt')
        ->assertSee('Image prompt', false)
        ->assertSee('Video prompt', false)
        ->assertSee($category->name);
});

test('store creates a pending prompt with an initial version and a product', function () {
    $creator = createCreator();

    $this->actingAs($creator)
        ->post(route('dashboard.prompts.store'), validPromptPayload())
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success');

    $prompt = Prompt::where('title', 'Cinematic Product Photography Frames')->first();

    expect($prompt)->not->toBeNull()
        ->and($prompt->user_id)->toBe($creator->id)
        ->and($prompt->status)->toBe(Prompt::STATUS_PENDING)
        ->and($prompt->type)->toBe('image')
        ->and($prompt->price_cents)->toBe(49900)
        ->and($prompt->license_tier)->toBe(Prompt::LICENSE_COMMERCIAL)
        ->and($prompt->slug)->toBe('cinematic-product-photography-frames')
        ->and($prompt->versions()->count())->toBe(1)
        ->and($prompt->latestVersion->body)->toContain('{{product}}')
        ->and($prompt->latestVersion->recommended_tools)->toBe(['Midjourney', 'DALL-E'])
        ->and($prompt->product()->where('status', 'active')->exists())->toBeTrue();
});

test('free prompts are created without a product', function () {
    $this->actingAs(createCreator())
        ->post(route('dashboard.prompts.store'), validPromptPayload(['price_npr' => '0']))
        ->assertRedirect(route('dashboard'));

    $prompt = Prompt::where('title', 'Cinematic Product Photography Frames')->first();

    expect($prompt->price_cents)->toBe(0)
        ->and($prompt->license_tier)->toBe(Prompt::LICENSE_PERSONAL)
        ->and($prompt->product()->exists())->toBeFalse();
});

test('store validates required fields and tool limits', function () {
    $this->actingAs(createCreator())
        ->post(route('dashboard.prompts.store'), validPromptPayload([
            'title' => '',
            'body' => 'short',
            'recommended_tools' => ['ChatGPT', 'Claude', 'Gemini', 'Midjourney', 'Sora'],
        ]))
        ->assertSessionHasErrors(['title', 'body', 'recommended_tools']);
});

test('slugs are made unique on collision', function () {
    Prompt::factory()->create(['slug' => 'cinematic-product-photography-frames']);

    $this->actingAs(createCreator())
        ->post(route('dashboard.prompts.store'), validPromptPayload())
        ->assertRedirect(route('dashboard'));

    $slugs = Prompt::where('slug', 'like', 'cinematic-product-photography-frames%')->pluck('slug');

    expect($slugs->count())->toBe(2)
        ->and($slugs->unique()->count())->toBe(2);
});

// ---------------------------------------------------------------- edit flow

test('non-owners cannot open the edit form', function () {
    $prompt = Prompt::factory()->hasVersion()->create();

    $this->actingAs(createCreator())
        ->get(route('dashboard.prompts.edit', $prompt))
        ->assertForbidden();
});

test('owners open a prefilled edit form', function () {
    $creator = createCreator();
    $prompt = Prompt::factory()->hasVersion()->create(['user_id' => $creator->id, 'title' => 'My Editable Probe']);
    $version = $prompt->latestVersion;
    $version->update(['tags' => ['editing', 'probe'], 'tips' => ['Tip one here'], 'audience' => 'Testers']);

    $this->actingAs($creator)
        ->get(route('dashboard.prompts.edit', $prompt))
        ->assertOk()
        ->assertSee('My Editable Probe')
        ->assertSee('editing, probe', false)
        ->assertSee('Tip one here')
        ->assertSee('Testers');
});

test('saving an edit appends a new version and never mutates old ones', function () {
    $creator = createCreator();
    $prompt = Prompt::factory()->hasVersion()->create(['user_id' => $creator->id]);
    $originalBody = $prompt->latestVersion->body;
    $originalVersionId = $prompt->latestVersion->id;

    $this->actingAs($creator)
        ->put(route('dashboard.prompts.update', $prompt), validPromptPayload([
            'body' => 'Brand new body text that is definitely longer than thirty characters.',
            'changelog' => 'Tightened the lighting grammar',
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $prompt->refresh();

    expect($prompt->versions()->count())->toBe(2)
        ->and($prompt->latestVersion->body)->toContain('Brand new body text')
        ->and($prompt->latestVersion->changelog)->toBe('Tightened the lighting grammar')
        // The original row is untouched (append-only history).
        ->and($prompt->versions()->find($originalVersionId)->body)->toBe($originalBody);
});

test('edit keeps pricing and the product in sync in both directions', function () {
    $creator = createCreator();
    $prompt = Prompt::factory()->hasVersion()->priced(29900)->create(['user_id' => $creator->id]);
    expect($prompt->product()->exists())->toBeFalse(); // factory does not create products

    // Start paid → product is created.
    $this->actingAs($creator)
        ->put(route('dashboard.prompts.update', $prompt), validPromptPayload(['price_npr' => '599']))
        ->assertRedirect();

    $product = $prompt->product()->first();
    expect($product)->not->toBeNull()->and($product->price_paisa)->toBe(59900);

    // Flip to free → product is archived.
    $this->actingAs($creator)
        ->put(route('dashboard.prompts.update', $prompt), validPromptPayload(['price_npr' => '0']))
        ->assertRedirect();

    expect($prompt->product()->first()->refresh()->status)->toBe('archived');
});

test('moderators can edit any prompt through the policy', function () {
    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $prompt = Prompt::factory()->hasVersion()->create();

    $this->actingAs($moderator)
        ->get(route('dashboard.prompts.edit', $prompt))
        ->assertOk();
});

// ------------------------------------------------- detail page body gating

test('guests see the full body of free prompts but only a teaser on paid ones', function () {
    $free = Prompt::factory()->hasVersion()->create(['price_cents' => 0, 'title' => 'Free Body Probe']);
    $free->latestVersion->update(['body' => 'FULL FREE BODY TEXT SHOULD BE VISIBLE']);

    $paid = Prompt::factory()->hasVersion()->priced(29900)->create(['title' => 'Paid Locked Probe']);
    $paid->latestVersion->update(['body' => "Line one is public teaser.\nSECRET LINE TWO\nSECRET LINE THREE"]);

    $this->get('/prompts/'.$free->slug)
        ->assertOk()
        ->assertSee('FULL FREE BODY TEXT SHOULD BE VISIBLE');

    $locked = $this->get('/prompts/'.$paid->slug)->assertOk();
    expect($locked->getContent())->toContain('The full prompt is locked')
        ->and($locked->getContent())->not->toContain('SECRET LINE THREE');
});

test('owners and license holders see the full paid body', function () {
    $owner = createCreator();
    $owned = Prompt::factory()->hasVersion()->priced(29900)->create(['user_id' => $owner->id]);
    $owned->latestVersion->update(['body' => 'OWNER SEES EVERYTHING HERE']);

    $this->actingAs($owner)->get('/prompts/'.$owned->slug)
        ->assertOk()
        ->assertSee('OWNER SEES EVERYTHING HERE');

    $buyer = User::factory()->create();
    $other = Prompt::factory()->hasVersion()->priced(29900)->create();
    $other->latestVersion->update(['body' => 'BUYER SEES EVERYTHING TOO']);

    LicenseGrant::factory()->create(['user_id' => $buyer->id, 'prompt_id' => $other->id]);

    $this->actingAs($buyer)->get('/prompts/'.$other->slug)
        ->assertOk()
        ->assertSee('BUYER SEES EVERYTHING TOO');
});

test('the detail page exposes variable fill-in for free prompts', function () {
    $prompt = Prompt::factory()->hasVersion()->create(['title' => 'Variable Probe']);
    $prompt->latestVersion->update(['body' => 'Write about {{topic}} for {{audience}}.']);

    $this->get('/prompts/'.$prompt->slug)
        ->assertOk()
        ->assertSee('Fill in the variables')
        ->assertSee('Topic', false)
        ->assertSee('Audience', false);
});
