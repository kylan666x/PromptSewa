<?php

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function seedOnePublicPrompt(array $overrides = []): Prompt
{
    $prompt = Prompt::factory()->published()->create($overrides);

    PromptVersion::create([
        'prompt_id' => $prompt->id,
        'version_number' => 1,
        'body' => 'A demo prompt body.',
        'tags' => ['demo', 'test'],
        'user_id' => $prompt->user_id,
        'status' => PromptVersion::STATUS_PUBLISHED,
    ]);

    return $prompt->fresh(['category', 'creator', 'latestVersion']);
}

test('homepage renders successfully for guests', function () {
    $this->get('/')->assertOk();
});

test('homepage shows prompt cards for seeded public prompts', function () {
    $prompt = seedOnePublicPrompt(['title' => 'Cold Email Sequencer']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Cold Email Sequencer')
        ->assertSee(route('prompts.show', $prompt), false);
});

test('homepage displays no deployment or infrastructure text', function () {
    seedOnePublicPrompt();

    $response = $this->get('/');
    $content = strtolower($response->getContent());

    foreach (['cpanel', 'shared hosting', 'deployment runbook', 'artisan', 'sqlite', '.env'] as $banned) {
        expect($content)->not->toContain($banned);
    }
});

test('homepage shows storefront copy and stats', function () {
    seedOnePublicPrompt();

    $this->get('/')
        ->assertOk()
        ->assertSee('Version control for your')
        ->assertSee('Published prompts')
        ->assertSee('Creators')
        ->assertSee('Free prompts');
});

test('library page lists prompts and category sidebar', function () {
    $prompt = seedOnePublicPrompt(['title' => 'Unique Sidebar Probe']);

    $this->get('/prompts')
        ->assertOk()
        ->assertSee('Unique Sidebar Probe')
        ->assertSee($prompt->category->name);
});

test('welcome placeholder view no longer exists', function () {
    expect(view()->exists('welcome'))->toBeFalse();
});

test('prompt cards show a cover image when present and a placeholder otherwise', function () {
    $withCover = seedOnePublicPrompt(['title' => 'Covered Probe', 'cover_image_path' => 'covers/demo.jpg']);

    $this->get('/prompts')
        ->assertOk()
        ->assertSee('<img', false)
        ->assertSee('/storage/covers/demo.jpg', false);

    $placeholder = seedOnePublicPrompt(['title' => 'Bare Probe']);
    $response = $this->get('/prompts');
    $content = $response->getContent();

    expect(str_contains($content, '/storage/covers/demo.jpg'))->toBeTrue()
        ->and(str_contains($content, 'BP'))->toBeTrue(); // deterministic initials for the placeholder "Bare Probe"
});

test('navbar shows login and register links for guests, dashboard for users', function () {
    seedOnePublicPrompt();

    $this->get('/')
        ->assertOk()
        ->assertSee(route('login'))
        ->assertSee(route('register'));

    $user = User::create([
        'name' => 'Nima Creator',
        'email' => 'nima@example.test',
        'password' => Hash::make('super-secret-9'),
        'role' => User::ROLE_CREATOR,
    ]);

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee(route('dashboard'))
        ->assertSee('Log out');
});
