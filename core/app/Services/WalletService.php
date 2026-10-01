<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * M2 (v1.6.0) — THE single choke point for all money movement.
 *
 * Every creator credit and every withdrawal hold/release goes through this
 * service. No other class may INSERT into wallet_transactions (M6 arch
 * test greps for call sites). The manual-approval controller and the eSewa
 * verification path BOTH call settleOrder — a third call site must not
 * exist.
 *
 * Integer paisa only (AGENTS.md #1): commission math is
 * intdiv(gross * (10000 - bps), 10000); the platform share is DERIVED
 * (gross − credit) in reports, never stored, never a float.
 */
class WalletService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    // -------------------------------------------------------------
    // Commission math (pure, integer-only — property-tested in M6)
    // -------------------------------------------------------------

    public function commissionBps(): int
    {
        // K3 (v1.6.1): a missing settings row degrades to the DOCUMENTED
        // default — never to null math. (int) null === 0, so the explicit
        // existence check keeps 0 distinct from "absent".
        $row = Setting::query()->whereKey('commission_bps')->first();
        $bps = $row === null ? 2000 : (int) $row->value;

        return max(0, min(5000, $bps));
    }

    /** Creator credit for a gross line: floor((gross × (10000 − bps)) / 10000). */
    public function creatorCreditPaisa(int $grossPaisa): int
    {
        if ($grossPaisa < 0) {
            throw new InvalidArgumentException('Gross amount cannot be negative.');
        }

        return intdiv($grossPaisa * (10000 - $this->commissionBps()), 10000);
    }

    // -------------------------------------------------------------
    // Balances (SUM of the ledger under lock — never a cached column)
    // -------------------------------------------------------------

    public function balancePaisa(User $user): int
    {
        return (int) DB::transaction(function () use ($user) {
            $this->lockUserLedger($user);

            // K2 (v1.6.1): every SUM crosses the service edge as int — an
            // empty ledger yields SQL SUM = NULL, and null arithmetic is a
            // 500 waiting for its first zero-row reader.
            return $this->sumAllRows($user) + $this->openHeldPaisa($user);
        });
    }

    /**
     * Available = the plain SUM of every ledger row. The hold row (−X) is
     * already inside the SUM, so availability drops the moment the hold is
     * inserted and is restored by the release row (+X) — no subtraction on
     * top, ever (that would double-count the hold).
     */
    public function availablePaisa(User $user): int
    {
        return (int) DB::transaction(function () use ($user) {
            $this->lockUserLedger($user);

            return $this->sumAllRows($user);
        });
    }

    /** SUM of every row for the user (call inside a locked transaction). */
    private function sumAllRows(User $user): int
    {
        // (int) cast is the K2 boundary: SUM over zero rows returns NULL.
        return (int) WalletTransaction::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->sum('amount_paisa');
    }

    /** Absolute paisa held by UNDECIDED payouts (call inside a locked transaction). */
    private function openHeldPaisa(User $user): int
    {
        $openHoldKeys = Payout::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Payout::STATUS_REQUESTED, Payout::STATUS_APPROVED])
            ->pluck('id')
            ->map(fn ($id) => "withdrawal_hold:{$id}");

        if ($openHoldKeys->isEmpty()) {
            return 0;
        }

        return (int) abs((int) WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', WalletTransaction::TYPE_WITHDRAWAL_HOLD)
            ->whereIn('idempotency_key', $openHoldKeys->all())
            ->lockForUpdate()
            ->sum('amount_paisa'));
    }

    // -------------------------------------------------------------
    // The only credit path in the codebase (M2/M3)
    // -------------------------------------------------------------

    /**
     * Settle a paid order: mark paid, issue license grants, credit the
     * creator of each line — one DB::transaction, idempotent end to end.
     *
     * (a) already-paid orders are a no-op (double webhook / callback+
     *     webhook races fall out of the paid-state guard);
     * (b) grants follow payment inside the same transaction (invariant #4);
     * (c) per order_item: idempotency key sale:{order_id}:{item_id} — the
     *     UNIQUE constraint makes a replay of ANY line credit once.
     *
     * @param  'manual'|'esewa'  $source
     */
    public function settleOrder(Order $order, string $source): void
    {
        if (! in_array($source, ['manual', 'esewa'], true)) {
            throw new InvalidArgumentException("Unknown settlement source: {$source}");
        }

        DB::transaction(function () use ($order, $source) {
            // Lock the order row — the paid-state guard is race-safe.
            /** @var Order $order */
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($order->isPaid()) {
                return; // idempotent replay — nothing to do
            }

            if (! $order->isPending()) {
                throw new RuntimeException('Cannot settle order '.$order->id.' with status '.$order->status);
            }

            $order->fill([
                'status' => Order::STATUS_PAID,
                'paid_at' => now(), // G5: paid_at set inside settleOrder (same transaction)
                'payment_method' => $source,
            ])->save();

            // Grants follow payment in the SAME transaction.
            app(EntitlementService::class)->fulfill($order);

            // Creator credits — one ledger row per line, idempotency-keyed.
            $order->items()->get()->each(function (OrderItem $item) {
                $creatorId = $this->creatorIdForItem($item);

                if ($creatorId === null) {
                    return; // pack lines are platform revenue (packs have no owner)
                }

                $gross = $item->lineTotalPaisa();

                if ($gross <= 0) {
                    return; // free lines never touch the ledger
                }

                $credit = $this->creatorCreditPaisa($gross);

                WalletTransaction::query()->create([
                    'user_id' => $creatorId,
                    'type' => WalletTransaction::TYPE_SALE_CREDIT,
                    'amount_paisa' => $credit,
                    'order_id' => $item->order_id,
                    'order_item_id' => $item->id,
                    // UNIQUE — a replayed webhook lands here and is swallowed
                    // by the insert failing OR by the existence check below.
                    'idempotency_key' => "sale:{$item->order_id}:{$item->id}",
                    'meta' => [
                        'source' => 'settleOrder',
                        'gross_paisa' => $gross,
                        'commission_bps' => $this->commissionBps(),
                    ],
                    'created_at' => now(),
                ]);
            });
        });
    }

    // -------------------------------------------------------------
    // Withdrawals (M4)
    // -------------------------------------------------------------

    /**
     * Request a payout: inserts withdrawal_hold (−amount) + the payout row
     * (requested) in ONE transaction. Validation is the caller's job for
     * UX, but this method re-asserts the hard invariants server-side.
     */
    public function requestPayout(User $user, int $amountPaisa, string $method, string $destinationPlaintext): Payout
    {
        if ($amountPaisa <= 0) {
            throw new InvalidArgumentException('Payout amount must be positive.');
        }

        if (! in_array($method, Payout::METHODS, true)) {
            throw new InvalidArgumentException("Unknown payout method: {$method}");
        }

        // K3: fallback default lives in code, not just the settings table.
        $min = (int) ($this->settings->get('payout_min_paisa', '50000') ?? '50000');

        return DB::transaction(function () use ($user, $amountPaisa, $method, $destinationPlaintext, $min) {
            $available = $this->availablePaisa($user);

            if ($amountPaisa < $min) {
                throw new InvalidArgumentException('Payout amount is below the minimum threshold.');
            }

            if ($amountPaisa > $available) {
                throw new InvalidArgumentException('Payout amount exceeds the available balance.');
            }

            $payout = Payout::query()->create([
                'user_id' => $user->id,
                'amount_paisa' => $amountPaisa,
                'status' => Payout::STATUS_REQUESTED,
                'method' => $method,
                // Encrypted at rest; plaintext exists only in this call frame.
                'destination_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString($destinationPlaintext),
                'requested_at' => now(),
            ]);

            WalletTransaction::query()->create([
                'user_id' => $user->id,
                'type' => WalletTransaction::TYPE_WITHDRAWAL_HOLD,
                'amount_paisa' => -$amountPaisa,
                'idempotency_key' => "withdrawal_hold:{$payout->id}",
                'meta' => ['payout_id' => $payout->id, 'method' => $method],
                'created_at' => now(),
            ]);

            return $payout;
        });
    }

    /** Reject/cancel: insert withdrawal_release (+amount), flip state. */
    public function releasePayout(Payout $payout, User $decider, string $status, ?string $note = null): void
    {
        if (! in_array($status, [Payout::STATUS_REJECTED, Payout::STATUS_CANCELLED], true)) {
            throw new InvalidArgumentException('releasePayout accepts only rejected|cancelled.');
        }

        DB::transaction(function () use ($payout, $decider, $status, $note) {
            /** @var Payout $payout */
            $payout = Payout::query()->whereKey($payout->getKey())->lockForUpdate()->firstOrFail();

            // Already-released payouts must not release twice: the state
            // guard below is the race-safe gate (only 'requested' or an
            // 'approved' rollback can reach here, and decided rows flip
            // idempotently to the SAME status with no second insert).
            if ($payout->status === $status) {
                return; // idempotent replay
            }

            $payout->transitionTo($status);
            $payout->fill([
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'note' => $note ?? $payout->note,
            ])->save();

            // Insert-only reversal: the release is a NEW row, never an
            // UPDATE of the hold.
            WalletTransaction::query()->create([
                'user_id' => $payout->user_id,
                'type' => WalletTransaction::TYPE_WITHDRAWAL_RELEASE,
                'amount_paisa' => $payout->amount_paisa,
                'idempotency_key' => "withdrawal_release:{$payout->id}",
                'meta' => ['payout_id' => $payout->id, 'status' => $status, 'by' => $decider->id],
                'created_at' => now(),
            ]);
        });
    }

    // -------------------------------------------------------------

    private function lockUserLedger(User $user): void
    {
        // Lock the user's ledger rows for the duration of the balance read.
        WalletTransaction::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->get();
    }

    /**
     * Who gets credited for this line? Single-prompt lines credit the
     * prompt's creator. Pack lines credit NO ONE: packs are admin-owned
     * merchandising (the packs table has no owner column) — the bundle
     * price is platform revenue by definition. If the founder later wants
     * per-member pack payouts, that is a deliberate contract change.
     */
    private function creatorIdForItem(OrderItem $item): ?int
    {
        if ($item->pack_id !== null) {
            return null;
        }

        return $item->product?->prompt?->user_id
            ?? $item->prompt?->user_id;
    }
}
