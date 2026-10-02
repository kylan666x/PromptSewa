@props(['user', 'size' => 'md', 'frame' => null])

@php
    /**
     * C1 — identity avatar component (v1.4.4 "Avatar Fidelity").
     *
     * v1.7.5 (R1) — THE COMPOSITE BOX. This is the FOURTH geometry ruling
     * and it supersedes all three before it (v1.7.2's un-isolated `-inset-1`
     * ring, v1.7.3-hotfix's `inset-0`-inside-clip, and v1.7.4 G1's
     * negative-inset protrusion). Three layers, ONE coordinate system —
     * the wrapper's border box:
     *
     *   1. WRAPPER — `relative inline-block isolate` + the size class that
     *      the `size` prop maps to. It carries NO rounding, NO background,
     *      NO border, NO overflow. The composite box is exactly the frame
     *      art's canvas: nothing may grow, shrink or paint it.
     *   2. FRAME — the art at `absolute inset-0 size-full object-contain`,
     *      `pointer-events-none z-10`, rendered ONLY when a frame is
     *      equipped. It fills the box; it never protrudes.
     *   3. PHOTO/BADGE — `absolute overflow-hidden rounded-full`, inset by
     *      (100 − hole) / 2 percent when framed (`inset: 19%` for the
     *      standard 62% hole) and `inset-0` when frameless. The circle is
     *      created HERE, and the photo/badge fill that same box.
     *
     * WHY THIS ENDS THE SAGA: the founder's screenshot showed the ring and
     * photo hanging off the top-left of a saffron squircle. Three
     * coordinate systems were in play at once — the hero caller's border
     * box (96px, 4px border), the padding box the `size-full` clipper
     * resolved against (88px, offset +4/+4), and the overlay's
     * over-constrained inset box (88×109 for a SQUARE png, because all
     * four insets over-determine a replaced element, so `object-contain`
     * letterboxed the art inside it). Nothing could line up. In the
     * composite box every layer is inset against the SAME border box, the
     * frame never leaves it, and the caller cannot inject geometry (see
     * stripGeometryClasses below + tests/Arch/UserAvatarGeometryTest).
     *
     * CONSEQUENCE: nothing protrudes, so the v1.7.4 ancestor-clip contract
     * (no overflow-hidden above a framed avatar) is no longer load-bearing
     * for frames. Card roots and admin panels stay un-clipped anyway —
     * harmless, and the cover element still clips for its own bleed.
     *
     * Frame art spec: 512×512 alpha PNG, ring may run to the canvas edges,
     * transparent centre hole 35–70% of the canvas (62 = shipped standard;
     * the founder's Abyssal measures 37.5%). Animated frames keep their
     * frame-anim-* class (W3), gated behind prefers-reduced-motion in the
     * built CSS.
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
        // v1.7.5: the profile hero. It is a SIZE PROP, not a caller class —
        // the hero used to merge size-24/rounded-3xl/border-4/bg-saffron
        // onto the wrapper, which is precisely what broke the composite.
        'xl' => 'size-24 text-4xl sm:size-28',
    ];
    $wrapperSize = $sizes[$size] ?? $sizes['md'];

    $framed = $frame !== null && (string) $frame->image_path !== '';
    $inset = $framed ? $frame->photoInsetPercent() : 0.0;
    $animationClass = ($frame?->animation ?? 'none') !== 'none' ? ' frame-anim-'.$frame->animation : '';

    // v1.7.5 (R2): the caller may style the box (transition, ring offsets)
    // but may NEVER inject geometry. A merged `size-24` silently beat the
    // prop's size class and produced the founder's broken hero; stripping
    // the tokens here means the component is immune even if the arch ban
    // is bypassed. Sizes flow through the `size` prop only.
    $callerClass = (string) $attributes->get('class', '');
    $safeClass = trim(implode(' ', array_filter(
        preg_split('/\s+/', $callerClass) ?: [],
        fn (string $token): bool => ! preg_match('/^(rounded|size|bg|border|overflow)-/', $token),
    )));
@endphp

<span {{ $attributes->merge(['class' => 'relative inline-block isolate shrink-0 '.$wrapperSize.($safeClass !== '' ? ' '.$safeClass : '')]) }}
      data-avatar-size="{{ $size }}"
      @if ($framed) data-frame-hole="{{ $frame->holePercent() }}" @endif>

    {{-- LAYER 2 — the frame art fills the composite box exactly. --}}
    @if ($framed)
        <img src="{{ $frame->url }}" alt="" aria-hidden="true" loading="lazy"
             class="pointer-events-none absolute inset-0 z-10 size-full object-contain{{ $animationClass }}">
    @endif

    {{-- LAYER 3 — the circle, inset to the frame's own hole. --}}
    <span class="absolute overflow-hidden rounded-full{{ $framed ? '' : ' inset-0' }}"
          @if ($framed) style="inset: {{ $inset }}%" @endif>
        @if ($user->avatar_path)
            <img src="{{ Storage::disk('public')->url($user->avatar_path) }}"
                 alt="{{ '@'.$handle }}"
                 loading="lazy"
                 class="size-full object-cover">
        @else
            <span class="flex size-full items-center justify-center rounded-full bg-ink font-bold text-saffron"
                  aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
        @endif
    </span>
</span>
