<x-admin-layout title="Manual payment methods">
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\ManualPaymentMethod> $methods */
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-ink">Manual payment methods</h2>
            <p class="text-sm text-ink/60">Bank / wallet methods buyers see at checkout, each with its own instructions and optional QR code.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mt-4 rounded-xl border border-emerald-700/20 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('success') }}</div>
    @endif

    {{-- Existing methods --}}
    <div class="mt-6 space-y-4">
        @forelse ($methods as $method)
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-4">
                        @if ($method->qr_path)
                            <img src="{{ Storage::disk('public')->url($method->qr_path) }}" alt="QR code for {{ $method->name }}" class="size-20 rounded-xl border border-ink/10 object-contain">
                        @endif
                        <div>
                            <p class="flex items-center gap-2 font-semibold text-ink">
                                <span class="rounded-md bg-paper-deep px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wide text-ink/60" title="Method kind">{{ $method->kind }}</span>
                                {{ $method->name }}
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $method->active ? 'bg-emerald-100 text-emerald-800' : 'bg-ink/10 text-ink/50' }}">
                                    {{ $method->active ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="font-mono text-xs text-ink/40">pos {{ $method->position }}</span>
                            </p>
                            @if ($method->instructions)
                                <p class="mt-1 max-w-xl whitespace-pre-line text-xs text-ink/60">{{ $method->instructions }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- R2 (v1.7.7 raid): management is admin-only; staff read the
                     list but get no forms their POSTs would 403. --}}
                @if (auth()->user()->isAdmin())
                <details class="mt-3">
                    <summary class="cursor-pointer font-mono text-xs font-semibold text-saffron-deep">Edit method</summary>
                    <form method="POST" action="{{ route('admin.manual-methods.update', $method) }}" enctype="multipart/form-data" class="mt-3 grid gap-3 sm:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-xs font-medium text-ink/60">Name</label>
                            <input type="text" name="name" value="{{ $method->name }}" required maxlength="100"
                                   class="mt-1 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink outline-none focus:border-saffron-deep">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-ink/60">Position</label>
                            <input type="number" name="position" value="{{ $method->position }}" min="0" max="9999"
                                   class="mt-1 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink outline-none focus:border-saffron-deep">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-ink/60">Kind</label>
                            <select name="kind" class="mt-1 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink outline-none focus:border-saffron-deep">
                                @foreach (\App\Models\ManualPaymentMethod::KINDS as $kind)
                                    <option value="{{ $kind }}" @selected($method->kind === $kind)>{{ ucfirst($kind) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-ink/60">Instructions shown at checkout</label>
                            <textarea name="instructions" rows="3"
                                      class="mt-1 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink outline-none focus:border-saffron-deep">{{ $method->instructions }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-ink/60">Replace QR (PNG/WebP, transparency preserved, ≤4 MB)</label>
                            <input type="file" name="qr" accept=".png,.webp,.jpg,.jpeg"
                                   class="mt-1 block w-full text-xs text-ink/70">
                        </div>
                        <div class="flex items-end gap-4">
                            <label class="flex items-center gap-2 text-sm text-ink/80">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" name="active" value="1" @checked($method->active)
                                       class="size-4 rounded border-ink/20 text-saffron-deep focus:ring-saffron/40"> Active
                            </label>
                            @if ($method->qr_path)
                                <label class="flex items-center gap-2 text-sm text-ink/80">
                                    <input type="checkbox" name="remove_qr" value="1"
                                           class="size-4 rounded border-ink/20 text-rose-600 focus:ring-rose-300"> Remove QR
                                </label>
                            @endif
                        </div>
                        <div class="sm:col-span-2 flex items-center gap-3">
                            <button class="rounded-xl bg-saffron px-4 py-2 text-sm font-semibold text-ink transition hover:bg-saffron-deep">Save changes</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('admin.manual-methods.destroy', $method) }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button class="font-mono text-xs font-semibold text-rose-700 transition hover:text-rose-900">
                            {{ $method->isUsedByOrders() ? 'Deactivate (referenced by orders)' : 'Delete method' }}
                        </button>
                    </form>
                </details>
                @endif
            </div>
        @empty
            <p class="rounded-2xl border border-ink/10 bg-white p-6 text-sm text-ink/60">No manual methods yet — create the first one below.</p>
        @endforelse
    </div>

    {{-- Create form --}}
    @if (auth()->user()->isAdmin())
    <div class="mt-8 rounded-2xl border border-saffron-deep/30 bg-white p-6 shadow-sm">
        <h3 class="font-semibold text-ink">Add a method</h3>
        <form method="POST" action="{{ route('admin.manual-methods.store') }}" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-2">
            @csrf
            <div>
                <label class="block text-xs font-medium text-ink/60">Name <span class="text-saffron-deep">*</span></label>
                <input type="text" name="name" required maxlength="100" placeholder="e.g. eSewa — 98XXXXXXXX"
                       class="mt-1 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink outline-none focus:border-saffron-deep">
            </div>
            <div>
                <label class="block text-xs font-medium text-ink/60">Kind</label>
                <select name="kind" class="mt-1 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink outline-none focus:border-saffron-deep">
                    @foreach (\App\Models\ManualPaymentMethod::KINDS as $kind)
                        <option value="{{ $kind }}" @selected($kind === \App\Models\ManualPaymentMethod::KIND_OTHER)>{{ ucfirst($kind) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-ink/60">Position</label>
                <input type="number" name="position" value="0" min="0" max="9999"
                       class="mt-1 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink outline-none focus:border-saffron-deep">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-ink/60">Instructions shown at checkout</label>
                <textarea name="instructions" rows="3"
                          class="mt-1 block w-full rounded-xl border border-ink/10 bg-paper-deep px-3 py-2 text-sm text-ink outline-none focus:border-saffron-deep"
                          placeholder="e.g. Scan the QR, send the amount, then submit the transaction ID below."></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-ink/60">QR code (PNG/WebP, transparency preserved, ≤4 MB)</label>
                <input type="file" name="qr" accept=".png,.webp,.jpg,.jpeg"
                       class="mt-1 block w-full text-xs text-ink/70">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm text-ink/80">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" checked
                           class="size-4 rounded border-ink/20 text-saffron-deep focus:ring-saffron/40"> Active
                </label>
            </div>
            <div class="sm:col-span-2">
                <button class="rounded-xl bg-saffron px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-saffron-deep">Create method</button>
            </div>
        </form>
    </div>
    @else
        <p class="mt-8 rounded-2xl border border-ink/10 bg-white p-6 text-sm text-ink/60">
            Manual payment methods are admin-managed. Ask an admin to add or change a method.
        </p>
    @endif
</x-admin-layout>
