<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\Prompt;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * M4 (v1.6.0) — creator Earnings tab (dashboard, ?tab=earnings).
 *
 * Read side: balance, available, lifetime credits, sales count (comp
 * grants excluded by construction — comps never touch orders/ledger),
 * payout history, recent ledger rows.
 *
 * Write side: payout request (hold) and cancel (release) — both delegate
 * to WalletService, the only money choke point. Destination plaintext
 * exists only inside the request frame; it is Crypt-encrypted by the
 * service before storage.
 */
class EarningsController extends Controller
{
    public function __construct(
        private readonly WalletService $wallet,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // K2 (v1.6.1): aggregates are cast at the boundary — SUM over an
        // EMPTY ledger returns NULL, and the controller hands the view
        // ints only (null arithmetic is the prod-500 class).
        $balance = (int) $this->wallet->balancePaisa($user);
        $available = (int) $this->wallet->availablePaisa($user);

        $lifetimeCredits = (int) WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', WalletTransaction::TYPE_SALE_CREDIT)
            ->sum('amount_paisa');

        // Sales excluding comps: comps never create order items, so counting
        // paid order lines that credit this creator IS comp-free.
        $salesCount = (int) OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('status', 'paid'))
            ->where(function ($q) use ($user) {
                $q->whereHas('product.prompt', fn ($p) => $p->where('user_id', $user->id))
                    ->orWhereHas('prompt', fn ($p) => $p->where('user_id', $user->id));
            })
            ->count();

        $payouts = Payout::query()
            ->where('user_id', $user->id)
            ->latest('requested_at')
            ->limit(20)
            ->get();

        $ledger = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(15)
            ->get();

        // K3: settings fallback in code — a missing payout_min row renders
        // the documented Rs. 500 default, never null math in the form.
        $minPayoutPaisa = (int) (app(\App\Services\SettingsService::class)->get('payout_min_paisa', '50000') ?? '50000');

        return view('dashboard.earnings', [
            'balance' => $balance,
            'available' => $available,
            'lifetimeCredits' => $lifetimeCredits,
            'salesCount' => $salesCount,
            'payouts' => $payouts,
            'ledger' => $ledger,
            'minPayoutPaisa' => $minPayoutPaisa,
        ]);
    }

    public function requestPayout(Request $request)
    {
        $validated = $request->validate([
            // Paisa comes in as rupees ×100 from the form; integer math only.
            'amount_npr' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:'.implode(',', Payout::METHODS)],
            'destination' => ['required', 'string', 'min:4', 'max:190'],
        ]);

        $amountPaisa = $validated['amount_npr'] * 100;

        try {
            $this->wallet->requestPayout(
                $request->user(),
                $amountPaisa,
                $validated['method'],
                trim($validated['destination']),
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return back()->with('success', 'Payout requested — the amount is now on hold.');
    }

    public function cancelPayout(Request $request, Payout $payout)
    {
        abort_unless($payout->user_id === $request->user()->id, 403);
        abort_unless($payout->status === Payout::STATUS_REQUESTED, 422, 'Only open requests can be cancelled.');

        $this->wallet->releasePayout($payout, $request->user(), Payout::STATUS_CANCELLED);

        return back()->with('success', 'Payout cancelled — the held amount is back in your available balance.');
    }
}
