@php
    /** @var array<string, array<string, mixed>> $typeContexts */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Category> $categories */
    $oldType = old('type', \App\Models\Prompt::TYPE_TEXT);
    $variableHint = 'Wrap reusable inputs in {{double braces}} — buyers get fill-in fields automatically.';
@endphp

<x-app-layout>
    {{-- P3 (v1.7.1): the add-prompt page was the SEO crawl's founding
         offender — headless (brand-only/no title). x-seo is mandatory. --}}
    <x-seo :title="'Add a new prompt'" robots="noindex, follow"/>
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6" x-data="promptForm({
        contexts: {{ Js::from($typeContexts) }},
        categories: {{ Js::from($categories->values()) }},
        tools: {{ Js::from($tools->map(fn ($t) => ['name' => $t->name, 'modality' => $t->modality, 'is_active' => $t->is_active])->values()) }},
        initialType: {{ Js::from($oldType) }},
        initialCategoryId: {{ Js::from((string) old("category_id", "")) }},
        initialTools: {{ Js::from(array_values((array) old("recommended_tools", []))) }}
    })" >
        <header>
            <p class="text-xs font-semibold uppercase tracking-widest text-saffron-deep/90">Creator workspace</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-ink">Add a new prompt</h1>
            <p class="mt-2 max-w-2xl text-sm text-ink/60">
                Pick the kind of prompt you are selling first — the form adapts to it with the right
                placeholders, structure guidance, and suggested tools. Every submission goes through
                a quick review before it appears in the library.
            </p>
        </header>

        <form method="POST" enctype="multipart/form-data" action="{{ route('dashboard.prompts.store') }}" class="mt-8" @submit="onSubmit()">
            @csrf
            {{-- A1 (v1.7.2): the type radios themselves submit name="type" —
                 the hidden :value mirror is gone (server-rendered checked
                 state IS the contract now). --}}

            {{-- Step 1: prompt type cards (God of Prompt taxonomy) --}}
            <section aria-label="Prompt type">
                <div class="flex items-center gap-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-saffron/25 text-xs font-bold text-saffron-deep">1</span>
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-ink/60">What are you selling?</h2>
                </div>

                {{-- A1 (v1.7.2): five SERVER-RENDERED radio cards — the served
                     HTML carries real checked inputs (mono label + one-line
                     description each), visible and submittable with zero
                     JavaScript. A2: Alpine switchType only ENHANCES — guidance
                     copy, category re-scope, the section-3 chip, the cover
                     block and the tool wall adapt on change. Server-side
                     validation remains the source of truth. --}}
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

                {{-- A2: guidance copy ships server-side for the initial type;
                     Alpine swaps it (x-text) when the radio changes. --}}
                <p class="mt-3 rounded-xl border border-saffron-deep/40 bg-saffron/10 px-4 py-3 text-sm leading-relaxed text-saffron-deep/90"
                   x-text="guidance">{{ $typeContexts[$oldType]['guidance'] ?? '' }}</p>
            </section>

            <div class="mt-10 grid gap-8 lg:grid-cols-[1fr_320px]">
                {{-- Step 2: listing + content --}}
                <div class="space-y-8">
                    <section aria-label="Listing details">
                        <div class="flex items-center gap-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-saffron/25 text-xs font-bold text-saffron-deep">2</span>
                            <h2 class="text-sm font-semibold uppercase tracking-wider text-ink/60">Listing details</h2>
                        </div>

                        <div class="mt-4 space-y-5">
                            <div>
                                <x-form.label name="title" label="Title" :required="true"/>
                                <div class="mt-1.5">
                                    <x-form.input
                                        name="title"
                                        :value="old('title')"
                                        placeholder="e.g. Cinematic Product Photography Hero Shot"
                                        :required="true"
                                        maxlength="160"
                                    />
                                </div>
                                @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <x-form.label name="description" label="Short description" hint="Shown on cards and search results. Sell the outcome, not the mechanics." :required="true"/>
                                <div class="mt-1.5">
                                    <x-form.textarea
                                        name="description"
                                        :value="old('description')"
                                        :rows="3"
                                        :required="true"
                                        maxlength="600"
                                        placeholder="One paragraph: what this prompt produces and who it is for."
                                    />
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
                                        <option value="" disabled @selected(! old('category_id'))>Choose a category…</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                @selected((string) old('category_id', '') === (string) $category->id)
                                                @if ($category->type_scope) x-show="type === '{{ $category->type_scope }}'" @endif
                                            >{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <p class="mt-1 text-xs text-ink0">Only categories that fit the selected prompt type are listed.</p>
                                @error('category_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    {{-- Step 3: the prompt itself — placeholders follow the type --}}
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
                                         see PromptFormRequest::MAX_BODY_CHARS. The
                                         counter is plain JS on an x-data div, so it
                                         works even with JS off (maxlength still caps
                                         the input; no template mutation needed). --}}
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
                                        >{{ old('body') }}</textarea>
                                        <p class="mt-1 text-right text-[11px] text-ink/40" aria-live="off">
                                            <span x-text="n.toLocaleString()">{{ number_format(strlen(old('body') ?? '')) }}</span> / {{ number_format(\App\Http\Requests\PromptFormRequest::MAX_BODY_CHARS) }} chars
                                        </p>
                                    </div>
                                </div>
                                @error('body') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <x-form.label name="recommended_tools" label="Works best in" hint="Pick 1–4 tools this prompt is tuned for." :required="true"/>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    {{-- P2 (v1.7.1): tool chips render ONCE with a
                                         data-modality attribute and an x-show gate —
                                         an image tool is invisible on Text the
                                         instant the form loads, with zero JS work. --}}
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
                                            value="{{ old('audience') }}"
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
                                        <x-form.input
                                            name="tags"
                                            :value="old('tags')"
                                            :required="true"
                                            maxlength="200"
                                            placeholder="photography, product shots, lighting"
                                        />
                                    </div>
                                    @error('tags') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <x-form.label name="tips" label="Usage tips (optional)" hint="One tip per line — shown under the prompt on its page."/>
                                <div class="mt-1.5">
                                    <x-form.textarea
                                        name="tips"
                                        :value="old('tips')"
                                        :rows="3"
                                        maxlength="1500"
                                        placeholder="Works best with aspect ratio 3:2&#10;Increase stylize for softer light"
                                    />
                                </div>
                                @error('tips') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>
                </div>

                {{-- Sidebar: pricing + visibility --}}
                <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                    {{-- A2 (v1.7.2): cover-upload block appears for image. Zero-JS
                         truth lives in the type radio: CSS `:has()` + the shared
                         `data-cover-only` hook show/hide it in served HTML; the
                         x-show is the same Alpine enhancement the other adaptive
                         regions use. image type only — server validation mirrors. --}}
                    <div class="rounded-2xl border border-ink/10 bg-white p-5"
                         data-cover-only
                         x-show="type === 'image'" x-cloak>
                        <h3 class="text-sm font-semibold text-ink">Cover image</h3>
                        <p class="mt-1 text-xs leading-relaxed text-ink/50">Show buyers the result your prompt produces. JPG/PNG/WebP — large files are compressed automatically.</p>
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
                                <x-form.input
                                    name="price_npr"
                                    type="number"
                                    :value="old('price_npr', 0)"
                                    :required="true"
                                    min="0"
                                    placeholder="0"
                                />
                            </div>
                            @error('price_npr') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            <p class="mt-2 text-xs leading-relaxed text-ink0">
                                Free prompts publish instantly after review. Paid prompts also go through
                                review; buyers get a personal license automatically at checkout.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-ink/10 bg-white p-5">
                        <h3 class="text-sm font-semibold text-ink">Visibility</h3>
                        <div class="mt-4">
                            <x-form.label name="visibility" label="Who can see this?" :required="true"/>
                            <div class="mt-1.5">
                                <x-form.select name="visibility" :selected="old('visibility', 'public')" :options="[
                                    'public' => 'Public — listed in the marketplace',
                                    'private' => 'Private — only you can open it',
                                ]"/>
                            </div>
                            @error('visibility') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="rounded-2xl border border-ink/10 bg-white p-5">
                        {{-- T3 (v1.7.3): bot check on prompt submit (off by default). --}}
                        <x-captcha form="submit"/>

                        <button
                            type="submit"
                            :disabled="submitting"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-saffron px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-saffron-deep disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span x-text="submitting ? 'Submitting…' : 'Submit for review'"></span>
                        </button>
                        <p class="mt-3 text-center text-xs text-ink0">You can edit and version it any time.</p>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</x-app-layout>
