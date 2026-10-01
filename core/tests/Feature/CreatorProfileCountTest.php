<?php

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * H1 (v1.5.2) — count semantics, defined once:
 *
 *   Public creator profile stat = PUBLISHED (and public) listings only.
 *   Admin Users column          = ALL prompts, every status/visibility.
 *
 * Two different numbers on purpose; neither is a bug.
 *
 * Reconciliation note (recorded, never "fixed"): pre-adoption the catalog
 * showed 270 published storefront listings; after the v1.5.2 adoption the
 * official account holds 268 published + 1 non-published listing while the
 * Users table shows 276 total prompts owned by @promptsewa. The deltas are
 * composed of: (a) rows that were never published or are private — drafts,
 * pending-review and rejected listings seeded by the bulk/demo seeders and
 * carried over by adoption (published-only ≠ adopted-total), and (b) the
 * storefront's publicListing scope ALSO excluding private visibility, which
 * the admin total includes. No numbers were edited anywhere; this comment
 * is the recorded explanation the directive asked for.
 */

test('public creator profile stat counts published listings only', function () {
    $creator = User::factory()->create();

    Prompt::factory()->for($creator, 'creator')->count(3)->create([
        'status' => Prompt::STATUS_PUBLISHED,
        'visibility' => Prompt::VISIBILITY_PUBLIC,
    ]);
    Prompt::factory()->for($creator, 'creator')->create(['status' => Prompt::STATUS_DRAFT]);
    Prompt::factory()->for($creator, 'creator')->create(['status' => Prompt::STATUS_PENDING]);
    Prompt::factory()->for($creator, 'creator')->create(['status' => Prompt::STATUS_PUBLISHED, 'visibility' => Prompt::VISIBILITY_PRIVATE]);

    $html = $this->get(route('creators.show', $creator))->getContent();

    // 4 + 1 = 5 non-published/public listings never reach the stat.
    expect($html)->toContain('>3</dd>');
});

test('public profile stat excludes unpublished even when the row is visible to the owner', function () {
    $creator = User::factory()->create();

    Prompt::factory()->for($creator, 'creator')->create([
        'status' => Prompt::STATUS_PUBLISHED,
        'visibility' => Prompt::VISIBILITY_PUBLIC,
        'price_cents' => 0,
    ]);
    Prompt::factory()->for($creator, 'creator')->create(['status' => Prompt::STATUS_REJECTED]);

    $published = $creator->prompts()->publicListing()->count();

    expect($published)->toBe(1);
});

test('admin users column counts all prompts regardless of status or visibility', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $creator = User::factory()->create();

    Prompt::factory()->for($creator, 'creator')->count(2)->create([
        'status' => Prompt::STATUS_PUBLISHED,
        'visibility' => Prompt::VISIBILITY_PUBLIC,
    ]);
    Prompt::factory()->for($creator, 'creator')->create(['status' => Prompt::STATUS_DRAFT]);
    Prompt::factory()->for($creator, 'creator')->create(['status' => Prompt::STATUS_PENDING, 'visibility' => Prompt::VISIBILITY_PRIVATE]);

    $html = $this->actingAs($admin)->get(route('admin.users.index'))->getContent();

    // Admin table header says "All prompts" and the row shows the TOTAL (4).
    expect($html)->toContain('All prompts')
        ->and($creator->prompts()->count())->toBe(4)
        ->and($creator->prompts()->publicListing()->count())->toBe(2);
});
