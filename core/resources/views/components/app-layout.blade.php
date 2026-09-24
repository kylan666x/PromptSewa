<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $siteTagline !== '' ? $siteTagline : 'PromptSewa — discover, test, buy and sell premium AI prompts.' }}">
    <title>@isset($pageTitle){{ $pageTitle }} — @endisset{{ $siteName }}</title>

    {{-- Admin-managed favicon falls back to the static default. --}}
    @if ($brandFaviconPath !== '')
        <link rel="icon" href="{{ asset('storage/'.$brandFaviconPath) }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif

    {{-- Fonts are loaded from Google Fonts; assets are compiled locally by
         Vite and uploaded as static files. No Node.js runs on the cPanel
         server (PRD §4). --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-paper text-ink antialiased selection:bg-saffron/40 selection:text-ink">
    <x-navbar :categories="$navCategories ?? collect()" :site-name="$siteName" :brand-logo-path="$brandLogoPath"/>

    {{-- Flash toast (server redirects) --}}
    @if (session('success'))
        <div class="pointer-events-none fixed inset-x-0 top-20 z-50 flex justify-center px-4" x-data="{ show: true }" x-show="show" x-transition.opacity.duration.500ms x-init="setTimeout(() => show = false, 5000)">
            <div class="pointer-events-auto flex items-center gap-2.5 rounded-xl border border-emerald-700/20 bg-ink px-4 py-2.5 text-sm text-emerald-300 shadow-card-hover" role="status">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 0 1 .208 1.04l-9 13.5a.75.75 0 0 1-1.154.114l-6-6a.75.75 0 0 1 1.06-1.06l5.353 5.353 8.493-12.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd"/>
                </svg>
                {{ session('success') }}
            </div>
        </div>
    @endif

    <main class="flex-1">
        {{ $slot }}
    </main>

    <x-footer :site-name="$siteName" :contact-email="$contactEmail" :support-email="$supportEmail" :brand-logo-path="$brandLogoPath"/>
</body>
</html>
