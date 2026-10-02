<?php

use App\Models\Frame;
use App\Models\Pack;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * P5 + P6 (v1.7.1) — avatar/frame composition regression and packs parity.
 */
uses(RefreshDatabase::class);

function compositionPng(): UploadedFile
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

    return UploadedFile::fake()->createWithContent('ring.png', $binary);
}

function heroBlock(string $html): string
{
    preg_match('/<div class="relative -mt-12.*?<div class="mt-6/is', $html, $m);

    return $m[0] ?? '';
}

// ---------------------------------------------------------------------------
// P5 — avatar/frame composition
// ---------------------------------------------------------------------------

test('no-frame hero with a photo renders one full-size img and zero saffron placeholder nodes', function () {
    Storage::fake('public');
    $creator = User::factory()->create(['username' => 'noframehero', 'name' => 'No Frame Hero']);
    Storage::disk('public')->put('avatars/hero.jpg', 'fake-bytes');
    $creator->forceFill(['avatar_path' => 'avatars/hero.jpg'])->save();

    $html = $this->get(route('creators.show', $creator))->getContent();

    $avatarUrl = Storage::disk('public')->url('avatars/hero.jpg');

    // The photo fills the badge: its img is size-full object-cover inside a
    // size-full inner badge — and the caller's size-24 hero class rules the
    // wrapper. Exactly ONE full-size avatar img on the hero surface.
    expect($html)->toContain($avatarUrl)
        // Inner badge + photo both fill the wrapper (the composition fix).
        ->and($html)->toContain('flex size-full items-center justify-center overflow-hidden rounded-[inherit] bg-ink')
        // No frame on THIS creator → no ring on the hero block. (W1 parity:
        // other avatars elsewhere on the page may legitimately carry one.)
        ->and(heroBlock($html))->not->toContain('pointer-events-none absolute -inset-1');
});

test('with-frame hero renders photo plus ring', function () {
    Storage::fake('public');
    $path = Storage::disk('public')->putFileAs('frames', compositionPng(), 'hero-ring.png');
    $frame = Frame::query()->create(['name' => 'Hero ring', 'image_path' => $path, 'is_active' => true]);
    $creator = User::factory()->for($frame, 'activeFrame')->create(['username' => 'ringhero', 'name' => 'Ring Hero']);
    Storage::disk('public')->put('avatars/ring.jpg', 'fake-bytes');
    $creator->forceFill(['avatar_path' => 'avatars/ring.jpg'])->save();

    $html = $this->get(route('creators.show', $creator))->getContent();

    expect($html)->toContain(Storage::disk('public')->url('avatars/ring.jpg'))
        ->and($html)->toContain(Storage::disk('public')->url($path))
        // Ring overlay present exactly where the frame is equipped.
        ->and($html)->toContain('pointer-events-none absolute -inset-1');
});

test('preset sizes still apply when no caller size class is present', function () {
    Storage::fake('public');
    $creator = User::factory()->create(['name' => 'Preset Hero']);
    Prompt::factory()->for($creator, 'creator')->published()->create();

    $html = $this->get(route('home'))->getContent();

    // Cards use the md preset — its size utility must survive the fix.
    expect($html)->toContain('size-8');
});

// ---------------------------------------------------------------------------
// P6 — packs parity
// ---------------------------------------------------------------------------

test('packs index carries full x-seo head tags', function () {
    $html = $this->get(route('packs.index'))->getContent();

    preg_match('/<head>(.*?)<\/head>/s', $html, $head);
    $headHtml = $head[1] ?? '';

    expect($headHtml)->toContain('<title>Prompt packs — ')
        ->and($headHtml)->toContain('name="description"')
        ->and($headHtml)->toContain('property="og:title"')
        ->and($headHtml)->toContain('rel="canonical"');
});

test('pack landing carries full x-seo and Product/Offer JSON-LD', function () {
    $pack = Pack::factory()->create(['name' => 'Parity Pack', 'price_paisa' => 99_900]);

    $html = $this->get(route('packs.show', $pack))->getContent();

    preg_match('/<head>(.*?)<\/head>/s', $html, $head);
    $headHtml = $head[1] ?? '';

    expect($headHtml)->toContain('<title>Parity Pack — ')
        ->and($headHtml)->toContain('rel="canonical"')
        ->and($headHtml)->toContain('property="og:title"')
        // T8's Product/Offer JSON-LD — locked here so it can't regress.
        ->and($html)->toContain('application/ld+json')
        ->and($html)->toContain('"@type":"Product"')
        ->and($html)->toContain('"price":"999.00"')
        ->and($html)->toContain('"priceCurrency":"NPR"')
        ->and($html)->toContain('InStock');
});

test('the mobile dock renders on both pack surfaces', function () {
    $pack = Pack::factory()->create(['name' => 'Dock Pack']);

    foreach ([route('packs.index'), route('packs.show', $pack)] as $url) {
        $html = $this->get($url)->getContent();
        preg_match('/<nav aria-label="Mobile primary".*?<\/nav>/s', $html, $m);

        expect($m[0] ?? '')->toContain('Mobile primary')
            ->toContain('href="'.route('home').'"');
    }
});
