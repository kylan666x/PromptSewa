<?php

namespace App\Services;

use App\Models\LicenseGrant;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Support\SikkaFormat;
use Illuminate\Support\Facades\Crypt;
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

            $credited = 0;
            $bonus = 0;

            $order->items()->with('sikkaPack')->get()->each(function (OrderItem $item) use ($order, &$credited, &$bonus) {
                $pack = $item->sikkaPack;

                if ($pack === null) {
                    return; // not a top-up line
                }

                $quantity = max(1, (int) $item->quantity);

                if ($this->creditBuyerOnce(
                    $order,
                    $item,
                    SikkaTransaction::TYPE_TOPUP,
                    $pack->sikka_amount * $quantity,
                    "topup:{$order->id}:{$item->id}",
                    'sikka_amount',
                ) !== null) {
                    $credited += $pack->sikka_amount * $quantity;
                }

                if ($this->creditBuyerOnce(
                    $order,
                    $item,
                    SikkaTransaction::TYPE_TOPUP_BONUS,
                    $pack->bonus_sikka * $quantity,
                    "topupbonus:{$order->id}:{$item->id}",
                    'bonus_sikka',
                ) !== null) {
                    $bonus += $pack->bonus_sikka * $quantity;
                }
            });

            // S6 (v1.8.0): the buyer's bell row commits with the credit —
            // and only on the delivery that actually credited (a double
            // approval credits, and notifies, exactly once).
            if ($credited > 0 && $order->buyer !== null) {
                Notification::emit(
                    $order->buyer,
                    Notification::TYPE_SIKKA_TOPUP,
                    'Top-up credited: '.SikkaFormat::render($credited).' Sikka credits'
                        .($bonus > 0 ? ' + '.SikkaFormat::render($bonus).' bonus' : '').'.',
                    $order,
                );
            }
        });
    }

    // -------------------------------------------------------------
    // Engagement rewards (S3) — bounded, idempotent, SPEND-ONLY
    // -------------------------------------------------------------

    /** The three engagement emitters (S3 contract). */
    final public const ENGAGEMENT_DAILY_VISIT = 'daily_visit';

    final public const ENGAGEMENT_PUBLISH = 'publish';

    final public const ENGAGEMENT_RATING_RECEIVED = 'rating_received';

    public const ENGAGEMENT_TYPES = [
        self::ENGAGEMENT_DAILY_VISIT,
        self::ENGAGEMENT_PUBLISH,
        self::ENGAGEMENT_RATING_RECEIVED,
    ];

    /**
     * Emit one engagement_reward row for (user, type, day).
     *
     * Bounds, in order:
     *   - kill-switch: while sikka_enabled is off nothing moves;
     *   - the per-type amount must be > 0 (an admin setting of 0 = off);
     *   - ONE row per (user, type, calendar day) — the UNIQUE idempotency
     *     key `engage:{user}:{type}:{yyyy-mm-dd}` makes a double-fire
     *     (observer re-entry, replay, two emitters) credit exactly once;
     *   - the same-day SUM of engagement rows must stay below
     *     engage_daily_cap_sikka — earning is BOUNDED.
     *
     * Engagement rows are SPEND-ONLY: cashout_eligible = false at write
     * time (S2 contract) — they can be spent on prompts, never cashed out.
     */
    public function rewardEngagement(User $user, string $type): ?SikkaTransaction
    {
        if (! in_array($type, self::ENGAGEMENT_TYPES, true)) {
            throw new InvalidArgumentException("Unknown engagement type: {$type}");
        }

        if (! $this->settings->isOn('sikka_enabled')) {
            return null; // kill-switch: the economy is off, so is earning
        }

        $amount = $this->engagementAmount($type);

        if ($amount <= 0) {
            return null; // this emitter is administratively off
        }

        $cap = max(0, (int) ($this->settings->get('engage_daily_cap_sikka', '5') ?? '5'));
        $day = now()->toDateString();
        $key = "engage:{$user->id}:{$type}:{$day}";
        $startOfDay = now()->startOfDay();

        return DB::transaction(function () use ($user, $type, $amount, $cap, $day, $key, $startOfDay) {
            if (SikkaTransaction::query()->where('idempotency_key', $key)->exists()) {
                return null; // double-fire — already credited today
            }

            $todaySum = (int) SikkaTransaction::query()
                ->where('user_id', $user->id)
                ->where('type', SikkaTransaction::TYPE_ENGAGEMENT_REWARD)
                ->where('created_at', '>=', $startOfDay)
                ->lockForUpdate()
                ->sum('amount_sikka');

            if ($todaySum >= $cap) {
                return null; // the day's cap is spent — bounded earning
            }

            return SikkaTransaction::query()->create([
                'user_id' => $user->id,
                'type' => SikkaTransaction::TYPE_ENGAGEMENT_REWARD,
                'amount_sikka' => $amount,
                'cashout_eligible' => false, // spend-only, always
                'idempotency_key' => $key,
                'meta' => [
                    'engagement' => $type,
                    'day' => $day,
                    'daily_cap_sikka' => $cap,
                ],
                'created_at' => now(),
            ]);
        });
    }

    // -------------------------------------------------------------
    // Membership stipends (S3/S5) — replay-safe forever
    // -------------------------------------------------------------

    /**
     * Grant ONE stipend period for a membership: a membership_stipend row
     * (+plan stipend, cashout-eligible) keyed
     * `stipend:{membership_id}:{period_index}`.
     *
     * Replay safety is structural: the existence check plus the UNIQUE key
     * mean a replayed scan (cron fires twice, a manual rerun, a catch-up
     * after the kill-switch was off) grants each period exactly once — the
     * period index never re-prices.
     */
    public function grantStipend(Membership $membership, int $periodIndex): ?SikkaTransaction
    {
        $amount = (int) ($membership->plan?->stipend_sikka ?? 0);

        if ($amount <= 0) {
            return null; // stipend-less plans write no row (still replay-safe)
        }

        $key = "stipend:{$membership->id}:{$periodIndex}";

        return DB::transaction(function () use ($membership, $periodIndex, $amount, $key) {
            if (SikkaTransaction::query()->where('idempotency_key', $key)->exists()) {
                return null;
            }

            $row = SikkaTransaction::query()->create([
                'user_id' => $membership->user_id,
                'type' => SikkaTransaction::TYPE_MEMBERSHIP_STIPEND,
                'amount_sikka' => $amount,
                'cashout_eligible' => true,
                'idempotency_key' => $key,
                'meta' => [
                    'membership_id' => $membership->id,
                    'plan_id' => $membership->plan_id,
                    'period_index' => $periodIndex,
                    'period_days' => Membership::PERIOD_DAYS,
                ],
                'created_at' => now(),
            ]);

            // S6 (v1.8.0): one bell row per stipend actually granted — a
            // replay grants no row and therefore notifies nothing.
            if ($membership->user !== null) {
                Notification::emit(
                    $membership->user,
                    Notification::TYPE_SIKKA_STIPEND,
                    'Membership stipend: '.SikkaFormat::render($amount).' Sikka credits credited.',
                    $membership,
                );
            }

            return $row;
        });
    }

    // -------------------------------------------------------------
    // Cash-out (S4) — Sikka → NPR at the spread
    // -------------------------------------------------------------

    /**
     * The configured cash-out rate in paisa per Sikka, bounded between 10
     * and the buy rate (the spread can never invert: the founder cannot
     * make cashing out pay better than buying in).
     */
    public function cashoutRatePaisaPerToken(): int
    {
        $buy = max(1, (int) ($this->settings->get('sikka_buy_paisa_per_token', '100') ?? '100'));
        $rate = (int) ($this->settings->get('sikka_cashout_paisa_per_token', '80') ?? '80');

        return max(10, min($buy, $rate));
    }

    /**
     * Request a Sikka withdrawal: payout row + payout_hold row in ONE
     * transaction.
     *
     * Checks (all throw InvalidArgumentException — controllers map them to
     * 422): a positive amount, a known method, >= sikka_cashout_min, and
     * <= cashoutableAvailable. The hold (−S, eligible=true) lives inside
     * the balance SUM, so the amount is unavailable the moment it is held
     * and is restored by the release row if the request is refused.
     */
    public function requestPayout(User $user, int $sikkaAmount, string $method, string $destinationPlaintext): Payout
    {
        if ($sikkaAmount <= 0) {
            throw new InvalidArgumentException('Withdrawal amount must be positive.');
        }

        if (! in_array($method, Payout::METHODS, true)) {
            throw new InvalidArgumentException("Unknown payout method: {$method}");
        }

        $min = max(0, (int) ($this->settings->get('sikka_cashout_min', '500') ?? '500'));

        return DB::transaction(function () use ($user, $sikkaAmount, $method, $destinationPlaintext, $min) {
            if ($sikkaAmount < $min) {
                throw new InvalidArgumentException('Withdrawal amount is below the Sikka minimum.');
            }

            if ($sikkaAmount > $this->cashoutableAvailable($user)) {
                throw new InvalidArgumentException('Withdrawal amount exceeds the cashoutable Sikka balance.');
            }

            $payout = Payout::query()->create([
                'user_id' => $user->id,
                // NPR is DERIVED at settlement; the hold itself is Sikka.
                'amount_paisa' => 0,
                'status' => Payout::STATUS_REQUESTED,
                'method' => $method,
                // Encrypted at rest; plaintext exists only in this call frame.
                'destination_encrypted' => Crypt::encryptString($destinationPlaintext),
                'source_currency' => Payout::SOURCE_SIKKA,
                'sikka_amount' => $sikkaAmount,
                'requested_at' => now(),
            ]);

            SikkaTransaction::query()->create([
                'user_id' => $user->id,
                'type' => SikkaTransaction::TYPE_PAYOUT_HOLD,
                'amount_sikka' => -$sikkaAmount,
                'cashout_eligible' => true,
                'idempotency_key' => "sikka_payout_hold:{$payout->id}",
                'meta' => ['payout_id' => $payout->id, 'method' => $method],
                'created_at' => now(),
            ]);

            return $payout;
        });
    }

    /**
     * Settle an APPROVED Sikka payout: compute settled_npr_paisa = S ×
     * cashout_rate with a pure integer multiply and store it ONCE on the
     * payout row. No ledger row is inserted — the hold was the debit, and
     * re-pricing a settled payout is impossible by construction.
     */
    public function settlePayout(Payout $payout, User $decider): void
    {
        DB::transaction(function () use ($payout, $decider) {
            /** @var Payout $payout */
            $payout = Payout::query()->whereKey($payout->getKey())->lockForUpdate()->firstOrFail();

            $this->assertSikkaPayout($payout);

            if ($payout->status === Payout::STATUS_SETTLED) {
                return; // idempotent replay — the stored NPR never re-prices
            }

            if ($payout->status !== Payout::STATUS_APPROVED) {
                throw new RuntimeException('Only approved payouts can be settled.');
            }

            $fill = ['decided_by' => $decider->id, 'decided_at' => now()];

            if ($payout->settled_npr_paisa === null) {
                $fill['settled_npr_paisa'] = (int) $payout->sikka_amount * $this->cashoutRatePaisaPerToken();
            }

            $payout->fill($fill);
            $payout->transitionTo(Payout::STATUS_SETTLED);

            if ($payout->user !== null) {
                Notification::emit(
                    $payout->user,
                    Notification::TYPE_PAYOUT_SETTLED,
                    'Your withdrawal of '.SikkaFormat::render((int) $payout->sikka_amount)
                        .' Sikka credits was settled — Rs '.number_format(intdiv((int) $payout->settled_npr_paisa, 100)).' sent.',
                    $payout,
                );
            }
        });
    }

    /**
     * Reject or cancel a Sikka payout: the release row (+S) restores the
     * held credits, and the payout row carries the decision. Insert-only
     * reversal — the hold row is never rewritten.
     */
    public function releasePayout(Payout $payout, User $decider, string $status, ?string $note = null): void
    {
        if (! in_array($status, [Payout::STATUS_REJECTED, Payout::STATUS_CANCELLED], true)) {
            throw new InvalidArgumentException('releasePayout accepts only rejected|cancelled.');
        }

        DB::transaction(function () use ($payout, $decider, $status, $note) {
            /** @var Payout $payout */
            $payout = Payout::query()->whereKey($payout->getKey())->lockForUpdate()->firstOrFail();

            $this->assertSikkaPayout($payout);

            if ($payout->status === $status) {
                return; // idempotent replay
            }

            $payout->fill([
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'note' => $note ?? $payout->note,
            ]);
            $payout->transitionTo($status);

            SikkaTransaction::query()->create([
                'user_id' => $payout->user_id,
                'type' => SikkaTransaction::TYPE_PAYOUT_RELEASE,
                'amount_sikka' => (int) $payout->sikka_amount,
                'cashout_eligible' => true,
                'idempotency_key' => "sikka_payout_release:{$payout->id}",
                'meta' => ['payout_id' => $payout->id, 'status' => $status, 'by' => $decider->id],
                'created_at' => now(),
            ]);

            if ($payout->user !== null) {
                Notification::emit(
                    $payout->user,
                    Notification::TYPE_PAYOUT_REJECTED,
                    'Your withdrawal of '.SikkaFormat::render((int) $payout->sikka_amount)
                        .' Sikka credits was '.($status === Payout::STATUS_REJECTED ? 'rejected' : 'cancelled')
                        .' — the credits are back in your Sikka balance.',
                    $payout,
                );
            }
        });
    }

    private function assertSikkaPayout(Payout $payout): void
    {
        if (! $payout->isSikkaSource()) {
            throw new InvalidArgumentException('That payout is an NPR wallet withdrawal — the wallet service owns it.');
        }
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

    private function creditBuyerOnce(Order $order, OrderItem $item, string $type, int $amount, string $key, string $label): ?SikkaTransaction
    {
        if ($amount <= 0) {
            return null; // bonus-less packs credit no bonus row
        }

        if (SikkaTransaction::query()->where('idempotency_key', $key)->exists()) {
            return null; // double approval credits once
        }

        return SikkaTransaction::query()->create([
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

    /** Per-type engagement amount from settings (0 disables the emitter). */
    private function engagementAmount(string $type): int
    {
        $key = match ($type) {
            self::ENGAGEMENT_DAILY_VISIT => 'engage_daily_sikka',
            self::ENGAGEMENT_PUBLISH => 'engage_publish_sikka',
            self::ENGAGEMENT_RATING_RECEIVED => 'engage_rating_sikka',
            default => null,
        };

        if ($key === null) {
            return 0;
        }

        return max(0, (int) ($this->settings->get($key, '0') ?? '0'));
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
