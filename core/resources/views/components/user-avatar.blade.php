@props(['user', 'size' => 'md', 'frame' => null])

@php
    /**
     * C1 — identity avatar component (v1.4.4 "Avatar Fidelity").
     *
     * G1 (v1.7.4) — FRAME OUTSIDE THE CIRCLE. The component is three
     * layers, and the ORDER of the rules is the whole design:
     *
     *   1. WRAPPER  — `relative inline-block isolate` + the caller's size.
     *      It owns the stacking context (H3's `isolate`, kept) and it must
     *      NEVER carry overflow-hidden: the frame protrudes past this box,
     *      so clipping here would eat the ornament. It also carries no
     *      rounded-full — the circle belongs to the photo, not the frame.
     *   2. CLIPPER  — an inner `size-full overflow-hidden rounded-full`
     *      span wrapping the photo / initials badge. The CIRCLE is created
     *      HERE and only here.
     *   3. OVERLAY  — the frame art, `pointer-events-none absolute z-10
     *      object-contain`, pulled OUT by a negative inset keyed to the
     *      size (xs/sm −8%, md/lg −12%) so the ring surrounds the photo
     *      instead of sitting inside it.
     *
     * This SUPERSEDES two earlier rulings: v1.7.2's un-isolated `-inset-1`
     * "ring outside the box" and v1.7.3-hotfix H2's `inset-0`
     * "picture inside the frame" inside-clip. Do not reintroduce either.
     *
     * Frame art spec: a 512×512 PNG with a transparent centre hole of
     * 55–70% — the ring deliberately overlaps the photo's outer annulus.
     * Animated frames keep their frame-anim-* class (W3), gated behind
     * prefers-reduced-motion: no-preference in the built CSS.
     *
     * ANCESTOR CONTRACT: because the overlay protrudes, no ancestor of a
     * framed avatar may carry overflow-hidden (card roots that clipped for
     * cover bleed must move the clip onto the cover element itself). This
     * is locked by rendered-HTML assertions, not by this file.
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
    // G1: how far the frame art sits outside the photo, per size. Small
    // avatars get proportionally less protrusion (a ring would swallow a
    // 20px avatar at 12%).
    $frameInsets = [
        'xs' => '-inset-[8%]',
        'sm' => '-inset-[8%]',
        'md' => '-inset-[12%]',
        'lg' => '-inset-[12%]',
    ];
    $callerClass = (string) $attributes->get('class', '');
    $preset = str_contains($callerClass, 'size-') ? '' : ($sizes[$size] ?? $sizes['md']);
    $frameInset = $frameInsets[$size] ?? $frameInsets['md'];
    $animationClass = ($frame?->animation ?? 'none') !== 'none' ? ' frame-anim-'.$frame->animation : '';
@endphp

<span {{ $attributes->merge(['class' => 'relative inline-block isolate shrink-0 text-[length:inherit] '.$preset]) }}>
    <span class="block size-full overflow-hidden rounded-full">
        @if ($user->avatar_path)
            <img src="{{ Storage::disk('public')->url($user->avatar_path) }}"
                 alt="{{ '@'.$handle }}"
                 loading="lazy"
                 class="size-full rounded-full object-cover">
        @else
            <span class="flex size-full items-center justify-center rounded-full bg-ink font-bold text-saffron"
                  aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
        @endif
    </span>

    @if ($frame?->image_path)
        <img src="{{ $frame->url }}" alt="" aria-hidden="true" loading="lazy"
             class="pointer-events-none absolute z-10 object-contain {{ $frameInset }}{{ $animationClass }}">
    @endif
</span>