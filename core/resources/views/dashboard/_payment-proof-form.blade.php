@php
    /** @var \App\Models\Order $order */
    $proofExists = $order->manual_proof_path !== null;
@endphp

{{-- C3 (v1.4.4): buyer TXN + screenshot submission on a pending manual order.
     Re-submission replaces the current proof (old file deleted server-side).
     Submission never grants entitlement — admin approval does. --}}
<div class="mt-6 rounded-2xl border border-ink/10 bg-white p-6 shadow-sm">
    <h3 class="flex items-center gap-2 text-sm font-semibold text-ink">
        <span class="rounded-md bg-sky-100 px-2 py-0.5 font-mono text-xs font-bold text-sky-800">Proof</span>
        {{ $proofExists ? 'Update your payment proof' : 'Submit your payment proof' }}
    </h3>

    @if ($proofExists)
        <p class="mt-2 font-mono text-xs text-ink/60">
            TXN: {{ $order->manual_txn_id }} · submitted {{ $order->manual_submitted_at?->format('M j, Y H:i') }}
        </p>
        <a href="{{ route('orders.proof.show', $order) }}" target="_blank" class="mt-2 inline-block">
            <img src="{{ route('orders.proof.show', $order) }}" alt="Current payment proof" class="max-h-40 rounded-xl border border-ink/10 object-contain">
        </a>
    @endif

    <form method="POST" action="{{ route('orders.proof.store', $order) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
        @csrf
        <div>
            <label for="txn_id" class="block text-xs font-medium text-ink/70">Transaction ID <span class="text-saffron-deep">*</span></label>
            <input id="txn_id" type="text" name="txn_id" required maxlength="100" value="{{ $order->manual_txn_id }}"
                   placeholder="e.g. 9F3K2L8Q"
                   class="mt-1.5 block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
            @error('txn_id') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="proof" class="block text-xs font-medium text-ink/70">Payment screenshot <span class="text-saffron-deep">*</span></label>
            <input id="proof" type="file" name="proof" required accept=".png,.webp,.jpg,.jpeg"
                   class="mt-1.5 block w-full cursor-pointer rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink/80 file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-saffron file:px-4 file:py-2 file:text-sm file:font-semibold file:text-ink hover:file:bg-saffron-deep">
            <p class="mt-1 text-xs text-ink/50">JPG/PNG/WebP, up to 8 MB.{{ $proofExists ? ' Uploading again replaces the current screenshot.' : '' }}</p>
            @error('proof') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="note" class="block text-xs font-medium text-ink/70">Note (optional)</label>
            <input id="note" type="text" name="note" maxlength="500"
                   placeholder="Anything the admin should know"
                   class="mt-1.5 block w-full rounded-xl border border-ink/15 bg-white px-3.5 py-2.5 text-sm text-ink placeholder-creak shadow-sm outline-none transition focus:border-saffron-deep focus:ring-2 focus:ring-saffron/40">
            @error('note') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror
        </div>
        <button class="w-full rounded-full border border-sky-700/30 bg-sky-100 px-4 py-3 text-sm font-bold text-sky-900 transition hover:bg-sky-200">
            {{ $proofExists ? 'Replace proof' : 'Submit proof' }}
        </button>
        <p class="text-center text-xs text-ink/50">Approval is what unlocks your prompts — proof submission alone does nothing until an admin approves.</p>
    </form>
</div>
