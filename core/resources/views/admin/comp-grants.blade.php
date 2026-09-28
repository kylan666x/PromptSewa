<x-admin-layout title="Comp grants">
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
        /** @var \Illuminate\Support\Collection<int, \App\Models\Prompt> $prompts */
        /** @var string $search */
    @endphp

    <p class="max-w-2xl text-sm text-ink/60">
        Complimentary grants give an account a free license — press copies, partner
        giveaways, support make-goods. Every grant is recorded in the license ledger
        as tier <code class="font-mono text-ink/80">comp</code> with your name and the
        reason, and shows up in the recipient's library like any purchase.
    </p>

    <form method="GET" action="{{ route('admin.comp-grants.create') }}" class="mt-6 flex max-w-md gap-2">
        <input type="search" name="q" value="{{ $search }}" placeholder="Find user by name, @handle or email…"
               class="block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"/>
        <button class="rounded-xl border border-ink/15 bg-white px-4 text-sm font-medium text-ink/80 transition hover:border-saffron-deep hover:text-saffron-deep">Find</button>
    </form>

    <form method="POST" action="{{ route('admin.comp-grants.store') }}" class="mt-6 max-w-xl space-y-4 rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
        @csrf
        <label class="block">
            <span class="text-sm font-semibold text-ink/90">Recipient</span>
            <select name="user_id" required class="mt-1 block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                @endforeach
            </select>
            @error('user_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold text-ink/90">Prompt (published)</span>
            <select name="prompt_id" required class="mt-1 block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
                @foreach ($prompts as $prompt)
                    <option value="{{ $prompt->id }}">{{ $prompt->title }} — {{ $prompt->priceLabel() }}</option>
                @endforeach
            </select>
            @error('prompt_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </label>

        <label class="block">
            <span class="text-sm font-semibold text-ink/90">Reason (audit trail)</span>
            <textarea name="reason" required minlength="3" maxlength="500" rows="3"
                      class="mt-1 block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"
                      placeholder="e.g. Press copy for TechSamachar review — approved by Aasha"></textarea>
            @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </label>

        <button class="inline-flex items-center gap-2 rounded-xl bg-saffron px-5 py-2.5 text-sm font-semibold text-ink shadow-lg shadow-saffron/30 transition hover:bg-saffron-deep">
            Issue comp grant
        </button>
    </form>
</x-admin-layout>
