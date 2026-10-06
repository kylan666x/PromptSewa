<?php

namespace App\Observers;

use App\Models\FeedEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Prompt;
use App\Services\GamificationService;
use App\Services\MembershipService;
use App\Services\SikkaService;

/**
 * G2/G4 (v1.7.0) — sale-side gamification, fired from the paid transition
 * inside settleOrder's transaction. Milestones: first_sale, sales_10,
 * sales_50 — evaluated from the REAL sales_count column (no fabricated
 * copy; feed text comes from the actual numbers).
 */
class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->status !== Order::STATUS_PAID) {
            return;
        }

        if ($order->getOriginal('status') === Order::STATUS_PAID) {
            return; // idempotent replay of the same settlement
        }

        // Per-line creators (pack lines credit nobody — WalletService ruling).
        $order->items()->with(['product.prompt', 'prompt'])->get()->each(function ($item) {
            $prompt = $item->product?->prompt ?? $item->prompt;

            if ($prompt === null) {
                return;
            }

            $creator = $prompt->creator;
            if ($creator === null) {
                return;
            }

            $gamification = app(GamificationService::class);

            // F1 (v1.9.2): fresh sales count AFTER this sale — read from the
            // paid order lines. The old read of `$prompt->fresh()->sales_count`
            // was always 0 (nothing writes that column), so sale milestones
            // and the first_sale/sales_10/sales_50 awards never fired for a
            // credit-rail sale.
            $salesCount = OrderItem::paidSalesCountForPrompt($prompt);

            $gamification->grantXp($creator, 'sale');
            $gamification->evaluateCriteria($creator, 'first_sale');

            if ($salesCount >= 10) {
                $gamification->evaluateCriteria($creator, 'sales_10');
            }

            if ($salesCount >= 50) {
                $gamification->evaluateCriteria($creator, 'sales_50');
            }

            $this->emitSaleMilestone($prompt, $creator->id, $salesCount);
        });

        // S5 (v1.8.0): membership plan lines activate with the payment —
        // membership row + first stipend + perk grants, all idempotent.
        // Riding the paid transition keeps ONE activation path for the
        // manual and eSewa rails alike.
        app(MembershipService::class)->activateForOrder($order);

        // S6 (v1.8.0): Sikka top-up lines credit the buyer on the SAME paid
        // transition (keys make a replay credit once). Moved here from the
        // manual-approval controller so eSewa-paid top-ups credit too.
        app(SikkaService::class)->topupCredit($order);
    }

    /** Sale milestones at the real thresholds only (10, 50). */
    private function emitSaleMilestone(Prompt $prompt, int $actorId, int $salesCount): void
    {
        if (! in_array($salesCount, [10, 50], true)) {
            return; // milestones only — no per-sale feed spam
        }

        $dedupeKey = "sale_milestone:{$prompt->id}:{$salesCount}";

        if (FeedEvent::query()->where('dedupe_key', $dedupeKey)->exists()) {
            return;
        }

        FeedEvent::query()->create([
            'type' => FeedEvent::TYPE_SALE_MILESTONE,
            'actor_id' => $actorId,
            'subject_type' => $prompt::class,
            'subject_id' => $prompt->id,
            'meta' => [
                'title' => $prompt->title,
                'slug' => $prompt->slug,
                'sales_count' => $salesCount, // real number, never a superlative
            ],
            'dedupe_key' => $dedupeKey,
            'created_at' => now(),
        ]);
    }
}
