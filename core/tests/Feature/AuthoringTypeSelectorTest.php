<?php

use App\Models\Category;
use App\Models\Prompt;
use App\Models\ToolLogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A3 (v1.7.2) — "The Type Selector, For Real".
 *
 * Every assertion in this file hits the SERVED ROUTE HTML or a real
 * POST/PUT round-trip. Asserting a component/template file's contents is
 * BANNED here — that is the exists-but-not-included failure class
 * (fourth occurrence; see handoff §6 watch-out).
 */
uses(RefreshDatabase::class);

function a3Creator(): User
{
    return User::factory()->create(['role' => User::ROLE_CREATOR]);
}

// ---------------------------------------------------------------------------
// A1 — server-rendered type cards on create AND edit
// ---------------------------------------------------------------------------

test('create serves five real type radios with the text one checked', function () {
    $html = $this->actingAs(a3Creator())->get(route('dashboard.prompts.create'))->getContent();

    // Five REAL inputs (not a JS template) — the served HTML itself carries
    // them, so the form is submittable with JavaScript disabled.
    expect(substr_count($html, 'type="radio" name="type"'))->toBe(5)
        ->and($html)->not->toContain('<template x-for="(ctx, key) in contexts"');

    foreach (['text', 'image', 'video', 'agentic', 'skill'] as $type) {
        expect(substr_count($html, 'name="type" value="'.$type.'"'))->toBe(1);
    }

    // Create defaults to text — checked in the SERVED html.
    expect(preg_match('/<input type="radio" name="type" value="text" checked/', $html))->toBe(1)
        ->and(preg_match('/<input type="radio" name="type" value="image" checked/', $html))->toBe(0);

    // Mono label + one-line description rendered per card.
    expect($html)->toContain('Text prompt')
        ->and($html)->toContain('Image prompt')
        ->and($html)->toContain('Video prompt')
        ->and($html)->toContain('Agentic workflow')
        ->and($html)->toContain('Skill / framework');
});

test('edit serves the stored type checked in the HTML, not via JS', function () {
    $creator = a3Creator();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->ofType(Prompt::TYPE_VIDEO)->create();

    $html = $this->actingAs($creator)->get(route('dashboard.prompts.edit', $prompt))->getContent();

    expect(substr_count($html, 'type="radio" name="type"'))->toBe(5)
        ->and(preg_match('/<input type="radio" name="type" value="video" checked/', $html))->toBe(1)
        ->and(preg_match('/<input type="radio" name="type" value="text" checked/', $html))->toBe(0);
});

// ---------------------------------------------------------------------------
// A2 — type-driven adaptive regions in the served HTML
// ---------------------------------------------------------------------------

test('tool chips carry data-modality and the modality gate expression', function () {
    ToolLogo::query()->firstOrCreate(['name' => 'Midjourney'], ['modality' => 'image', 'is_active' => true, 'position' => 10]);
    ToolLogo::query()->firstOrCreate(['name' => 'ChatGPT'], ['modality' => 'text', 'is_active' => true, 'position' => 11]);

    $html = $this->actingAs(a3Creator())->get(route('dashboard.prompts.create'))->getContent();

    expect($html)->toContain(':data-modality="entry.modality"')
        ->and($html)->toContain("x-show=\"entry.modality === type || entry.modality === 'any'");
});

test('cover block is served inside the form and hidden for non-image types without JS', function () {
    $html = $this->actingAs(a3Creator())->get(route('dashboard.prompts.create'))->getContent();

    // The cover upload block ships in the served HTML…
    expect($html)->toContain('data-cover-only')
        ->and($html)->toContain('name="cover_image"')
        // …and NO type radio is checked image in the served HTML (so the
        // block's show rule cannot apply on first paint).
        ->and(preg_match('/name="type" value="image" checked/', $html))->toBe(0);

    // The zero-JS show/hide contract ships in the BUILT stylesheet the
    // page serves via @vite (resolved through the manifest, not guessed).
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/public/build/manifest.json'), true);
    $cssAsset = $manifest['resources/css/app.css']['file'] ?? null;
    expect($cssAsset)->not->toBeNull('built CSS asset missing — run npm run build');

    $builtCss = (string) file_get_contents(dirname(__DIR__, 2).'/public/build/'.$cssAsset);
    // The minifier drops attribute-VALUE quotes; match the minified form.
    expect($builtCss)->toContain('form:has(input[name=type][value=image]:checked)')
        ->and($builtCss)->toContain('data-cover-only]{display:block}')
        ->and($builtCss)->toContain('data-cover-only]{display:none}');
});

test('editing an image prompt serves the cover block, preview and remove toggle', function () {
    Storage::fake('public');
    $creator = a3Creator();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->ofType(Prompt::TYPE_IMAGE)->create([
        'cover_image_path' => 'covers/edit-probe.png',
    ]);
    Storage::disk('public')->put('covers/edit-probe.png', 'png-bytes');

    $html = $this->actingAs($creator)->get(route('dashboard.prompts.edit', $prompt))->getContent();

    // Image pre-checked (A1) → cover block visible via the same zero-JS rule.
    expect(preg_match('/name="type" value="image" checked/', $html))->toBe(1)
        ->and($html)->toContain('data-cover-only')
        // Existing cover preview + remove toggle (server-rendered).
        ->and($html)->toContain('data-cover-preview')
        ->and($html)->toContain(Storage::url('covers/edit-probe.png'))
        ->and($html)->toContain('name="remove_cover"');
});

test('category options are server-rendered and scoped per type in the markup', function () {
    $imageCat = Category::factory()->create(['name' => 'Image Generation', 'type_scope' => 'image']);
    $textCat = Category::factory()->create(['name' => 'Writing', 'type_scope' => 'text']);

    $html = $this->actingAs(a3Creator())->get(route('dashboard.prompts.create'))->getContent();

    // Options are real <option> nodes (server truth), each scoped option
    // carrying its own Alpine re-scope gate.
    expect(preg_match('/<option value="'.$imageCat->id.'"[^>]*x-show="type === \'image\'"/s', $html))->toBe(1)
        ->and(preg_match('/<option value="'.$textCat->id.'"[^>]*x-show="type === \'text\'"/s', $html))->toBe(1)
        ->and($html)->toContain('Image Generation')
        ->and($html)->toContain('Writing');
});

// ---------------------------------------------------------------------------
// A3 — server-side validation round-trips
// ---------------------------------------------------------------------------

function a3ImagePayload(int $categoryId): array
{
    return [
        'title' => 'Cinematic Product Photography Frames',
        'description' => 'Midjourney-ready prompts for dramatic product shots with studio rim lighting.',
        'category_id' => $categoryId,
        'type' => 'image',
        'body' => "Photorealistic product photograph of {{product}}.\n\n85mm, f/1.8, rim light, cinematic mood.",
        'tags' => 'midjourney, product, photography',
        'recommended_tools' => ['Midjourney'],
        'price_npr' => '0',
        'visibility' => 'public',
    ];
}

test('posting create with type image and a png cover stores both', function () {
    Storage::fake('public');
    $category = Category::factory()->create(['type_scope' => 'image']);

    $png = imagecreatetruecolor(120, 90);
    imagefill($png, 0, 0, imagecolorallocate($png, 245, 197, 24));
    ob_start();
    imagepng($png);
    $binary = (string) ob_get_clean();
    imagedestroy($png);

    $this->actingAs(a3Creator())
        ->post(route('dashboard.prompts.store'), a3ImagePayload($category->id) + [
            'cover_image' => UploadedFile::fake()->createWithContent('cover.png', $binary),
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    $prompt = Prompt::where('title', 'Cinematic Product Photography Frames')->first();

    expect($prompt)->not->toBeNull()
        ->and($prompt->type)->toBe(Prompt::TYPE_IMAGE)
        ->and($prompt->cover_image_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists($prompt->cover_image_path))->toBeTrue();
});

test('posting create with an image-modality tool on type text is a validation error', function () {
    $category = Category::factory()->create(['type_scope' => 'text']);
    ToolLogo::query()->firstOrCreate(['name' => 'Midjourney'], ['modality' => 'image', 'is_active' => true, 'position' => 20]);

    $payload = a3ImagePayload($category->id);
    $payload['type'] = 'text';
    $payload['body'] = 'A text prompt body long enough to clear the validation floor easily.';
    $payload['tags'] = 'writing, probe';

    $this->actingAs(a3Creator())
        ->post(route('dashboard.prompts.store'), $payload)
        ->assertSessionHasErrors('recommended_tools.0');
});

test('editing text to image without a cover is a validation error', function () {
    $creator = a3Creator();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->ofType(Prompt::TYPE_TEXT)->create();

    $this->actingAs($creator)
        ->put(route('dashboard.prompts.update', $prompt), [
            'title' => $prompt->title,
            'description' => 'A deliberately long description that always clears the forty character floor.',
            'category_id' => $prompt->category_id,
            'type' => Prompt::TYPE_IMAGE,
            'body' => 'Switched body for the image conversion probe, long enough.',
            'tags' => 'switch, probe',
            'recommended_tools' => ['Midjourney'],
            'price_npr' => '0',
            'visibility' => 'public',
        ])
        ->assertSessionHasErrors('cover_image');
});

test('editing text to image with a cover passes and persists the switch', function () {
    Storage::fake('public');
    $creator = a3Creator();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->ofType(Prompt::TYPE_TEXT)->create();

    $png = imagecreatetruecolor(80, 80);
    imagefill($png, 0, 0, imagecolorallocate($png, 224, 168, 0));
    ob_start();
    imagepng($png);
    $binary = (string) ob_get_clean();
    imagedestroy($png);

    $this->actingAs($creator)
        ->put(route('dashboard.prompts.update', $prompt), [
            'title' => $prompt->title,
            'description' => 'A deliberately long description that always clears the forty character floor.',
            'category_id' => $prompt->category_id,
            'type' => Prompt::TYPE_IMAGE,
            'body' => 'Switched body for the image conversion probe, long enough.',
            'tags' => 'switch, probe',
            'recommended_tools' => ['Midjourney'],
            'price_npr' => '0',
            'visibility' => 'public',
            'cover_image' => UploadedFile::fake()->createWithContent('cover.png', $binary),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $prompt->refresh();
    expect($prompt->type)->toBe(Prompt::TYPE_IMAGE)
        ->and($prompt->cover_image_path)->not->toBeNull();
});

test('a category scoped to another type is rejected server-side', function () {
    $textCat = Category::factory()->create(['name' => 'Writing', 'type_scope' => 'text']);

    $payload = a3ImagePayload($textCat->id);
    $payload['body'] = str_repeat('Image body line. ', 4);
    $payload['tags'] = 'probe, mismatch';

    $this->actingAs(a3Creator())
        ->post(route('dashboard.prompts.store'), $payload)
        ->assertSessionHasErrors('category_id');
});
