<?php

use App\Mail\TestEmail;
use App\Models\Setting;
use App\Models\User;
use App\Services\MailConfigService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * A3 (v1.7.6) — the mail rail.
 *
 * The rail was the quietest failure in the product: `MAIL_MAILER=log` (the
 * Laravel default this app shipped with) meant a password-reset link went to
 * a log file while the user was told "check your inbox" — forever. These
 * locks pin the three things that matter:
 *
 *  - sendmail is the SHIPPED default and it needs zero settings to be right;
 *  - an admin's choice overrides config at boot, TLS scheme included;
 *  - the SMTP password is encrypted at rest, write-only in the form, and
 *    never echoed back — not even in the probe's error message.
 */

uses(RefreshDatabase::class);

function mailAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN, 'email' => 'boss@example.test']);
}

test('the shipped default is sendmail — auth mail must work with zero configuration', function () {
    // phpunit.xml pins MAIL_MAILER=array so the SUITE never really sends;
    // the shipped fallback is locked here on the config file itself,
    // because that fallback is what a fresh cPanel install gets.
    $shipped = file_get_contents(config_path('mail.php'));

    expect($shipped)->toContain("env('MAIL_MAILER', 'sendmail')")
        ->and(config('mail.mailers.sendmail.transport'))->toBe('sendmail')
        ->and(config('mail.mailers.sendmail.path'))->toContain('sendmail');

    $example = file_get_contents(base_path('.env.example'));

    expect($example)->toContain('MAIL_MAILER=sendmail')
        ->and($example)->toContain('MAIL_HOST=mail.babal.host');
});

test('with zero mail settings saved, apply() changes nothing', function () {
    expect(Setting::query()->whereIn('key', MailConfigService::KEYS)->count())->toBe(0);

    $before = config('mail.default');

    expect(app(MailConfigService::class)->apply())->toBeFalse()
        ->and(config('mail.default'))->toBe($before);
});

test('saved settings land in the smtp transport, encryption included', function () {
    $settings = app(SettingsService::class);

    $settings->set('mail_mailer', 'smtp');
    $settings->set('mail_host', 'mail.babal.host');
    $settings->set('mail_port', '465');
    $settings->set('mail_encryption', 'ssl');
    $settings->set('mail_username', 'no-reply@example.test');
    $settings->set('mail_password', 'sup3r-secret');
    $settings->set('mail_from_address', 'no-reply@example.test');
    $settings->set('mail_from_name', 'PromptSewa');

    expect(app(MailConfigService::class)->apply())->toBeTrue();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('mail.babal.host')
        ->and(config('mail.mailers.smtp.port'))->toBe(465)
        ->and(config('mail.mailers.smtp.username'))->toBe('no-reply@example.test')
        ->and(config('mail.mailers.smtp.password'))->toBe('sup3r-secret')
        // ssl on 465 is implicit TLS: a DSN scheme, not a legacy flag.
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.from.address'))->toBe('no-reply@example.test')
        ->and(config('mail.from.name'))->toBe('PromptSewa');
});

test('tls and none map to the right Symfony transport switches — and leave nothing behind', function () {
    $settings = app(SettingsService::class);
    $settings->set('mail_mailer', 'smtp');
    $settings->set('mail_host', 'smtp.example.test');

    $settings->set('mail_encryption', 'tls');
    $settings->set('mail_port', '587');
    app(MailConfigService::class)->apply();

    expect(config('mail.mailers.smtp.scheme'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.require_tls'))->toBeTrue()
        ->and(config('mail.mailers.smtp.port'))->toBe(587);

    // tls → none must CLEAR require_tls, or the relay refuses forever.
    $settings->set('mail_encryption', 'none');
    app(MailConfigService::class)->apply();

    expect(config('mail.mailers.smtp.auto_tls'))->toBeFalse()
        ->and(config('mail.mailers.smtp.require_tls'))->toBeFalse();

    $settings->set('mail_encryption', 'ssl');
    app(MailConfigService::class)->apply();

    expect(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.mailers.smtp.require_tls'))->toBeFalse();
});

test('the from address falls back to the Brand contact email and site name', function () {
    $settings = app(SettingsService::class);

    $settings->set('contact_email', 'hello@brand-fallback.test');
    $settings->set('site_name', 'The Paper Shop');
    // The admin saved the panel but left the From boxes empty: a row with
    // an empty value is still "the admin has configured this".
    $settings->set('mail_from_address', '');
    $settings->set('mail_from_name', '');

    expect(app(MailConfigService::class)->apply())->toBeTrue();

    expect(config('mail.from.address'))->toBe('hello@brand-fallback.test')
        ->and(config('mail.from.name'))->toBe('The Paper Shop');
});

test('the SMTP password is encrypted at rest and never served back', function () {
    $admin = mailAdmin();

    $this->actingAs($admin)->put(route('admin.email.update'), [
        'mail_mailer' => 'smtp',
        'mail_host' => 'mail.babal.host',
        'mail_port' => '465',
        'mail_encryption' => 'ssl',
        'mail_username' => 'no-reply@example.test',
        'mail_password' => 'sup3r-secret',
        'mail_from_address' => 'no-reply@example.test',
        'mail_from_name' => 'PromptSewa',
    ])->assertRedirect();

    // At rest: the raw settings row is ciphertext.
    $raw = Setting::query()->find('mail_password')->value;

    expect($raw)->not->toBe('sup3r-secret')
        ->and($raw)->not->toBeNull()
        ->and(Crypt::decryptString($raw))->toBe('sup3r-secret')
        ->and(app(SettingsService::class)->get('mail_password'))->toBe('sup3r-secret');

    // In the page: only the "saved" chip, never the value.
    $html = $this->actingAs($admin)->get(route('admin.email.edit'))->getContent();

    expect($html)->not->toContain('sup3r-secret')
        ->and($html)->toContain('Leave empty to keep the saved password');
});

test('an empty password box keeps the saved one instead of wiping it', function () {
    $admin = mailAdmin();

    app(SettingsService::class)->set('mail_password', 'keep-me');

    $this->actingAs($admin)->put(route('admin.email.update'), [
        'mail_mailer' => 'sendmail',
        'mail_encryption' => 'ssl',
        'mail_password' => '',
    ])->assertRedirect();

    expect(app(SettingsService::class)->get('mail_password'))->toBe('keep-me');
});

test('the nav pill exists — a settings screen nobody can reach is a missing feature', function () {
    $admin = mailAdmin();

    expect($this->actingAs($admin)->get(route('admin.dashboard'))->getContent())
        ->toContain(route('admin.email.edit'));

    $this->actingAs($admin)->get(route('admin.email.edit'))
        ->assertOk()
        ->assertSee('Send test email')
        ->assertSee('mail.babal.host');
});

test('moderators and members are refused the mail settings', function () {
    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);

    $this->actingAs($moderator)->get(route('admin.email.edit'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.email.edit'))->assertForbidden();

    $this->actingAs($moderator)->put(route('admin.email.update'), [
        'mail_mailer' => 'smtp',
        'mail_encryption' => 'ssl',
    ])->assertForbidden();

    $this->actingAs($moderator)->post(route('admin.email.test'))->assertForbidden();
});

test('the probe sends to the acting admin on the configured rail', function () {
    Mail::fake();

    $admin = mailAdmin();

    $this->actingAs($admin)->post(route('admin.email.test'))
        ->assertRedirect()
        ->assertSessionHas('success');

    Mail::assertSent(TestEmail::class, fn (TestEmail $mail) => $mail->hasTo($admin->email));
});

test('a failed send reports the class and one line — never the password', function () {
    $admin = mailAdmin();

    app(SettingsService::class)->set('mail_password', 'do-not-leak-me');

    // Worst case an SMTP error could produce: the message itself quotes the
    // credential. Both methods are stubbed because the controller purges the
    // mailer before it sends.
    Mail::shouldReceive('purge')->andReturnNull();
    Mail::shouldReceive('send')->once()->andThrow(
        new \Symfony\Component\Mailer\Exception\TransportException(
            'Connection to smtp host failed: authentication rejected for password do-not-leak-me'
        )
    );

    $this->actingAs($admin)->post(route('admin.email.test'))
        ->assertRedirect()
        ->assertSessionHas('error');

    $error = (string) session('error');

    expect($error)->toContain('TransportException')
        ->and($error)->toContain('•••')
        ->and($error)->not->toContain('do-not-leak-me')
        ->and($error)->not->toContain('Symfony\\');
});