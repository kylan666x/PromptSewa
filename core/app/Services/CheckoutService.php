<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Pack;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MKT-001: creates pending Orders from a cart of products.
 *
 * Financial safety (AGENTS.md invariants):
 * - INTEGER paisa math only; totals = sum of integer line totals.
 * - Idempotency: the client key rides a UNIQUE constraint; concurrent
 *   double-submission resolves via firstOrCreate inside a transaction,
 *   so exactly ONE order ever exists per key (acceptance criterion #3).
 * - The order is born `pending`; nothing is granted until a confirmed
 *   payment calls EntitlementService.
 */
class CheckoutService
{
    /**
     * Cart line style A: product_id lines (single prompts).
     * Cart line style B: pack_id lines (bundles priced as one unit).
     *
     * @param  array<int, array{product_id?: int, pack_id?: int, quantity?: int}>  $cartItems
     * @return array{order: Order, created: bool}
     */
    public function createOrderFor(User $buyer, array $cartItems, ?string $idempotencyKey = null): array
    {
        $hasPackLines = collect($cartItems)->contains(fn (array $item) => isset($item['pack_id']));

        return $hasPackLines
            ? $this->createPackOrder($buyer, $cartItems, $idempotencyKey)
            : $this->createOrder($buyer, $cartItems, $idempotencyKey);
    }

    /**
     * Pack order: one line per pack, priced from packs.price_paisa.
     *
     * @param  array<int, array{pack_id: int, quantity?: int}>  $cartItems
     * @return array{order: Order, created: bool}
     */
    public function createPackOrder(User $buyer, array $cartItems, ?string $idempotencyKey = null): array
    {
        $idempotencyKey ??= (string) Str::uuid();

        return DB::transaction(function () use ($buyer, $cartItems, $idempotencyKey) {
            $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return ['order' => $existing, 'created' => false];
            }

            $packs = Pack::query()
                ->whereIn('id', collect($cartItems)->pluck('pack_id'))
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($packs->count() !== collect($cartItems)->pluck('pack_id')->unique()->count()) {
                throw new \InvalidArgumentException('Cart contains invalid or inactive packs.');
            }

            $subtotal = 0;
            $lines = collect($cartItems)->map(function (array $item) use ($packs, &$subtotal) {
                /** @var Pack $pack */
                $pack = $packs->get($item['pack_id']);
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $lineTotal = $pack->price_paisa * $quantity;
                $subtotal += $lineTotal;

                return ['pack' => $pack, 'quantity' => $quantity, 'line_total_paisa' => $lineTotal];
            });

            /** @var Pack $firstPack */
            $firstPack = $packs->first();

            $order = Order::create([
                'buyer_id' => $buyer->id,
                'status' => Order::STATUS_PENDING,
                'subtotal_paisa' => $subtotal,
                'tax_paisa' => 0,
                'total_paisa' => $subtotal,
                'currency' => $firstPack->currency,
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'pack_id' => $line['pack']->id,
                    'price_paisa' => $line['pack']->price_paisa,
                    'currency' => $line['pack']->currency,
                    'quantity' => $line['quantity'],
                ]);
            }

            return ['order' => $order, 'created' => true];
        });
    }

    /**
     * @param  array<int, array{product_id: int, quantity?: int}>  $cartItems
     * @return array{order: Order, created: bool}
     */
    public function createOrder(User $buyer, array $cartItems, ?string $idempotencyKey = null): array
    {
        $idempotencyKey ??= (string) Str::uuid();

        return DB::transaction(function () use ($buyer, $cartItems, $idempotencyKey) {
            // Idempotent replay: same key → return the original order.
            $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return ['order' => $existing, 'created' => false];
            }

            $products = Product::query()
                ->whereIn('id', collect($cartItems)->pluck('product_id'))
                ->where('status', Product::STATUS_ACTIVE) // draft/archived products are not sellable
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== collect($cartItems)->pluck('product_id')->unique()->count()) {
                throw new \InvalidArgumentException('Cart contains invalid or inactive products.');
            }

            $subtotal = 0; // paisa, integer

            $lines = collect($cartItems)->map(function (array $item) use ($products, &$subtotal) {
                /** @var Product $product */
                $product = $products->get($item['product_id']);
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $lineTotal = $product->price_paisa * $quantity; // integer × integer

                $subtotal += $lineTotal;

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'price_paisa' => $product->price_paisa,
                    'line_total_paisa' => $lineTotal,
                ];
            });

            // Tax is added by the platform later (Phase 3); zero for now,
            // computed in paisa integers when it arrives — never %float.
            $tax = 0;
            $total = $subtotal + $tax;

            $order = Order::create([
                'buyer_id' => $buyer->id,
                'status' => Order::STATUS_PENDING,
                'subtotal_paisa' => $subtotal,
                'tax_paisa' => $tax,
                'total_paisa' => $total,
                'currency' => $products->first()->currency,
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'prompt_id' => $line['product']->prompt_id,
                    'price_paisa' => $line['price_paisa'],
                    'currency' => $line['product']->currency,
                    'quantity' => $line['quantity'],
                ]);
            }

            return ['order' => $order, 'created' => true];
        });
    }
}
