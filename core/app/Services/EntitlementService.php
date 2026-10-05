<?php

namespace App\Services;

use App\Models\LicenseGrant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pack;
use App\Models\Prompt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MKT-001: converts a PAID order into license grants (entitlements).
 *
 * Called only after payment confirmation (webhook / verified status
 * check / admin approval of a manual payment) — never from the checkout
 * flow (AGENTS.md invariant #4).
 *
 * Idempotency: single-prompt lines are keyed on order_item_id (the legacy
 * UNIQUE constraint; re-established as a partial-style guard by checking
 * existence first). Pack lines issue one grant per member prompt — replay
 * safety comes from the (user_id, prompt_id, active) existence check
 * inside the same transaction, so a webhook replay can never double-grant.
 */
class EntitlementService
{
    public function fulfill(Order $order, ?string $source = null): void
    {
        // Fulfillment is a single atomic unit: either the whole order is
        // granted or nothing is — no partial entitlements, ever.
        DB::transaction(function () use ($order, $source) {
            // Lock the order row so a concurrent webhook/process cannot
            // double-fulfill while we are mid-transaction.
            /** @var Order $order */
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $order->isPaid()) {
                throw new \DomainException('Cannot fulfill order '.$order->id.' with status '.$order->status);
            }

            $order->items()->with(['product.prompt'])->get()->each(function (OrderItem $item) use ($order, $source) {
                if ($item->pack_id !== null) {
                    $this->grantPack($order, $item, $source);

                    return;
                }

                $this->grantPrompt($order, $item, $item->prompt_id, $source);
            });
        });
    }

    /** Single-prompt line: one grant (idempotent via order_item check). */
    private function grantPrompt(Order $order, OrderItem $item, ?int $promptId, ?string $source = null): void
    {
        if ($promptId === null) {
            return;
        }

        if ($item->licenseGrant()->exists()) {
            return; // idempotent replay — grant already issued
        }

        LicenseGrant::create([
            'user_id' => $order->buyer_id,
            'order_item_id' => $item->id,
            'prompt_id' => $promptId,
            'license_tier' => $item->product?->prompt?->license_tier
                ?? Prompt::query()->whereKey($promptId)->value('license_tier')
                ?? 'personal',
            'source' => $source,
            'grant_code' => $this->grantCode(),
            'status' => LicenseGrant::STATUS_ACTIVE,
        ]);
    }

    /**
     * Pack line: one grant per published prompt in the pack, all pointing
     * at the same order_item. Already-entitled prompts are skipped so a
     * buyer who owns 3 of 10 pack members only gains the other 7.
     */
    private function grantPack(Order $order, OrderItem $item, ?string $source = null): void
    {
        /** @var Pack|null $pack */
        $pack = Pack::query()->find($item->pack_id);

        if ($pack === null) {
            return;
        }

        $pack->publishedPrompts()->get(['prompts.id', 'license_tier'])->each(function (Prompt $prompt) use ($order, $item, $source) {
            $exists = LicenseGrant::query()
                ->where('user_id', $order->buyer_id)
                ->where('prompt_id', $prompt->id)
                ->where('status', LicenseGrant::STATUS_ACTIVE)
                ->exists();

            if ($exists) {
                return;
            }

            LicenseGrant::create([
                'user_id' => $order->buyer_id,
                'order_item_id' => $item->id,
                'prompt_id' => $prompt->id,
                'license_tier' => $prompt->license_tier ?? 'personal',
                'source' => $source,
                'grant_code' => $this->grantCode(),
                'status' => LicenseGrant::STATUS_ACTIVE,
            ]);
        });
    }

    private function grantCode(): string
    {
        return strtoupper(Str::random(12)).'-'.Str::random(8);
    }
}
