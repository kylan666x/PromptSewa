@props(['label', 'name', 'hint' => null, 'required' => false])

<label for="{{ $name }}" class="block text-sm font-medium text-ink/90">
    {{ $label }}
    @if ($required)
        <span class="text-saffron-deep">*</span>
    @endif
</label>

@if ($hint)
    <p class="mt-1 text-xs text-ink/50">{{ $hint }}</p>
@endif
