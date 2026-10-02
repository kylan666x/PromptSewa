<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * A2 (v1.7.6) — the view-password toggle.
 *
 * Two rules, asserted against SERVED HTML (never against a Blade file's
 * source — that is how a "present in the template" bug hides):
 *
 *  1. every password surface renders the toggle: eye/eye-off icons, an
 *     aria-pressed state and a Show/Hide label;
 *  2. NO password input ever ships a value. A `value="…"` on a password
 *     field is plaintext in the page source (browser history, proxies,
 *     "view source", a shoulder-surfer's screenshot) — the same family as
 *     the signup-meter rule, so it is banned here too. The component
 *     strips the attribute defensively; this test proves it.
 */

uses(RefreshDatabase::class);

/** Assertions every password surface must satisfy. */
function assertRendersPasswordToggle(Illuminate\Testing\TestResponse $response, string $where): void
{
    $html = $response->getContent();

    // The toggle button itself.
    expect($html)->toContain('aria-pressed')
        ->and($html)->toContain('Show password')
        ->and($html)->toContain('Hide password')
        ->and($html)->toContain('x-bind:type');

    // No password field may carry a value — in ANY form on the page.
    preg_match_all('/<input\b[^>]*\btype="password"[^>]*>/i', $html, $matches);

    $inputs = $matches[0] ?? [];

    expect($inputs)->not->toBeEmpty("{$where} must serve at least one password input");

    foreach ($inputs as $input) {
        expect($input)->not->toMatch('/\svalue=/i');
    }
}

test('login, register and the reset form all render the eye toggle', function () {
    $token = 'some-token';

    assertRendersPasswordToggle($this->get(route('login')), 'login');
    assertRendersPasswordToggle($this->get(route('register')), 'register');
    assertRendersPasswordToggle(
        $this->get(route('password.reset', $token).'?email=sita@example.test'),
        'reset-password'
    );
});

test('a failed login never echoes the attempted password back into the page', function () {
    $user = User::factory()->create(['password' => Hash::make('the-real-password')]);

    $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'the-wrong-password',
    ])->assertRedirect(route('login'));

    $response = $this->get(route('login'));

    expect($response->getContent())
        ->not->toContain('the-wrong-password')
        ->not->toContain('the-real-password');

    assertRendersPasswordToggle($response, 'login (after a failed attempt)');
});

test('a rejected registration never echoes the attempted password back', function () {
    $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Sita Member',
        'username' => 'sita-member',
        'email' => 'not-an-email',
        'password' => 'leaked-plaintext-1',
        'password_confirmation' => 'leaked-plaintext-1',
    ])->assertSessionHasErrors('email');

    $html = $this->get(route('register'))->getContent();

    expect($html)->not->toContain('leaked-plaintext-1');
});

test('the admin SMTP password field renders the toggle and never echoes the secret', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    app(\App\Services\SettingsService::class)->set('mail_password', 'smtp-secret-value');

    $response = $this->actingAs($admin)->get(route('admin.email.edit'));

    $response->assertOk();
    assertRendersPasswordToggle($response, 'admin → email');

    expect($response->getContent())
        ->not->toContain('smtp-secret-value')
        ->toContain('Leave empty to keep the saved password');
});

test('the component refuses to render a value even if a caller passes one', function () {
    // The ban is enforced in the component, not only in the tests.
    $html = view('components.password-input', [
        'name' => 'password',
        'label' => 'Password',
        'slot' => '',
        'errors' => new \Illuminate\Support\ViewErrorBag,
        'value' => 'sneaky-plaintext',
    ])->render();

    expect($html)->not->toContain('sneaky-plaintext')
        ->and($html)->toContain('Show password');
});