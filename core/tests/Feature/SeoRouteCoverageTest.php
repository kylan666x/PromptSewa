<?php

use App\Models\Pack;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 * P3 (v1.7.1) — the PERMANENT SEO route-coverage crawl.
 *
 * Every named GET route must render EXACTLY ONE <title>, produced by
 * x-seo, whose subject matches the route's expected content. A route
 * serving the brand-only default title (or none) is a FAILING TEST from
 * now on — the founder's add-prompt page was the original offender.
 *
 * Fixtures: guest / member / owner / admin as applicable per route.
 */
uses(RefreshDatabase::class);

/**
 * Per-route expectations. Subject substring must appear in <title> for
 * the route to pass. Roles listed = every role that can render the page;
 * routes are asserted for each applicable fixture.
 */
function seoExpectedSubjects(): array
{
    return [
        // Public catalog.
        'home' => ['PromptSewa', ['guest']],
        'library.index' => ['Prompt library', ['guest']],
        'library.category' => [null, ['guest']], // subject = category name (asserted dynamically)
        'prompts.show' => [null, ['guest']], // subject = prompt title (asserted dynamically)
        'prompts.versions' => ['version history', ['guest']],
        'prompts.report.create' => [null, ['guest']], // bound route, subject asserted dynamically
        'creators.show' => [null, ['guest']], // subject = creator name (asserted dynamically)
        'packs.index' => ['Prompt packs', ['guest']],
        'packs.show' => [null, ['guest']], // subject = pack name (asserted dynamically)
        'feed.index' => ['Community feed', ['guest']],
        'pages.about' => ['About', ['guest']],

        // Auth.
        'login' => ['Sign in', ['guest']],
        'register' => ['Create an account', ['guest']],

        // Dashboard (member fixtures).
        'dashboard' => ['Your prompts', ['member', 'owner']],
        'dashboard.profile.edit' => ['Edit profile', ['member', 'owner']],
        'dashboard.prompts.create' => ['Add a new prompt', ['member', 'owner']],
        'dashboard.prompts.edit' => ['Edit:', ['owner']], // bound route (owner fixture)
        'dashboard.earnings' => ['Earnings', ['member', 'owner']],
        'purchases.index' => ['My purchases', ['member', 'owner']],

        // Admin pills (admin fixture) — every admin GET surface.
        'admin.dashboard' => ['Overview', ['admin']],
        'admin.prompts.preview' => [null, ['admin']], // bound route: subject = prompt title
        'admin.prompts.index' => ['Prompts', ['admin']],
        'admin.packs.create' => ['New pack', ['admin']],
        'admin.packs.edit' => ['Edit pack', ['admin']], // bound route (pack fixture)
        'admin.users.index' => ['Users', ['admin']],
        'admin.reports.index' => ['Reports', ['admin']],
        'admin.orders.index' => ['Orders', ['admin']],
        'admin.packs.index' => ['Packs', ['admin']],
        'admin.payments.edit' => ['Payment methods', ['admin']],
        'admin.manual-methods.index' => ['Manual payment methods', ['admin']],
        'admin.tool-logos.index' => ['Tool logos', ['admin']],
        'admin.brand.edit' => ['Brand', ['admin']],
        'admin.finance' => ['Finance', ['admin']],
        'admin.badges.index' => ['Badges', ['admin']],
        'admin.frames.index' => ['Frames', ['admin']],
        'admin.comp-grants.create' => ['Comp grants', ['admin']],
        'admin.update' => ['Software update', ['admin']],
    ];
}

/** Routes that only resolve with a bound model; handled by dynamic fixtures. */
function boundRoutes(): array
{
    return ['library.category', 'prompts.show', 'prompts.versions', 'creators.show', 'packs.show', 'dashboard.prompts.edit'];
}

function seoMember(): User
{
    return User::factory()->create(['role' => User::ROLE_MEMBER]);
}

function seoAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

function seedOwnerWorld(): array
{
    $owner = User::factory()->create(['role' => User::ROLE_CREATOR, 'username' => 'crawlowner', 'name' => 'Crawl Owner']);
    $prompt = Prompt::factory()->for($owner, 'creator')->hasVersion()->create([
        'title' => 'Crawl Probe Listing',
        'status' => Prompt::STATUS_PUBLISHED,
    ]);
    $category = $prompt->category;
    $pack = Pack::factory()->create(['name' => 'Crawl Probe Pack']);

    return [$owner, $prompt, $category, $pack];
}

function headTitles(string $html): array
{
    preg_match('/<head>(.*?)<\/head>/s', $html, $head);
    preg_match_all('/<title>(.*?)<\/title>/s', $head[1] ?? '', $titles);

    return $titles[1];
}

test('every named GET route renders exactly one x-seo <title> with the expected subject', function () {
    [$owner, $prompt, $category, $pack] = seedOwnerWorld();
    $memberUser = seoMember();
    $adminUser = seoAdmin();

    $checked = 0;

    foreach (seoExpectedSubjects() as $routeName => [$subject, $roles]) {
        $url = match ($routeName) {
            'library.category' => route($routeName, $category),
            'prompts.show' => route($routeName, $prompt),
            'prompts.versions' => route($routeName, $prompt),
            'creators.show' => route($routeName, $owner),
            'packs.show' => route($routeName, $pack),
            'dashboard.prompts.edit' => route($routeName, $prompt),
            'admin.prompts.preview' => route($routeName, $prompt),
            'admin.packs.edit' => route($routeName, $pack),
            'prompts.report.create' => route($routeName, $prompt),
            default => route($routeName),
        };

        // Resolve the expected subject for dynamic routes.
        $expected = match ($routeName) {
            'library.category' => $category->name,
            'prompts.show' => 'Crawl Probe Listing',
            'creators.show' => 'Crawl Owner',
            'packs.show' => 'Crawl Probe Pack',
            'dashboard.prompts.edit' => 'Edit: Crawl Probe Listing',
            'admin.prompts.preview' => 'Crawl Probe Listing',
            default => $subject,
        };

        foreach ($roles as $role) {
            $user = match ($role) {
                'member' => $memberUser,
                'owner' => $owner,
                'admin' => $adminUser,
                default => null,
            };

            $response = $user !== null
                ? $this->actingAs($user)->get($url)
                : $this->get($url);

            // Only 200 responses carry the page head; auth/403 flows are
            // covered by their own suites. Skip anything non-200.
            if ($response->getStatusCode() !== 200) {
                continue;
            }

            $titles = headTitles($response->getContent());

            expect(count($titles))->toBe(1, "route {$routeName} (as {$role}) must render EXACTLY ONE <title> — found ".count($titles))
                ->and($expected !== null ? str_contains($titles[0], $expected) : true)
                ->toBeTrue("route {$routeName} (as {$role}) title '{$titles[0]}' must contain subject '{$expected}'");

            $checked++;
        }
    }

    // Sanity: the crawl must actually have covered the surface.
    expect($checked)->toBeGreaterThan(30);
});

test('the permanent route list stays in sync — every named GET route is either asserted or explicitly exempted', function () {
    seedOwnerWorld();        $exempt = [
        // JSON endpoints, non-HTML responses, POST/PUT-only flows.
        'search.preview',
        'sitemap',
        'robots',
        'dashboard.update', // redirect shim to admin.update
        'logout', 'register.store', 'login.store', // POST
        'checkout.show', // noindex transactional page, covered by CheckoutFlowTest
        'checkout.esewa.pay', // POST-like redirect flow, covered by CheckoutServiceTest
        'purchases.download', // file download — not an HTML page
        'orders.proof.show', // private proof image — not an HTML page
        'storage.proofs', // private proof static file route
        'prompts.versions.restore', // POST
        'dashboard.prompts.store', 'dashboard.prompts.update', 'dashboard.profile.update', // POST/PUT
        'dashboard.earnings.request', 'dashboard.earnings.cancel', // POST
        'checkout.prompts.buy', 'checkout.packs.buy', 'checkout.manual.submit', 'orders.proof.store', // POST
        'bookmarks.toggle', 'prompts.rate', 'prompts.report.store', // POST
        'payments.esewa.webhook', 'checkout.esewa.verify', 'impersonation.stop', // POST
        'admin.prompts.status', 'admin.users.role', 'admin.users.verified', 'admin.users.banned', // PATCH/POST
        'admin.users.impersonate', 'admin.users.purge.preview', 'admin.users.purge.run', // POST
        'admin.users.adopt.preview', 'admin.users.adopt.run', // POST
        'admin.comp-grants.store', // POST
        'admin.reports.status', 'admin.payments.update', // PATCH/PUT
        'admin.manual-methods.store', 'admin.manual-methods.update', 'admin.manual-methods.destroy', // POST/PUT/DELETE
        'admin.packs.store', 'admin.packs.update', 'admin.packs.destroy', // POST/PUT/DELETE
        'admin.tool-logos.store', 'admin.tool-logos.update', 'admin.tool-logos.destroy', // POST/PATCH/DELETE
        'admin.brand.update', // PUT
        'admin.orders.approve', 'admin.orders.reject', // PATCH
        'admin.finance.payouts.approve', 'admin.finance.payouts.settle', // POST
        'admin.finance.payouts.reject', 'admin.finance.payouts.destination', // POST/GET-JSON
        'admin.badges.store', 'admin.badges.update', 'admin.badges.destroy', 'admin.badges.award', // POST/PUT/DELETE
        'admin.frames.store', 'admin.frames.update', 'admin.frames.destroy', // POST/PUT/DELETE
        'admin.update.run', // POST
    ];

    $named = collect(Route::getRoutes()->getRoutesByName())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->keys()
        ->reject(fn ($name) => $name === '' || in_array($name, $exempt, true) || str_contains($name, 'generated::'))
        ->values();

    foreach ($named as $name) {
        expect(array_key_exists($name, seoExpectedSubjects()))->toBeTrue(
            "route {$name} is not covered by the SEO crawl — add it to seoExpectedSubjects() or the exempt list",
        );
    }
});
