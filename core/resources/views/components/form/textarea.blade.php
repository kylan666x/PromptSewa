@props(['name', 'value' => null, 'placeholder' => null, 'rows' => 5, 'required' => false, 'maxlength' => null])

@php
    $error = $errors->has($name);
@endphp

<textarea
    id="{{ $name }}"
    name="{{ $name }}"
    rows="{{ $rows }}"
    @if ($placeholder) placeholder="{{ $placeholder }}" @endif
    @if ($required) required @endif
    @if ($maxlength) maxlength="{{ $maxlength }}" @endif
    {{ $attributes->merge(['class' => 'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:ring-2 '.($error ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-200' : 'border-ink/15 focus:border-saffron-deep focus:ring-saffron/40')]) }}>{{ $value }}</textarea>

@error($name)
    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
@enderror
