@props([
    'series',        // array of values (ints or floats), oldest → newest
    'label' => '',   // aria-label for accessibility (WCAG 2.1 AA)
    'stroke' => '#f0b429', // saffron-deep; light-world safe
    'fill' => 'rgba(240, 180, 41, 0.12)',
])

@php
    /**
     * G5 (v1.7.0) — inline SVG sparkline. Blade+Alpine only, ZERO new deps
     * (cPanel parity + no-ride-alongs). Empty series is a first-class
     * state: renders a flat baseline, never a broken chart (K-series
     * lesson applied to analytics).
     *
     * @var array $series
     */
    $values = array_values($series);

    // Densify: exactly 30 points; missing/null → 0.
    $points = [];
    for ($i = 0; $i < 30; $i++) {
        $points[] = (float) ($values[$i] ?? 0);
    }

    $width = 220;
    $height = 48;
    $max = max($points);
    $min = min($points);
    $range = $max - $min;

    // Build the polyline coordinates. Flat-zero series still produce a
    // valid baseline at the bottom of the viewport.
    $coords = [];
    foreach ($points as $i => $v) {
        $x = count($points) > 1 ? $i / (count($points) - 1) * $width : 0;
        $y = $range > 0 ? $height - (($v - $min) / $range) * ($height - 4) - 2 : $height - 2;
        $coords[] = round($x, 1).','.round($y, 1);
    }

    $polyline = implode(' ', $coords);
    $area = '0,'.$height.' '.implode(' ', $coords).' '.$width.','.$height;
    $peak = max($points) > 0;
@endphp

<svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-12 w-full" role="img"
     aria-label="{{ $label !== '' ? $label : '30-day trend' }}">
    @if ($peak)
        <polygon points="{{ $area }}" fill="{{ $fill }}" stroke="none"/>
    @endif
    <polyline points="{{ $polyline }}" fill="none" stroke="{{ $stroke }}" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
</svg>
