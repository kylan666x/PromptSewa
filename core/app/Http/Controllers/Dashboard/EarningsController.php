<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\SikkaTransaction;
use App\Models\WalletTransaction;
use App\Services\SettingsService;
use App\Services\SikkaService;
use App\Services\WalletService;
use Illuminate\Http\Request;
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
        private readonly SikkaService $sikka,
        private readonly SettingsService $settings,
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
        $minPayoutPaisa = (int) ($this->settings->get('payout_min_paisa', '50000') ?? '50000');

        // S4/S6 (v1.8.0): the Sikka half of the earnings tab — read-only
        // while the kill-switch is off (zero Sikka markup anywhere).
        $sikkaEnabled = $this->settings->isOn('sikka_enabled');
        $sikkaSpendable = $sikkaEnabled ? $this->sikka->spendableAvailable($user) : 0;
        $sikkaCashoutable = $sikkaEnabled ? $this->sikka->cashoutableAvailable($user) : 0;
        $sikkaMin = max(0, (int) ($this->settings->get('sikka_cashout_min', '500') ?? '500'));
        $sikkaRate = $this->sikka->cashoutRatePaisaPerToken();

        $sikkaLedger = $sikkaEnabled
            ? SikkaTransaction::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->limit(25)
                ->get()
            : collect();

        // Two queues, one table: the wallet history stays NPR-only; Sikka
        // withdrawals render in the Sikka card with their own cancel door.
        $walletPayouts = collect($payouts)
            ->reject(fn (Payout $payout) => $payout->isSikkaSource())
            ->values();
        $sikkaPayouts = collect($payouts)->where('source_currency', Payout::SOURCE_SIKKA)->values();

        return view('dashboard.earnings', [
            'balance' => $balance,
            'available' => $available,
            'lifetimeCredits' => $lifetimeCredits,
            'salesCount' => $salesCount,
            'payouts' => $walletPayouts,
            'walletPayouts' => $walletPayouts,
            'ledger' => $ledger,
            'minPayoutPaisa' => $minPayoutPaisa,
            'sikkaEnabled' => $sikkaEnabled,
            'sikkaSpendable' => $sikkaSpendable,
            'sikkaCashoutable' => $sikkaCashoutable,
            'sikkaMin' => $sikkaMin,
            'sikkaRate' => $sikkaRate,
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
