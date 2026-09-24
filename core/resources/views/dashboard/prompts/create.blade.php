@php
    /** @var array<string, array<string, mixed>> $typeContexts */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Category> $categories */
    $oldType = old('type', \App\Models\Prompt::TYPE_TEXT);
    $variableHint = 'Wrap reusable inputs in {{double braces}} — buyers get fill-in fields automatically.';
@endphp

<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6" x-data='promptForm({
        contexts: {{ Js::from($typeContexts) }},
        categories: {{ Js::from($categories->values()) }},
        initialType: {{ Js::from($oldType) }},
        initialCategoryId: {{ Js::from((string) old("category_id", "")) }},
        initialTools: {{ Js::from(array_values((array) old("recommended_tools", []))) }}
    })'>
        <header>
            <p class="text-xs font-semibold uppercase tracking-widest text-saffron-deep/90">Creator workspace</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-ink">Add a new prompt</h1>
            <p class="mt-2 max-w-2xl text-sm text-ink/60">
                Pick the kind of prompt you are selling first — the form adapts to it with the right
                placeholders, structure guidance, and suggested tools. Every submission goes through
                a quick review before it appears in the library.
            </p>
        </header>

        <form method="POST" action="{{ route('dashboard.prompts.store') }}" class="mt-8" @submit="onSubmit()">
            @csrf
            <input type="hidden" name="type" :value="type">

            {{-- Step 1: prompt type cards (God of Prompt taxonomy) --}}
            <section aria-label="Prompt type">
                <div class="flex items-center gap-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-saffron/25 text-xs font-bold text-saffron-deep">1</span>
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-ink/60">What are you selling?</h2>
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
                                        <option value="" disabled>Choose a category…</option>
                                        <template x-for="option in categoryOptions" :key="option.id">
                                            <option :value="option.id" x-text="option.name" :selected="String(option.id) === categoryId"></option>
                                        </template>
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
                                    >{{ old('body') }}</textarea>
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

                            <div class="grid gap-5 sm:grid-cols-2">                                <div>
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
