<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payout;
use App\Models\WalletTransaction;
use App\Services\SettingsService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * M5 (v1.6.0) — Admin → Finance desk.
 *
 * Totals are computed FROM the ledger (creator credits) and orders (gross),
 * with platform net derived — never stored, never a float. Pre-ledger paid
 * orders (before the ledger_started_at cutover) are listed READ-ONLY with
 * an honest banner: visible, truthful, never backfilled into a balance.
 */
class FinanceController extends Controller
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can open the Finance desk.');

        $ledgerStartedAt = $this->ledgerStartedAt();

        // K2 (v1.6.1): (int) at the boundary — SUM over zero rows is NULL
        // and the desk must render Rs. 0, not 500.
        $grossPaisa = (int) Order::query()
            ->where('status', Order::STATUS_PAID)
            ->where('paid_at', '>=', $ledgerStartedAt)
            ->sum('total_paisa');

        $creatorCreditsPaisa = (int) WalletTransaction::query()
            ->where('type', WalletTransaction::TYPE_SALE_CREDIT)
            ->sum('amount_paisa');

        // Derived, never stored (M2).
        $platformNetPaisa = $grossPaisa - $creatorCreditsPaisa;

        $preLedgerOrders = Order::query()
            ->where('status', Order::STATUS_PAID)
            ->where('paid_at', '<', $ledgerStartedAt)
            ->with('buyer')
            ->orderBy('paid_at')
            ->get();

        $payoutQueue = Payout::query()
            ->whereIn('status', [Payout::STATUS_REQUESTED, Payout::STATUS_APPROVED])
            ->with('user')
            ->orderBy('requested_at')
            ->get();

        // Ledger browser: filter user / type / date window.
        $ledger = WalletTransaction::query()
            ->with('user')
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', (int) $request->query('user')))
            ->when($request->filled('type') && in_array($request->query('type'), WalletTransaction::TYPES, true),
                fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->query('to').' 23:59:59'))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.finance', [
            'grossPaisa' => $grossPaisa,
            'creatorCreditsPaisa' => $creatorCreditsPaisa,
            'platformNetPaisa' => $platformNetPaisa,
            'ledgerStartedAt' => $ledgerStartedAt,
            'preLedgerOrders' => $preLedgerOrders,
            'preLedgerTotalPaisa' => (int) $preLedgerOrders->sum('total_paisa'), // collection sum; empty → 0
            'payoutQueue' => $payoutQueue,
            'ledger' => $ledger,
            'filters' => $request->only(['user', 'type', 'from', 'to']),
        ]);
    }

    public function approvePayout(Request $request, Payout $payout)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        // Approve inserts NOTHING financial — the hold was the debit.
        $payout->transitionTo(Payout::STATUS_APPROVED);
        $payout->fill([
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'note' => $request->input('note'),
        ])->save();

        return back()->with('success', "Payout #{$payout->id} approved — settle after transferring.");
    }

    public function settlePayout(Request $request, Payout $payout)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($payout->status === Payout::STATUS_APPROVED, 422, 'Only approved payouts can be settled.');

        // Settled inserts NOTHING financial — the hold was the debit; the
        // payout row carries the settlement state. F6 (v1.7.8): the
        // creator's bell row commits with the decision.
        DB::transaction(function () use ($payout, $request) {
            $payout->transitionTo(Payout::STATUS_SETTLED);
            $payout->fill([
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ])->save();

            if ($payout->user !== null) {
                Notification::emit(
                    $payout->user,
                    Notification::TYPE_PAYOUT_SETTLED,
                    'Your payout of Rs '.number_format(intdiv($payout->amount_paisa, 100)).' was settled.',
                    $payout,
                );
            }
        });

        return back()->with('success', "Payout #{$payout->id} marked settled.");
    }

    public function rejectPayout(Request $request, Payout $payout)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless(in_array($payout->status, [Payout::STATUS_REQUESTED, Payout::STATUS_APPROVED], true), 422);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        DB::transaction(function () use ($payout, $request, $validated) {
            app(WalletService::class)->releasePayout(
                $payout,
                $request->user(),
                Payout::STATUS_REJECTED,
                $validated['note'] ?? null,
            );

            if ($payout->user !== null) {
                Notification::emit(
                    $payout->user,
                    Notification::TYPE_PAYOUT_REJECTED,
                    'Your payout of Rs '.number_format(intdiv($payout->amount_paisa, 100)).' was rejected — the funds are back in your balance.',
                    $payout,
                );
            }
        });

        return back()->with('success', "Payout #{$payout->id} rejected — funds released back to the creator's balance.");
    }

    /** Staff-gated destination reveal (decrypts ONLY here). */
    public function payoutDestination(Request $request, Payout $payout)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        try {
            $plaintext = \Illuminate\Support\Facades\Crypt::decryptString($payout->destination_encrypted);
        } catch (\Throwable) {
            return response()->json(['error' => 'undecryptable'], 422);
        }

        return response()->json(['destination' => $plaintext]);
    }

    private function ledgerStartedAt(): Carbon
    {
        // K3: a missing cutover marker degrades to the v1.6.0 ship date —
        // never null math on the desk.
        $raw = (string) ($this->settings->get('ledger_started_at', '') ?? '');

        return $raw !== '' ? Carbon::parse($raw) : Carbon::createFromDate(2026, 9, 30)->startOfDay();
    }
}
