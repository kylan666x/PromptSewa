<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * C3 (v1.4.4): buyer-side manual payment proof.
 *
 * store() — submit or REPLACE the TXN id + screenshot on the buyer's own
 * PENDING manual order. Re-submission replaces the file and deletes the
 * orphaned old one from disk (files are not financial records; the order
 * row keeps a single current proof). Submission NEVER grants entitlement
 * — approval remains the only grant path (money invariant).
 *
 * show() — private proof serving. Proof screenshots live on the private
 * "proofs" disk and are handed to owner or staff through this controller,
 * never through a raw public storage URL.
 */
class PaymentProofController extends Controller
{
    public function __construct(
        private readonly ImageUploadService $images,
    ) {}

    public function store(Request $request, Order $order)
    {
        abort_unless($request->user()?->id === $order->buyer_id, 403);
        abort_unless($order->isPending(), 400, 'This order is no longer payable.');
        abort_unless($order->payment_method === 'manual', 400, 'Proof can only be submitted on a manual order.');

        $validated = $request->validate([
            'txn_id' => ['required', 'string', 'max:100'],
            'proof' => ['required', 'image', 'max:8192', 'mimes:png,webp,jpeg,jpg'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $newPath = null;

        try {
            $newPath = $this->images->store($request->file('proof'), 'proof');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['proof' => $e->getMessage()]);
        }

        // Replace: delete the orphaned old file after the new one is safely
        // stored (order row always points at exactly one live file).
        $oldPath = $order->manual_proof_path;

        $order->fill([
            'manual_txn_id' => trim($validated['txn_id']),
            'manual_proof_path' => $newPath,
            'manual_submitted_at' => now(),
            // F5 (v1.7.8): the note lives in its OWN column (manual_note).
            // Before this, a non-empty note overwrote payment_reference and
            // the admin desk lost the Method · Reference line. The reference
            // is the order's historical record — never rewritten here.
            // Absent/empty note preserves the existing one.
            'manual_note' => trim((string) ($validated['note'] ?? '')) !== ''
                ? mb_substr(trim((string) $validated['note']), 0, 500)
                : $order->manual_note,
        ])->save();

        if ($oldPath !== null && $oldPath !== $newPath) {
            $this->images->delete($oldPath);
        }

        return back()->with('success', 'Payment proof received — an admin will verify it shortly.');
    }

    /**
     * Stream the proof image to the buyer (own order) or staff. Response is
     * inline so staff can preview it directly in the admin order desk.
     */
    public function show(Request $request, Order $order): BinaryFileResponse
    {
        abort_unless($order->manual_proof_path !== null, 404);

        $isOwner = $request->user()?->id === $order->buyer_id;
        abort_unless($isOwner || $request->user()?->isModerator(), 403);

        $disk = Storage::disk('proofs');
        abort_unless($disk->exists($order->manual_proof_path), 404);

        $full = $disk->path($order->manual_proof_path);

        return response()->file($full, ['Content-Disposition' => 'inline']);
    }
}
