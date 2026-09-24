<?php

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login when visiting the dashboard', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('a member can register and lands on the storefront signed in', function () {
    $response = $this->post('/register', [
        'name' => 'Sita Member',
        'email' => 'sita@example.test',
        'password' => 'long-enough-password',
        'password_confirmation' => 'long-enough-password',
    ]);

    $response->assertRedirect(route('home'));
    $this->assertAuthenticated();
    expect(User::where('email', 'sita@example.test')->first()->role)->toBe(User::ROLE_MEMBER);
});

test('registration validates duplicate emails and short passwords', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    $this->post('/register', [
        'name' => 'Second Person',
        'email' => 'taken@example.test',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors(['email', 'password']);
});

test('a user can log in with valid credentials and out again', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
        ->assertRedirect(route('home'));
    $this->assertAuthenticated();

    $this->post('/logout')->assertRedirect(route('home'));
    $this->assertGuest();
});

test('login rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertRedirect('/login');

    $this->assertGuest();
});

test('the dashboard lists only the signed-in creator prompts', function () {
    $mine = User::factory()->create();
    $other = User::factory()->create();

    $myPrompt = Prompt::factory()->published()->create(['title' => 'My Own Prompt', 'user_id' => $mine->id]);
    Prompt::factory()->published()->create(['title' => 'Someone Else Prompt', 'user_id' => $other->id]);

    $this->actingAs($mine)->get('/dashboard')
        ->assertOk()
        ->assertSee('My Own Prompt')
        ->assertDontSee('Someone Else Prompt');
});
