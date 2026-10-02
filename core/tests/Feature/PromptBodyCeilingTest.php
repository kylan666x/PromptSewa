<?php

use App\Http\Requests\PromptFormRequest;
use App\Models\Category;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * H1 (v1.7.3 hotfix) — prompt body ceiling 4,000 → 50,000 chars.
 *
 * Rendered-route discipline like A3: every assertion hits a served route
 * or a real POST round-trip. The old 4,000 ceiling blocked serious
 * system/agent prompts; research baseline for the new ceiling:
 * PromptBase truncates at 2,000 / 1,000 (marketplace peer — the old 4k was
 * already ahead), GPT-4.1-class chat ~8k chars per message, Claude ~40k
 * tokens per message (far above 50k chars). 50,000 chars ≈ 12.5k words —
 * a sane guardrail, no DB truncation risk (LONGTEXT migration in the same
 * release: 2026_09_30_210000).
 */
uses(RefreshDatabase::class);

function h1Creator(): User
{
    return User::factory()->create(['role' => User::ROLE_CREATOR]);
}

function h1Payload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Long Form Agentic System Prompt Framework',
        'description' => 'A full agentic system prompt with procedure, guardrails and output contracts for production use.',
        'category_id' => Category::factory()->create()->id,
        'type' => 'text',
        'body' => 'A body long enough to pass the thirty character floor.',
        'tags' => 'agentic, system-prompt',
        'recommended_tools' => ['ChatGPT', 'Claude'],
        'audience' => null,
        'tips' => null,
        'price_npr' => '0',
        'visibility' => 'public',
        'changelog' => '',
    ], $overrides);
}

test('create serves the body textarea with the fifty thousand char maxlength and counter', function () {
    $html = $this->actingAs(h1Creator())->get(route('dashboard.prompts.create'))->getContent();

    expect($html)->toContain('maxlength="'.PromptFormRequest::MAX_BODY_CHARS.'"')
        ->and($html)->toContain(number_format(PromptFormRequest::MAX_BODY_CHARS).' chars')
        ->and($html)->not->toContain('maxlength="4000"');
});

test('edit serves the same ceiling for an existing prompt', function () {
    $creator = h1Creator();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();

    $html = $this->actingAs($creator)->get(route('dashboard.prompts.edit', $prompt))->getContent();

    expect($html)->toContain('maxlength="'.PromptFormRequest::MAX_BODY_CHARS.'"')
        ->and($html)->not->toContain('maxlength="4000"');
});

test('a body under the old four thousand ceiling still stores fine', function () {
    $creator = h1Creator();
    // NOTE: global TrimStrings middleware strips trailing whitespace from
    // POST inputs, so these bodies end with a real sentence, not a space.
    $body = str_repeat('Still comfortably within the ceiling. ', 99).'Final line ends here.'; // ≈3,819 chars

    $this->actingAs($creator)
        ->post(route('dashboard.prompts.store'), h1Payload(['body' => $body]))
        ->assertSessionHasNoErrors();

    $prompt = Prompt::where('title', 'Long Form Agentic System Prompt Framework')->first();

    expect($prompt->latestVersion->body)->toBe($body);
});

test('a body between four thousand and fifty thousand chars is accepted now', function () {
    $creator = h1Creator();
    // 30,000 chars — squarely rejected under the old 4,000 rule.
    $body = str_repeat('This line only exists to push the body past the old limit. ', 468).'Final line ends here.';

    expect(mb_strlen($body))->toBeGreaterThan(4000)
        ->and(mb_strlen($body))->toBeLessThanOrEqual(PromptFormRequest::MAX_BODY_CHARS);

    $this->actingAs($creator)
        ->post(route('dashboard.prompts.store'), h1Payload(['body' => $body]))
        ->assertSessionHasNoErrors();

    $prompt = Prompt::where('title', 'Long Form Agentic System Prompt Framework')->first();

    expect($prompt->latestVersion->body)->toBe($body)
        ->and(mb_strlen($prompt->latestVersion->body))->toBeGreaterThan(4000);
});

test('a body over fifty thousand chars is a validation error', function () {
    $creator = h1Creator();
    $body = str_repeat('x', PromptFormRequest::MAX_BODY_CHARS + 1);

    $this->actingAs($creator)
        ->post(route('dashboard.prompts.store'), h1Payload(['body' => $body]))
        ->assertSessionHasErrors(['body']);
});

test('an edit raising the body past four thousand chars persists in full', function () {
    $creator = h1Creator();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();
    $body = str_repeat('Edited long body line with real content. ', 999).'Final line ends here.'; // ≈41,000 chars

    $this->actingAs($creator)
        ->put(route('dashboard.prompts.update', $prompt), h1Payload([
            'title' => $prompt->title,
            'description' => $prompt->description,
            'category_id' => $prompt->category_id,
            'body' => $body,
        ]))
        ->assertSessionHasNoErrors();

    expect($prompt->latestVersion->refresh()->body)->toBe($body)
        ->and(mb_strlen($prompt->latestVersion->body))->toBeGreaterThan(4000);
});
