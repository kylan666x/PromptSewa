<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Pack;
use App\Models\Product;
use App\Models\Prompt;
use App\Services\CheckoutService;
use App\Services\EntitlementService;
use App\Services\EsewaService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Buyer checkout (PAY-001 flow, cPanel-safe: no webhooks required).
 *
 * Two payment methods, both ending in EntitlementService::fulfill:
 *  - eSewa: order → gateway form POST → user returns to verify URL →
 *    HMAC signature verified → order paid → grants issued.
 *  - Manual: order → user submits payment reference (e.g. eSewa/Khalti/
 *    bank txn id + screenshot note) → admin approves from the panel →
 *    grants issued. Until approval the buyer sees "pending verification".
 *
 * Grants never precede payment confirmation (AGENTS.md #4).
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly EntitlementService $entitlements,
        private readonly EsewaService $esewa,
        private readonly SettingsService $settings,
    ) {}

    /** Order review page before choosing a payment method. */
    public function show(Request $request, Order $order)
    {
        abort_unless($request->user()?->id === $order->buyer_id || $request->user()?->isModerator(), 403);

        $order->load(['items.product.prompt', 'items.pack']);

        return view('checkout.show', [
            'order' => $order,
            'esewaEnabled' => $this->settings->isOn('esewa_enabled'),
            'manualEnabled' => $this->settings->isOn('manual_payment_enabled'),
            'manualInstructions' => (string) $this->settings->get('manual_payment_instructions', ''),
        ]);
    }

    /** Create a pending order for a single prompt and go to checkout. */
    public function buyPrompt(Request $request, Prompt $prompt)
    {
        $product = $prompt->product()->where('status', Product::STATUS_ACTIVE)->first();

        if ($product === null || $prompt->price_cents === 0) {
            return back()->withErrors(['buy' => 'This prompt is not currently for sale.']);
        }

        [, $order] = $this->createOrder($request, fn () => [['product_id' => $product->id]]);

        return redirect()->route('checkout.show', $order);
    }

    /** Create a pending order for a pack and go to checkout. */
    public function buyPack(Request $request, Pack $pack)
    {
        abort_unless($pack->is_active, 404);

        [, $order] = $this->createOrder($request, fn () => [['pack_id' => $pack->id]]);

        return redirect()->route('checkout.show', $order);
    }

    /** Redirect the buyer to the eSewa hosted checkout form. */
    public function esewaPay(Request $request, Order $order)
    {
        abort_unless($request->user()?->id === $order->buyer_id, 403);
        abort_unless($order->isPending(), 400, 'This order is no longer payable.');
        abort_unless($this->settings->isOn('esewa_enabled'), 403, 'eSewa payments are not enabled.');

        try {
            $form = $this->esewa->buildPaymentForm($order);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return view('checkout.esewa-redirect', ['form' => $form]);
    }

    /**
     * eSewa return URL. The gateway POSTs the signed payload here.
     * Signature verified → mark paid → fulfill. Grants follow payment,
     * never the redirect itself.
     */
    public function esewaVerify(Request $request)
    {
        $payload = $request->all();

        try {
            $reference = $this->esewa->verifyCallback(is_array($payload) ? $payload : []);
        } catch (\RuntimeException $e) {
            return redirect()->route('home')->withErrors(['payment' => 'Payment verification failed: '.$e->getMessage()]);
        }

        $orderId = (int) ($payload['transaction_uuid'] ?? 0);
        $order = Order::query()->find($orderId);

        if ($order === null) {
            return redirect()->route('home')->withErrors(['payment' => 'Unknown order in payment response.']);
        }

        if ($order->isPending()) {
            DB::transaction(function () use ($order, $reference) {
                $order->fill([
                    'status' => Order::STATUS_PAID,
                    'paid_at' => now(),
                    'payment_method' => 'esewa',
                    'payment_reference' => $reference,
                ])->save();

                $this->entitlements->fulfill($order->refresh());
            });
        }

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Payment confirmed — your prompts are unlocked.');
    }

    /** Buyer submits a manual payment proof; admin approves later. */
    public function manualSubmit(Request $request, Order $order)
    {
        abort_unless($request->user()?->id === $order->buyer_id, 403);
        abort_unless($order->isPending(), 400, 'This order is no longer payable.');
        abort_unless($request->user()?->id === $order->buyer_id, 403);
        abort_unless($order->isPending(), 400, 'This order is no longer payable.');
        abort_unless($this->settings->isOn('manual_payment_enabled'), 403, 'Manual payments are not enabled.');

        $validated = $request->validate([
            'payment_reference' => ['required', 'string', 'min:4', 'max:180'],
        ]);

        $order->fill([
            'payment_method' => 'manual',
            'payment_reference' => trim($validated['payment_reference']),
        ])->save();

        return redirect()
            ->route('checkout.show', $order)
            ->with('success', 'Payment reference received — an admin will verify it shortly.');
    }

    /** The buyer's library: purchased prompts + owned packs. */
    public function purchases(Request $request)
    {
        $user = $request->user();

        $grants = $user->licenseGrants()
            ->with(['prompt.category', 'prompt.latestVersion', 'orderItem.order'])
            ->where('status', 'active')
            ->latest()
            ->paginate(12, pageName: 'prompts_page');

        $orders = Order::query()
            ->where('buyer_id', $user->id)
            ->with('items.pack')
            ->latest()
            ->paginate(8, pageName: 'orders_page');

        return view('purchases.index', [
            'grants' => $grants,
            'orders' => $orders,
        ]);
    }

    /**
     * Create a pending order from a cart callback (prompt product or pack).
     *
     * @return array{0: bool, 1: Order}
     */
    private function createOrder(Request $request, callable $cartItems): array
    {
        /** @var array{order: Order, created: bool} $result */
        $result = $this->checkout->createOrderFor($request->user(), $cartItems());

        return [$result['created'], $result['order']];
    }
}
