@php
    /** @var \App\Models\Prompt $prompt */
    /** @var \App\Models\PromptVersion|null $latest */
    /** @var array<string, array<string, mixed>> $typeContexts */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Category> $categories */
    $oldType = old('type', $prompt->type);
    $priceNpr = old('price_npr', intdiv($prompt->price_cents, 100));
    $variableHint = 'Wrap reusable inputs in {{double braces}} — buyers get fill-in fields automatically.';
@endphp

<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6" x-data='promptForm({
        contexts: {{ Js::from($typeContexts) }},
        categories: {{ Js::from($categories->values()) }},
        initialType: {{ Js::from($oldType) }},
        initialCategoryId: {{ Js::from((string) old("category_id", (string) $prompt->category_id)) }},
        initialTools: {{ Js::from(array_values((array) old("recommended_tools", $latest?->toolList() ?? []))) }}
    })'>
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-saffron-deep/90">Creator workspace</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-ink">Edit prompt</h1>
                <p class="mt-2 text-sm text-ink/60">
                    Editing <span class="font-medium text-ink/90">{{ $prompt->title }}</span> —
                    every save commits a new version, like git.
                </p>
            </div>
            <a href="{{ route('prompts.show', $prompt) }}"
               class="inline-flex items-center gap-2 rounded-xl border border-ink/10 bg-paper-deep px-4 py-2 text-sm text-ink/80 transition hover:border-ink/25 hover:text-ink">
                View public page ↗
            </a>
        </header>

        <form method="POST" action="{{ route('dashboard.prompts.update', $prompt) }}" class="mt-8" @submit="onSubmit()">
            @csrf
            @method('PUT')
            <input type="hidden" name="type" :value="type">

            <div class="grid gap-8 lg:grid-cols-[1fr_320px]">
                <div class="space-y-8">
                    <section aria-label="Prompt type">
                        <div class="flex items-center gap-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-saffron/25 text-xs font-bold text-saffron-deep">1</span>
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-ink/60">Prompt type</h2>
                        </div>

                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <template x-for="(ctx, key) in contexts" :key="key">
                                <button
                                    type="button"
                                    @click="switchType(key)"
                                    :class="type === key
                                        ? 'border-saffron-deep bg-saffron/20 shadow-[0_0_0_1px_rgba(245,158,11,0.25)]'
                                        : 'border-ink/10 bg-paper-deep hover:border-ink/25'"
                                    class="rounded-2xl border p-4 text-left transition"
                                >
                                    <span class="text-lg" x-text="ctx.icon"></span>
                                    <span class="mt-1.5 block text-sm font-semibold text-ink" x-text="ctx.label"></span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-ink0" x-text="ctx.blurb"></span>
                                </button>
                            </template>
                        </div>

                        <p class="mt-3 rounded-xl border border-saffron-deep/40 bg-saffron/10 px-4 py-3 text-sm leading-relaxed text-saffron-deep/90"
                           x-text="guidance"></p>
                    </section>

                    <section aria-label="Listing details">
                        <div class="flex items-center gap-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-saffron/25 text-xs font-bold text-saffron-deep">2</span>
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-ink/60">Listing details</h2>
                        </div>

                        <div class="mt-4 space-y-5">
                            <div>
                                <x-form.label name="title" label="Title" :required="true"/>
                                <div class="mt-1.5">
                                    <x-form.input name="title" :value="old('title', $prompt->title)" :required="true" maxlength="160"/>
                                </div>
                                @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <x-form.label name="description" label="Short description" hint="Shown on cards and search results." :required="true"/>
                                <div class="mt-1.5">
                                    <x-form.textarea name="description" :value="old('description', $prompt->description)" :rows="3" :required="true" maxlength="600"/>
                                </div>
                                @error('description') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <x-form.label name="category_id" label="Category" :required="true"/>
                                <div class="mt-1.5">
                                    <select
                                        id="category_id"
                                        name="category_id"
                                        required
                                        x-model="categoryId"
                                        class="block w-full rounded-xl border bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none transition focus:bg-paper-deep border-ink/10 focus:border-saffron-deep"
                                    >
                                        <option value="" disabled>Choose a category…</option>
                                        <template x-for="option in categoryOptions" :key="option.id">
                                            <option :value="option.id" x-text="option.name" :selected="String(option.id) === categoryId"></option>
                                        </template>
                                    </select>
                                </div>
                                @error('category_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    <section aria-label="Prompt content">
                        <div class="flex items-center gap-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-saffron/25 text-xs font-bold text-saffron-deep">3</span>
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-ink/60">The prompt</h2>
                            <span class="rounded-full border border-ink/10 bg-paper-deep px-2.5 py-0.5 text-[11px] text-ink/60" x-text="context.label"></span>
                        </div>

                        <div class="mt-4 space-y-5">
                            <div>
                                <x-form.label name="body" label="Prompt body" :hint="$variableHint" :required="true"/>
                                <div class="mt-1.5">
                                    <textarea
                                        id="body"
                                        name="body"
                                        rows="12"
                                        required
                                        maxlength="4000"
                                        :placeholder="bodyPlaceholder"
                                        class="block w-full rounded-xl border bg-white px-3.5 py-3 font-mono text-[13px] leading-relaxed text-ink outline-none transition placeholder:text-ink/40 border-ink/10 focus:border-saffron-deep"
                                    >{{ old('body', $latest?->body) }}</textarea>
                                </div>
                                @error('body') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <x-form.label name="recommended_tools" label="Works best in" hint="Pick 1–4 tools this prompt is tuned for." :required="true"/>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <template x-for="tool in toolChips" :key="tool">
                                        <button
                                            type="button"
                                            @click="toggleTool(tool)"
                                            :class="selectedTools.includes(tool)
                                                ? 'border-emerald-500/50 bg-emerald-100 text-emerald-800'
                                                : (isToolDisabled(tool)
                                                    ? 'border-ink/10 bg-white/[0.02] text-ink/40 cursor-not-allowed'
                                                    : 'border-ink/10 bg-paper-deep text-ink/60 hover:border-ink/25')"
                                            class="rounded-full border px-3.5 py-1.5 text-xs font-medium transition"
                                            x-text="tool"
                                        ></button>
                                    </template>
                                </div>
                                <template x-for="tool in selectedTools" :key="'hidden-'+tool">
                                    <input type="hidden" name="recommended_tools[]" :value="tool">
                                </template>
                                @error('recommended_tools.*') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                @error('recommended_tools') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <x-form.label name="audience" label="Perfect for (optional)"/>
                                    <div class="mt-1.5">
                                        <input
                                            type="text"
                                            id="audience"
                                            name="audience"
                                            value="{{ old('audience', $latest?->audience) }}"
                                            maxlength="120"
                                            :placeholder="audiencePlaceholder"
                                            class="block w-full rounded-xl border bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-ink/40 focus:bg-paper-deep border-ink/10 focus:border-saffron-deep"
                                        >
                                    </div>
                                    @error('audience') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <x-form.label name="tags" label="Tags" hint="Comma separated, up to 5." :required="true"/>
                                    <div class="mt-1.5">
                                        <x-form.input name="tags" :value="old('tags', $tagsValue)" :required="true" maxlength="200"/>
                                    </div>
                                    @error('tags') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <x-form.label name="tips" label="Usage tips (optional)" hint="One tip per line — shown under the prompt on its page."/>
                                <div class="mt-1.5">
                                    <x-form.textarea name="tips" :value="old('tips', $tipsValue)" :rows="3" maxlength="1500"/>
                                </div>
                                @error('tips') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                    <div class="rounded-2xl border border-ink/10 bg-white p-5">
                        <h3 class="text-sm font-semibold text-ink">Pricing</h3>
                        <div class="mt-4">
                            <x-form.label name="price_npr" label="Price (NPR)" hint="Set 0 to offer it for free." :required="true"/>
                            <div class="mt-1.5">
                                <x-form.input name="price_npr" type="number" :value="$priceNpr" :required="true" min="0"/>
                            </div>
                            @error('price_npr') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="rounded-2xl border border-ink/10 bg-white p-5">
                        <h3 class="text-sm font-semibold text-ink">Visibility</h3>
                        <div class="mt-4">
                            <x-form.select name="visibility" :selected="old('visibility', $prompt->visibility)" :options="[
                                'public' => 'Public — listed in the marketplace',
                                'private' => 'Private — only you can open it',
                            ]"/>
                            @error('visibility') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="rounded-2xl border border-ink/10 bg-white p-5">
                        <h3 class="text-sm font-semibold text-ink">Changelog</h3>
                        <div class="mt-3">
                            <x-form.input name="changelog" :value="old('changelog')" maxlength="500" placeholder="What changed in this version?"/>
                        </div>
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-saffron px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-saffron-deep disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span x-text="submitting ? 'Saving…' : 'Save new version'"></span>
                        </button>
                    </div>

                    @if ($prompt->versions->isNotEmpty())
                        <div class="rounded-2xl border border-ink/10 bg-white p-5">
                            <h3 class="text-sm font-semibold text-ink">History</h3>
                            <ol class="mt-3 space-y-3">
                                @foreach ($prompt->versions->sortByDesc('version_number')->take(5) as $version)
                                    <li class="flex gap-3 text-xs">
                                        <span class="mt-0.5 rounded-md bg-paper-deep px-1.5 py-0.5 font-mono text-[11px] text-saffron-deep">{{ $version->label() }}</span>
                                        <div class="min-w-0">
                                            <p class="truncate text-ink/80">{{ $version->changelog ?? 'Updated prompt' }}</p>
                                            <p class="text-ink/40">{{ $version->created_at->format('M j, Y') }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                </aside>
            </div>
        </form>
    </div>
</x-app-layout>
