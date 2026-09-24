<?php

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Services\PromptSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedSearchCorpus(): array
{
    $hit = Prompt::factory()->published()->create([
        'title' => 'Cold Email Sequencer',
        'search_text' => 'Cold Email Sequencer outbound sales outreach sequences that get replies',
    ]);
    $other = Prompt::factory()->published()->create([
        'title' => 'Vector Icon Generator',
        'search_text' => 'Vector Icon Generator minimalist stroke icons for interfaces',
    ]);

    foreach ([$hit, $other] as $prompt) {
        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => 1,
            'body' => 'Body for '.$prompt->title,
            'tags' => ['searchtest'],
            'user_id' => $prompt->user_id,
            'status' => PromptVersion::STATUS_PUBLISHED,
        ]);
    }

    return [$hit, $other];
}

test('search finds published public prompts through the scout service', function () {
    seedSearchCorpus();

    $results = app(PromptSearchService::class)->search('sequencer')->getCollection();

    expect($results->pluck('title'))->toContain('Cold Email Sequencer')
        ->and($results->pluck('title'))->not->toContain('Vector Icon Generator');
});

test('search validation rejects terms over the max length', function () {
    $this->get('/prompts?q='.str_repeat('x', PromptSearchService::MAX_TERM_LENGTH + 10))
        ->assertSessionHasErrors('q');
});

test('search term is trimmed before matching', function () {
    seedSearchCorpus();

    $this->get('/prompts?q='.urlencode('  sequencer  '))
        ->assertOk()
        ->assertSee('Cold Email Sequencer');
});

test('empty search shows the polished empty state', function () {
    seedSearchCorpus();

    $this->get('/prompts?q=zzzqqqxxx')
        ->assertOk()
        ->assertSee('No prompts found');
});

test('blank search lists prompts newest first', function () {
    Prompt::factory()->count(3)->published()->create();

    $this->get('/prompts')
        ->assertOk();

    expect(app(PromptSearchService::class)->search('')->total())->toBe(3);
});
