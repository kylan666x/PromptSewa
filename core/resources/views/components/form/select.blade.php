@props(['name', 'options' => [], 'selected' => null, 'required' => false])

@php
    $error = $errors->has($name);
@endphp

<select
    id="{{ $name }}"
    name="{{ $name }}"
    @if ($required) required @endif
    {{ $attributes->merge(['class' => 'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm outline-none transition focus:ring-2 '.($error ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-200' : 'border-ink/15 focus:border-saffron-deep focus:ring-saffron/40')]) }}>
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}</option>
    @endforeach
</select>

@error($name)
    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
@enderror
