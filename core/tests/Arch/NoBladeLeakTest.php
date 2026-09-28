<?php

use Illuminate\Support\Facades\File;

/**
 * B2 permanent leak guard: `@{{ }}` in Blade renders as literal text
 * (the v1.4.1 handle leak shipped to prod through it), and raw U+2192
 * arrows bypass the entity convention. Both are banned repo-wide.
 */
test('no blade view contains escaped-brace leaks or raw arrows', function () {
    $viewDir = __DIR__.'/../../resources/views';
    expect(is_dir($viewDir))->toBeTrue('resources/views not found at '.$viewDir);

    $files = File::allFiles($viewDir);
    expect(count($files))->toBeGreaterThan(10);

    $leaks = [];
    $arrows = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        if (str_contains($content, '@{{')) {
            $leaks[] = $file->getPathname();
        }

        if (mb_strpos($content, "\u{2192}") !== false) {
            $arrows[] = $file->getPathname();
        }
    }

    expect($leaks)->toBeArray()->toBeEmpty(
        'Blade files contain `@{{` — escaped braces render as LITERAL text to users. '
        .'Use x-text/:attr for Alpine interpolation or plain {{ }} echoes. Offenders: '
        .implode(', ', $leaks)
    );

    expect($arrows)->toBeArray()->toBeEmpty(
        'Blade files contain a raw U+2192 arrow character — use the &rarr; entity instead. '
        .'Offenders: '.implode(', ', $arrows)
    );
});
