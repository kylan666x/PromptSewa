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

{{-- F2 (v1.9.2): the founder's responsive modal contract.
     The ground (ink/80 + backdrop-blur-sm) is painted on the MODAL, not on a
     child, so the whole viewport is one consistent scrim at every size; the
     inner layer stays a real click target (backdrop click closes). The image
     box is width-capped (max-w-screen-md) and height-capped (max-h-[80vh])
     with object-contain, so a tall portrait upload can never overflow the
     viewport and never distorts. The Close control is pinned to the modal's
     top-right — reachable on a 360px phone without hunting. --}}
<div x-show="lightbox" x-cloak
     @keydown.escape.window="lightbox = false"
     data-testid="avatar-lightbox"
     role="dialog" aria-modal="true" aria-label="Profile picture"
     class="fixed inset-0 z-50 flex items-center justify-center bg-ink/80 backdrop-blur-sm">
    {{-- Backdrop: a real click target, not decoration. --}}
    <div class="absolute inset-0" @click="lightbox = false" aria-hidden="true"></div>

    <div class="relative mx-auto w-full max-w-screen-md p-4">
        @if ($user->avatar_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path) }}"
                 alt="Profile picture of {{ $user->name }}"
                 data-testid="avatar-lightbox-image"
                 class="mx-auto block max-h-[80vh] w-auto max-w-full rounded-lg object-contain shadow-2xl">
        @else
            {{-- No upload yet: show the same initials avatar the app falls
                 back to, at lightbox scale — never an empty box. --}}
            <div data-testid="avatar-lightbox-image"
                 class="mx-auto flex size-56 max-h-[80vh] items-center justify-center rounded-full bg-ink text-6xl font-bold text-saffron shadow-2xl">
                {{ mb_substr($user->name, 0, 1) }}
            </div>
        @endif
    </div>

    {{-- Close: always visible, pinned top-right of the modal. --}}
    <button type="button" @click="lightbox = false"
            data-testid="avatar-lightbox-close"
            class="absolute right-4 top-4 flex size-10 items-center justify-center rounded-full bg-white text-xl font-bold text-ink shadow-2xl transition hover:bg-paper-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-saffron"
            aria-label="Close profile picture">&times;</button>
</div>
