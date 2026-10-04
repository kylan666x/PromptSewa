@props(['user', 'frame' => null, 'size' => 'xl'])

{{--
    F2 (v1.7.8) — the founder's profile-picture click menu.

    Clicking your own profile avatar opens a server-rendered Alpine menu:

      1. View profile picture  → lightbox modal (full-size)
      2. Upload / edit picture → profile editor (#avatar)
      3. Edit frame            → profile editor frame picker (#avatar-frame)

    OWNER-ONLY BY CONSTRUCTION: the component is only invoked on surfaces
    where the viewer IS the avatar's owner (the hero of their own public
    profile, and the navbar account dropdown). Strangers get the plain
    x-user-avatar and therefore no menu, no data-testid, no lightbox markup
    — which is what the served-HTML locks assert from both sides.

    The markup is server-rendered (not JS-built): the entries and hrefs are
    real HTML the moment the page is served, so tests and no-JS crawlers
    see them; Alpine only toggles visibility.
--}}

<div x-data="{ open: false, lightbox: false }"
     @keydown.escape.window="open = false"
     data-testid="own-avatar-menu"
     class="relative">

    <button type="button"
            @click="open = !open"
            @click.outside="open = false"
            class="block rounded-full outline-none transition focus-visible:ring-2 focus-visible:ring-saffron"
            aria-haspopup="menu"
            :aria-expanded="open"
            aria-label="Profile picture options">
        <x-user-avatar :user="$user" :size="$size" :frame="$frame"/>
    </button>

    <div x-show="open" x-cloak x-transition role="menu"
         class="absolute left-0 z-40 mt-2 w-60 overflow-hidden rounded-2xl border border-ink/10 bg-white py-1.5 text-left shadow-card-hover">
        <button type="button" role="menuitem"
                @click="open = false; lightbox = true"
                data-testid="avatar-view-picture"
                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-ink/80 transition hover:bg-paper-deep hover:text-ink">
            <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
            </svg>
            View profile picture
        </button>

        <a role="menuitem" href="{{ route('dashboard.profile.edit') }}#avatar"
           data-testid="avatar-upload-edit"
           class="flex items-center gap-2.5 px-4 py-2 text-sm text-ink/80 transition hover:bg-paper-deep hover:text-ink">
            <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
            </svg>
            Upload / edit picture
        </a>

        <a role="menuitem" href="{{ route('dashboard.profile.edit') }}#avatar-frame"
           data-testid="avatar-edit-frame"
           class="flex items-center gap-2.5 px-4 py-2 text-sm text-ink/80 transition hover:bg-paper-deep hover:text-ink">
            <svg class="size-4 text-ink/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <circle cx="12" cy="12" r="5.5"/>
            </svg>
            Edit frame
        </a>
    </div>

    <x-avatar-lightbox :user="$user"/>
</div>
