<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin order desk: browse payment history, approve or reject manual
 * payments. Approval runs inside the same transaction that fulfills the
 * order — grants follow payment confirmation in one atomic step.
 */
class OrderAdminController extends Controller
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {}

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

        DB::transaction(function () use ($order) {
            $order->fill([
                'status' => Order::STATUS_PAID,
                'paid_at' => now(),
            ])->save();

            $this->entitlements->fulfill($order->refresh());
        });

        return back()->with('success', "Order #{$order->id} approved — licenses granted.");
    }

    public function reject(Request $request, Order $order)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can reject payments.');

        if (! $order->isPending()) {
            return back()->withErrors(['order' => "Order #{$order->id} is already {$order->status}."]);
        }

        $order->transitionTo(Order::STATUS_FAILED);

        return back()->with('success', "Order #{$order->id} rejected.");
    }
}
