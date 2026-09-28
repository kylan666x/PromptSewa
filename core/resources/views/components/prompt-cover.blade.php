@props(['prompt', 'large' => false, 'gallery' => false])

@php
    /**
     * Cover artwork for a prompt listing, three layers deep:
     *  1. Uploaded cover image (creators, image prompts) — always wins.
     *  2. Generated SVG "title banner" — the prompt's own name typeset on a
     *     deterministic brand gradient. Every text prompt gets a beautiful,
     *     unique banner without any upload.
     *
     * B3 typography contract: the wrapped title block is centered on BOTH
     * axes, fits fully inside the viewBox at every title length, wraps to
     * ≤3 lines, and truncates at a word boundary with an ellipsis — it
     * never overflows the canvas edge.
     */
    $palettes = [
        ['#F5C518', '#B45309'], // saffron &rarr; bronze
        ['#FBBF24', '#78350F'], // amber &rarr; espresso
        ['#FCD34D', '#C2410C'], // honey &rarr; rust
        ['#FDE68A', '#92400E'], // cream &rarr; caramel
        ['#F59E0B', '#1C1917'], // gold &rarr; ink
        ['#FACC15', '#7C2D12'], // lemon &rarr; clay
    ];
    [$c1, $c2] = $palettes[$prompt->id % count($palettes)];

    $W = 400;
    $H = 250;
    $title = (string) $prompt->title;
    $quote = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    // Approximate rendered width so we can size the font without knowing
    // real glyph metrics: bold Space Grotesk averages ~0.58em per char.
    $charWidthFactor = 0.58;
    $maxTextWidth = $W * 0.82; // 8% side padding on each edge

    // Font size steps DOWN by title length so long titles shrink to fit.
    $fs = $large ? 46 : 36;
    if (mb_strlen($title) > 24) {
        $fs = $large ? 40 : 32;
    }
    if (mb_strlen($title) > 40) {
        $fs = $large ? 34 : 28;
    }
    $lh = (int) round($fs * 1.15);

    // Word-wrap to ≤3 lines at the current font size.
    $words = preg_split('/\s+/u', trim($title)) ?: [];
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current.' '.$word;
        $fits = mb_strlen($candidate) * $charWidthFactor * $fs <= $maxTextWidth;
        if (! $fits && $current !== '') {
            $lines[] = $current;
            $current = $word;
            if (count($lines) === 3) {
                break; // word loop ends; truncation below handles the rest
            }
        } else {
            $current = $candidate;
        }
    }
    if (count($lines) < 3 && $current !== '') {
        $lines[] = $current;
    }
    $lines = array_slice($lines, 0, 3);

    // Word-boundary ellipsis truncation: if any input word was dropped, the
    // last rendered line ends with "…" (never a dangling "&"/"—"/",").
    $wrappedChars = mb_strlen(implode(' ', $lines));
    $truncated = count($words) > 0 && $wrappedChars < mb_strlen(trim($title));
    if ($truncated) {
        $lastKey = count($lines) - 1;
        $last = preg_replace('/[\s&—,\-–]+\s*$/u', '', $lines[$lastKey]);
        while (mb_strlen($last) * $charWidthFactor * $fs > $maxTextWidth - $fs && mb_strlen($last) > 1) {
            // Shrink the last line word by word until "…" fits.
            $spacePos = mb_strrpos($last, ' ');
            if ($spacePos === false) {
                break;
            }
            $last = mb_substr($last, 0, $spacePos);
        }
        $lines[$lastKey] = $last.'…';
    }

    // Center the block on both axes: baseline math keeps the text visually
    // centered and guarantees y + descent never exceeds the viewBox.
    $blockHeight = count($lines) * $lh;
    $firstBaseline = ($H - $blockHeight) / 2 + $fs * 0.8;

    $svgId = 'g'.$prompt->id;
    $tspans = implode('', array_map(
        fn (string $line, int $i): string => '<tspan x="'.($W / 2).'" dy="'.($i === 0 ? 0 : $lh).'">'.$quote($line).'</tspan>',
        $lines,
        array_keys($lines)
    ));

    $banner = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$W.'" height="'.$H.'" viewBox="0 0 '.$W.' '.$H.'">'
        .'<defs><linearGradient id="'.$svgId.'" x1="0" y1="0" x2="1" y2="1">'
        .'<stop offset="0%" stop-color="'.$c1.'"/><stop offset="100%" stop-color="'.$c2.'"/></linearGradient></defs>'
        .'<rect width="'.$W.'" height="'.$H.'" fill="url(#'.$svgId.')"/>'
        .'<circle cx="330" cy="215" r="90" fill="rgba(255,255,255,0.10)"/>'
        .'<circle cx="70" cy="30" r="55" fill="rgba(255,255,255,0.08)"/>'
        .'<text x="'.($W / 2).'" y="'.$firstBaseline.'" font-family="Space Grotesk, system-ui, sans-serif" font-size="'.$fs.'" font-weight="700" fill="rgba(23,23,21,0.88)" text-anchor="middle" dominant-baseline="middle">'
        .$tspans
        .'</text></svg>';
    $bannerUri = 'data:image/svg+xml;charset=utf-8,'.rawurlencode($banner);
@endphp

<div class="relative {{ $gallery ? 'aspect-[4/5]' : ($large ? 'aspect-[16/9]' : 'aspect-[16/10]') }} w-full overflow-hidden {{ $attributes->only('class') }}">
    @if ($prompt->cover_image_path)
        <img src="{{ Storage::url($prompt->cover_image_path) }}" alt="Cover for {{ $title }}"
             class="absolute inset-0 size-full object-cover">
    @else
        {{-- Generated title banner: the prompt's name IS the artwork. --}}
        <img src="{{ $bannerUri }}" alt="" class="absolute inset-0 size-full object-cover" aria-hidden="true">
    @endif
</div>
