<?php

use App\Models\Frame;
use App\Models\Prompt;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * G6 (v1.7.0) / W1 (v1.7.3) — profile frames.
 *
 * Surfaces ruling REVERSED by W1 (v1.7.3) FRAME SURFACE PARITY: the frame
 * ring now renders on EVERY avatar surface — profile hero, navbar dropdown
 * (both rows), dock "You", feed actors, prompt cards (both variants),
 * image-gallery cards, library creators grid, versions author, purchases
 * rows, admin users table and typeahead rows. Cards are framed now.
 */

function framePng(): UploadedFile
{
    // A real 64px transparent PNG built with GD (the alpha test's source).
    $img = imagecreatetruecolor(64, 64);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 255, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    $ring = imagecolorallocate($img, 255, 168, 0);
    imagesetthickness($img, 6);
    imagerectangle($img, 3, 3, 60, 60, $ring);

    ob_start();
    imagepng($img);
    $binary = (string) ob_get_clean();
    imagedestroy($img);

    return UploadedFile::fake()->createWithContent('frame.png', $binary);
}

test('frame uploads preserve alpha through the image pipeline', function () {
    Storage::fake('public');

    $path = app(ImageUploadService::class)->store(framePng(), 'frame');

    Storage::disk('public')->assertExists($path);

    // The stored file must still be a PNG carrying alpha (NOT flattened JPEG).
    $binary = Storage::disk('public')->get($path);
    expect(str_starts_with($binary, "\x89PNG"))->toBeTrue('frame variant must stay PNG');

    $decoded = imagecreatefromstring($binary);
    imagealphablending($decoded, false);
    imagesavealpha($decoded, true);

    // Center pixel: transparent in the source ring — alpha preserved?
    $center = imagecolorat($decoded, 32, 32);
    expect(($center >> 24) & 0x7F)->toBeGreaterThan(0, 'transparency was flattened — alpha lost');
});

test('jpeg sources are rejected for badge and frame variants', function () {
    Storage::fake('public');

    $jpeg = UploadedFile::fake()->image('photo.jpg');

    expect(fn () => app(ImageUploadService::class)->store($jpeg, 'badge'))
        ->toThrow(RuntimeException::class);

    expect(fn () => app(ImageUploadService::class)->store($jpeg, 'frame'))
        ->toThrow(RuntimeException::class);
});

test('frame overlay renders on every avatar surface including prompt cards', function () {
    Storage::fake('public');
    $path = Storage::disk('public')->putFileAs('frames', framePng(), 'ring.png');

    $frame = Frame::query()->create(['name' => 'Saffron ring', 'image_path' => $path, 'is_active' => true]);
    $creator = User::factory()->for($frame, 'activeFrame')->create();

    $prompt = Prompt::factory()->for($creator, 'creator')->draft()->create();
    $prompt->update(['status' => Prompt::STATUS_PUBLISHED]);

    $frameUrl = Storage::disk('public')->url($path);

    // 1. Profile hero.
    expect($this->get(route('creators.show', $creator))->getContent())->toContain($frameUrl)
        // Feed actor avatars (prompt publish emits an event).
        ->and($this->get(route('feed.index'))->getContent())->toContain($frameUrl)
        // Versions author row.
        ->and($this->get(route('prompts.versions', $prompt))->getContent())->toContain($frameUrl);

    // 2. Navbar dropdown (authenticated) — dashboard page hosts both rows.
    $navbar = $this->actingAs($creator)->get(route('dashboard'))->getContent();
    expect($navbar)->toContain($frameUrl);

    // 3. Dock "You" avatar — the dashboard profile page hosts the You menu.
    expect($this->actingAs($creator)->get(route('dashboard.profile.edit'))->getContent())->toContain($frameUrl);

    // W1 PARITY: the LIBRARY page — every prompt card in the grid now
    // carries the creator's frame (desktop dropdown + mobile row + the
    // published card on page 1 = 3 occurrences minimum on this fixture).
    $library = $this->get(route('library.index'))->getContent();
    expect($library)->toContain($creator->name)
        ->and(substr_count($library, $frameUrl))->toBeGreaterThanOrEqual(3, 'prompt cards must carry the creator frame (W1 parity)');

    // Storefront cards (featured + image gallery) carry it too.
    expect($this->get(route('home'))->getContent())->toContain($frameUrl);

    // Admin users table.
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    expect($this->actingAs($admin)->get(route('admin.users.index'))->getContent())->toContain($frameUrl);

    // Typeahead JSON carries the frame URL for the client row overlay.
    $preview = $this->get(route('search.preview', ['q' => $creator->name]));
    expect($preview->json('creators.0.frame_url'))->toBe($frameUrl);
});

test('users can select and clear a frame from profile edit', function () {
    Storage::fake('public');
    $path = Storage::disk('public')->putFileAs('frames', framePng(), 'ring2.png');
    $frame = Frame::query()->create(['name' => 'Bronze', 'image_path' => $path, 'is_active' => true]);

    $user = User::factory()->create();

    // Select.
    $this->actingAs($user)->put(route('dashboard.profile.update'), [
        'name' => $user->name,
        'username' => $user->username,
        'active_frame_id' => $frame->id,
    ])->assertRedirect();

    expect($user->refresh()->active_frame_id)->toBe($frame->id);

    // Clear (none).
    $this->actingAs($user)->put(route('dashboard.profile.update'), [
        'name' => $user->name,
        'username' => $user->username,
        'active_frame_id' => null,
    ])->assertRedirect();

    expect($user->refresh()->active_frame_id)->toBeNull();
});

test('deleting a frame falls users back to no frame without breaking them', function () {
    $frame = Frame::query()->create(['name' => 'Temp', 'image_path' => 'frames/temp.png', 'is_active' => true]);
    $user = User::factory()->for($frame, 'activeFrame')->create();

    $frame->delete();

    expect($user->refresh()->active_frame_id)->toBeNull()
        ->and($user->exists())->toBeTrue();
});
