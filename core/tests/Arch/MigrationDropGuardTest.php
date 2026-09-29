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
 */
test('every destructive DDL call in migrations is existence-guarded', function () {
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

    foreach ($files as $path) {
        $content = file_get_contents($path);
        $relative = str_replace('\\', '/', substr($path, strlen($dir) + 1));

        foreach ($destructive as $call) {
            if (! preg_match('/\$table->'.$call.'\(|DB::statement\([^)]*DROP\s/i', $content)) {
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
                $violations[] = "{$relative}: {$call}() without an existence guard "
                    .'(use SchemaInspector / Schema::hasColumn / information_schema — '
                    .'MySQL DDL autocommits, replays of unguarded drops 1091)';
            }
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});
