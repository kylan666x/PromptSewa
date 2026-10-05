<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

/**
 * R2 (v1.7.7 Bug Hunt Raid) — dead-link & dead-action scan.
 *
 * For every surface the R1 matrix renders, parse the SERVED HTML:
 *  - every internal <a href> must resolve to an existing route, and the
 *    viewing role must not be 403/404/405'd by it;
 *  - every <form action> must resolve, accept the form's effective verb
 *    (POST + _method spoof included), and be submittable by the role the
 *    form is shown to.
 *
 * "A link that 403s for the person seeing it is a bug, not a permission."
 * Logout/login edges and static-asset paths are exempt by list, with reasons.
 */

/**
 * Roles that may legitimately submit a form action. '*' = any signed-in
 * account. Unlisted routes fail the completeness assertion.
 *
 * @return array<string, list<string>|string>
 */
function raidDeadLinkFormViewers(): array
{
    $staff = ['moderator', 'admin'];
    $admin = ['admin'];
    $auth = '*';

    return [
        // Guest-world auth forms.
        'login.store' => ['guest'],
        'register.store' => ['guest'],
        'password.email' => ['guest'],
        'password.update' => ['guest'],

        // Shared GET forms (search rails) seen in both worlds.
        'library.index' => ['guest', 'member', 'creator', 'moderator', 'admin'],
        'admin.finance' => $admin, // ledger filter — admin-only page

        // Signed-in app forms.
        'logout' => $auth,
        'impersonation.stop' => $admin,
        'dashboard.prompts.store' => $auth,
        'dashboard.prompts.update' => $auth,
        'dashboard.profile.update' => $auth,
        'dashboard.earnings.request' => $auth,
        'dashboard.earnings.cancel' => $auth,
        'prompts.rate' => $auth,
        'prompts.report.store' => $auth,
        'prompts.versions.restore' => $auth,
        'checkout.prompts.buy' => $auth,
        'checkout.packs.buy' => $auth,
        'checkout.manual.submit' => $auth,
        'checkout.sikka.pay' => $auth,
        'orders.proof.store' => $auth,
        'notifications.read-all' => $auth,

        // Staff-tier admin mutations (controller permits moderators).
        'admin.packs.store' => $staff,
        'admin.packs.update' => $staff,
        'admin.packs.destroy' => $staff,
        'admin.prompts.status' => $staff,
        'admin.reports.status' => $staff,
        'admin.tool-logos.store' => $staff,
        'admin.tool-logos.update' => $staff,
        'admin.tool-logos.destroy' => $staff,
        'admin.update.run' => $staff,

        // Admin-only doors inside the staff perimeter.
        'admin.comp-grants.create' => $admin,
        // NOTE: admin.badges.update has no rendered form (badge edits are
        // store + destroy only) — declared out of sweep below.
        'admin.comp-grants.store' => $admin,
        'admin.badges.store' => $admin,
        'admin.badges.award' => $admin,
        'admin.badges.destroy' => $admin,
        'admin.badges.scan' => $admin,
        'admin.brand.update' => $admin,
        'admin.email.update' => $admin,
        'admin.email.test' => $admin,
        'admin.finance.payouts.approve' => $admin,
        'admin.finance.payouts.settle' => $admin,
        'admin.finance.payouts.reject' => $admin,
        'admin.frames.store' => $admin,
        'admin.frames.update' => $admin,
        'admin.frames.destroy' => $admin,
        'admin.frames.award' => $admin,
        'admin.frames.unlocks.revoke' => $admin,
        'admin.manual-methods.store' => $admin,
        'admin.manual-methods.update' => $admin,
        'admin.manual-methods.destroy' => $admin,
        'admin.orders.approve' => $admin,
        'admin.orders.reject' => $admin,
        // S6 (v1.8.0): the Sikka desk renders its full form set for admins on
        // one page — ledger filter (GET), rates (PUT) and the store forms.
        // The per-row pack/plan update + destroy forms are not classified
        // here: the sweep's world has no pack/plan rows, so those forms never
        // render (classifying them would trip the stale-map check).
        'admin.sikka.index' => $admin,
        'admin.sikka.settings' => $admin,
        'admin.sikka.packs.store' => $admin,
        'admin.sikka.plans.store' => $admin,
        'admin.sikka.grants.store' => $admin,
        'admin.payments.update' => $admin,
        'admin.security.update' => $admin,
        'admin.users.role' => $admin,
        'admin.users.verified' => $admin,
        'admin.users.banned' => $admin,
        'admin.users.impersonate' => $admin,
        'admin.users.purge.preview' => $admin,
        'admin.users.purge.run' => $admin,
        'admin.users.adopt.preview' => $admin,
        'admin.users.adopt.run' => $admin,
    ];
}

/**
 * Classified form routes that the sweep cannot physically encounter.
 *
 * @return array<string, string>
 */
function raidDeadLinkFormsOutsideSweep(): array
{
    return [
        'impersonation.stop' => 'Only rendered inside an impersonation session; the R1 impersonation sweep and ImpersonationTest cover it.',
        'admin.badges.update' => 'No form renders this route (badge edits ship as store/destroy only) — audited against the served HTML.',
        'checkout.sikka.pay' => 'Rendered only when the Sikka kill-switch is ON (admin setting, default OFF) and the order is credit-eligible — SikkaServiceTest covers the served form with the switch on.',
    ];
}

/** Static assets and non-route URLs are not links the router should own. */
function raidDeadLinkExemptPath(string $path, string $reason = ''): bool
{
    $path = parse_url($path, PHP_URL_PATH) ?: $path;

    return str_starts_with($path, '/storage/')      // public disk / framework file routes
        || str_starts_with($path, '/build/')        // compiled assets
        || str_starts_with($path, '/favicon')
        || str_starts_with($path, '/mnt/')
        || $path === '/update.php'                 // docroot script, not a Laravel route; exists on a real install
        || $path === '/robots.txt'
        || $path === '/sitemap.xml';
}

/**
 * Normalise a served URL to an app-internal path+query, or null when it is
 * external / non-navigable. route() emits absolute URLs (http://localhost/…),
 * so a leading-slash check alone would silently scan nothing — the first run
 * of this test proved that trap.
 */
function raidDeadLinkInternalUrl(string $raw): ?string
{
    $raw = html_entity_decode(trim($raw), ENT_QUOTES);

    if ($raw === '' || str_starts_with($raw, '#') || str_starts_with($raw, 'mailto:') || str_starts_with($raw, 'tel:') || str_starts_with($raw, 'javascript:')) {
        return null;
    }

    if (str_starts_with($raw, '/')) {
        return $raw;
    }

    $parts = parse_url($raw);
    if (! isset($parts['scheme']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return null;
    }

    $appHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
    $host = strtolower($parts['host'] ?? '');
    if ($host !== strtolower($appHost) && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
        return null; // external link
    }

    return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
}

/** Internal `<a href>` targets from served HTML. */
function raidDeadLinkHrefs(string $html): array
{
    preg_match_all('/<a\b[^>]*\bhref="([^"]*)"/i', $html, $m);
    $hrefs = [];

    foreach ($m[1] as $raw) {
        $path = raidDeadLinkInternalUrl($raw);
        if ($path === null || raidDeadLinkExemptPath($path)) {
            continue;
        }
        $hrefs[$path] = $path;
    }

    return $hrefs;
}

/**
 * Form descriptors from served HTML: action, effective verb, route name.
 *
 * @return list<array{action: string, verb: string, route: ?string}>
 */
function raidDeadLinkForms(string $html): array
{
    $forms = [];

    preg_match_all('/<form\b([^>]*)>/i', $html, $m, PREG_OFFSET_CAPTURE);

    foreach ($m[1] as $i => [$attrs, $offset]) {
        if (! preg_match('/action="([^"]*)"/i', $attrs, $a)) {
            continue;
        }
        $action = raidDeadLinkInternalUrl($a[1]);
        if ($action === null) {
            continue; // external action (e.g. the eSewa gateway form)
        }

        $method = 'GET';
        if (preg_match('/method="([^"]*)"/i', $attrs, $mm)) {
            $method = strtoupper($mm[1]);
        }

        // _method spoof lives in the form body, before </form>.
        $end = stripos($html, '</form>', (int) $offset);
        $body = $end === false ? substr($html, (int) $offset) : substr($html, (int) $offset, $end - (int) $offset);
        $verb = $method;
        if ($method === 'POST' && preg_match('/name="_method"\s+value="([A-Z]+)"/i', $body, $spoof)) {
            $verb = strtoupper($spoof[1]);
        }

        $forms[] = ['action' => $action, 'verb' => $verb, 'route' => null, 'body' => $body];
    }

    return $forms;
}

test('every internal href on every rendered surface resolves and is viewable by the role seeing it', function () {
    $w = raidMatrixWorld();
    $routes = raidMatrixRoutes($w);
    $roles = [
        'guest' => null,
        'member' => $w['member'],
        'creator' => $w['owner'],
        'moderator' => $w['mod'],
        'admin' => $w['admin'],
    ];

    $checked = 0;
    $failures = [];

    foreach ($roles as $role => $user) {
        $seen = [];

        foreach ($routes as $name => $spec) {
            if ($spec['url'] === null) {
                continue;
            }

            $response = $user ? $this->actingAs($user)->get($spec['url']) : $this->get($spec['url']);
            if ($response->getStatusCode() !== 200) {
                continue;
            }

            foreach (raidDeadLinkHrefs((string) $response->getContent()) as $path => $raw) {
                if (isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;

                try {
                    $route = Route::getRoutes()->match(Request::create($raw, 'GET'));
                } catch (NotFoundHttpException) {
                    $failures[] = "{$role} | {$name} | href {$raw} matches NO route";

                    continue;
                }

                $status = $this->get($raw)->getStatusCode();
                if (in_array($status, [403, 404, 405, 419, 500], true)) {
                    $failures[] = "{$role} | {$name} | href {$raw} → {$status} (route {$route->getName()})";
                }
                $checked++;
            }
        }
    }

    expect($failures)->toBe([], "dead links found:\n  ".implode("\n  ", $failures));
    expect($checked)->toBeGreaterThan(100);
});

test('every form action resolves, matches its verb, and is submittable by the role shown it', function () {
    $w = raidMatrixWorld();
    $routes = raidMatrixRoutes($w);
    $viewers = raidDeadLinkFormViewers();

    $roles = [
        'guest' => null,
        'member' => $w['member'],
        'creator' => $w['owner'],
        'moderator' => $w['mod'],
        'admin' => $w['admin'],
    ];

    $checked = 0;
    $failures = [];
    $encountered = [];

    foreach ($roles as $role => $user) {
        foreach ($routes as $name => $spec) {
            if ($spec['url'] === null) {
                continue;
            }

            $response = $user ? $this->actingAs($user)->get($spec['url']) : $this->get($spec['url']);
            if ($response->getStatusCode() !== 200) {
                continue;
            }

            foreach (raidDeadLinkForms((string) $response->getContent()) as $form) {
                $action = $form['action'];
                $verb = $form['verb'];

                try {
                    $route = Route::getRoutes()->match(Request::create($action, $verb));
                } catch (NotFoundHttpException) {
                    $failures[] = "{$role} | {$name} | form {$verb} {$action} matches NO route";

                    continue;
                }

                $routeName = (string) $route->getName();
                $encountered[$routeName] = true;

                if (! in_array($verb, $route->methods(), true)) {
                    $failures[] = "{$role} | {$name} | form {$verb} {$action} → route [{$routeName}] accepts ".implode('/', $route->methods());
                }

                if (! isset($viewers[$routeName])) {
                    $failures[] = "{$role} | {$name} | form {$verb} {$action} → route [{$routeName}] is unclassified in raidDeadLinkFormViewers()";

                    continue;
                }

                $allowed = $viewers[$routeName];
                if ($allowed !== '*' && ! in_array($role, $allowed, true)) {
                    $failures[] = "{$role} | {$name} | form {$verb} {$action} → route [{$routeName}] is limited to ".implode(',', $allowed);
                }

                $checked++;
            }
        }
    }

    // Completeness: every classified route must be exercised (or explicitly
    // declared out of sweep), so a stale map cannot hide a dead action.
    $outside = raidDeadLinkFormsOutsideSweep();
    foreach (array_keys($viewers) as $classified) {
        if (! isset($encountered[$classified]) && ! isset($outside[$classified])) {
            $failures[] = "classified form route [{$classified}] was never encountered — map is stale or the surface is not swept";
        }
    }

    expect($failures)->toBe([], "dead actions found:\n  ".implode("\n  ", $failures));
    expect($checked)->toBeGreaterThan(20);
});
