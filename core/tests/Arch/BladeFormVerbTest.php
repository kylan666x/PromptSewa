<?php

use Illuminate\Support\Facades\Route;

/**
 * S1 — permanent guard for the "405 bug class".
 *
 * v1.2.0 shipped a profile form without @method('PUT'); v1.4.0-era admin
 * review-queue forms lacked @method('PATCH') — both 405'd on live while the
 * suite stayed green. This test scans EVERY Blade form in the codebase,
 * resolves the verb of the route each form targets, and asserts:
 *   (a) forms targeting PUT/PATCH/DELETE carry the matching _method spoof,
 *   (b) no form targets a GET/HEAD-only route (browsers can only POST).
 *
 * A form whose action is not a route() call is skipped (external URLs,
 * logout-by-fetch, etc.); route resolution failures fail loudly — a form
 * pointing at a dead route is its own bug.
 */
test('every blade form verb matches its target route', function () {
    $viewDir = realpath(__DIR__.'/../../resources/views');
    expect($viewDir)->not->toBeFalse();

    $bladeFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $bladeFiles[] = $file->getPathname();
        }
    }
    sort($bladeFiles);
    expect($bladeFiles)->not->toBeEmpty();

    $violations = [];

    foreach ($bladeFiles as $path) {
        $content = file_get_contents($path);
        $relative = str_replace('\\', '/', substr($path, strlen($viewDir) + 1));

        // Split on form openings; each opening tag is analysed with the
        // segment that follows it (forms are not nested in this codebase).
        preg_match_all('/<form\b[^>]*>/i', $content, $formTags, PREG_OFFSET_CAPTURE);

        foreach ($formTags[0] as [$tag, $offset]) {
            // Skip plain GET forms and non-route actions.
            if (! preg_match('/action\s*=\s*"([^"]*)"/i', $tag, $action)) {
                continue;
            }

            if (! preg_match("/route\(\s*'([^']+)'/", $action[1], $nameMatch)) {
                continue;
            }

            $routeName = $nameMatch[1];
            $route = Route::getRoutes()->getByName($routeName);

            if ($route === null) {
                $violations[] = "{$relative}: form targets unknown route '{$routeName}'";
                continue;
            }

            $methods = $route->methods();
            $needsSpoof = ! empty(array_intersect(['PUT', 'PATCH', 'DELETE'], $methods));

            // Rule (b): a form that POSTS to a GET-only route can never work
            // (browsers have no native PUT/DELETE; method spoofing handles
            // those). Plain method="GET" search forms are legitimate.
            $postsToGetOnly = str_contains($tag, 'method="POST"')
                && ! $needsSpoof
                && (in_array('GET', $methods, true) || in_array('HEAD', $methods, true))
                && ! in_array('POST', $methods, true);

            if ($postsToGetOnly) {
                $violations[] = "{$relative}: form POSTs to GET-only route '{$routeName}'";
                continue;
            }

            if (! $needsSpoof) {
                continue;
            }

            // Grab the form's body up to the matching </form>.
            $closePos = stripos($content, '</form>', $offset);
            $body = $closePos !== false
                ? substr($content, $offset, $closePos - $offset)
                : substr($content, $offset);

            $expectedSpoofs = array_intersect(['PUT', 'PATCH', 'DELETE'], $methods);
            foreach ($expectedSpoofs as $verb) {
                $hasSpoof = str_contains($body, "@method('{$verb}')")
                    || str_contains($body, "@method(\"{$verb}\")")
                    || (bool) preg_match('/name\s*=\s*["\']_method["\'][^>]*value\s*=\s*["\']'.$verb.'["\']/i', $body)
                    || (bool) preg_match('/value\s*=\s*["\']'.$verb.'["\'][^>]*name\s*=\s*["\']_method["\']/i', $body);

                if (! $hasSpoof) {
                    $violations[] = "{$relative}: form targets {$verb} route '{$routeName}' but has no @method('{$verb}') spoof";
                }
            }
        }

        // A2: hardcoded-action forms — an action="..." that is neither a
        // route() call nor an explicit variable (eSewa gateway URLs) is a
        // drift hazard: it bypasses the route registry and can silently
        // point at a verb-mismatched or dead path.
        preg_match_all('/<form\b[^>]*>/i', $content, $hardcoded, PREG_OFFSET_CAPTURE);
        foreach ($hardcoded[0] as [$tag, $offset]) {
            if (! preg_match('/action\s*=\s*"([^"]*)"/i', $tag, $action)) {
                continue;
            }
            $actionValue = $action[1];
            if ($actionValue === '' || str_contains($actionValue, 'route(')) {
                continue; // route-resolved or empty (submits to self)
            }
            if (preg_match('/^\{\{|^\$/', $actionValue)) {
                continue; // runtime variable (e.g. eSewa gateway action)
            }
            $violations[] = "{$relative}: form has a hardcoded action \"{$actionValue}\" — use route('…') or an explicit gateway variable";
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});
