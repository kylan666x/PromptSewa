@props(['user', 'onInk' => false, 'size' => 'text-sm'])

@php($handle = $user->username ?? $user->name)

<span {{ $attributes->merge([
    'class' => 'min-w-0 truncate font-mono '.($onInk ? 'text-paper/60' : 'text-ink/60').' '.$size,
]) }}>{{ '@'.$handle }}</span>
