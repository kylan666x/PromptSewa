<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/**
 * F2 (v1.9.2) — the profile-picture lightbox is a proper responsive modal.
 *
 * The founder's report: the preview was "ugly and unresponsive". The
 * contract this locks (all classes asserted from the SERVED HTML, on the
 * navbar instance and the hero instance alike):
 *
 *   - the modal paints the full-viewport scrim itself
 *     (`fixed inset-0 z-50 … bg-ink/80 backdrop-blur-sm`);
 *   - the content box is width-capped (`max-w-screen-md`, `w-full`, `mx-auto`);
 *   - the image is height-capped and never distorted
 *     (`max-h-[80vh] w-auto object-contain`) and can never exceed the box;
 *   - there is a visible Close control pinned to the modal's top-right.
 */
uses(RefreshDatabase::class);

/** The rendered `<div … data-testid="avatar-lightbox" …>` opening tag. */
function lightboxTag(string $html): string
{
    preg_match('/<div[^>]*data-testid="avatar-lightbox"[^>]*>/s', $html, $match);

    return $match[0] ?? '';
}

test('the lightbox is a responsive modal with a capped, undistorted image', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/owner.png']);
    Storage::disk('public')->put('avatars/owner.png', 'fake-image-bytes');

    $html = $this->actingAs($user)
        ->get(route('creators.show', $user))
        ->assertOk()
        ->getContent();

    // The modal ground: full viewport, above the dock (z-40) and navbar,
    // centred, ink scrim + blur.
    expect(lightboxTag($html))
        ->toContain('fixed inset-0 z-50 flex items-center justify-center bg-ink/80 backdrop-blur-sm')
        // A11y: it is a real dialog, dismissible with Escape.
        ->and($html)->toContain('@keydown.escape.window="lightbox = false"')
        ->and($html)->toContain('role="dialog" aria-modal="true"');

    // The content box: width-capped, padded, centred.
    expect($html)->toContain('class="relative mx-auto w-full max-w-screen-md p-4"');

    // The image: height-capped, aspect-true, never overflowing the box.
    preg_match('/<img[^>]*data-testid="avatar-lightbox-image"[^>]*>/s', $html, $img);
    $tag = $img[0] ?? '';
    expect($tag)->toContain('object-contain')
        ->and($tag)->toContain('max-h-[80vh]')
        ->and($tag)->toContain('w-auto')
        ->and($tag)->toContain('max-w-full')
        ->and($tag)->toContain('rounded-lg')
        ->and($tag)->toContain('shadow-2xl')
        // No fixed pixel box that a 512px upload could overflow.
        ->and($tag)->not->toContain('max-w-[90vw]');

    // The Close control is visible, pinned top-right, and labelled.
    expect($html)->toContain('data-testid="avatar-lightbox-close"')
        ->and($html)->toContain('aria-label="Close profile picture"')
        ->and($html)->toContain('class="absolute right-4 top-4 flex size-10 items-center justify-center rounded-full bg-white');
});

test('the navbar picture opens the same lightbox — one implementation, both homes', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('data-testid="nav-avatar-view"')
        ->and(lightboxTag($html))->toContain('fixed inset-0 z-50 flex items-center justify-center bg-ink/80 backdrop-blur-sm')
        ->and($html)->toContain('data-testid="avatar-lightbox-close"')
        // An upload-less account still gets a real lightbox body, not a hole.
        ->and($html)->toContain('data-testid="avatar-lightbox-image"');
});
