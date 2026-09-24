@php
    /** @var \App\Models\Prompt $prompt */
    $reasons = \App\Models\PromptReport::REASONS;
@endphp

<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 font-mono text-xs text-ink/50">
            <a href="{{ route('home') }}" class="transition hover:text-ink">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('prompts.show', $prompt) }}" class="transition hover:text-ink">{{ Str::limit($prompt->title, 40) }}</a>
            <span aria-hidden="true">/</span>
            <span class="text-ink/70">Report</span>
        </nav>

        <h1 class="mt-6 text-3xl font-bold tracking-tight text-ink">Report this prompt</h1>
        <p class="mt-2 text-sm leading-relaxed text-ink/60">
            Something wrong with <span class="font-semibold text-ink">{{ $prompt->title }}</span>?
            Tell us what happened — a moderator reviews every report.
        </p>

        <form method="POST" action="{{ route('prompts.report.store', $prompt) }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="reason" class="block text-sm font-semibold text-ink">What is wrong? <span class="text-saffron-deep">*</span></label>
                <select
                    id="reason"
                    name="reason"
                    required
                    class="mt-1.5 block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"
                >
                    <option value="" disabled selected>Choose a reason…</option>
                    @foreach ($reasons as $key => $label)
                        <option value="{{ $key }}" @if(old('reason') === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
                @error('reason') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="message" class="block text-sm font-semibold text-ink">Details <span class="text-saffron-deep">*</span></label>
                <p class="mt-0.5 text-xs text-ink/50">What happened? Include anything a moderator would need (min 10 characters).</p>
                <textarea
                    id="message"
                    name="message"
                    rows="6"
                    required
                    minlength="10"
                    maxlength="2000"
                    placeholder="Describe the problem…"
                    class="mt-1.5 block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink placeholder-ink/30 shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"
                >{{ old('message') }}</textarea>
                @error('message') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
            </div>

            @guest
                <div>
                    <label for="reporter_email" class="block text-sm font-semibold text-ink">Your email <span class="text-xs font-normal text-ink/50">(optional — so we can follow up)</span></label>
                    <input
                        type="email"
                        id="reporter_email"
                        name="reporter_email"
                        value="{{ old('reporter_email') }}"
                        maxlength="255"
                        class="mt-1.5 block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"
                    >
                    @error('reporter_email') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                </div>
            @endguest

            <div class="flex items-center gap-3 pt-2">
                <button
                    type="submit"
                    class="rounded-full bg-saffron px-6 py-2.5 text-sm font-bold text-ink transition hover:bg-saffron-deep"
                >
                    Submit report
                </button>
                <a href="{{ route('prompts.show', $prompt) }}" class="text-sm font-medium text-ink/60 transition hover:text-ink">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
