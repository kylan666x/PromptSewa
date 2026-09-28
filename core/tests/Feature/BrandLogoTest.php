<?php

use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function brandLogoAdmin(): \App\Models\User
{
    return \App\Models\User::create([
        'name' => 'Brand Admin',
        'username' => 'brand-admin',
        'email' => 'brandadmin@promptsewa.test',
        'password' => 'password',
        'role' => \App\Models\User::ROLE_ADMIN,
    ]);
}

/** Build a real transparent PNG in memory (GD) and return an UploadedFile. */
function transparentPngUpload(int $width = 800, int $height = 200): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    $saffron = imagecolorallocate($image, 245, 197, 24);
    imagefilledrectangle($image, 10, 10, 120, 190, $saffron);

    ob_start();
    imagepng($image);
    $binary = (string) ob_get_clean();
    imagedestroy($image);

    $tempPath = sys_get_temp_dir().'/logo-test-'.bin2hex(random_bytes(4)).'.png';
    file_put_contents($tempPath, $binary);

    return new UploadedFile($tempPath, 'wordmark.png', 'image/png', null, true);
}

test('logo variant preserves alpha — transparent PNG stays PNG, never JPEG', function () {
    Storage::fake('public');
    $service = new ImageUploadService;

    $path = $service->store(transparentPngUpload(), 'logo');

    expect($path)->toEndWith('.png');

    $stored = Storage::disk('public')->get($path);
    // PNG magic bytes: \x89PNG\r\n\x1a\n
    expect(substr($stored, 0, 4))->toBe("\x89PNG");
});

test('logo variant downscales to at most 768px wide', function () {
    Storage::fake('public');
    $service = new ImageUploadService;

    $path = $service->store(transparentPngUpload(2000, 500), 'logo');

    $binary = Storage::disk('public')->get($path);
    $image = imagecreatefromstring($binary);

    expect(imagesx($image))->toBeLessThanOrEqual(768)
        ->and(imagesy($image))->toBeLessThanOrEqual(768);
    imagedestroy($image);
});

test('mark variant behaves like logo — alpha preserved on the public disk', function () {
    Storage::fake('public');
    $service = new ImageUploadService;

    $path = $service->store(transparentPngUpload(1024, 1024), 'mark');

    expect(str_starts_with($path, 'marks/'))->toBeTrue()
        ->and(substr(Storage::disk('public')->get($path), 0, 4))->toBe("\x89PNG");
});

test('photo variants still re-encode to JPEG', function () {
    Storage::fake('public');
    $service = new ImageUploadService;

    $image = imagecreatetruecolor(600, 400);
    imagefilledrectangle($image, 0, 0, 599, 399, imagecolorallocate($image, 200, 120, 40));
    $tempPath = sys_get_temp_dir().'/photo-test.jpg';
    imagejpeg($image, $tempPath, 90);
    imagedestroy($image);

    $path = $service->store(
        new UploadedFile($tempPath, 'photo.jpg', 'image/jpeg', null, true),
        'banner',
    );

    expect($path)->toEndWith('.jpg');
});

test('navbar shows the uploaded logo without a duplicate wordmark at desktop', function () {
    $admin = brandLogoAdmin();
    app(\App\Services\SettingsService::class)->set('brand_logo_path', 'brand/wordmark.png');
    app(\App\Services\SettingsService::class)->set('brand_mark_path', 'brand/mark.png');

    $html = $this->actingAs($admin)->get(route('home'))->getContent();

    // Logo img present with site name as alt…
    expect($html)->toContain('alt="PromptSewa"')
        // …and the visible wordmark span is gone when a logo is set…
        ->not->toContain('<span class="hidden sm:inline">PromptSewa</span>')
        // …and the desktop logo has the h-9 md:block treatment…
        ->toContain('h-9 w-auto')
        // …and mobile uses the square mark.
        ->toContain('md:hidden');
});

test('navbar falls back to badge + wordmark when no logo is uploaded', function () {
    $admin = brandLogoAdmin();

    $html = $this->actingAs($admin)->get(route('home'))->getContent();

    expect($html)->toContain('<span class="hidden sm:inline">PromptSewa</span>');
});

test('brand form accepts a transparent PNG logo and rejects JPEG logos', function () {
    $admin = brandLogoAdmin();
    Storage::fake('public');

    $this->actingAs($admin)
        ->post(route('admin.brand.update'), [
            '_method' => 'PUT',
            'site_name' => 'PromptSewa',
            'logo' => transparentPngUpload(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(app(\App\Services\SettingsService::class)->get('brand_logo_path'))->not->toBe('');

    $jpeg = imagecreatetruecolor(400, 100);
    imagefilledrectangle($jpeg, 0, 0, 399, 99, imagecolorallocate($jpeg, 200, 120, 40));
    $jpegPath = sys_get_temp_dir().'/logo-flat.jpg';
    imagejpeg($jpeg, $jpegPath, 90);
    imagedestroy($jpeg);

    $this->actingAs($admin)
        ->from(route('admin.brand.edit'))
        ->post(route('admin.brand.update'), [
            '_method' => 'PUT',
            'site_name' => 'PromptSewa',
            'logo' => new UploadedFile($jpegPath, 'logo.jpg', 'image/jpeg', null, true),
        ])
        ->assertSessionHasErrors('logo');
});
