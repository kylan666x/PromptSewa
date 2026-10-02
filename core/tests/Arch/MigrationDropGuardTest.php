<?php

/**
 * R2 — drop-guard arch test: the migration-world sibling of NoBladeLeakTest.
 *
 * MySQL DDL autocommits, so an interrupted migration leaves a half-applied
 * schema with no `migrations` record — and a replay then 1091s dropping an
 * index that no longer exists (the v1.4.2 prod incident). Any destructive
 * DDL call in a migration MUST be preceded by an existence guard:
 *
 *   - app(SchemaInspector::class)->hasIndex / hasUniqueIndex / hasColumn
 *   - Schema::hasColumn / hasTable / getColumnListing
 *   - PRAGMA / information_schema lookups
 *   - a try/catch around the call
 *
 * Guards may sit anywhere in the same FILE (usually a private helper), not
 * necessarily in the same method — migrations commonly factor the check
 * into a method of the anonymous class.
 *
 * v1.7.5: this test had NEVER RUN — tests/Arch was missing from
 * phpunit.xml's testsuites, so the guard the handoff cites as permanent
 * enforcement was dormant. Wiring it up surfaced 20 files, and 16 of them
 * were `down()`-only drops. The house culture is APPEND-ONLY: `pv:update`
 * calls `migrate` (up() only) and never rolls back, so a down() dropColumn
 * cannot 1091 a replay. The scan is therefore scoped to up(), where the
 * documented v1.4.2 prod incident actually lived. down() stays
 * unconstrained by design, not by oversight.
 */
test('every destructive DDL call in a migration up() is existence-guarded', function () {
    $dir = __DIR__.'/../../database/migrations';
    $files = glob($dir.'/*.php');
    expect($files)->not->toBeEmpty();

    $destructive = ['dropUnique', 'dropIndex', 'dropColumn', 'dropForeign', 'renameColumn'];

    // NOTE: `Schema::` alone does NOT count — Schema::table appears in
    // every migration. The guard must be a genuine existence check.
    $guardPatterns = [
        'hasIndex', 'hasUniqueIndex', 'hasColumn', 'hasTable', 'getColumnListing',
        'PRAGMA', 'pragma', 'information_schema', 'informationSchema',
        'try', 'catch', 'SchemaInspector',
    ];

    $violations = [];
    $scanned = 0;

    foreach ($files as $path) {
        $content = file_get_contents($path);
        $relative = str_replace('\\', '/', substr($path, strlen($dir) + 1));

        // Only the up() body — the method the host actually executes.
        if (! preg_match('/public function up\(\)\s*:\s*void\s*\{(.*?)\n    \}/s', $content, $m)) {
            continue;
        }
        $up = $m[1];
        $scanned++;

        foreach ($destructive as $call) {
            if (! preg_match('/\$table->'.$call.'\(|DB::statement\([^)]*DROP\s/i', $up)) {
                continue;
            }

            $guarded = false;
            foreach ($guardPatterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    $guarded = true;
                    break;
                }
            }

            if (! $guarded) {
                $violations[] = "{$relative}: {$call}() in up() without an existence guard "
                    .'(use SchemaInspector / Schema::hasColumn / information_schema — '
                    .'MySQL DDL autocommits, replays of unguarded drops 1091)';
            }
        }
    }

    expect($scanned)->toBeGreaterThan(10, 'the up() scan must actually see the migration corpus');

    expect($violations)->toBe([], implode("\n", $violations));
});
