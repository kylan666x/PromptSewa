<?php

use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

/**
 * A1 (v1.7.6) — the forgot-password flow.
 *
 * The central assertion is the WHOLE LOOP: the mail the request sends must
 * contain a URL that actually resets the password. A reset flow whose mail
 * carries a dead link passes every "the page renders" test and is still
 * completely broken in production.
 */

/** Pull the reset URL out of a rendered message, the way an inbox would. */
function resetUrlFromMail(string $html): string
{
    preg_match('#https?://[^\s"\'<]+/reset-password/([^\s"\'<]+)#', $html, $match);

    expect($match)->not->toBeEmpty('the reset mail must carry a working /reset-password/{token} URL');

    return html_entity_decode($match[0]);
}

/** The token segment of a reset URL. */
function resetTokenFromUrl(string $url): string
{
    $path = (string) parse_url($url, PHP_URL_PATH);

    preg_match('#/reset-password/([^/?]+)#', $path, $match);

    return rawurldecode($match[1] ?? '');
}

test('the request form renders in the paper world with the noindex guard', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Forgot your password?')
        ->assertSee('name="_token"', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

test('requesting a reset sends mail whose link really changes the password', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'sita@example.test', 'password' => Hash::make('the-old-password')]);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.sent'));

    $url = null;
    $rendered = null;

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$url, &$rendered, $user) {
        $mail = $notification->toMail($user);

        expect($mail)->toBeInstanceOf(ResetPasswordMail::class)
            ->and($mail->envelope()->subject)->toContain('password')
            ->and($mail->hasTo($user->email))->toBeTrue();

        $rendered = $mail->render();
        $url = resetUrlFromMail($rendered);

        // The branded shell: saffron button + ink header bar, inline CSS only.
        expect($rendered)->toContain('Choose a new password')
            ->and($rendered)->toContain('#f5c518')
            ->and($rendered)->toContain('#171715');

        return true;
    });

    // The mail can never carry the secret the user is about to choose —
    // it cannot, and if it ever did this test must burn.
    expect($rendered)->not->toContain('the-old-password');

    // The plain-text alternative ships alongside the HTML part.
    $text = view('mail.auth.reset-password-text', [
        'url' => $url,
        'minutes' => 60,
        'siteName' => 'PromptSewa',
        'name' => $user->name,
    ])->render();

    expect($text)->toContain($url);
    expect($text)->toContain('60 minutes');

    // Now walk the link the way the founder would.
    $this->get($url)->assertOk()->assertSee('Choose a new password');

    $token = resetTokenFromUrl($url);

    $this->post(route('password.update', $token), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertRedirect(route('login'));

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('the-old-password', $user->fresh()->password))->toBeFalse();

    // Single use: replaying the link changes nothing.
    $this->post(route('password.update', $token), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'yet-another-password',
        'password_confirmation' => 'yet-another-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue();
});

test('the real mail pipeline hands a rendered reset mail to the transport', function () {
    // The strongest form of this lock: NOT a facade fake. Mail::fake() cannot
    // see notification mail in Laravel 12 (MailChannel hands the mailer a
    // VIEW NAME, and MailFake only records Mailable objects — a fact that
    // hides an entire class of "mail works" test). phpunit pins
    // MAIL_MAILER=array, so the REAL pipeline runs and the composed message
    // lands in the array transport where we can read the actual payload.
    $user = User::factory()->create(['email' => 'rail@example.test']);

    $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();

    $this->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.sent'));

    $messages = $transport->messages();

    expect($messages)->toHaveCount(1);

    $sent = $messages[0];
    $recipients = $sent->getEnvelope()->getRecipients();

    expect($recipients)->not->toBeEmpty()
        ->and($recipients[0]->getAddress())->toBe($user->email);

    // Token extracted from the message that would really have been sent.
    $url = resetUrlFromMail($sent->getOriginalMessage()->getHtmlBody());

    $this->get($url)->assertOk();

    $token = resetTokenFromUrl($url);

    $this->post(route('password.update', $token), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertRedirect(route('login'));

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue();
});

test('an unknown address lands on the same notice — the form is not an account oracle', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'nobody-at-all@example.test'])
        ->assertRedirect(route('password.sent'));

    Notification::assertNothingSent();

    $this->get(route('password.sent'))
        ->assertOk()
        ->assertSee('Check your email')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('an expired token is refused and the password is untouched', function () {
    $user = User::factory()->create(['password' => Hash::make('the-old-password')]);

    $token = Password::broker()->createToken($user);

    // Backdate past config('auth.passwords.users.expire') = 60 minutes.
    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => now()->subMinutes(61)]);

    $this->post(route('password.update', $token), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('the-old-password', $user->fresh()->password))->toBeTrue();
});

test('a garbage token is refused', function () {
    $user = User::factory()->create(['password' => Hash::make('the-old-password')]);

    $this->post(route('password.update', 'not-a-real-token'), [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('the-old-password', $user->fresh()->password))->toBeTrue();
});

test('the reset enforces the same password floor as signup', function () {
    $user = User::factory()->create(['password' => Hash::make('the-old-password')]);
    $token = Password::broker()->createToken($user);

    $this->post(route('password.update', $token), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('password');

    $this->post(route('password.update', $token), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    $this->post(route('password.update', $token), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'does-not-match',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('the-old-password', $user->fresh()->password))->toBeTrue();
});

test('six reset requests a minute, then 429 — this form is not a mail cannon', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.sent'));

    // 2..6 hit the broker's own 60s resend throttle (a validation error),
    // but the route's 6/minute budget still has to allow them through —
    // otherwise a user pressing "send again" gets a 429 instead of a
    // patient message.
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();
    }

    $this->post(route('password.email'), ['email' => $user->email])->assertStatus(429);
});

test('signed-in users have no business on any of the three pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('password.request'))->assertRedirect();
    $this->actingAs($user)->get(route('password.sent'))->assertRedirect();
    $this->actingAs($user)->get(route('password.reset', 'any-token'))->assertRedirect();
});

test('a broken mail rail is reported, never swallowed', function () {
    // No sendmail binary, refused relay: the broker persists the token and
    // the notification throws. That has to surface as an honest error
    // rather than a cheerful "check your inbox".
    config(['mail.default' => 'smtp']);
    config([
        'mail.mailers.smtp' => array_merge(config('mail.mailers.smtp'), [
            'scheme' => 'smtp',
            'host' => '127.0.0.1',
            'port' => 1, // nothing is listening here
            'username' => null,
            'password' => null,
            'auto_tls' => false,
            'require_tls' => false,
        ]),
    ]);

    $user = User::factory()->create(['email' => 'rail@example.test']);

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))
        ->toBe('We could not send that email just now — please try again in a minute.');
});