<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin order desk: browse payment history, approve or reject manual
 * payments. Approval runs through the WalletService::settleOrder choke
 * point — grants AND creator credits follow payment confirmation in one
 * atomic, idempotent step (M2, v1.6.0).
 */
class OrderAdminController extends Controller
{

    public function index(Request $request)
    {
        $status = (string) $request->query('status', '');

        $orders = Order::query()
            ->with(['buyer', 'items.pack', 'items.product.prompt'])
            ->when($status !== '' && in_array($status, [Order::STATUS_PENDING, Order::STATUS_PAID, Order::STATUS_FAILED, Order::STATUS_REFUNDED], true),
                fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders', [
            'orders' => $orders,
            'currentStatus' => $status,
        ]);
    }

    public function approve(Request $request, Order $order)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can approve payments.');

        if (! $order->isPending()) {
            return back()->withErrors(['order' => "Order #{$order->id} is already {$order->status}."]);
        }

        // M2 (v1.6.0): approval goes through the WalletService choke point —
        // paid guard + grants + creator credits, one transaction, idempotent.
        // No controller may duplicate this pipeline (M6 arch test).
        // F6 (v1.7.8): the buyer's bell row commits with the settlement.
        DB::transaction(function () use ($order) {
            app(\App\Services\WalletService::class)->settleOrder($order, 'manual');

            if ($order->buyer !== null) {
                Notification::emit(
                    $order->buyer,
                    Notification::TYPE_ORDER_APPROVED,
                    "Your order #{$order->id} was approved — your prompts are unlocked.",
                    $order,
                );
            }
        });

        return back()->with('success', "Order #{$order->id} approved — licenses granted.");
    }

    public function reject(Request $request, Order $order)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can reject payments.');

        if (! $order->isPending()) {
            return back()->withErrors(['order' => "Order #{$order->id} is already {$order->status}."]);
        }

        DB::transaction(function () use ($order) {
            $order->transitionTo(Order::STATUS_FAILED);

            if ($order->buyer !== null) {
                Notification::emit(
                    $order->buyer,
                    Notification::TYPE_ORDER_REJECTED,
                    "Your order #{$order->id} was rejected.",
                    $order,
                );
            }
        });

        return back()->with('success', "Order #{$order->id} rejected.");
    }
}
