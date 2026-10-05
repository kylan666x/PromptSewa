<?php

use App\Support\SikkaFormat;

test('sikka amounts render as grouped integers with no decimals', function () {
    expect(SikkaFormat::render(0))->toBe('0')
        ->and(SikkaFormat::render(7))->toBe('7')
        ->and(SikkaFormat::render(1234))->toBe('1,234')
        ->and(SikkaFormat::render(-20))->toBe('-20')
        ->and(SikkaFormat::render(100000))->toBe('100,000');
});

test('sikka rendering never contains a decimal point or fiat glyph', function () {
    foreach ([0, 5, 999, 1000000] as $amount) {
        $rendered = SikkaFormat::render($amount);

        expect($rendered)->not->toContain('.')
            ->not->toContain('Rs')
            ->not->toContain('₨')
            ->not->toContain('$');
    }
});
