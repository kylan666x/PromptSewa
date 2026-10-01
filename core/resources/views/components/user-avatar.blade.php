@props(['user', 'size' => 'md', 'frame' => null])

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
     * G3 (v1.7.0): optional `frame` (App\Models\Frame|null) renders an
     * absolute, pointer-events-none, decorative alpha-PNG ring around the
     * avatar. Ruled surfaces only (profile hero, navbar dropdown, dock
     * "You" avatar, feed actors) — prompt cards stay clean by design.
     *
     * P5 (v1.7.1) — composition fix: the SIZE now lives on the outer
     * wrapper and the inner badge fills it (`size-full` + `rounded-inherit`).
     * Before, custom caller classes (the hero's size-24 bg-saffron) merged
     * onto the wrapper while the photo stayed in a fixed size-8 inner box —
     * the wrapper painted a full-size saffron block with the photo cornered.
     * If the caller supplies its own size-* class, the preset is dropped so
     * exactly one size utility rules the box.
     *
     * @var \App\Models\User $user
     * @var \App\Models\Frame|null $frame
     */
    $handle = $user->username ?? $user->name;
    $sizes = [
        'xs' => 'size-5 text-[9px]',
        'sm' => 'size-6 text-[10px]',
        'md' => 'size-8 text-xs',
        'lg' => 'size-11 text-sm',
    ];
    $callerClass = (string) $attributes->get('class', '');
    $preset = str_contains($callerClass, 'size-') ? '' : ($sizes[$size] ?? $sizes['md']);
@endphp

<span {{ $attributes->merge(['class' => 'relative inline-flex shrink-0 text-[length:inherit] '.$preset]) }}>
    <span class="flex size-full items-center justify-center overflow-hidden rounded-[inherit] bg-ink font-bold text-saffron">
        @if ($user->avatar_path)
            <img src="{{ Storage::disk('public')->url($user->avatar_path) }}"
                 alt="{{ '@'.$handle }}"
                 loading="lazy"
                 class="size-full object-cover">
        @else
            <span aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
        @endif
    </span>

    @if ($frame?->image_path)
        <img src="{{ Storage::disk('public')->url($frame->image_path) }}" alt="" aria-hidden="true"
             class="pointer-events-none absolute -inset-1 size-[calc(100%+8px)]" loading="lazy">
    @endif
</span>
