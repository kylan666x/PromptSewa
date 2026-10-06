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

    // The photo fills the badge: its img is size-full object-cover inside the
    // hard-forced circle wrapper (HOTFIX ADDENDUM: rounded-full +
    // overflow-hidden always; the initials badge is rounded-full itself).
    expect($html)->toContain($avatarUrl)
        ->and($html)->toContain('size-full object-cover')
        // No frame on THIS creator → no overlay on the hero block. (W1 parity:
        // other avatars elsewhere on the page may legitimately carry one.)
        ->and(heroBlock($html))->not->toContain('pointer-events-none absolute inset-0');
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
        // v1.7.5 composite box: the hero's size comes from the PROP (xl), the
        // frame fills the box at inset-0 with z-10, and the circle is the
        // photo layer inset to the frame's hole. The v1.7.4 caller-size +
        // protruding-overlay coupling is gone.
        ->and($html)->toContain('pointer-events-none absolute inset-0 z-10 size-full object-contain')
        ->and($html)->toContain('data-avatar-size="xl"')
        ->and($html)->toMatch('/<span class="absolute overflow-hidden rounded-full"[^>]*style="inset: \d/');
});

test('the size prop alone drives the wrapper size on every preset', function () {
    Storage::fake('public');
    $creator = User::factory()->create(['name' => 'Preset Hero']);
    Prompt::factory()->for($creator, 'creator')->published()->create();

    $html = $this->get(route('home'))->getContent();

    // Cards pass size="sm"; the wrapper's size class comes from the prop and
    // can no longer be suppressed by a caller (the founder's hero bug).
    foreach (['xs' => 'size-5', 'sm' => 'size-6', 'md' => 'size-8', 'lg' => 'size-11', 'xl' => 'size-24'] as $size => $class) {
        $markup = view('components.user-avatar', ['user' => $creator, 'size' => $size])->render();
        expect($markup)->toContain($class)
            ->and($markup)->toContain('data-avatar-size="'.$size.'"');
    }

    expect($html)->toContain('size-6');
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
        // S1b (v1.9.0): the offer prices in SIKKA credits (99,900 paisa =
        // 999 credits); the NPR mirror never leaks into structured data.
        ->and($html)->toContain('application/ld+json')
        ->and($html)->toContain('"@type":"Product"')
        ->and($html)->toContain('"price":"999"')
        ->and($html)->toContain('"priceCurrency":"SIKKA"')
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
