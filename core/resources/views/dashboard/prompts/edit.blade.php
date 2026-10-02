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
    {{-- P3 (v1.7.1): SEO coverage — the edit page must not be headless. --}}
    <x-seo :title="'Edit: '.$prompt->title" robots="noindex, follow"/>
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6" x-data="promptForm({
        contexts: {{ Js::from($typeContexts) }},
        categories: {{ Js::from($categories->values()) }},
        tools: {{ Js::from($tools->map(fn ($t) => ['name' => $t->name, 'modality' => $t->modality, 'is_active' => $t->is_active])->values()) }},
        initialType: {{ Js::from($oldType) }},
        initialCategoryId: {{ Js::from((string) old("category_id", (string) $prompt->category_id)) }},
        initialTools: {{ Js::from(array_values((array) old("recommended_tools", $latest?->toolList() ?? []))) }}
    })" >
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

        <form method="POST" enctype="multipart/form-data" action="{{ route('dashboard.prompts.update', $prompt) }}" class="mt-8" @submit="onSubmit()">
            @csrf
            @method('PUT')
            {{-- A1 (v1.7.2): the type radios themselves submit name="type" —
                 the hidden :value mirror is gone (server-rendered checked
                 state IS the contract now). --}}

            <div class="grid gap-8 lg:grid-cols-[1fr_320px]">
                <div class="space-y-8">
                    <section aria-label="Prompt type">
                        <div class="flex items-center gap-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-saffron/25 text-xs font-bold text-saffron-deep">1</span>
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-ink/60">Prompt type</h2>
                        </div>

                        {{-- A1 (v1.7.2): five SERVER-RENDERED radio cards,
                             pre-selected to the STORED type (checked in the
                             served HTML, not JS); same adaptation contract as
                             create. --}}
                        <div class="mt-3 grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            @foreach ($typeContexts as $key => $ctx)
                                <label class="cursor-pointer">
                                    <input type="radio" name="type" value="{{ $key }}" @checked($oldType === $key)
                                           @change="switchType('{{ $key }}')" class="peer sr-only">
                                    <span class="block rounded-2xl border border-ink/10 bg-paper-deep p-4 transition hover:border-ink/25 peer-checked:border-saffron-deep peer-checked:bg-saffron/20 peer-checked:shadow-[0_0_0_1px_rgba(245,158,11,0.25)]">
                                        <span class="text-lg">{{ $ctx['icon'] }}</span>
                                        <span class="mt-1.5 block font-mono text-sm font-bold tracking-tight text-ink">{{ $ctx['label'] }}</span>
                                        <span class="mt-0.5 block text-xs leading-relaxed text-ink0">{{ $ctx['blurb'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        {{-- A2: guidance copy ships server-side for the stored type;
                             Alpine swaps it (x-text) when the radio changes. --}}
                        <p class="mt-3 rounded-xl border border-saffron-deep/40 bg-saffron/10 px-4 py-3 text-sm leading-relaxed text-saffron-deep/90"
                           x-text="guidance">{{ $typeContexts[$oldType]['guidance'] ?? '' }}</p>
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
                                        {{-- A2: options are SERVER-RENDERED (no JS
                                             needed to pick one); each scoped option
                                             carries its own x-show gate so Alpine
                                             re-scopes the list on type change. --}}
                                        <option value="" disabled @selected(! old('category_id', $prompt->category_id))>Choose a category…</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                @selected((string) old('category_id', (string) $prompt->category_id) === (string) $category->id)
                                                @if ($category->type_scope) x-show="type === '{{ $category->type_scope }}'" @endif
                                            >{{ $category->name }}</option>
                                        @endforeach
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
                            {{-- A2: chip label ships server-side, swaps via Alpine. --}}
                            <span class="rounded-full border border-ink/10 bg-paper-deep px-2.5 py-0.5 text-[11px] text-ink/60" x-text="context.label">{{ $typeContexts[$oldType]['label'] ?? '' }}</span>
                        </div>

                        <div class="mt-4 space-y-5">
                            <div>
                                <x-form.label name="body" label="Prompt body" :hint="$variableHint" :required="true"/>
                                <div class="mt-1.5">
                                    {{-- H1 (v1.7.3 hotfix): body ceiling is 50,000 chars —
                                         see PromptFormRequest::MAX_BODY_CHARS. Same
                                         zero-JS-safe counter as create. --}}
                                    <div x-data="{ n: document.getElementById('body') ? document.getElementById('body').value.length : 0 }"
                                         x-on:input.debounce.100ms="n = document.getElementById('body').value.length">
                                        <textarea
                                            id="body"
                                            name="body"
                                            rows="12"
                                            required
                                            maxlength="{{ \App\Http\Requests\PromptFormRequest::MAX_BODY_CHARS }}"
                                            :placeholder="bodyPlaceholder"
                                            class="block w-full rounded-xl border bg-white px-3.5 py-3 font-mono text-[13px] leading-relaxed text-ink outline-none transition placeholder:text-ink/40 border-ink/10 focus:border-saffron-deep"
                                        >{{ old('body', $latest?->body) }}</textarea>
                                        <p class="mt-1 text-right text-[11px] text-ink/40" aria-live="off">
                                            <span x-text="n.toLocaleString()">{{ number_format(strlen(old('body', $latest?->body) ?? '')) }}</span> / {{ number_format(\App\Http\Requests\PromptFormRequest::MAX_BODY_CHARS) }} chars
                                        </p>
                                    </div>
                                </div>
                                @error('body') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <x-form.label name="recommended_tools" label="Works best in" hint="Pick 1–4 tools this prompt is tuned for." :required="true"/>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    {{-- P2 (v1.7.1): data-modality + x-show gate —
                                         identical contract to the create form. --}}
                                    <template x-for="entry in toolEntries" :key="entry.name">
                                        <button
                                            type="button"
                                            :data-modality="entry.modality"
                                            x-show="entry.modality === type || entry.modality === 'any' || selectedTools.includes(entry.name)"
                                            @click="toggleTool(entry.name)"
                                            :class="selectedTools.includes(entry.name)
                                                ? 'border-emerald-500/50 bg-emerald-100 text-emerald-800'
                                                : (isToolDisabled(entry.name)
                                                    ? 'border-ink/10 bg-white/[0.02] text-ink/40 cursor-not-allowed'
                                                    : 'border-ink/10 bg-paper-deep text-ink/60 hover:border-ink/25')"
                                            class="rounded-full border px-3.5 py-1.5 text-xs font-medium transition"
                                            x-text="entry.name"
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
                    {{-- A2 (v1.7.2): cover block appears for image. Zero-JS truth
                         lives in the type radio: CSS `:has()` + the shared
                         `data-cover-only` hook show/hide it in served HTML; the
                         x-show is the same Alpine enhancement the other adaptive
                         regions use. Existing cover preview + remove toggle
                         render server-side. image type only — server mirrors. --}}
                    <div class="rounded-2xl border border-ink/10 bg-white p-5"
                         data-cover-only
                         x-show="type === 'image'" x-cloak>
                        <h3 class="text-sm font-semibold text-ink">Cover image</h3>
                        <p class="mt-1 text-xs leading-relaxed text-ink/50">Show buyers the result your prompt produces. JPG/PNG/WebP — large files are compressed automatically.</p>
                        @if ($prompt->cover_image_path)
                            <div class="mt-3 overflow-hidden rounded-xl border border-ink/10" data-cover-preview>
                                <img src="{{ Storage::url($prompt->cover_image_path) }}" alt="Current cover" class="aspect-[4/3] w-full object-cover">
                            </div>
                            <label class="mt-2 flex items-center gap-2 text-xs text-ink/60">
                                <input type="checkbox" name="remove_cover" value="1" class="size-3.5 rounded border-ink/20 accent-saffron-deep">
                                Remove current cover
                            </label>
                        @endif
                        <div class="mt-3">
                            <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"
                                   data-cover-input
                                   class="block w-full cursor-pointer rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-xs text-ink/80 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-saffron file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink hover:file:bg-saffron-deep">
                        </div>
                        @error('cover_image') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

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
