<?php

use App\Models\Category;
use App\Models\Frame;
use App\Models\Order;
use App\Models\Pack;
use App\Models\Prompt;
use App\Models\PromptReport;
use App\Models\PromptVersion;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * R4 (v1.7.7 Bug Hunt Raid) — edge-state matrix.
 *
 * The surfaces the catalog lists (storefront, detail variants, profiles,
 * versions, packs, feed, every dashboard tab, every admin pill, both
 * checkout rails, auth) rendered under: empty fixture, single row, 30-row
 * pagination, a 200-char title, a Devanagari bio, a deleted-author version
 * row, a deleted-frame user and an impersonation session.
 *
 * The bar: honest copy, never a blank panel, never a 500.
 */

function raidEdgeAssertClean($response, string $context): void
{
    $status = $response->getStatusCode();
    expect($status)->not->toBe(500, "{$context} returned 500");
    expect($status)->toBeIn([200, 302, 403, 404], "{$context} returned {$status}");

    if ($status !== 200) {
        return;
    }

    $body = (string) $response->getContent();

    expect($body)->not->toContain('Undefined variable')
        ->and($body)->not->toContain('Undefined property')
        ->and($body)->not->toContain('Whoops, looks like something went wrong.');

    if (str_contains($response->headers->get('Content-Type', ''), 'html')) {
        expect(substr_count($body, '<title>'))->toBe(1, "{$context} must serve exactly one <title>");
        expect(strlen($body))->toBeGreaterThan(1200, "{$context} looks like a blank panel");
        expect($body)->not->toMatch('/@?\{\{\s*\$[A-Za-z_]/', "{$context} leaks a Blade expression");
    }
}

test('empty catalogue, dashboard and admin surfaces render honest copy — never blank, never 500', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $member = User::factory()->create();
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR, 'username' => 'emptycreator', 'name' => 'Empty Creator']);
    Category::factory()->create(['name' => 'Empty Category']);

    // Surfaces with a distinct honest empty-state line.
    $honest = [
        [route('library.index'), $member, 'No prompts found'],
        [route('packs.index'), $member, 'No packs are live yet'],
        [route('feed.index'), $member, 'The feed is quiet'],
        [route('purchases.index'), $member, 'No purchases yet.'],
        [route('dashboard'), $member, 'No prompts yet'],
        [route('creators.show', $creator), $member, 'Nothing published yet'],
    ];

    foreach ($honest as [$url, $user, $copy]) {
        $response = $this->actingAs($user)->get($url);
        raidEdgeAssertClean($response, $url);
        expect($response->getStatusCode())->toBe(200);
        // NOTE: Pest's toContain() treats a second argument as another NEEDLE,
        // not a message — assert via str_contains + toBeTrue for a message.
        expect(str_contains((string) $response->getContent(), $copy))
            ->toBeTrue("{$url} lost its empty-state copy");
    }

    // Blanket sweep: no blank panels, no 500s.
    $sweep = [
        [route('home'), null],
        [route('pages.about'), null],
        [route('login'), null],
        [route('register'), null],
        [route('password.request'), null],
        [route('dashboard.earnings'), $creator],
        [route('dashboard.profile.edit'), $member],
        [route('admin.dashboard'), $admin],
        [route('admin.prompts.index'), $admin],
        [route('admin.users.index'), $admin],
        [route('admin.reports.index'), $admin],
        [route('admin.orders.index'), $admin],
        [route('admin.packs.index'), $admin],
        [route('admin.finance'), $admin],
        [route('admin.badges.index'), $admin],
        [route('admin.frames.index'), $admin],
        [route('admin.comp-grants.create'), $admin],
        [route('admin.email.edit'), $admin],
        [route('admin.security.edit'), $admin],
        [route('admin.manual-methods.index'), $admin],
        [route('admin.payments.edit'), $admin],
        [route('admin.brand.edit'), $admin],
        [route('admin.tool-logos.index'), $admin],
        [route('admin.update'), $admin],
    ];

    foreach ($sweep as [$url, $user]) {
        $response = $user ? $this->actingAs($user)->get($url) : $this->get($url);
        raidEdgeAssertClean($response, $url);
    }

    // The Finance desk must survive an empty ledger with a real zero.
    expect((string) $this->actingAs($admin)->get(route('admin.finance'))->getContent())
        ->toContain('Rs. 0');
});

test('overflow, unicode and deleted-relation contexts render — 200-char title, Devanagari bio, Former creator, deleted frame', function () {
    Storage::fake('public');
    $viewer = User::factory()->create();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    // 200-char title (validation caps at 160; the renderer must not care).
    $longTitle = rtrim(str_repeat('Overflowing Title Segment ', 8));
    $longTitle = substr($longTitle, 0, 200);
    $owner = User::factory()->create(['role' => User::ROLE_CREATOR, 'username' => 'unicodeowner', 'name' => 'Unicode Owner']);
    $owner->forceFill(['bio' => 'काठमाडौंका सिर्जनशील लेखक — प्रॉम्प्ट इन्जिनियर।'])->save();

    $prompt = Prompt::factory()->for($owner, 'creator')->create([
        'title' => $longTitle,
        'status' => Prompt::STATUS_PUBLISHED,
        'category_id' => Category::factory()->create()->id,
    ]);
    $prompt->versions()->create([
        'version_number' => 1,
        'body' => 'Body of the long-title prompt.',
        'changelog' => 'Initial release.',
        'user_id' => $owner->id,
        'status' => PromptVersion::STATUS_PUBLISHED,
    ]);

    // Deleted author: a version row whose author is force-deleted.
    $vanished = User::factory()->create();
    $prompt->versions()->create([
        'version_number' => 2,
        'body' => 'Second body.',
        'changelog' => 'Second release.',
        'user_id' => $vanished->id,
        'status' => PromptVersion::STATUS_PUBLISHED,
    ]);
    $vanished->forceDelete();

    // Deleted frame: the wearer falls back to no frame (nullOnDelete).
    $frame = Frame::query()->create(['name' => 'Doomed Frame', 'image_path' => 'frames/doomed.png', 'is_active' => true]);
    $wearer = User::factory()->create(['role' => User::ROLE_CREATOR, 'username' => 'framelessone', 'name' => 'Frameless One', 'active_frame_id' => $frame->id]);
    $frame->delete();

    $targets = [
        [route('prompts.show', $prompt), $viewer],
        [route('prompts.versions', $prompt), $viewer],
        [route('creators.show', $owner), $viewer],
        [route('creators.show', $wearer), $viewer],
        [route('library.index'), $viewer],
        [route('library.index', ['q' => 'Overflowing']), $viewer],
        [route('admin.prompts.index'), $admin],
        [route('admin.users.index'), $admin],
    ];

    foreach ($targets as [$url, $user]) {
        $response = $this->actingAs($user)->get($url);
        raidEdgeAssertClean($response, $url);
        expect($response->getStatusCode())->toBe(200);

        $body = (string) $response->getContent();

        if ($url === route('prompts.show', $prompt)) {
            expect($body)->toContain(substr($longTitle, 0, 40));
        }
        if ($url === route('prompts.versions', $prompt)) {
            expect($body)->toContain('Former creator');
        }
        if ($url === route('creators.show', $owner)) {
            expect($body)->toContain('सिर्जनशील लेखक');
        }
        if ($url === route('creators.show', $wearer)) {
            // frameless fallback: the page renders and the profile exists.
            expect($body)->toContain('Frameless One');
        }
    }
});

test('pagination page two renders on the 30-row surfaces', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $member = User::factory()->create();

    Prompt::factory()->count(31)->withVersion()->create(['status' => Prompt::STATUS_PUBLISHED]);
    User::factory()->count(31)->create();
    PromptReport::factory()->count(31)->create();

    $orders = Order::factory()->count(31)->create(['buyer_id' => $member->id]);

    $pageTwos = [
        [route('library.index', ['page' => 2]), $member],
        [route('admin.prompts.index', ['page' => 2]), $admin],
        [route('admin.users.index', ['page' => 2]), $admin],
        [route('admin.reports.index', ['page' => 2]), $admin],
        [route('admin.orders.index', ['page' => 2]), $admin],
        [route('purchases.index', ['page' => 2]), $member],
    ];

    foreach ($pageTwos as [$url, $user]) {
        $response = $this->actingAs($user)->get($url);
        raidEdgeAssertClean($response, $url);
        expect($response->getStatusCode())->toBe(200, "{$url} must render its second page");
    }

    expect($orders)->toHaveCount(31);
});

test('checkout renders both rails when both are enabled', function () {
    Setting::query()->updateOrCreate(['key' => 'manual_payment_enabled'], ['value' => '1']);
    Setting::query()->updateOrCreate(['key' => 'esewa_enabled'], ['value' => '1']);
    Setting::query()->updateOrCreate(['key' => 'esewa_merchant_code'], ['value' => 'EPAYTEST']);
    Setting::query()->updateOrCreate(['key' => 'esewa_secret_key'], ['value' => '8gBm/:&EnhH.1/q']);

    $buyer = User::factory()->create();
    \App\Models\ManualPaymentMethod::query()->create(['name' => 'R4 eSewa', 'kind' => 'esewa', 'position' => 1, 'active' => true]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => Order::STATUS_PENDING]);

    $response = $this->actingAs($buyer)->get(route('checkout.show', $order));
    raidEdgeAssertClean($response, 'checkout rails');
    $response->assertOk()->assertSee('eSewa')->assertSee('Manual')->assertSee('R4 eSewa');

    // The hosted-gateway rail now really builds (a redirect view), not a 403.
    $esewa = $this->actingAs($buyer)->get(route('checkout.esewa.pay', $order));
    expect($esewa->getStatusCode())->toBeIn([200, 302]);
    expect($esewa->getStatusCode())->not->toBe(403);
});

test('the impersonation context renders key surfaces with the chrome bar and no identity leaks', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $member = User::factory()->create(['username' => 'edgeimpersonated', 'name' => 'Edge Impersonated']);
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR, 'username' => 'edgecreator', 'name' => 'Edge Creator']);
    Prompt::factory()->for($creator, 'creator')->withVersion()->create(['status' => Prompt::STATUS_PUBLISHED]);

    $this->actingAs($admin)->post(route('admin.users.impersonate', $member))->assertRedirect();
    expect(session('impersonator_id'))->toBe($admin->id);

    // The test client does not carry session cookies between requests, so the
    // browser's continuation is expressed explicitly: the session data the
    // POST created + the impersonated user. (The bar renders the handle inside
    // a <strong>, so assert the parts, not one contiguous string.)
    foreach ([route('home'), route('library.index'), route('dashboard'), route('purchases.index'), route('creators.show', $creator)] as $url) {
        $response = $this->withSession(['impersonator_id' => $admin->id])->actingAs($member)->get($url);
        raidEdgeAssertClean($response, $url);
        expect($response->getStatusCode())->toBe(200);

        $body = (string) $response->getContent();
        expect(str_contains($body, 'Acting as'))->toBeTrue("{$url} lost the impersonation chrome bar");
        expect(str_contains($body, '@edgeimpersonated'))->toBeTrue("{$url} lost the impersonated handle");
        expect(str_contains($body, 'Return to my account'))->toBeTrue("{$url} lost the return action");
    }
});
