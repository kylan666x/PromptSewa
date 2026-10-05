<?php

namespace App\Services;

use App\Models\LicenseGrant;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SikkaTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * S2 (v1.8.0) — THE single choke point for Sikka movement, WalletService's
 * mirror for the credit economy.
 *
 * Sikka is a CREDIT, not a currency: integers only, never a decimal, never
 * a float. Every balance is a SUM over the insert-only ledger under
 * lockForUpdate(); there is no cached balance column and there never will
 * be. Every SUM crosses this service edge as (int) (K2: an empty ledger
 * yields SQL NULL, and null arithmetic is a 500 waiting for its first
 * zero-row reader).
 *
 * Two rails, both idempotent end to end:
 *   - spendSikka(): checkout on the Sikka rail — spend + order paid +
 *     license grant + creator sale credit in ONE transaction. An active
 *     unlimited_unlock membership bypasses the spend entirely
 *     (entitlement, never balance: zero spend rows).
 *   - topupCredit(): approval of a sikka-pack line — topup + bonus rows,
 *     exactly once.
 */
class SikkaService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    // -------------------------------------------------------------
    // Balances (SUM of the ledger under lock — never a cached column)
    // -------------------------------------------------------------

    /**
     * Spendable = the plain SUM of every row. Payout holds (−X) live
     * INSIDE the SUM — the v1.6.0 wallet design, mirrored deliberately:
     * a hold lowers spendable and the release row restores it. No
     * subtraction on top, ever (that would double-count the hold).
     */
    public function spendableAvailable(User $user): int
    {
        return (int) DB::transaction(function () use ($user) {
            $this->lockUserLedger($user);

            return $this->sumRows($user, eligibleOnly: false);
        });
    }

    /** Cashoutable = SUM over the rows written with cashout_eligible = true. */
    public function cashoutableAvailable(User $user): int
    {
        return (int) DB::transaction(function () use ($user) {
            $this->lockUserLedger($user);

            return $this->sumRows($user, eligibleOnly: true);
        });
    }

    // -------------------------------------------------------------
    // Spending (the checkout Sikka rail)
    // -------------------------------------------------------------

    /**
     * Pay a pending order with Sikka credits — the balance IS the
     * verification, so the order is paid immediately, in the same
     * transaction that moves the ledger:
     *
     *   (a) unlimited_unlock membership → license grant with
     *       source='membership_unlimited', ZERO spend rows (entitlement);
     *   (b) otherwise the price_sikka total must fit the spendable
     *       balance — a shortfall throws InvalidArgumentException (the
     *       controller maps it to 422) and rolls back everything;
     *   (c) one spend row per line (−S, eligible=true, key
     *       spend:{order}:{item});
     *   (d) order paid + license grants, one transaction;
     *   (e) one creator sale credit per line (+S, eligible=true, key
     *       sale:{order}:{item}).
     *
     * Replay safety: a paid order is a no-op, and the UNIQUE idempotency
     * keys make any double-delivery credit exactly once.
     *
     * @throws InvalidArgumentException on shortfall or a non-prompt line
     */
    public function spendSikka(Order $order, User $user): void
    {
        DB::transaction(function () use ($order, $user) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($order->isPaid()) {
                return; // idempotent replay — nothing to do
            }

            if (! $order->isPending()) {
                throw new RuntimeException('Cannot spend Sikka on order '.$order->id.' with status '.$order->status);
            }

            if ($order->buyer_id !== $user->id) {
                throw new InvalidArgumentException('Sikka spends must come from the order buyer.');
            }

            $items = $order->items()->with(['product.prompt', 'prompt'])->get();

            foreach ($items as $item) {
                if ($item->pack_id !== null || $item->membership_plan_id !== null || $item->sikka_pack_id !== null) {
                    throw new InvalidArgumentException('The Sikka rail supports prompt lines only — packs and memberships ride the NPR rails.');
                }
            }

            // (a) Entitlement bypass: never a balance movement.
            $membership = $this->activeUnlimitedMembership($user);

            if ($membership !== null) {
                $this->markPaidOnSikkaRail($order, 0, [
                    'unlimited_membership_id' => $membership->id,
                    'unlimited_plan_id' => $membership->plan_id,
                ]);

                app(EntitlementService::class)->fulfill($order, LicenseGrant::SOURCE_MEMBERSHIP_UNLIMITED);

                return;
            }

            // (b) Balance check: the SUM of price_sikka across the lines.
            $total = (int) $items->sum(fn (OrderItem $item) => $this->itemSikkaPrice($item));
            $available = $this->spendableAvailable($user);

            if ($total > $available) {
                throw new InvalidArgumentException(
                    "Insufficient Sikka credits for order {$order->id}: {$total} needed, {$available} available."
                );
            }

            // (c) Spend rows — one per line, idempotency-keyed.
            foreach ($items as $item) {
                $price = $this->itemSikkaPrice($item);

                if ($price <= 0) {
                    continue; // free lines never touch the ledger
                }

                SikkaTransaction::query()->create([
                    'user_id' => $user->id,
                    'type' => SikkaTransaction::TYPE_SPEND,
                    'amount_sikka' => -$price,
                    'cashout_eligible' => true,
                    'order_id' => $order->id,
                    'idempotency_key' => "spend:{$order->id}:{$item->id}",
                    'meta' => [
                        'order_item_id' => $item->id,
                        'prompt_id' => $item->prompt_id,
                        'price_sikka' => $price,
                    ],
                    'created_at' => now(),
                ]);
            }

            // (d) The balance IS the verification: paid immediately; grants
            //     follow payment inside this same transaction (invariant #4).
            $this->markPaidOnSikkaRail($order, $total);

            app(EntitlementService::class)->fulfill($order);

            // (e) Creator sale credits — one per line.
            foreach ($items as $item) {
                $price = $this->itemSikkaPrice($item);

                if ($price <= 0) {
                    continue;
                }

                $creatorId = $item->product?->prompt?->user_id ?? $item->prompt?->user_id;

                if ($creatorId === null) {
                    continue;
                }

                SikkaTransaction::query()->create([
                    'user_id' => $creatorId,
                    'type' => SikkaTransaction::TYPE_SALE_CREDIT,
                    'amount_sikka' => $price,
                    'cashout_eligible' => true,
                    'order_id' => $order->id,
                    'idempotency_key' => "sale:{$order->id}:{$item->id}",
                    'meta' => [
                        'order_item_id' => $item->id,
                        'buyer_id' => $user->id,
                        'price_sikka' => $price,
                    ],
                    'created_at' => now(),
                ]);
            }
        });
    }

    // -------------------------------------------------------------
    // Top-ups (NPR-rail pack purchase, credited on approval)
    // -------------------------------------------------------------

    /**
     * Credit every sikka-pack line on an approved order: one topup row
     * (+S) and, when the pack carries a bonus, one topup_bonus row (+B) —
     * separate rows so the bonus stays auditable on its own. Keys
     * topup:{order}:{item} / topupbonus:{order}:{item} make a double
     * approval credit exactly once.
     *
     * Non-sikka-pack lines are ignored: the NPR settle path handles them.
     */
    public function topupCredit(Order $order): void
    {
        DB::transaction(function () use ($order) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $order->items()->with('sikkaPack')->get()->each(function (OrderItem $item) use ($order) {
                $pack = $item->sikkaPack;

                if ($pack === null) {
                    return; // not a top-up line
                }

                $quantity = max(1, (int) $item->quantity);

                $this->creditBuyerOnce(
                    $order,
                    $item,
                    SikkaTransaction::TYPE_TOPUP,
                    $pack->sikka_amount * $quantity,
                    "topup:{$order->id}:{$item->id}",
                    'sikka_amount',
                );

                $this->creditBuyerOnce(
                    $order,
                    $item,
                    SikkaTransaction::TYPE_TOPUP_BONUS,
                    $pack->bonus_sikka * $quantity,
                    "topupbonus:{$order->id}:{$item->id}",
                    'bonus_sikka',
                );
            });
        });
    }

    // -------------------------------------------------------------
    // Pure helpers (read-only; controller/display consumers)
    // -------------------------------------------------------------

    /** Total Sikka this order charges (prompt lines; 0 for other rails). */
    public function orderTotalSikka(Order $order): int
    {
        return (int) $order->items()->with(['product.prompt', 'prompt'])->get()
            ->sum(fn (OrderItem $item) => $this->itemSikkaPrice($item));
    }

    /** Can this order ride the Sikka rail? Prompt lines only. */
    public function supportsSikkaRail(Order $order): bool
    {
        $items = $order->items()->get();

        if ($items->isEmpty()) {
            return false;
        }

        return $items->every(fn (OrderItem $item) => $item->pack_id === null
            && $item->membership_plan_id === null
            && $item->sikka_pack_id === null);
    }

    /** Is the user an active unlimited_unlock member? (entitlement, not balance) */
    public function hasUnlimitedUnlock(User $user): bool
    {
        return $this->activeUnlimitedMembership($user) !== null;
    }

    // -------------------------------------------------------------

    private function markPaidOnSikkaRail(Order $order, int $totalSikka, array $extraMeta = []): void
    {
        $buyRate = (int) ($this->settings->get('sikka_buy_paisa_per_token', '100') ?? '100');

        $order->fill([
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
            'payment_method' => 'sikka',
            'currency' => Order::CURRENCY_SIKKA,
            'sikka_amount' => $totalSikka,
            'meta' => array_merge($order->meta ?? [], [
                'rail' => 'sikka',
                // History never re-prices: the buy rate is snapshotted at
                // purchase time, never re-read later.
                'buy_paisa_per_token' => $buyRate,
            ], $extraMeta),
        ])->save();
    }

    private function creditBuyerOnce(Order $order, OrderItem $item, string $type, int $amount, string $key, string $label): void
    {
        if ($amount <= 0) {
            return; // bonus-less packs credit no bonus row
        }

        if (SikkaTransaction::query()->where('idempotency_key', $key)->exists()) {
            return; // double approval credits once
        }

        SikkaTransaction::query()->create([
            'user_id' => $order->buyer_id,
            'type' => $type,
            'amount_sikka' => $amount,
            'cashout_eligible' => true,
            'order_id' => $order->id,
            'idempotency_key' => $key,
            'meta' => [
                'order_item_id' => $item->id,
                'sikka_pack_id' => $item->sikka_pack_id,
                'quantity' => max(1, (int) $item->quantity),
                'label' => $label,
            ],
            'created_at' => now(),
        ]);
    }

    private function activeUnlimitedMembership(User $user): ?Membership
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->where('status', Membership::STATUS_ACTIVE)
            ->with('plan')
            ->get()
            ->first(fn (Membership $membership) => ! $membership->hasEnded()
                && $membership->plan?->hasUnlimitedUnlock() === true);
    }

    private function itemSikkaPrice(OrderItem $item): int
    {
        return (int) ($item->product?->prompt?->price_sikka
            ?? $item->prompt?->price_sikka
            ?? 0);
    }

    private function sumRows(User $user, bool $eligibleOnly): int
    {
        $query = SikkaTransaction::query()
            ->where('user_id', $user->id)
            ->lockForUpdate();

        if ($eligibleOnly) {
            $query->where('cashout_eligible', true);
        }

        // (int) cast is the K2 boundary: SUM over zero rows returns NULL.
        return (int) $query->sum('amount_sikka');
    }

    private function lockUserLedger(User $user): void
    {
        SikkaTransaction::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->get();
    }
}
