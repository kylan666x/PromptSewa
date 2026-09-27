@props(['prompt', 'large' => false, 'gallery' => false])

@php
    /**
     * Cover artwork for a prompt listing, three layers deep:
     *  1. Uploaded cover image (creators, image prompts) — always wins.
     *  2. Generated SVG "title banner" — the prompt's own name typeset on a
     *     deterministic brand gradient. Every text prompt gets a beautiful,
     *     unique banner without any upload.
     *  3. (Legacy fallback inside the SVG path is not needed — the SVG IS the
     *     generator; initials only appear for the image-prompt placeholder.)
     */
    $palettes = [
        ['#F5C518', '#B45309'], // saffron → bronze
        ['#FBBF24', '#78350F'], // amber → espresso
        ['#FCD34D', '#C2410C'], // honey → rust
        ['#FDE68A', '#92400E'], // cream → caramel
        ['#F59E0B', '#1C1917'], // gold → ink
        ['#FACC15', '#7C2D12'], // lemon → clay
    ];
    [$c1, $c2] = $palettes[$prompt->id % count($palettes)];

    $title = (string) $prompt->title;

    // Word-wrap the title into at most 3 lines of ~14 chars for SVG text.
    $words = preg_split('/\s+/u', $title) ?: [];
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current.' '.$word;
        if (mb_strlen($candidate) > 14 && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $candidate;
        }
        if (count($lines) === 2 && mb_strlen($current) > 14) {
            break;
        }
    }
    if ($current !== '' && count($lines) < 3) {
        $lines[] = $current;
    }
    $lines = array_slice($lines, 0, 3);

    $svgId = 'g'.$prompt->id;
    $fs = $large ? 46 : 34;
    $lh = (int) ($fs * 1.15);
    $cy = 190 - (count($lines) - 1) * (int) ($lh / 2);
    $quote = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $banner = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="250" viewBox="0 0 400 250">'
        .'<defs><linearGradient id="'.$svgId.'" x1="0" y1="0" x2="1" y2="1">'
        .'<stop offset="0%" stop-color="'.$c1.'"/><stop offset="100%" stop-color="'.$c2.'"/></linearGradient></defs>'
        .'<rect width="400" height="250" fill="url(#'.$svgId.')"/>'
        .'<circle cx="330" cy="215" r="90" fill="rgba(255,255,255,0.10)"/>'
        .'<circle cx="70" cy="30" r="55" fill="rgba(255,255,255,0.08)"/>'
        .'<text x="200" y="'.$cy.'" font-family="Space Grotesk, system-ui, sans-serif" font-size="'.$fs.'" font-weight="700" fill="rgba(23,23,21,0.88)" text-anchor="middle">'
        .implode('', array_map(fn (string $line, int $i): string => '<tspan x="200" dy="'.($i === 0 ? 0 : $lh).'">'.$quote($line).'</tspan>', $lines, array_keys($lines)))
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
