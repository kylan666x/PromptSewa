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
     * $attributes so special shapes (creator profile's hero) can override
     * the default pill without forking the markup.
     *
     * W1 (v1.7.3) — FRAME SURFACE PARITY: the frame overlay now renders on
     * EVERY surface that passes a frame (hero, prompt cards, image-gallery
     * cards, library creators grid, navbar dropdown, dock You, feed actors,
     * versions author, admin users table). This REVERSES the v1.7.0
     * "cards stay clean" ruling — see handoff §6.40. The overlay is always
     * pointer-events-none + aria-hidden; sizes xs–lg all support it.
     *
     * Geometry (v1.7.2 DESIGN addendum): the shape stays the CALLER's
     * choice — the hero's sanctioned squircle + ring, circular pills on
     * small badges — because the inner badge is rounded-[inherit]; the
     * frame overlay inherits the wrapper's shape by layering over it.
     *
     * P5 (v1.7.1) — composition fix: the SIZE now lives on the outer
     * wrapper and the inner badge fills it (`size-full` + `rounded-inherit`).
     * If the caller supplies its own size-* class, the preset is dropped so
     * exactly one size utility rules the box.
     *
     * W3 (v1.7.3): an animated frame carries its CSS motion class on the
     * overlay (spin | pulse | shine) — decorative-only, keyframes gated
     * behind prefers-reduced-motion: no-preference in app.css.
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
    $animationClass = ($frame?->animation ?? 'none') !== 'none' ? ' frame-anim-'.$frame->animation : '';
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
             class="pointer-events-none absolute -inset-1 size-[calc(100%+8px)]{{ $animationClass }}" loading="lazy">
    @endif
</span>
