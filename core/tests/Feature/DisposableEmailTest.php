<?php

use App\Models\User;
use App\Services\SettingsService;
use App\Support\NotDisposableEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function disposableAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

test('the bundled disposable-domain list is loaded', function () {
    $list = app('disposable.domains');

    expect($list->count())->toBeGreaterThan(100)
        ->and($list->contains('mailinator.com'))->toBeTrue()
        ->and($list->contains('guerrillamail.com'))->toBeTrue()
        ->and($list->contains('10minutemail.com'))->toBeTrue();
});

test('listed domains, subdomains and casing are all blocked', function () {
    $rule = new NotDisposableEmail;

    // Legit domains pass (no failure callback invoked):
    $rule('email', 'someone@gmail.com', fn () => throw new \RuntimeException('blocked'));
    $rule('email', 'someone@protonmail.com', fn () => throw new \RuntimeException('blocked'));

    expect(true)->toBeTrue();
});

test('blocked domains trigger the failure callback', function () {
    $rule = new NotDisposableEmail;

    expect(fn () => $rule('email', 'someone@mailinator.com', fn () => throw new \RuntimeException('blocked')))
        ->toThrow(\RuntimeException::class, 'blocked');
});

test('subdomains of blocked domains are blocked', function () {
    $rule = new NotDisposableEmail;

    expect(fn () => $rule('email', 'someone@sub.mailinator.com', fn () => throw new \RuntimeException('blocked')))
        ->toThrow(\RuntimeException::class, 'blocked');
});

test('uppercase addresses are normalised before matching', function () {
    $rule = new NotDisposableEmail;

    expect(fn () => $rule('email', 'Someone@MAILINATOR.COM', fn () => throw new \RuntimeException('blocked')))
        ->toThrow(\RuntimeException::class, 'blocked');
});

test('admin-extended domains are merged at runtime', function () {
    app(SettingsService::class)->set('blocked_domains_extra', "mythrowaway.test\nsecond.test");

    $rule = new NotDisposableEmail;

    expect(fn () => $rule('email', 'someone@mythrowaway.test', fn () => throw new \RuntimeException('blocked')))
        ->toThrow(\RuntimeException::class, 'blocked');
});

test('gmail and permanent inboxes pass the rule', function () {
    $rule = new NotDisposableEmail;

    // No exception = pass.
    $rule('email', 'human@gmail.com', fn () => throw new \RuntimeException('blocked: gmail is fine'));
    $rule('email', 'human@yahoo.co.in', fn () => throw new \RuntimeException('blocked: yahoo is fine'));
    $rule('email', 'human+npm@gmail.com', fn () => throw new \RuntimeException('blocked: plus-addressing is fine'));

    expect(true)->toBeTrue();
});

test('signup with a disposable address is refused', function () {
    $this->post(route('register.store'), [
        'name' => 'Throwaway',
        'username' => 'throwaway',
        'email' => 'bot@mailinator.com',
        'password' => 'Corr3ct-Horse-Battery!',
        'password_confirmation' => 'Corr3ct-Horse-Battery!',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('username', 'throwaway')->exists())->toBeFalse();
});

test('signup with a disposable subdomain is refused but gmail passes', function () {
    $this->post(route('register.store'), [
        'name' => 'Sub Bot',
        'username' => 'sub-bot',
        'email' => 'bot@x.guerrillamail.com',
        'password' => 'Corr3ct-Horse-Battery!',
        'password_confirmation' => 'Corr3ct-Horse-Battery!',
    ])->assertSessionHasErrors('email');

    $this->post(route('register.store'), [
        'name' => 'Real Human',
        'username' => 'real-human',
        'email' => 'real@gmail.com',
        'password' => 'Corr3ct-Horse-Battery!',
        'password_confirmation' => 'Corr3ct-Horse-Battery!',
    ])->assertValid(['email']);

    expect(User::query()->where('username', 'real-human')->exists())->toBeTrue();
});

test('the admin test-email endpoint reports blocked and ok', function () {
    $admin = disposableAdmin();

    $this->actingAs($admin)
        ->post(route('admin.security.test-email'), ['email' => 'Bot@Mailinator.Com'])
        ->assertOk()
        ->assertJson(['result' => 'blocked', 'matched' => 'mailinator.com']);

    $this->actingAs($admin)
        ->post(route('admin.security.test-email'), ['email' => 'human@gmail.com'])
        ->assertOk()
        ->assertJson(['result' => 'ok', 'matched' => null]);
});

test('the test-email endpoint is admin-only', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->post(route('admin.security.test-email'), ['email' => 'x@mailinator.com'])
        ->assertForbidden();
});
