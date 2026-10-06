<x-admin-layout title="Brand & contact">
    @php
        /** @var string $siteName */
        /** @var string $siteTagline */
        /** @var string $contactEmail */
        /** @var string $supportEmail */
        /** @var string $logoPath */
        /** @var string $faviconPath */
        /** @var string $sikkaIconPath */
        /** @var string $sikkaIconMonoPath */
    @endphp

    {{-- R2 (v1.7.7 raid): read stays staff (audit ladder); the save form is
         admin-only because the controller refuses moderators. --}}
    @if (auth()->user()->isAdmin())
    <form method="POST" action="{{ route('admin.brand.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="text-sm font-semibold text-ink">Identity</h3>
            <p class="mt-1 text-xs text-ink/60">Shown in the navbar, footer and browser tab.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="site_name" class="block text-xs font-medium text-ink/60">Site name <span class="text-saffron-deep">*</span></label>
                    <input id="site_name" type="text" name="site_name" required maxlength="60" value="{{ $siteName }}"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                    @error('site_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="site_tagline" class="block text-xs font-medium text-ink/60">Tagline</label>
                    <input id="site_tagline" type="text" name="site_tagline" maxlength="200" value="{{ $siteTagline }}"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                    @error('site_tagline') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="text-sm font-semibold text-ink">Logos</h3>
            <p class="mt-1 text-xs text-ink/60">Transparent PNG or WebP — transparency is preserved (never flattened to black). Use the logo for landscape wordmarks and the mark for square icons.</p>

            <div class="mt-4 grid gap-6 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-ink/60">Logo <span class="text-ink/60">(landscape, e.g. 370×80)</span></label>
                    <div class="mt-1.5 flex items-center gap-3">
                        @if ($logoPath !== '')
                            <img src="{{ asset('storage/'.$logoPath) }}" alt="Current logo" class="h-10 w-auto max-w-[160px] rounded-lg border border-ink/10 bg-white object-contain p-1">
                        @else
                            <span class="flex size-14 items-center justify-center rounded-xl border border-dashed border-ink/20 text-[10px] text-ink/60">none</span>
                        @endif
                        <input type="file" name="logo" accept="image/png,image/webp"
                               class="block w-full text-sm text-ink/60 file:mr-3 file:rounded-lg file:border-0 file:bg-paper-deep file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink/90">
                    </div>
                    @error('logo') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-ink/60">Desktop navbar wordmark. @if ($logoPath !== '') Uploaded — upload a new file to replace it. @else Falls back to the {{ mb_substr($siteName, 0, 2) }} badge. @endif</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink/60">Mark / favicon <span class="text-ink/60">(square 512×512)</span></label>
                    <div class="mt-1.5 flex items-center gap-3">
                        @if ($markPath !== '')
                            <img src="{{ asset('storage/'.$markPath) }}" alt="Current mark" class="size-14 rounded-xl border border-ink/10 bg-white object-contain p-1">
                        @else
                            <span class="flex size-14 items-center justify-center rounded-xl border border-dashed border-ink/20 text-[10px] text-ink/60">none</span>
                        @endif
                        <input type="file" name="mark" accept="image/png,image/webp"
                               class="block w-full text-sm text-ink/60 file:mr-3 file:rounded-lg file:border-0 file:bg-paper-deep file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink/90">
                    </div>
                    @error('mark') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-ink/60">Mobile navbar + favicon fallback. @if ($markPath !== '') Uploaded — upload a new file to replace it. @else Falls back to the saffron initial badge. @endif</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink/60">Favicon</label>
                    <div class="mt-1.5 flex items-center gap-3">
                        @if ($faviconPath !== '')
                            <img src="{{ asset('storage/'.$faviconPath) }}" alt="Current favicon" class="size-14 rounded-xl border border-ink/10 bg-white object-contain p-1">
                        @else
                            <span class="flex size-14 items-center justify-center rounded-xl border border-dashed border-ink/20 text-[10px] text-ink/60">default</span>
                        @endif
                        <input type="file" name="favicon" accept=".ico,.png,.svg,image/x-icon,image/png,image/svg+xml"
                               class="block w-full text-sm text-ink/60 file:mr-3 file:rounded-lg file:border-0 file:bg-paper-deep file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink/90">
                    </div>
                    @error('favicon') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-ink/60">@if ($faviconPath !== '') Uploaded — upload a new file to replace it. @else Falls back to /favicon.ico. @endif</p>
                </div>
            </div>
        </div>

        {{-- S9 (v1.8.0): the Sikka unit mark is an admin-managed brand asset
             — uploaded here, never shipped inside the release zip. Color for
             the app (>= 20px, reads on both worlds), mono for mail headers
             and and plain-text fallbacks. With no mark uploaded the UI falls
             back to an honest bordered “Sikka” chip, never a broken image. --}}
        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="text-sm font-semibold text-ink">Sikka unit mark</h3>
            <p class="mt-1 text-xs text-ink/60">Brand asset — PNG or WebP only, transparency preserved, downscaled to 512px; JPEG is refused. The marks appear wherever Sikka amounts render — the economy ships ON and its kill-switch is retired. Never bundled in a release zip: upload it here on each install.</p>

            <div class="mt-4 grid gap-6 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-ink/60">Icon <span class="text-ink/60">(color)</span></label>
                    <div class="mt-1.5 flex items-center gap-3">
                        @foreach (['paper' => 'border-ink/10 bg-paper', 'ink' => 'border-inkline bg-ink'] as $ground => $groundClass)
                            <span data-sikka-preview="{{ $ground }}" title="On the {{ $ground }} ground"
                                  class="flex size-14 shrink-0 items-center justify-center rounded-xl border p-1.5 {{ $groundClass }}">
                                @if ($sikkaIconPath !== '')
                                    <img src="{{ asset('storage/'.$sikkaIconPath) }}" alt="Sikka icon on the {{ $ground }} ground" class="size-11 object-contain">
                                @else
                                    <span class="text-[10px] {{ $ground === 'ink' ? 'text-paper/50' : 'text-ink/60' }}">none</span>
                                @endif
                            </span>
                        @endforeach
                        <input type="file" name="sikka_icon" accept="image/png,image/webp"
                               class="block w-full text-sm text-ink/60 file:mr-3 file:rounded-lg file:border-0 file:bg-paper-deep file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink/90">
                    </div>
                    @error('sikka_icon') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-ink/60">App surfaces. @if ($sikkaIconPath !== '') Uploaded — upload a new file to replace it. @else Falls back to the bordered “Sikka” chip. @endif</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink/60">Icon <span class="text-ink/60">(mono)</span></label>
                    <div class="mt-1.5 flex items-center gap-3">
                        @foreach (['paper' => 'border-ink/10 bg-paper', 'ink' => 'border-inkline bg-ink'] as $ground => $groundClass)
                            <span data-sikka-preview="mono-{{ $ground }}" title="On the {{ $ground }} ground"
                                  class="flex size-14 shrink-0 items-center justify-center rounded-xl border p-1.5 {{ $groundClass }}">
                                @if ($sikkaIconMonoPath !== '')
                                    <img src="{{ asset('storage/'.$sikkaIconMonoPath) }}" alt="Sikka mono icon on the {{ $ground }} ground" class="size-11 object-contain">
                                @else
                                    <span class="text-[10px] {{ $ground === 'ink' ? 'text-paper/50' : 'text-ink/60' }}">none</span>
                                @endif
                            </span>
                        @endforeach
                        <input type="file" name="sikka_icon_mono" accept="image/png,image/webp"
                               class="block w-full text-sm text-ink/60 file:mr-3 file:rounded-lg file:border-0 file:bg-paper-deep file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink/90">
                    </div>
                    @error('sikka_icon_mono') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-ink/60">Mail headers + ink grounds. @if ($sikkaIconMonoPath !== '') Uploaded — upload a new file to replace it. @else With none, the color icon covers ink too. @endif</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white p-6">
            <h3 class="text-sm font-semibold text-ink">Contact emails</h3>
            <p class="mt-1 text-xs text-ink/60">Public — shown in the footer and on report links. Leave empty to hide.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="contact_email" class="block text-xs font-medium text-ink/60">General contact</label>
                    <input id="contact_email" type="email" name="contact_email" maxlength="190" value="{{ $contactEmail }}" placeholder="hello@yoursite.com"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                    @error('contact_email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="support_email" class="block text-xs font-medium text-ink/60">Support</label>
                    <input id="support_email" type="email" name="support_email" maxlength="190" value="{{ $supportEmail }}" placeholder="support@yoursite.com"
                           class="mt-1.5 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink outline-none focus:border-saffron-deep">
                    @error('support_email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button class="rounded-xl bg-saffron px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-saffron-deep">Save brand settings</button>
            <span class="text-xs text-ink/60">Changes apply immediately.</span>
        </div>
    </form>
    @else
        <p class="mt-6 rounded-2xl border border-ink/10 bg-white p-6 text-sm text-ink/60">
            Brand settings are admin-managed. Ask an admin to change the site identity.
        </p>
    @endif
</x-admin-layout>
