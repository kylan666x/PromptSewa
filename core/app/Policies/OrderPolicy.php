<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * MKT-001 requirement 7: buyers may only view their own orders.
 * Moderators/Admins can view any order for support/disputes.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id || $user->isModerator();
    }
}
