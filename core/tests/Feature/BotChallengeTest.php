<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\BotChallengeService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function botAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function botSettings(array $overrides = []): void
{
    $settings = app(SettingsService::class);
    $settings->set('captcha_provider', $overrides['provider'] ?? 'turnstile');
    $settings->set('captcha_site_key', $overrides['site_key'] ?? 'site-key-x');
    if (array_key_exists('secret', $overrides)) {
        $settings->set('captcha_secret', $overrides['secret'] ?? '0xSECRET');
    } else {
        $settings->set('captcha_secret', '0xSECRET');
    }
    foreach ($overrides as $key => $value) {
        if (in_array($key, ['provider', 'site_key', 'secret'], true)) {
            continue;
        }
        $settings->set($key, $value);
    }
}

test('service is a pass-through when no provider is configured', function () {
    $bot = app(BotChallengeService::class);

    expect($bot->provider())->toBeNull()
        ->and($bot->enabledFor('register'))->toBeFalse()
        ->and($bot->verify('register', null))->toBeTrue()
        ->and($bot->verify('register', 'anything'))->toBeTrue();
});

test('turnstile success is accepted via server-side siteverify', function () {
    botSettings();

    Http::fake([
        'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true]),
    ]);

    expect(app(BotChallengeService::class)->verify('register', 'tok'))->toBeTrue();

    Http::assertSent(fn ($request) => $request['secret'] === '0xSECRET' && $request['response'] === 'tok');
});

test('turnstile failure is rejected', function () {
    botSettings();

    Http::fake([
        'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
    ]);

    expect(app(BotChallengeService::class)->verify('register', 'tok'))->toBeFalse();
});

test('transport timeout fails closed by default', function () {
    botSettings();

    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

    expect(app(BotChallengeService::class)->verify('register', 'tok'))->toBeFalse();
});

test('transport timeout fails open when captcha_fail_open is on', function () {
    botSettings(['captcha_fail_open' => '1']);

    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

    expect(app(BotChallengeService::class)->verify('register', 'tok'))->toBeTrue();
});

test('recaptcha v3 requires score and matching action', function () {
    botSettings(['provider' => 'recaptcha_v3', 'captcha_min_score' => '0.7']);

    // One fake for the whole test — mutate the payload between cases
    // (repeated Http::fake() calls do not reliably replace earlier stubs).
    $payload = [];
    Http::fake(function () use (&$payload) {
        return Http::response($payload);
    });

    $payload = ['success' => true, 'score' => 0.9, 'action' => 'register'];
    expect(app(BotChallengeService::class)->verify('register', 'tok'))->toBeTrue();

    // Score below the threshold.
    $payload = ['success' => true, 'score' => 0.2, 'action' => 'register'];
    expect(app(BotChallengeService::class)->verify('register', 'tok'))->toBeFalse();

    // Action mismatch — token minted for another form cannot be replayed.
    $payload = ['success' => true, 'score' => 0.9, 'action' => 'login'];
    expect(app(BotChallengeService::class)->verify('register', 'tok'))->toBeFalse();
});

test('per-form enablement defaults register on and others off', function () {
    botSettings();
    $bot = app(BotChallengeService::class);

    expect($bot->enabledFor('register'))->toBeTrue()
        ->and($bot->enabledFor('login'))->toBeFalse()
        ->and($bot->enabledFor('reset'))->toBeFalse()
        ->and($bot->enabledFor('submit'))->toBeFalse()
        ->and($bot->enabledFor('report'))->toBeFalse()
        ->and($bot->enabledFor('not-a-form'))->toBeFalse();

    // Login switched on at runtime.
    app(SettingsService::class)->set('captcha_form_login', '1');
    expect($bot->enabledFor('login'))->toBeTrue();
});

test('forms without the challenge enabled never hit the provider', function () {
    botSettings();

    Http::fake();

    expect(app(BotChallengeService::class)->verify('login', null))->toBeTrue();

    Http::assertNothingSent();
});

test('the captcha secret is encrypted at rest in settings', function () {
    botSettings();

    $row = Setting::query()->find('captcha_secret');

    expect($row->value)->not->toBe('0xSECRET')
        ->and(app(SettingsService::class)->get('captcha_secret'))->toBe('0xSECRET');
});

test('missing token or secret is rejected without a provider call', function () {
    botSettings();

    Http::fake();

    // Token missing.
    expect(app(BotChallengeService::class)->verify('register', ' '))->toBeFalse();

    // Secret unset.
    app(SettingsService::class)->set('captcha_secret', '');
    expect(app(BotChallengeService::class)->verify('register', 'tok'))->toBeFalse();

    Http::assertNothingSent();
});

test('the captcha component renders silently when no provider is active', function () {
    $html = view('components.captcha', ['form' => 'register'])->render();

    expect($html)->not->toContain('challenges.cloudflare.com')
        ->and($html)->not->toContain('recaptcha');
});

test('the captcha component renders the turnstile widget when active for register', function () {
    botSettings();

    $html = view('components.captcha', ['form' => 'register'])->render();

    expect($html)->toContain('challenges.cloudflare.com')
        // The cf-turnstile-response input is injected by the widget JS —
        // the server markup carries the widget div itself.
        ->and($html)->toContain('cf-turnstile');
});

test('register is refused when the challenge fails and accepted when it passes', function () {
    botSettings();

    // One fake for the whole test — mutate the payload between cases.
    $payload = ['success' => false];
    Http::fake(function () use (&$payload) {
        return Http::response($payload);
    });

    $this->post(route('register.store'), [
        'name' => 'Blocked Bot',
        'username' => 'blocked-bot',
        'email' => 'bot@example.test',
        'password' => 'Corr3ct-Horse-Battery!',
        'password_confirmation' => 'Corr3ct-Horse-Battery!',
        'cf-turnstile-response' => 'tok',
    ])->assertSessionHasErrors();

    expect(User::query()->where('username', 'blocked-bot')->exists())->toBeFalse();

    // Accepted: provider says yes.
    $payload = ['success' => true];

    $this->post(route('register.store'), [
        'name' => 'Human Person',
        'username' => 'human-person',
        'email' => 'human@example.test',
        'password' => 'Corr3ct-Horse-Battery!',
        'password_confirmation' => 'Corr3ct-Horse-Battery!',
        'cf-turnstile-response' => 'tok',
    ])->assertRedirect();

    expect(User::query()->where('username', 'human-person')->exists())->toBeTrue();
});

test('the security admin page shows captcha controls and moderators are 403', function () {
    $member = User::factory()->create();
    $this->actingAs($member)->get(route('admin.security.edit'))->assertForbidden();

    $admin = botAdmin();
    $this->actingAs($admin)->get(route('admin.security.edit'))
        ->assertOk()
        ->assertSee('captcha_provider', false);
});
