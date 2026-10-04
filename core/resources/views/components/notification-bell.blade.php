@auth
    @php
        /** @var \App\Models\User $bellUser */
        $bellUser = auth()->user();
        $bellUnread = $bellUser->notifications()->unread()->count();
        $bellItems = app(\App\Http\Controllers\NotificationController::class)->items(
            $bellUser->notifications()->orderByDesc('id')->limit(20)->get()
        );
        $bellPayload = [
            'url' => route('notifications.unread'),
            'count' => $bellUnread,
            'items' => $bellItems,
        ];
    @endphp

    {{-- F6 (v1.7.8): the product word is "Notifications" — no live-delivery
         vocabulary (cPanel has no websockets). Entries are server-rendered
         for the first paint, then the 60s Alpine poll refreshes count +
         list from the same JSON shape. Renders nothing for guests. --}}
    <div x-data="notificationBell(@js($bellPayload))" @click.outside="open = false"
         {{ $attributes->merge(['class' => 'relative']) }} data-testid="notification-bell">
        <button type="button" @click="open = !open" :aria-expanded="open" aria-label="Notifications"
                class="relative flex size-9 items-center justify-center rounded-full text-ink/70 transition hover:bg-ink/5 hover:text-ink">
            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
            </svg>
            <span x-show="count > 0" x-cloak x-text="count" data-testid="notification-unread-chip"
                  class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 font-mono text-[10px] font-bold text-white">{{ $bellUnread }}</span>
        </button>

        <div x-show="open" x-cloak x-transition.opacity
             class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-card-hover"
             data-testid="notification-panel">
            <p class="border-b border-ink/10 px-4 py-2.5 font-mono text-[10px] font-bold uppercase tracking-widest text-ink/40">Notifications</p>

            <div class="max-h-96 overflow-y-auto">
                <template x-if="items.length === 0">
                    <p class="px-4 py-6 text-center text-sm text-ink/50">Nothing yet — activity on your account lands here.</p>
                </template>
                <template x-for="item in items" :key="item.id">
                    <a :href="item.url" :data-testid="'notification-item-' + item.id"
                       class="block border-b border-ink/5 px-4 py-2.5 transition hover:bg-paper-deep">
                        <p class="text-sm text-ink/85" x-text="item.message"></p>
                        <p class="mt-0.5 font-mono text-[10px] text-ink/45" x-text="item.created_at"></p>
                    </a>
                </template>
            </div>

            <form method="POST" action="{{ route('notifications.read-all') }}"
                  class="flex items-center justify-between gap-2 border-t border-ink/10 px-4 py-2.5">
                @csrf
                <button type="submit" data-testid="notification-read-all" :disabled="count === 0"
                        class="text-xs font-semibold text-saffron-deep hover:underline disabled:opacity-50">Mark all read</button>
                <span class="text-[10px] text-ink/40">Checked every minute</span>
            </form>
        </div>
    </div>
@endauth
