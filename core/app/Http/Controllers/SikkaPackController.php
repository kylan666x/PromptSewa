<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SikkaPack;
use App\Services\CheckoutService;
use App\Services\SettingsService;
use App\Services\SikkaService;
use Illuminate\Http\Request;

/**
 * S6 (v1.8.0) — the Sikka top-up storefront (`/sikka`).
 *
 * Packs are sold through the ordinary NPR rails: buying creates a pending
 * order with one sikka-pack line, and the credits land on payment
 * approval (OrderObserver → SikkaService::topupCredit). While the
 * kill-switch is off the surface does not exist (404) — zero Sikka markup
 * anywhere, exactly like every other Sikka door.
 */
class SikkaPackController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly SettingsService $settings,
        private readonly SikkaService $sikka,
    ) {}

    public function index(Request $request)
    {
        abort_unless($this->settings->isOn('sikka_enabled'), 404);

        $user = $request->user();
        $buyPaisa = (int) ($this->settings->get('sikka_buy_paisa_per_token', '100') ?? '100');

        return view('sikka.index', [
            'packs' => SikkaPack::query()
                ->where('active', true)
                ->orderBy('price_paisa')
                ->get(),
            'spendable' => $user !== null ? $this->sikka->spendableAvailable($user) : null,
            'buyPaisa' => max(1, min(500, $buyPaisa)),
        ]);
    }

    /** Buy a pack: a pending NPR-rail order, then the ordinary checkout. */
    public function buy(Request $request, SikkaPack $pack)
    {
        abort_unless($this->settings->isOn('sikka_enabled'), 404);
        abort_unless($pack->active, 404);

        /** @var array{order: Order, created: bool} $result */
        $result = $this->checkout->createSikkaPackOrder($request->user(), $pack);

        return redirect()->route('checkout.show', $result['order']);
    }
}
