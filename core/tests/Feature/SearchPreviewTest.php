<?php

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use App\Services\PromptSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

function previewUser(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Bibek Shrestha',
        'username' => 'bibek-shrestha',
        'email' => 'bibek@promptsewa.test',
        'password' => 'password',
        'role' => User::ROLE_CREATOR,
    ], $overrides));
}

function previewPrompt(User $creator, array $overrides = []): Prompt
{
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create(array_merge([
        'title' => 'Cold Email Sequencer',
        'search_text' => 'Cold Email Sequencer outbound sales outreach that gets replies',
    ], $overrides));

    PromptVersion::create([
        'prompt_id' => $prompt->id,
        'version_number' => 1,
        'body' => 'Body.',
        'tags' => ['preview'],
        'user_id' => $creator->id,
        'status' => PromptVersion::STATUS_PUBLISHED,
    ]);

    return $prompt;
}

// ---------------------------------------------------------------------------
// /search/preview endpoint (TASK 2)
// ---------------------------------------------------------------------------

test('search preview returns prompts and creators as JSON with real handles', function () {
    $creator = previewUser(['name' => 'Email Wizard', 'username' => 'emailwizard']);
    previewPrompt($creator);

    $this->getJson(route('search.preview', ['q' => 'email']))
        ->assertOk()
        ->assertJsonStructure([
            'prompts' => [['title', 'type', 'price', 'url']],
            'creators' => [['name', 'username', 'prompts_count', 'url']],
        ])
        ->assertJsonFragment(['title' => 'Cold Email Sequencer'])
        ->assertJsonFragment(['name' => 'Email Wizard', 'username' => 'emailwizard'])
        // B2: typeahead rows must carry the actual handle, never a placeholder.
        ->assertJsonPath('creators.0.username', 'emailwizard');

    expect(json_encode(route('search.preview', ['q' => 'email'])))->not->toContain('{{');
});

test('search preview enforces the max term length', function () {
    $this->getJson(route('search.preview', ['q' => str_repeat('x', PromptSearchService::MAX_TERM_LENGTH + 5)]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');
});

test('search preview returns empty arrays for a blank query', function () {
    $this->getJson(route('search.preview', ['q' => '  ']))
        ->assertOk()
        ->assertJsonPath('prompts', [])
        ->assertJsonPath('creators', []);
});

test('search preview strips natural language stop words', function () {
    $creator = previewUser(['username' => 'bibek']);
    previewPrompt($creator, ['title' => 'Blog Post Machine', 'search_text' => 'Blog Post Machine writes long form blog articles']);

    // "I want a blog" normalizes to "blog" and must find the blog prompt.
    $this->getJson(route('search.preview', ['q' => 'I want a blog']))
        ->assertOk()
        ->assertJsonFragment(['title' => 'Blog Post Machine']);
});

test('search preview is throttled like the full search', function () {
    $this->getJson(route('search.preview', ['q' => 'test']))
        ->assertOk();

    expect(Route::getRoutes()->getByName('search.preview')->gatherMiddleware())
        ->toContain('throttle:60,1');
});

// ---------------------------------------------------------------------------
// normalizeQuery (TASK 3)
// ---------------------------------------------------------------------------

test('normalizeQuery strips stop words and collapses whitespace', function () {
    $service = app(PromptSearchService::class);

    expect($service->normalizeQuery('I want a blog'))->toBe('blog')
        ->and($service->normalizeQuery('  The   BEST   email  '))->toBe('best email')
        ->and($service->normalizeQuery('the of and'))->toBe('the of and')
        ->and($service->normalizeQuery('  '))->toBe('');
});

// ---------------------------------------------------------------------------
// username validation + profile update (TASK 1)
// ---------------------------------------------------------------------------

test('profile update saves a valid username', function () {
    $user = previewUser(['name' => 'Old Name']);

    $this->actingAs($user)
        ->from(route('dashboard.profile.edit'))
        ->put(route('dashboard.profile.update'), [
            'name' => 'New Name',
            'username' => 'newname',
            'bio' => 'hi',
        ])
        ->assertRedirect();

    expect($user->refresh()->username)->toBe('newname')
        ->and($user->refresh()->name)->toBe('New Name');
});

test('username must be unique', function () {
    previewUser(['username' => 'taken', 'email' => 'other@promptsewa.test']);
    $user = previewUser(['name' => 'Me Too', 'email' => 'me@promptsewa.test']);

    $this->actingAs($user)
        ->put(route('dashboard.profile.update'), [
            'name' => $user->name,
            'username' => 'taken',
        ])
        ->assertSessionHasErrors('username');
});

test('username rejects non alpha-dash characters and long values', function () {
    $user = previewUser();

    $this->actingAs($user)
        ->put(route('dashboard.profile.update'), [
            'name' => $user->name,
            'username' => 'bad handle!',
        ])
        ->assertSessionHasErrors('username');

    $this->actingAs($user)
        ->put(route('dashboard.profile.update'), [
            'name' => $user->name,
            'username' => str_repeat('a', 31),
        ])
        ->assertSessionHasErrors('username');
});

test('username cannot be cleared once set (required since v1.4.1)', function () {
    $user = previewUser(['username' => 'clearme']);

    $this->actingAs($user)
        ->put(route('dashboard.profile.update'), [
            'name' => $user->name,
            'username' => '',
        ])
        ->assertSessionHasErrors('username');

    expect($user->refresh()->username)->toBe('clearme');
});

test('profile edit form renders the method spoof and username field', function () {
    $user = previewUser();

    $this->actingAs($user)
        ->get(route('dashboard.profile.edit'))
        ->assertOk()
        ->assertSee('name="_method" value="PUT"', false)
        ->assertSee('name="username"', false);
});

// ---------------------------------------------------------------------------
// /creators/{username} route binding (TASK 1)
// ---------------------------------------------------------------------------

test('creator profile resolves by username', function () {
    $creator = previewUser(['username' => 'bibek']);
    previewPrompt($creator);

    $this->get('/creators/bibek')->assertOk()->assertSee('Bibek Shrestha');
});

test('creator profile falls back to the display name when no username is set', function () {
    $creator = previewUser(['name' => 'Fallback Person']);
    previewPrompt($creator);

    $this->get('/creators/Fallback%20Person')->assertOk()->assertSee('Fallback Person');
});

test('creator route() helper generates the username URL', function () {
    $creator = previewUser(['username' => 'bibek']);

    expect(route('creators.show', $creator))->toEndWith('/creators/bibek');
});
