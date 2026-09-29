@props([
    'title' => null,
    'description' => null,
    'canonical' => null,     // defaults to current URL
    'robots' => null,        // e.g. 'noindex, follow'
    'ogType' => 'website',
    'jsonLd' => null,        // array|string — extra structured data blocks
])

@php
    /** T9 (v1.5.0): per-page SEO head block. Rendered inside @stack('head').
     *  Falls back to the shared composer vars ($siteName/$siteTagline) when
     *  present; test contexts without the composer get safe literals. */
    $seoSiteName = isset($siteName) && $siteName !== '' ? $siteName : 'PromptSewa';
    $seoSiteTagline = $siteTagline ?? '';
    $seoTitle = $title !== null
        ? ($title === '' ? $seoSiteName : $title.' — '.$seoSiteName)
        : null; // null = layout <title> stays as-is
    $seoDescription = trim((string) ($description ?? $seoSiteTagline)) ?: 'PromptSewa — discover, test, buy and sell premium AI prompts.';
    $seoCanonical = $canonical ?? url()->current();
@endphp

@if ($seoTitle !== null)
    <title>{{ $seoTitle }}</title>
@endif
<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $seoCanonical }}">

@if ($robots !== null)
    <meta name="robots" content="{{ $robots }}">
@endif

{{-- Open Graph / Twitter --}}
<meta property="og:site_name" content="{{ $seoSiteName }}">
<meta property="og:title" content="{{ $seoTitle ?? (isset($pageTitle) ? (string) $pageTitle : $seoSiteName) }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle ?? ($pageTitle ?? $seoSiteName) }}">
<meta name="twitter:description" content="{{ $seoDescription }}">

@if ($jsonLd !== null)
    @foreach (is_array($jsonLd) && array_is_list($jsonLd) ? $jsonLd : [$jsonLd] as $block)
        @if ($block)
            {{-- Raw echo: json_encode output is data (strings already
                 escaped by the encoder); Blade {{ }} would double-escape
                 the quotes and corrupt the structured data. --}}
            <script type="application/ld+json">{!! is_string($block) ? $block : json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    @endforeach
@endif
