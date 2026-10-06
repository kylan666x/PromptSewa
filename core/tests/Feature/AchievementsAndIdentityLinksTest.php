<?php

use App\Models\Badge;
use App\Models\Prompt;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * P2b + P4b (v1.7.1) — user-facing Achievements and mobile identity links.
 */
uses(RefreshDatabase::class);

function badgeFor(string $criterion): Badge
{
    return Badge::query()->create([
        'name' => ucfirst(str_replace('_', ' ', $criterion)),
        'slug' => $criterion,
        'criterion' => $criterion,
        'is_active' => true,
    ]);
}

// ---------------------------------------------------------------------------
// P2b — profile Achievements section
// ---------------------------------------------------------------------------

test('a badged profile renders the Achievements section between stats and prompts', function () {
    $creator = User::factory()->create(['username' => 'badgedhero', 'xp' => 5000]);
    $badge = badgeFor('first_publish');
    UserBadge::query()->create([
        'user_id' => $creator->id,
        'badge_id' => $badge->id,
        'awarded_at' => now(),
    ]);

    Prompt::factory()->for($creator, 'creator')->published()->create();

    $html = $this->get(route('creators.show', $creator))->getContent();

    expect($html)->toContain('Achievements')
        ->and($html)->toContain('Lv 5')
        ->and($html)->toContain('First publish')
        // The awarded date renders in mono.
        ->and($html)->toContain(now()->format('M j, Y'))
        // Section sits between the stats strip and the prompts catalog.
        ->and(mb_strpos($html, 'Achievements'))->toBeLessThan(mb_strpos($html, 'aria-label="Prompts by'));
});

test('an unbadged profile shows the honest empty state', function () {
    $creator = User::factory()->create(['username' => 'nobodyet']);
    Prompt::factory()->for($creator, 'creator')->published()->create();

    $html = $this->get(route('creators.show', $creator))->getContent();

    expect($html)->toContain('No badges yet — publish your first prompt to earn one.');
});

// ---------------------------------------------------------------------------
// P2b — dashboard Achievements tab
// ---------------------------------------------------------------------------

test('the dashboard achievements tab shows the earned wall and real progress rows', function () {
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $badge = badgeFor('first_publish');
    UserBadge::query()->create([
        'user_id' => $creator->id,
        'badge_id' => $badge->id,
        'awarded_at' => now(),
    ]);

    // 3 REAL sales (paid order lines — F1 v1.9.2) → sales_10 progress must
    // read 3/10. Seeding `prompts.sales_count` would prove nothing: nothing
    // in the app writes that column.
    $sold = Prompt::factory()->for($creator, 'creator')->published()->create();

    foreach (range(1, 3) as $_) {
        recordPaidSale($sold, User::factory()->create());
    }

    $html = $this->actingAs($creator)->get(route('dashboard', ['tab' => 'achievements']))->getContent();

    expect($html)->toContain('Earned badges')
        ->and($html)->toContain('First publish')
        // Real progress from actual counts — no fabricated numbers.
        ->and($html)->toContain('3/10')
        ->and($html)->toContain('Publish your first prompt')
        ->and($html)->toContain('Reach 10 sales')
        // Hidden badges don't exist: every criterion renders a row.
        ->and($html)->toContain('Reach 50 sales');
});

// ---------------------------------------------------------------------------
// P4b — mobile identity links
// ---------------------------------------------------------------------------

test('the dock You slot links to the public profile for authed users', function () {
    $user = User::factory()->create(['username' => 'dockowner']);

    $html = $this->actingAs($user)->get(route('home'))->getContent();
    preg_match('/<nav aria-label="Mobile primary".*?<\/nav>/s', $html, $m);
    $dock = $m[0] ?? '';

    expect($dock)->toContain('href="'.route('creators.show', $user).'"')
        ->and($dock)->not->toContain(route('dashboard.profile.edit'));
});

test('the desktop dropdown keeps distinct view and edit profile entries', function () {
    $user = User::factory()->create(['username' => 'dropdownowner']);

    $html = $this->actingAs($user)->get(route('home'))->getContent();

    expect($html)->toContain('data-testid="nav-view-profile"')
        ->and($html)->toContain('data-testid="nav-edit-profile"')
        ->and($html)->toContain(route('creators.show', $user))
        ->and($html)->toContain(route('dashboard.profile.edit'));
});

test('the profile page hosts the You menu with a view-profile row', function () {
    $user = User::factory()->create(['username' => 'youmenu']);

    $html = $this->actingAs($user)->get(route('dashboard.profile.edit'))->getContent();

    expect($html)->toContain(route('creators.show', $user));
});
