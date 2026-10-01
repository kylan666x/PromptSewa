<x-app-layout>
    <x-seo :title="'Edit profile'" robots="noindex, follow"/>
    @php /** @var \App\Models\User $user */ @endphp

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <header>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-saffron-deep">Your account</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-ink">Edit profile</h1>
            <p class="mt-1 text-sm text-ink/60">This is what buyers see at <a href="{{ route('creators.show', $user) }}" class="text-saffron-deep hover:underline decoration-saffron decoration-2 underline-offset-4">{{ url('/creators/'.$user->creatorRouteKey()) }}</a>.</p>
        </header>

        @if (session('success'))
            <div class="mt-6 rounded-xl border border-emerald-500/30 bg-emerald-100 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('dashboard.profile.update') }}" enctype="multipart/form-data" class="mt-8 space-y-6">
            @csrf
            @method('PUT')

            {{-- Identity --}}
            <section class="rounded-2xl border border-ink/10 bg-white p-6">
                <h2 class="text-sm font-semibold text-ink">Identity</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <x-form.label name="name" label="Display name" :required="true"/>
                        <div class="mt-1.5">
                            <x-form.input name="name" :value="old('name', $user->name)" :required="true" maxlength="60"/>
                        </div>
                        @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-form.label name="username" label="Username" hint="Your public handle — letters, numbers, dashes. Up to 30 characters."/>
                        <div class="mt-1.5">
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 font-mono text-sm text-ink/40">@</span>
                                <input type="text" id="username" name="username" value="{{ old('username', $user->username) }}" maxlength="30"
                                       class="block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 pl-8 text-sm text-ink placeholder-creak outline-none transition focus:border-saffron-deep"
                                       placeholder="promptwizard"
                                       autocomplete="off" spellcheck="false">
                            </div>
                        </div>
                        @error('username') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-form.label name="bio" label="Bio" hint="Up to 400 characters — who are you, what do you build?"/>
                        <div class="mt-1.5">
                            <textarea id="bio" name="bio" rows="4" maxlength="400"
                                      class="block w-full rounded-xl border border-ink/10 bg-paper-deep px-3.5 py-2.5 text-sm text-ink placeholder-creak outline-none transition focus:border-saffron-deep"
                                      placeholder="Prompt engineer, designer, founder…">{{ old('bio', $user->bio) }}</textarea>
                        </div>
                        @error('bio') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- Images --}}
            <section class="rounded-2xl border border-ink/10 bg-white p-6">
                <h2 class="text-sm font-semibold text-ink">Photos</h2>
                <p class="mt-1 text-xs text-ink/50">JPG/PNG/WebP. Files are compressed automatically — no need to shrink them first.</p>

                <div class="mt-4 grid gap-6 sm:grid-cols-2">
                    <div>
                        <span class="block text-xs font-semibold text-ink/70">Avatar</span>
                        <div class="mt-2 flex items-center gap-3">
                            <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-saffron text-xl font-bold text-ink">
                                @if ($user->avatar_path)
                                    <img src="{{ Storage::url($user->avatar_path) }}" alt="" class="size-full object-cover">
                                @else
                                    {{ mb_substr($user->name, 0, 1) }}
                                @endif
                            </span>
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
                                   class="block w-full cursor-pointer rounded-xl border border-ink/10 bg-paper-deep px-2.5 py-1.5 text-xs text-ink/80 file:mr-2 file:cursor-pointer file:rounded-lg file:border-0 file:bg-paper-deep file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-ink hover:file:bg-ink/10">
                        </div>
                        @if ($user->avatar_path)
                            <label class="mt-2 flex items-center gap-2 text-xs text-ink/60">
                                <input type="checkbox" name="remove_avatar" value="1" class="size-3.5 rounded border-ink/20 accent-saffron-deep"> Remove avatar
                            </label>
                        @endif
                        @error('avatar') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <span class="block text-xs font-semibold text-ink/70">Cover banner</span>
                        <div class="mt-2 overflow-hidden rounded-xl border border-ink/10">
                            @if ($user->banner_path)
                                <img src="{{ Storage::url($user->banner_path) }}" alt="" class="h-20 w-full object-cover">
                            @else
                                <div class="flex h-20 items-center justify-center bg-gradient-to-br from-saffron/60 to-amber-200/40 font-mono text-[11px] text-ink/40">no banner yet</div>
                            @endif
                        </div>
                        <input type="file" name="banner" accept="image/jpeg,image/png,image/webp"
                               class="mt-2 block w-full cursor-pointer rounded-xl border border-ink/10 bg-paper-deep px-2.5 py-1.5 text-xs text-ink/80 file:mr-2 file:cursor-pointer file:rounded-lg file:border-0 file:bg-paper-deep file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-ink hover:file:bg-ink/10">
                        @if ($user->banner_path)
                            <label class="mt-2 flex items-center gap-2 text-xs text-ink/60">
                                <input type="checkbox" name="remove_banner" value="1" class="size-3.5 rounded border-ink/20 accent-saffron-deep"> Remove banner
                            </label>
                        @endif
                        @error('banner') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- G3 (v1.7.0): profile frame picker — active frames, none = clear --}}
            <section class="rounded-2xl border border-ink/10 bg-white p-6">
                <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Avatar frame</h2>
                <p class="mt-1 text-xs text-ink/50">Optional decorative ring shown on your profile, the navbar and the feed.</p>
                <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-5">
                    <label class="cursor-pointer">
                        <input type="radio" name="active_frame_id" value="" @checked(! $user->active_frame_id) class="peer sr-only">
                        <span class="flex flex-col items-center gap-1 rounded-xl border border-ink/10 p-3 text-xs text-ink/60 transition peer-checked:border-saffron-deep peer-checked:bg-saffron/10 peer-checked:text-ink">
                            <span class="flex size-10 items-center justify-center rounded-full bg-paper-deep text-ink/40">—</span>
                            None
                        </span>
                    </label>
                    @foreach ($frames as $frame)
                        <label class="cursor-pointer">
                            <input type="radio" name="active_frame_id" value="{{ $frame->id }}" @checked($user->active_frame_id === $frame->id) class="peer sr-only">
                            <span class="flex flex-col items-center gap-1 rounded-xl border border-ink/10 p-3 text-xs text-ink/60 transition peer-checked:border-saffron-deep peer-checked:bg-saffron/10 peer-checked:text-ink">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($frame->image_path) }}" alt="" class="size-10">
                                {{ $frame->name }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-full bg-saffron px-6 py-2.5 text-sm font-bold uppercase tracking-wide text-ink shadow-[0_4px_0_0_#a16207] transition-all hover:-translate-y-0.5 hover:shadow-[0_6px_0_0_#a16207] active:translate-y-0.5 active:shadow-none">
                    Save changes
                </button>
                <a href="{{ route('creators.show', $user) }}" class="text-sm font-medium text-ink/60 transition hover:text-ink">View public profile &rarr;</a>
            </div>
        </form>

        {{-- T12 (v1.5.0): the "You" menu — the burger drawer is retired, so
             Packs/About/Dashboard/Admin/Logout live here. The Admin row is
             gated server-side (never CSS-hidden). The dock's You tab lands here. --}}
        <section aria-label="Account menu" class="mt-10 rounded-2xl border border-ink/10 bg-white p-5">
            <h2 class="font-mono text-xs font-semibold uppercase tracking-widest text-ink/50">Account</h2>
            <div class="mt-3 grid gap-1.5 sm:grid-cols-2">
                {{-- P4 (v1.7.1): the You menu carries BOTH identities — view
                     (public) and edit (this form). --}}
                <a href="{{ route('creators.show', $user) }}" class="rounded-xl border border-ink/10 bg-paper-deep px-4 py-2.5 text-sm font-medium text-ink/80 transition hover:border-saffron-deep hover:text-ink">View profile</a>
                <a href="{{ route('packs.index') }}" class="rounded-xl border border-ink/10 bg-paper-deep px-4 py-2.5 text-sm font-medium text-ink/80 transition hover:border-saffron-deep hover:text-ink">Packs</a>
                <a href="{{ route('pages.about') }}" class="rounded-xl border border-ink/10 bg-paper-deep px-4 py-2.5 text-sm font-medium text-ink/80 transition hover:border-saffron-deep hover:text-ink">About PromptSewa</a>
                <a href="{{ route('dashboard') }}" class="rounded-xl border border-ink/10 bg-paper-deep px-4 py-2.5 text-sm font-medium text-ink/80 transition hover:border-saffron-deep hover:text-ink">Dashboard</a>
                @if ($user->isModerator())
                    <a href="{{ route('admin.dashboard') }}" class="rounded-xl border border-saffron-deep/40 bg-saffron/15 px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-saffron/30">Admin panel</a>
                @endif
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="w-full rounded-xl border border-ink/15 px-4 py-2.5 text-sm font-medium text-ink/80 transition hover:border-rose-500 hover:text-rose-700">Log out</button>
            </form>
        </section>
    </div>
</x-app-layout>
