@props(['user', 'size' => 'md'])

@php
    /**
     * C1 — identity avatar component (v1.4.4 "Avatar Fidelity").
     *
     * Renders the user's real profile photo when `avatar_path` exists,
     * otherwise the deterministic initials badge. The verified tick is
     * ALWAYS a sibling outside this component — never baked inside the img.
     *
     * Sizes: xs (cards/typeahead), sm (compact rows), md (bylines, navbar),
     * lg (creator grids, admin tables). Extra classes merge through
     * $attributes so special shapes (creator profile's rounded-3xl hero)
     * can override the default pill without forking the markup.
     *
     * @var \App\Models\User $user
     */
    $handle = $user->username ?? $user->name;
    $sizes = [
        'xs' => 'size-5 text-[9px]',
        'sm' => 'size-6 text-[10px]',
        'md' => 'size-8 text-xs',
        'lg' => 'size-11 text-sm',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-ink font-bold text-saffron '.($sizes[$size] ?? $sizes['md']),
]) }}>
    @if ($user->avatar_path)
        <img src="{{ Storage::disk('public')->url($user->avatar_path) }}"
             alt="{{ '@'.$handle }}"
             loading="lazy"
             class="size-full object-cover">
    @else
        <span aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
    @endif
</span>
