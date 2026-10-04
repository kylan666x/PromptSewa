@props(['user'])

{{--
    F2 (v1.7.8) — the profile-picture lightbox.

    Server-rendered modal markup, Alpine-toggled. It deliberately does NOT
    declare its own x-data: it borrows `lightbox` (Boolean) from an ancestor
    Alpine scope so both the profile hero menu and the navbar dropdown can
    share one implementation. Callers guarantee the scope exists.

    Full-size truth: avatar uploads are stored at their compressed full size
    (ImageUploadService caps at 512px), and this is the only surface that
    shows them uncropped — everywhere else the avatar is a small circle.
--}}

<div x-show="lightbox" x-cloak
     @keydown.escape.window="lightbox = false"
     data-testid="avatar-lightbox"
     role="dialog" aria-modal="true" aria-label="Profile picture"
     class="fixed inset-0 z-[80] flex items-center justify-center p-4">
    {{-- Backdrop: a real click target, not decoration. --}}
    <div class="absolute inset-0 bg-ink/80" @click="lightbox = false" aria-hidden="true"></div>

    <div class="relative max-h-[85vh] max-w-[90vw]">
        @if ($user->avatar_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path) }}"
                 alt="Profile picture of {{ $user->name }}"
                 data-testid="avatar-lightbox-image"
                 class="max-h-[85vh] max-w-[90vw] rounded-2xl object-contain shadow-2xl">
        @else
            {{-- No upload yet: show the same initials avatar the app falls
                 back to, at lightbox scale — never an empty box. --}}
            <div data-testid="avatar-lightbox-image"
                 class="flex size-56 items-center justify-center rounded-full bg-ink text-6xl font-bold text-saffron shadow-2xl">
                {{ mb_substr($user->name, 0, 1) }}
            </div>
        @endif

        <button type="button" @click="lightbox = false"
                class="absolute -right-3 -top-3 flex size-9 items-center justify-center rounded-full bg-white text-lg font-bold text-ink shadow-lg transition hover:bg-paper-deep"
                aria-label="Close profile picture">&times;</button>
    </div>
</div>
