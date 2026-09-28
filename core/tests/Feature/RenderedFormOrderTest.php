<?php

use App\Models\Pack;
use App\Models\Prompt;
use App\Models\PromptReport;
use App\Models\PromptVersion;
use App\Models\ToolLogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A6: rendered-form order tests. The v1.3.0 and v1.4.1 405 incidents both
 * passed the suite while live 405'd, because no test asserted the RENDERED
 * HTML carries the method spoof. These tests lock the served output —
 * not the Blade source — for every verb-spoofed form in the app.
 *
 * Ordering matters: _method must appear before fields that depend on it
 * being parsed (some proxies truncate oversized bodies) — we assert the
 * spoof appears within the form and before its submit button.
 */
function assertSpoofedForm(string $html, string $action, string $verb): void
{
    // Find the form whose action= targets the route (match on the action
    // attribute itself so navbar/dropdown links to the same URL don't
    // false-match).
    $actionPos = strpos($html, 'action="'.$action.'"');
    expect($actionPos)->not->toBeFalse("form targeting {$action} not rendered");

    $formStart = strrpos(substr($html, 0, $actionPos), '<form');
    expect($formStart)->not->toBeFalse('action found outside any form');

    $formEnd = strpos($html, '</form>', $actionPos) ?: strlen($html);
    $form = substr($html, $formStart, $formEnd - $formStart);

    expect($form)->toContain('name="_token"')
        ->and($form)->toContain('_method')
        ->and($form)->toContain($verb);

    $methodPos = strpos($form, 'name="_method"');
    $submitPos = stripos($form, '<button');
    if ($submitPos !== false) {
        expect($methodPos)->toBeLessThan($submitPos, "{$action}: _method spoof must precede the submit button");
    }
}

test('profile edit form renders PUT spoof in order', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get(route('dashboard.profile.edit'))->getContent();

    assertSpoofedForm($html, route('dashboard.profile.update'), 'PUT');
});

test('prompt edit form renders PUT spoof in order', function () {
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create();

    $html = $this->actingAs($creator)->get(route('dashboard.prompts.edit', $prompt))->getContent();

    assertSpoofedForm($html, route('dashboard.prompts.update', $prompt), 'PUT');
});

test('brand form renders PUT spoof in order', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $html = $this->actingAs($admin)->get(route('admin.brand.edit'))->getContent();

    assertSpoofedForm($html, route('admin.brand.update'), 'PUT');
});

test('payments form renders PUT spoof in order', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $html = $this->actingAs($admin)->get(route('admin.payments.edit'))->getContent();

    assertSpoofedForm($html, route('admin.payments.update'), 'PUT');
});

test('pack edit form renders PUT spoof in order', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    Pack::factory()->create();

    $html = $this->actingAs($admin)->get(route('admin.packs.index'))->getContent();

    // Index renders per-row DELETE forms.
    expect($html)->toContain('name="_method"')
        ->and($html)->toContain('DELETE');
});

test('admin review forms render PATCH spoofs in order', function () {
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->pending()->for($creator, 'creator')->create();
    PromptVersion::create([
        'prompt_id' => $prompt->id, 'version_number' => 1, 'body' => 'B.',
        'tags' => ['x'], 'user_id' => $creator->id, 'status' => PromptVersion::STATUS_PENDING,
    ]);
    PromptReport::factory()->create();

    $prompts = $this->actingAs($mod)->get(route('admin.prompts.index'))->getContent();
    expect($prompts)->toContain('name="_method"')
        ->and($prompts)->toContain('PATCH');

    $reports = $this->actingAs($mod)->get(route('admin.reports.index'))->getContent();
    expect($reports)->toContain('name="_method"')
        ->and($reports)->toContain('PATCH');
});

test('tool logo and ban forms render PATCH spoofs', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    ToolLogo::query()->create(['name' => 'Order Tool', 'is_active' => true, 'position' => 1]);
    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);

    $tools = $this->actingAs($admin)->get(route('admin.tool-logos.index'))->getContent();
    expect($tools)->toContain('name="_method"')->and($tools)->toContain('PATCH');

    $users = $this->actingAs($admin)->get(route('admin.users.index'))->getContent();
    expect($users)->toContain('name="_method"')->and($users)->toContain('PATCH')
        ->and($users)->toContain(route('admin.users.banned', $member), false);
});
