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
            // C3 (v1.4.4): admin-configured methods with per-method QR +
            // instructions, position-ordered, active only.
            'manualMethods' => \App\Models\ManualPaymentMethod::query()
                ->orderedForCheckout()
                ->get(),
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
     * eSewa return URL (callback). The gateway POSTs the signed payload
     * here. Signature verified → settleOrder — the M2 choke point (paid
     * guard + grants + creator credits, all idempotent). Grants follow
     * payment, never the redirect itself.
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
            app(\App\Services\WalletService::class)->settleOrder($order, 'esewa');
        }

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Payment confirmed — your prompts are unlocked.');
    }

    /**
     * M3 (v1.6.0): server-to-server eSewa webhook. CSRF-exempt (bootstrap
     * app), signature-verified with the decrypted merchant secret. The
     * per-item idempotency key inside settleOrder makes callback+webhook
     * double-delivery credit exactly once. Responds 200 fast — eSewa
     * retries on non-2xx and we never want a duplicate to fail loudly.
     */
    public function esewaWebhook(Request $request)
    {
        $payload = is_array($request->all()) ? $request->all() : [];

        // Masked info-level log: audit trail without leaking secrets.
        \Illuminate\Support\Facades\Log::info('esewa webhook received', [
            'transaction_uuid' => substr((string) ($payload['transaction_uuid'] ?? ''), 0, 12),
            'has_signature' => ($payload['signature'] ?? '') !== '',
            'signed_field_names' => (string) ($payload['signed_field_names'] ?? ''),
        ]);

        // M3: the admin payments card shows this masked info line.
        app(\App\Services\SettingsService::class)->set('esewa_last_webhook', now()->toDateTimeString()
            .' · uuid '.substr((string) ($payload['transaction_uuid'] ?? '?'), 0, 12)
            .' · sig '.((($payload['signature'] ?? '') !== '') ? 'present' : 'absent'));

        try {
            $reference = $this->esewa->verifyCallback($payload);
        } catch (\RuntimeException $e) {
            \Illuminate\Support\Facades\Log::warning('esewa webhook rejected', ['reason' => $e->getMessage()]);

            return response()->json(['status' => 'rejected', 'reason' => 'signature verification failed'], 200);
        }

        $order = Order::query()->find((int) ($payload['transaction_uuid'] ?? 0));

        if ($order === null) {
            return response()->json(['status' => 'ignored', 'reason' => 'unknown order'], 200);
        }

        if ($order->isPending()) {
            $order->fill(['payment_reference' => $reference])->save();
            app(\App\Services\WalletService::class)->settleOrder($order, 'esewa');
        }

        return response()->json(['status' => 'ok']);
    }

    /** Buyer submits a manual payment reference; admin approves later. */
    public function manualSubmit(Request $request, Order $order)
    {
        abort_unless($request->user()?->id === $order->buyer_id, 403);
        abort_unless($order->isPending(), 400, 'This order is no longer payable.');
        abort_unless($this->settings->isOn('manual_payment_enabled'), 403, 'Manual payments are not enabled.');

        $validated = $request->validate([
            'payment_reference' => ['required', 'string', 'min:4', 'max:180'],
            'method_name' => ['nullable', 'string', 'max:100'],
        ]);

        // C3 (v1.4.4): snapshot the chosen method NAME as a plain string —
        // editing/deactivating the method later never rewrites this.
        $methodName = trim((string) ($validated['method_name'] ?? ''));
        $reference = trim($validated['payment_reference']);

        $order->fill([
            'payment_method' => 'manual',
            'payment_reference' => $methodName !== '' ? $methodName.' · '.$reference : $reference,
        ])->save();

        return redirect()
            ->route('checkout.show', $order)
            ->with('success', 'Payment reference received — an admin will verify it shortly.');
    }

    /**
     * T13 (v1.5.0): the buyer's library — every order → items → license
     * state chip, pack rows expand to their granted prompts. Pure reads of
     * existing grants/orders; no money writes.
     */
    public function purchases(Request $request)
    {
        $user = $request->user();

        // Hotfix directive: the grants table renders creator rows with the
        // frame overlay — eager-load creator + frame with the grants too.
        $grants = $user->licenseGrants()
            ->with(['prompt.category', 'prompt.creator.activeFrame', 'prompt.latestVersion', 'orderItem.order', 'orderItem.pack'])
            ->latest()
            ->get();

        $orders = Order::query()
            ->where('buyer_id', $user->id)
            ->with(['items.pack', 'items.product.prompt.creator.activeFrame', 'items.product.prompt.creator', 'items.product.prompt', 'items.licenseGrant'])
            ->latest()
            ->get();

        return view('purchases.index', [
            'grants' => $grants,
            'orders' => $orders,
        ]);
    }

    /**
     * T13 (v1.5.0): re-download the full prompt body for an owned listing.
     * Gated by the same entitlement check as the full-body view: an ACTIVE
     * grant (owner-level access) or staff moderation preview — never a raw
     * paywall bypass.
     */
    public function redownload(Request $request, Prompt $prompt)
    {
        $user = $request->user();

        $hasActiveGrant = \App\Models\LicenseGrant::query()
            ->where('user_id', $user->id)
            ->where('prompt_id', $prompt->id)
            ->where('status', \App\Models\LicenseGrant::STATUS_ACTIVE)
            ->exists();

        $isOwner = $prompt->user_id === $user->id;

        abort_unless($hasActiveGrant || $isOwner, 403, 'No active license for this prompt.');

        $latest = $prompt->latestVersion;

        $filename = 'promptsewa-'.$prompt->slug.'-v'.($latest?->version_number ?? 1).'.txt';

        return response()->streamDownload(function () use ($latest, $prompt): void {
            echo "# {$prompt->title}\n";
            echo "# License: personal/commercial per purchase — do not resell.\n\n";
            echo (string) $latest?->body;
            echo "\n\n## Variables\n";
            foreach ($latest?->variableNames() ?? [] as $variable) {
                echo "- {{$variable}}\n";
            }
        }, $filename, ['Content-Type' => 'text/plain; charset=UTF-8']);
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
