<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Frame;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

/**
 * S5 (v1.8.0) — the membership storefront.
 *
 * Membership plans ride the ordinary NPR rails: this controller only
 * creates a PENDING order (one membership line). Activation — membership
 * row + first stipend + perk grants — happens on the paid transition via
 * OrderObserver → MembershipService, so manual approval and eSewa settle
 * share one idempotent path.
 */
class MembershipController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
    ) {}

    /** Public plan catalog + the visitor's own memberships. */
    public function index(Request $request)
    {
        $user = $request->user();

        $memberships = $user !== null
            ? Membership::query()
                ->where('user_id', $user->id)
                ->with('plan')
                ->latest('starts_at')
                ->get()
            : collect();

        return view('memberships.index', [
            'plans' => MembershipPlan::query()
                ->where('active', true)
                ->orderBy('price_paisa')
                ->get(),
            'memberships' => $memberships,
            // Perk pickers resolve names from the same catalogs the admin
            // desk uses; unknown ids degrade to nothing rather than a 500.
            'badges' => Badge::query()->orderBy('name')->pluck('name', 'id'),
            'frames' => Frame::query()->orderBy('name')->pluck('name', 'id'),
            // While the Sikka economy is off, plans render without their
            // stipend promise — the kill-switch keeps zero Sikka markup on
            // every public surface, this one included.
            'sikkaEnabled' => app(SettingsService::class)->isOn('sikka_enabled'),
        ]);
    }

    /** Buy a plan: a pending NPR-rail order, then the ordinary checkout. */
    public function buy(Request $request, MembershipPlan $plan)
    {
        abort_unless($plan->active, 404);

        /** @var array{order: Order, created: bool} $result */
        $result = $this->checkout->createMembershipOrder($request->user(), $plan);

        return redirect()->route('checkout.show', $result['order']);
    }
}
