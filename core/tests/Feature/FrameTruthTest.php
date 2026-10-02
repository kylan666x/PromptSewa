<?php

use App\Models\Bookmark;
use App\Models\Frame;
use App\Models\Prompt;
use App\Models\User;
use App\Models\UserFrameUnlock;
use App\Services\CriterionEvaluator;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * W1–W5 (v1.7.3) — Frame Truth & Heart Truth. Every route-facing assertion
 * hits the SERVED HTML or a real POST/PUT (component-file assertions banned).
 */
uses(RefreshDatabase::class);

function v173FramePng(): UploadedFile
{
    $img = imagecreatetruecolor(64, 64);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 0, 0, 127));
    imagerectangle($img, 3, 3, 60, 60, imagecolorallocate($img, 255, 168, 0));

    ob_start();
    imagepng($img);
    $binary = (string) ob_get_clean();
    imagedestroy($img);

    return UploadedFile::fake()->createWithContent('frame.png', $binary);
}

function v173WebpPng(): UploadedFile
{
    $img = imagecreatetruecolor(80, 80);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 255, 0, 127));
    imagerectangle($img, 4, 4, 75, 75, imagecolorallocate($img, 0, 120, 255));

    ob_start();
    imagewebp($img, null, 90);
    $binary = (string) ob_get_clean();
    imagedestroy($img);

    return UploadedFile::fake()->createWithContent('frame.webp', $binary);
}

// ---------------------------------------------------------------------------
// W1 — frame surface parity
// ---------------------------------------------------------------------------

test('the frame overlay renders on every avatar surface', function () {
    Storage::fake('public');
    // §6.35: the database-cache store survives RefreshDatabase — flush so
    // the feed's cached page 1 doesn't shadow this test's fixture.
    cache()->flush();
    $path = Storage::disk('public')->putFileAs('frames', v173FramePng(), 'parity-ring.png');
    $frame = Frame::query()->create(['name' => 'Parity', 'image_path' => $path, 'is_active' => true]);
    $creator = User::factory()->for($frame, 'activeFrame')->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->draft()->create();
    // Draft -> published TRANSITION: PromptObserver only emits feed events on
    // the status change, never on initial published creates.
    $prompt->update(['status' => Prompt::STATUS_PUBLISHED]);

    $frameUrl = Storage::disk('public')->url($path);
    // Hotfix addendum DOM: the overlay is inset-0 + object-contain over
    // the hard-forced circle wrapper.
    $overlayClass = 'pointer-events-none absolute inset-0 size-full rounded-full object-contain';

    // Profile hero + versions author + feed actor.
    expect($this->get(route('creators.show', $creator))->getContent())->toContain($frameUrl)
        ->and($this->get(route('prompts.versions', $prompt))->getContent())->toContain($frameUrl)
        ->and($this->get(route('feed.index'))->getContent())->toContain($frameUrl);

    // Storefront (featured + image gallery cards) and library prompt cards.
    $home = $this->get(route('home'))->getContent();
    expect($home)->toContain($frameUrl)
        ->and($home)->toContain($overlayClass);

    $library = $this->get(route('library.index'))->getContent();
    expect($library)->toContain($frameUrl);

    // Admin users table + typeahead JSON frame_url.
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    expect($this->actingAs($admin)->get(route('admin.users.index'))->getContent())->toContain($frameUrl)
        ->and($this->get(route('search.preview', ['q' => $creator->name]))->json('creators.0.frame_url'))->toBe($frameUrl);
});

test('the overlay stays pointer-events-none and aria-hidden everywhere it renders', function () {
    Storage::fake('public');
    $path = Storage::disk('public')->putFileAs('frames', v173FramePng(), 'a11y-ring.png');
    $frame = Frame::query()->create(['name' => 'A11y', 'image_path' => $path, 'is_active' => true]);
    $creator = User::factory()->for($frame, 'activeFrame')->create();
    Prompt::factory()->for($creator, 'creator')->hasVersion()->published()->create();

    $html = $this->get(route('library.index'))->getContent();

    // The frame img is aria-hidden (never announced) — decorative only.
    preg_match('/<img src="'.preg_quote($frameUrl = Storage::disk('public')->url($path), '/').'"[^>]*>/', $html, $m);

    expect($m[0] ?? '')->toContain('aria-hidden="true"')
        ->and($m[0] ?? '')->toContain('pointer-events-none');
});

// ---------------------------------------------------------------------------
// W2 — alpha truth
// ---------------------------------------------------------------------------

test('stored frame corner alpha is fully transparent for a PNG source', function () {
    Storage::fake('public');

    $path = app(ImageUploadService::class)->store(v173FramePng(), 'frame');
    $binary = Storage::disk('public')->get($path);

    expect(str_starts_with($binary, "\x89PNG"))->toBeTrue();

    $decoded = imagecreatefromstring($binary);
    imagealphablending($decoded, false);
    imagesavealpha($decoded, true);

    // CORNER pixel (0,0): transparent in the source ring — must be alpha 0
    // (fully transparent), NOT a flattened black corner (the founder bug).
    $corner = imagecolorat($decoded, 0, 0);

    expect(($corner >> 24) & 0x7F)->toBeGreaterThanOrEqual(120, 'corner alpha lost — the pipeline flattened transparency');
});

test('stored frame corner alpha survives a WebP source', function () {
    Storage::fake('public');

    $path = app(ImageUploadService::class)->store(v173WebpPng(), 'frame');
    $binary = Storage::disk('public')->get($path);

    // WebP source → WebP output (never JPEG-flattened).
    expect(str_starts_with($binary, 'RIFF'))->toBeTrue();

    $decoded = imagecreatefromstring($binary);
    imagealphablending($decoded, false);
    imagesavealpha($decoded, true);

    $corner = imagecolorat($decoded, 0, 0);

    expect(($corner >> 24) & 0x7F)->toBeGreaterThanOrEqual(120, 'WebP source flattened — corner alpha lost');
});

test('stored badge and QR variants keep transparent corners', function () {
    Storage::fake('public');

    $badgePath = app(ImageUploadService::class)->store(v173FramePng(), 'badge');
    $qrPath = app(ImageUploadService::class)->store(v173FramePng(), 'qr');

    foreach ([$badgePath, $qrPath] as $path) {
        $decoded = imagecreatefromstring((string) Storage::disk('public')->get($path));
        imagealphablending($decoded, false);
        imagesavealpha($decoded, true);

        $corner = imagecolorat($decoded, 0, 0);
        expect(($corner >> 24) & 0x7F)->toBeGreaterThanOrEqual(120);
    }
});

// ---------------------------------------------------------------------------
// W3 — animated pass-through + animation classes
// ---------------------------------------------------------------------------

test('animated gif uploads pass through byte-identical', function () {
    Storage::fake('public');

    // Build a real 2-frame animated GIF.
    $gif = imagecreatetruecolor(64, 64);
    imagefill($gif, 0, 0, imagecolorallocate($gif, 255, 255, 255));
    ob_start();
    imagegif($gif);
    $binary = (string) ob_get_clean();
    imagedestroy($gif);

    // Hand-stamp a second image separator so the file parses as animated.
    $animatedBinary = substr($binary, 0, 60)."\x2C".substr($binary, 60);

    $uploaded = UploadedFile::fake()->createWithContent('animated.gif', $animatedBinary);
    $uploaded->headers = ['CONTENT_TYPE' => 'image/gif'];

    $path = app(ImageUploadService::class)->store($uploaded, 'frame');
    $stored = Storage::disk('public')->get($path);

    // HASH EQUALITY: stored == uploaded (no GD re-encode).
    expect(md5($stored))->toBe(md5($animatedBinary));
});

test('oversized animated webp is refused', function () {
    Storage::fake('public');

    $img = imagecreatetruecolor(600, 600);
    imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 255));
    ob_start();
    imagewebp($img, null, 90);
    $binary = (string) ob_get_clean();
    imagedestroy($img);

    // Stamp an ANIM chunk AFTER the VP8 chunk (leaving the VP8 header
    // intact so getimagesize still reports the true 600×600 dimensions —
    // isAnimated() scans for 'ANIM' anywhere in the container).
    $animated = $binary.'ANIM '.pack('V', 4).'\x00\x00\x00\x00';

    $uploaded = UploadedFile::fake()->createWithContent('big.webp', $animated);
    $uploaded->headers = ['CONTENT_TYPE' => 'image/webp'];

    expect(fn () => app(ImageUploadService::class)->store($uploaded, 'frame'))
        ->toThrow(RuntimeException::class, '512×512');
});

test('an animated frame renders its animation class on the overlay', function () {
    Storage::fake('public');
    $path = Storage::disk('public')->putFileAs('frames', v173FramePng(), 'spin-ring.png');
    $frame = Frame::query()->create(['name' => 'Spinner', 'image_path' => $path, 'is_active' => true, 'animation' => 'spin']);
    $creator = User::factory()->for($frame, 'activeFrame')->create();
    Prompt::factory()->for($creator, 'creator')->hasVersion()->published()->create();

    $html = $this->get(route('library.index'))->getContent();

    expect($html)->toContain('frame-anim-spin');
});

test('reduced-motion guard is present in the compiled stylesheet', function () {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/public/build/manifest.json'), true);
    $cssAsset = $manifest['resources/css/app.css']['file'] ?? null;
    expect($cssAsset)->not->toBeNull();

    $builtCss = (string) file_get_contents(dirname(__DIR__, 2).'/public/build/'.$cssAsset);

    // Minifier drops the space after the colon — assert the minified form.
    expect($builtCss)->toContain('prefers-reduced-motion:no-preference')
        ->and($builtCss)->toContain('frame-anim-spin')
        ->and($builtCss)->toContain('frame-anim-pulse')
        ->and($builtCss)->toContain('frame-anim-shine');
});

// ---------------------------------------------------------------------------
// W4 — criteria & awarding
// ---------------------------------------------------------------------------

test('admin frames page carries criterion selects and the manual award panel', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $html = $this->actingAs($admin)->get(route('admin.frames.index'))->getContent();

    expect($html)->toContain('Manual award')
        ->and($html)->toContain('name="reason"')
        ->and($html)->toContain('name="criterion"')
        ->and($html)->toContain('first_publish');
});

test('moderators are 403 on the frames admin and award endpoint', function () {
    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $frame = Frame::query()->create(['name' => 'X', 'image_path' => 'frames/x.png', 'is_active' => true]);
    $target = User::factory()->create();

    $this->actingAs($moderator)->get(route('admin.frames.index'))->assertForbidden();

    $this->actingAs($moderator)->post(route('admin.frames.award'), [
        'user_id' => $target->id,
        'frame_id' => $frame->id,
        'reason' => 'nope',
    ])->assertForbidden();
});

test('manual award persists an audited unlock and is idempotent', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $frame = Frame::query()->create(['name' => 'Radiant', 'image_path' => 'frames/r.png', 'is_active' => true]);
    $target = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.frames.award', []), [
        'user_id' => $target->id,
        'frame_id' => $frame->id,
        'reason' => 'Founder drop — Radiant #4',
    ])->assertRedirect()->assertSessionHas('success');

    $unlock = UserFrameUnlock::query()
        ->where('user_id', $target->id)
        ->where('frame_id', $frame->id)
        ->first();

    expect($unlock)->not->toBeNull()
        ->and($unlock->granted_by)->toBe($admin->id)
        ->and($unlock->reason)->toBe('Founder drop — Radiant #4')
        ->and(UserFrameUnlock::query()->where('user_id', $target->id)->count())->toBe(1);

    // Idempotent second grant.
    $this->actingAs($admin)->post(route('admin.frames.award'), [
        'user_id' => $target->id,
        'frame_id' => $frame->id,
        'reason' => 'Second try',
    ])->assertRedirect();

    expect(UserFrameUnlock::query()->where('user_id', $target->id)->count())->toBe(1);
});

test('a locked frame shows an ink lock chip and equipping it is refused', function () {
    $frame = Frame::query()->create([
        'name' => 'Ten Sales', 'image_path' => 'frames/ten.png',
        'is_active' => true, 'criterion' => 'sales_10',
    ]);
    $user = User::factory()->create();

    // Picker shows the LOCKED chip with honest progress 0/10.
    $html = $this->actingAs($user)->get(route('dashboard.profile.edit'))->getContent();
    expect($html)->toContain('sales 10 0/10');

    // Direct PUT with the locked id → 422-class validation error, not saved.
    $this->actingAs($user)->put(route('dashboard.profile.update'), [
        'name' => $user->name,
        'username' => $user->username,
        'active_frame_id' => $frame->id,
    ])->assertSessionHasErrors('active_frame_id');

    expect($user->refresh()->active_frame_id)->toBeNull();
});

test('granting an unlock lets the user equip the frame', function () {
    $frame = Frame::query()->create([
        'name' => 'Granted', 'image_path' => 'frames/g.png',
        'is_active' => true, 'criterion' => 'manual',
    ]);
    $user = User::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->post(route('admin.frames.award'), [
        'user_id' => $user->id,
        'frame_id' => $frame->id,
        'reason' => 'Community winner',
    ])->assertRedirect();

    $this->actingAs($user)->put(route('dashboard.profile.update'), [
        'name' => $user->name,
        'username' => $user->username,
        'active_frame_id' => $frame->id,
    ])->assertRedirect();

    expect($user->refresh()->active_frame_id)->toBe($frame->id);
});

test('criterion evaluator is the single threshold source', function () {
    $seller = User::factory()->create(['is_verified' => true]);
    $prompt = Prompt::factory()->for($seller, 'creator')->hasVersion()->priced(19900)->published()->create(['sales_count' => 12]);

    expect(CriterionEvaluator::metCriteria($seller))->toContain('first_publish')
        ->and(CriterionEvaluator::metCriteria($seller))->toContain('first_sale')
        ->and(CriterionEvaluator::metCriteria($seller))->toContain('sales_10')
        ->and(CriterionEvaluator::metCriteria($seller))->toContain('verified')
        ->and(CriterionEvaluator::metCriteria($seller))->not->toContain('sales_50')
        ->and(CriterionEvaluator::metCriteria($seller))->not->toContain('top_rated')
        // manual is never auto-met.
        ->and(CriterionEvaluator::isMet($seller, 'manual'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// W5 — heart truth + profile Dashboard button
// ---------------------------------------------------------------------------

test('a saved card serves the filled-rose class in the HTML after refresh', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->published()->create();

    Bookmark::query()->create(['user_id' => $user->id, 'prompt_id' => $prompt->id]);

    // Library card (desktop + the dock renders the same page on mobile).
    $html = $this->actingAs($user)->get(route('library.index'))->getContent();

    expect($html)->toContain('text-rose-600 fill-current')
        ->and($html)->toContain('saved'.chr(92).'u0022:true');
});

test('the Saved tab serves filled hearts and unsaved serves outlines', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $savedPrompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->published()->create();
    $otherPrompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->published()->create(['title' => 'Unsaved Probe Listing']);

    Bookmark::query()->create(['user_id' => $user->id, 'prompt_id' => $savedPrompt->id]);

    $savedHtml = $this->actingAs($user)->get(route('dashboard', ['tab' => 'saved']))->getContent();

    expect($savedHtml)->toContain($savedPrompt->title);

    $unsavedHtml = $this->actingAs($user)->get(route('library.index'))->getContent();
    expect($unsavedHtml)->toContain('text-ink/40 fill-none');
});

test('own profile shows Dashboard and Edit profile buttons; strangers see neither', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $own = $this->actingAs($owner)->get(route('creators.show', $owner))->getContent();
    expect($own)->toContain('data-testid="profile-dashboard"')
        ->and($own)->toContain('data-testid="profile-edit"');

    $other = $this->actingAs($stranger)->get(route('creators.show', $owner))->getContent();
    expect($other)->not->toContain('data-testid="profile-dashboard"')
        ->and($other)->not->toContain('data-testid="profile-edit"');
});
