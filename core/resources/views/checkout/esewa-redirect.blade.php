<x-app-layout>
    @php
        /** @var array{action: string, fields: array<string, string>} $form */
    @endphp

    <div class="mx-auto max-w-md px-4 py-20 text-center sm:px-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">Redirecting to eSewa…</h1>
        <p class="mt-2 text-sm text-ink/60">Please wait — do not close this page.</p>
        <div class="mt-6 h-1.5 w-full overflow-hidden rounded-full bg-paper-deep">
            <div class="h-full w-1/3 animate-pulse rounded-full bg-emerald-600"></div>
        </div>
    </div>

    <form method="POST" action="{{ $form['action'] }}" id="esewa-form">
        @foreach ($form['fields'] as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
    </form>

    @push('scripts')
        <script>
            document.getElementById('esewa-form').submit();
        </script>
    @endpush
</x-app-layout>
