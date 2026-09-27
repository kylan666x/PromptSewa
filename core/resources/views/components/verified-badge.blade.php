@props(['user', 'size' => 'sm'])

@php
    /** @var \App\Models\User $user */
    $sizeClass = match ($size) {
        'xs' => 'size-3',
        'md' => 'size-5',
        'lg' => 'size-7',
        default => 'size-4',
    };
@endphp

@if ($user?->is_verified)
    <span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center']) }}
          title="Verified" aria-label="Verified creator">
        {{-- Seal-style badge in PromptSewa saffron with ink check — brand answer to the blue tick. --}}
        <svg class="{{ $sizeClass }} drop-shadow-sm" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M12 1.8l2.36 2.05 3.1-.35 1.02 2.95 2.8 1.44-.86 3 1.62 2.62-2.25 2.16.13 3.12-3.08.68-1.84 2.55L12 20.6l-3 1.47-1.84-2.55-3.08-.68.13-3.12L1.96 13.56l1.62-2.62-.86-3 2.8-1.44 1.02-2.95 3.1.35L12 1.8z" fill="#EAB308"/>
            <path d="M12 2.6l2.06 1.79 2.7-.3.89 2.57 2.44 1.25-.75 2.61 1.41 2.28-1.96 1.88.11 2.72-2.68.59-1.6 2.22L12 18.9l-2.62 1.31-1.6-2.22-2.68-.59.11-2.72-1.96-1.88 1.41-2.28-.75-2.61 2.44-1.25.89-2.57 2.7.3L12 2.6z" fill="#FACC15"/>
            <path d="M8.2 12.1l2.5 2.5 5.1-5.4" stroke="#171715" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </span>
@endif
