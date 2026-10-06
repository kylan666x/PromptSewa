<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Frame;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

/**
 * S5 (v1.8.0) — the membership storefront.
 *
 * S1b (v1.9.0): plans are bought with Sikka credits. This controller only
 * creates a PENDING order (one membership line) and hands it to the
 * checkout page, where the Sikka rail pays it (or shows the top-up CTA
 * when the balance is short). Activation — membership row + first stipend
 * + perk grants — happens on the paid transition via OrderObserver →
 * MembershipService, so the credit rail and any legacy settle path share
 * one idempotent activation.
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
            // S1b (v1.9.0): the storefront prices in Sikka unconditionally —
            // the kill-switch is retired and Sikka is the permanent economy,
            // so there is no flag left for this page to read.
        ]);
    }

    /** Buy a plan: a pending Sikka-rail order, then the ordinary checkout. */
    public function buy(Request $request, MembershipPlan $plan)
    {
        abort_unless($plan->active, 404);

        /** @var array{order: Order, created: bool} $result */
        $result = $this->checkout->createMembershipOrder($request->user(), $plan);

        return redirect()->route('checkout.show', $result['order']);
    }
}
