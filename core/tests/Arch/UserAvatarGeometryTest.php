<?php

/**
 * v1.7.5 (R2) — PERMANENT BAN on caller-supplied avatar geometry.
 *
 * The founder's frame-misalignment incident had one root cause on the Blade
 * side: `<x-user-avatar>` merged the caller's class list onto its wrapper,
 * so `class="… size-24 rounded-3xl border-4 bg-saffron …"` on the profile
 * hero painted a saffron squircle AROUND the composite and split the
 * wrapper's border box from the box its layers resolved against. The ring
 * and photo ended up offset up-left inside the tile — three coordinate
 * systems where the composite box now has exactly one.
 *
 * R1 makes the component immune (it strips these tokens before merging),
 * but stripping silently is how the next drift hides. So this test bans
 * them at the SOURCE: no `<x-user-avatar>` tag in the repo may carry a
 * `rounded-*`, `size-*`, `bg-*`, `border*` or `overflow-*` attribute.
 * Sizes flow through the `size` prop only (xs/sm/md/lg/xl).
 *
 * A static Blade scan on purpose: this is a lint-style rule about how a
 * component MAY be called, and it must fail at commit time rather than only
 * on whichever surface someone remembers to render.
 */

/** @return array<int, string> */
function avatarGeometryBladeFiles(): array
{
    $viewDir = realpath(__DIR__.'/../../resources/views');
    expect($viewDir)->not->toBeFalse();

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

test('no x-user-avatar call site carries geometry attributes', function () {
    $banned = ['rounded-', 'size-', 'bg-', 'border', 'overflow-'];
    $offenders = [];

    foreach (avatarGeometryBladeFiles() as $file) {
        $source = (string) file_get_contents($file);
        $relative = str_replace(realpath(__DIR__.'/../..').DIRECTORY_SEPARATOR, '', $file);

        preg_match_all('/<x-user-avatar\b(.*?)(\/>|>)/s', $source, $tags, PREG_SET_ORDER);

        foreach ($tags as $tag) {
            $attributes = $tag[1];
            $line = substr_count(substr($source, 0, (int) strpos($source, $tag[0])), "\n") + 1;

            foreach ($banned as $prefix) {
                if (str_contains($attributes, $prefix)) {
                    $offenders[] = sprintf(
                        '%s:%d  [%s]  %s',
                        $relative,
                        $line,
                        trim(preg_replace('/\s+/', ' ', $attributes)),
                        'avatar geometry flows through the size prop only (v1.7.5 R2)',
                    );
                }
            }
        }
    }

    expect($offenders)->toBe([], "x-user-avatar geometry overrides found:\n".implode("\n", $offenders));
});

test('the profile hero declares a size prop and nothing else', function () {
    $source = (string) file_get_contents(base_path('resources/views/creators/show.blade.php'));

    preg_match('/<x-user-avatar\b(.*?)(\/>|>)/s', $source, $hero);
    $attributes = $hero[1] ?? '';

    // The hero's old overrides (size-24 sm:size-28 rounded-3xl border-4
    // border-paper bg-saffron text-4xl shadow-card-hover) are what painted
    // the saffron squircle and broke the composite — the size is now a prop.
    expect($attributes)->toContain('size="xl"')
        ->and($attributes)->not->toContain('class=')
        ->and($attributes)->not->toContain('rounded-')
        ->and($attributes)->not->toContain('bg-')
        ->and($attributes)->not->toContain('size-');
});

test('the v1.7.4 negative-inset frame geometry is gone from every view', function () {
    // Superseded, not deprecated: the composite box never protrudes, so a
    // negative inset can only be a leftover that would break the photo inset.
    $hits = [];

    foreach (avatarGeometryBladeFiles() as $file) {
        $source = (string) file_get_contents($file);

        if (preg_match('/-inset-\[[\d.]+%\]/', $source, $m)) {
            $hits[] = str_replace(realpath(__DIR__.'/../..').DIRECTORY_SEPARATOR, '', $file).' -> '.$m[0];
        }
    }

    expect($hits)->toBe([], "v1.7.4 protrusion geometry found (superseded by the v1.7.5 composite box):\n".implode("\n", $hits));
});
