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

    {{-- Users are served as a capped list; this server-side find reaches the
         rest of the table. The picker below filters what is served. --}}
    <form method="GET" action="{{ route('admin.comp-grants.create') }}" class="mt-5 flex max-w-sm gap-2">
        <input type="search" name="q" value="{{ $search }}" placeholder="Find a user by name, @handle or email…"
               class="block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"/>
        <button class="rounded-xl border border-ink/15 bg-white px-4 text-sm font-medium text-ink/80 transition hover:border-saffron-deep hover:text-saffron-deep">Find</button>
    </form>
    <p class="mt-1 text-xs text-ink/50">Showing up to 300 accounts — type in the picker to filter them, or use Find for the whole table.</p>

    {{-- R5 (v1.7.7 Bug Hunt Raid): the whole grant fits one screen — two
         server-rendered comboboxes on one row, reason beneath, one submit. --}}
    <form method="POST" action="{{ route('admin.comp-grants.store') }}" class="mt-5 rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <x-searchable-picker name="user_id" label="Recipient" :required="true"
                                 placeholder="Search name, @handle or email…"
                                 empty-text="No account matches.">
                <x-slot:options>
                    @foreach ($users as $user)
                        <button type="button" role="option" data-value="{{ $user->id }}"
                                data-chip="{{ $user->name }} ({{ $user->email }})"
                                data-search="{{ mb_strtolower($user->name.' '.$user->email.' '.($user->username ?? '')) }}"
                                @click="choose($el)"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm text-ink transition hover:bg-paper-deep">
                            <span class="min-w-0 flex-1 truncate">{{ $user->name }}</span>
                            <span class="shrink-0 font-mono text-xs text-ink/50">{{ $user->email }}</span>
                        </button>
                    @endforeach
                </x-slot:options>
            </x-searchable-picker>

            <x-searchable-picker name="prompt_id" label="Prompt" :required="true"
                                 placeholder="Search the full prompt list…"
                                 empty-text="No prompt matches.">
                <x-slot:options>
                    @foreach ($prompts as $prompt)
                        <button type="button" role="option" data-value="{{ $prompt->id }}"
                                data-chip="{{ \Illuminate\Support\Str::limit($prompt->title, 60) }}"
                                data-search="{{ mb_strtolower($prompt->title.' '.$prompt->status) }}"
                                @click="choose($el)"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm text-ink transition hover:bg-paper-deep">
                            <span class="min-w-0 flex-1 truncate">{{ $prompt->title }}</span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span @class([
                                    'rounded-md px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wide',
                                    'bg-emerald-100 text-emerald-800' => $prompt->status === \App\Models\Prompt::STATUS_PUBLISHED,
                                    'bg-saffron/25 text-saffron-deep' => $prompt->status === \App\Models\Prompt::STATUS_PENDING,
                                    'bg-rose-100 text-rose-700' => $prompt->status === \App\Models\Prompt::STATUS_REJECTED,
                                    'bg-paper-deep text-ink/60' => $prompt->status === \App\Models\Prompt::STATUS_DRAFT,
                                ])>{{ $prompt->status }}</span>
                                <x-money :paisa="$prompt->price_cents" class="text-xs text-ink/60"/>
                            </span>
                        </button>
                    @endforeach
                </x-slot:options>
            </x-searchable-picker>
        </div>

        <label class="mt-4 block">
            <span class="text-sm font-semibold text-ink/90">Reason (audit trail)</span>
            <textarea name="reason" required minlength="3" maxlength="500" rows="2"
                      class="mt-1 block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40"
                      placeholder="e.g. Press copy for TechSamachar review — approved by Aasha">{{ old('reason') }}</textarea>
            @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </label>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-saffron px-5 py-2.5 text-sm font-semibold text-ink shadow-lg shadow-saffron/30 transition hover:bg-saffron-deep">
                Issue comp grant
            </button>
            <p class="text-xs text-ink/50">Idempotent while an earlier grant is active — a double submit never double-grants.</p>
        </div>
    </form>
</x-admin-layout>
