<?php

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.test',
        'password' => 'password',
        'role' => User::ROLE_ADMIN,
    ]);
}

test('guests cannot open the brand settings page', function () {
    $this->get(route('admin.brand.edit'))->assertRedirect(route('login'));
});

test('non-admin members are forbidden from brand settings', function () {
    $user = User::create([
        'name' => 'Member',
        'email' => 'member@example.test',
        'password' => 'password',
        'role' => User::ROLE_MEMBER,
    ]);

    $this->actingAs($user)->get(route('admin.brand.edit'))->assertForbidden();
});

test('admins can view the brand settings form with current values', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.brand.edit'))
        ->assertOk()
        ->assertSee('Brand & contact')
        ->assertSee('site_name');
});

test('admins can update site name, tagline and contact emails', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->put(route('admin.brand.update'), [
            'site_name' => 'PromptSewa',
            'site_tagline' => 'Version control for AI prompts',
            'contact_email' => 'Hello@Example.com',
            'support_email' => 'support@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    // The homepage navbar/footer now render the configured values.
    $this->get('/')
        ->assertOk()
        ->assertSee('PromptSewa')
        ->assertSee('hello@example.com')
        ->assertSee('support@example.com');
});

test('brand update validates emails and required site name', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->put(route('admin.brand.update'), [
            'site_name' => '',
            'contact_email' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['site_name', 'contact_email']);
});

test('admins can upload a logo and favicon that render site-wide', function () {
    Storage::fake('public');
    $admin = adminUser();

    $this->actingAs($admin)
        ->put(route('admin.brand.update'), [
            'site_name' => 'PromptSewa',
            'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
            'favicon' => UploadedFile::fake()->create('favicon.png', 10, 'image/png'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('settings', ['key' => 'brand_logo_path']);
    $this->assertDatabaseHas('settings', ['key' => 'brand_favicon_path']);

    $logoPath = app(SettingsService::class)->get('brand_logo_path');
    $faviconPath = app(SettingsService::class)->get('brand_favicon_path');
    Storage::disk('public')->assertExists($logoPath);
    Storage::disk('public')->assertExists($faviconPath);

    $this->get('/')
        ->assertOk()
        ->assertSee('storage/'.$logoPath, false)
        ->assertSee('<link rel="icon" href="'.asset('storage/'.$faviconPath).'"', false);
});

test('submitting without new uploads keeps the existing brand files', function () {
    Storage::fake('public');
    $admin = adminUser();

    $this->actingAs($admin)->put(route('admin.brand.update'), [
        'site_name' => 'PromptSewa',
        'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
    ])->assertRedirect();

    $logoPath = app(SettingsService::class)->get('brand_logo_path');

    $this->actingAs($admin)->put(route('admin.brand.update'), [
        'site_name' => 'Renamed Site',
    ])->assertRedirect();

    expect(app(SettingsService::class)->get('brand_logo_path'))->toBe($logoPath);
    Storage::disk('public')->assertExists($logoPath);
});
