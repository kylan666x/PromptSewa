@props(['user', 'size' => 'md', 'frame' => null])

@php
    /**
     * C1 — identity avatar component (v1.4.4 "Avatar Fidelity").
     *
     * W1 (v1.7.3, HOTFIX ADDENDUM) — CIRCLE-EVERYWHERE, hard-forced:
     * the wrapper ALWAYS carries rounded-full + overflow-hidden, no
     * conditional geometry and no caller-override shape (the former
     * hero squircle class chain is retired; see handoff §6.40 and the
     * post-deploy hotfix directive). The avatar img and the initials
     * badge are both rounded-full themselves so the circle survives
     * even if a caller injects extra classes onto the wrapper.
     *
     * Frame overlay: absolutely positioned OVER the avatar
     * (inset-0, object-contain — the 512×512 PNG's transparent center
     * lets the picture show through), pointer-events-none + aria-hidden,
     * rendered whenever a frame is passed (callers only pass
     * ->activeFrame, which is null without an equipped frame — the
     * hidden-by-default contract). Animated frames keep their
     * frame-anim-* motion class (W3), gated behind
     * prefers-reduced-motion: no-preference in the built CSS.
     *
     * H3 (v1.7.3 hotfix 2) — STACKING: the wrapper carries `isolate`
     * (its own stacking context, so z-10 can never leak onto a
     * neighbouring element) and the overlay carries `z-10`, which forces
     * it above the avatar image/initial badge in every stacking context
     * (the "frame disappeared behind the picture" bug). The overlay's src
     * comes from the Frame::url accessor — the single resolved public
     * URL, never a hand-rolled disk call.
     *
     * Sizes: xs (cards/typeahead), sm (compact rows), md (bylines,
     * navbar), lg (creator grids, admin tables). Extra classes still
     * merge through $attributes (color/layout only — geometry is fixed).
     *
     * P5 (v1.7.1) — composition fix retained: the SIZE lives on the
     * outer wrapper; the inner badge fills it (size-full). If the
     * caller supplies its own size-* class, the preset is dropped so
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
    $animationClass = ($frame?->animation ?? 'none') !== 'none' ? ' frame-anim-'.$frame->animation : '';
@endphp

<span {{ $attributes->merge(['class' => 'relative isolate inline-flex shrink-0 rounded-full overflow-hidden text-[length:inherit] '.$preset]) }}>
    @if ($user->avatar_path)
        <img src="{{ Storage::disk('public')->url($user->avatar_path) }}"
             alt="{{ '@'.$handle }}"
             loading="lazy"
             class="absolute inset-0 size-full rounded-full object-cover">
    @else
        <span class="absolute inset-0 flex size-full items-center justify-center rounded-full bg-ink font-bold text-saffron"
              aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
    @endif

    @if ($frame?->image_path)
        <img src="{{ $frame->url }}" alt="" aria-hidden="true"
             class="pointer-events-none absolute inset-0 z-10 size-full rounded-full object-contain{{ $animationClass }}" loading="lazy">
    @endif
</span>
