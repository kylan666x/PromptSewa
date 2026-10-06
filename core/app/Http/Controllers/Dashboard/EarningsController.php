<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\SikkaTransaction;
use App\Services\SettingsService;
use App\Services\SikkaService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * M4 (v1.6.0) — creator Earnings tab (dashboard, ?tab=earnings).
 *
 * S1 (v1.9.0): the tab is SIKKA-ONLY. Read side: spendable + cash-out
 * eligible balances, sales count (comp grants excluded by construction —
 * comps never touch orders/ledger), the withdrawal queue and the recent
 * Sikka ledger rows. The legacy NPR wallet reads (balance, available,
 * lifetime credits, wallet ledger, wallet payouts) are gone from the
 * page — settlement still happens in NPR in the backend and is never
 * echoed here.
 *
 * Write side: Sikka withdrawal request (hold) and cancel (release) — both
 * delegate to SikkaService, the only credit choke point. The legacy NPR
 * payout endpoints are retained for backward compatibility but no longer
 * linked from any served page. Destination plaintext exists only inside
 * the request frame; it is Crypt-encrypted by the service before storage.
 */
class EarningsController extends Controller
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly SikkaService $sikka,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

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

        // S1 (v1.9.0): the Sikka half loads UNCONDITIONALLY — Sikka is the
        // permanent economy (the kill-switch is retired), so the ledger, the
        // cash-out balance and the withdrawal queue ARE the page. K2 (int)
        // casts hold at every SUM boundary.
        $sikkaSpendable = $this->sikka->spendableAvailable($user);
        $sikkaCashoutable = $this->sikka->cashoutableAvailable($user);
        $sikkaMin = max(0, (int) ($this->settings->get('sikka_cashout_min', '500') ?? '500'));

        $sikkaLedger = SikkaTransaction::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(25)
            ->get();

        // One table, two rails: only the Sikka withdrawal queue renders.
        $sikkaPayouts = collect($payouts)->where('source_currency', Payout::SOURCE_SIKKA)->values();

        return view('dashboard.earnings', [
            'salesCount' => $salesCount,
            'sikkaSpendable' => $sikkaSpendable,
            'sikkaCashoutable' => $sikkaCashoutable,
            'sikkaMin' => $sikkaMin,
            'sikkaLedger' => $sikkaLedger,
            'sikkaPayouts' => $sikkaPayouts,
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
        abort_unless(! $payout->isSikkaSource(), 422, 'That is a Sikka withdrawal — cancel it from the Sikka card.');
        abort_unless($payout->status === Payout::STATUS_REQUESTED, 422, 'Only open requests can be cancelled.');

        $this->wallet->releasePayout($payout, $request->user(), Payout::STATUS_CANCELLED);

        return back()->with('success', 'Payout cancelled — the held amount is back in your available balance.');
    }

    /**
     * S4 (v1.8.0): request a Sikka → NPR withdrawal. SikkaService owns
     * every check (positive amount, known method, >= sikka_cashout_min,
     * <= cashoutableAvailable); a violation maps to 422.
     */
    public function requestSikkaPayout(Request $request)
    {
        $validated = $request->validate([
            'amount_sikka' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:'.implode(',', Payout::METHODS)],
            'destination' => ['required', 'string', 'min:4', 'max:190'],
        ]);

        try {
            $this->sikka->requestPayout(
                $request->user(),
                (int) $validated['amount_sikka'],
                $validated['method'],
                trim($validated['destination']),
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return back()->with('success', 'Withdrawal requested — the credits are now on hold until an admin settles it.');
    }

    public function cancelSikkaPayout(Request $request, Payout $payout)
    {
        abort_unless($payout->user_id === $request->user()->id, 403);
        abort_unless($payout->isSikkaSource(), 422, 'That is not a Sikka withdrawal.');
        abort_unless($payout->status === Payout::STATUS_REQUESTED, 422, 'Only open requests can be cancelled.');

        $this->sikka->releasePayout($payout, $request->user(), Payout::STATUS_CANCELLED);

        return back()->with('success', 'Withdrawal cancelled — the held credits are back in your Sikka balance.');
    }
}
