<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->hasPermission(Ability::OrdersView) || $user->isSuperAdmin();
    }

    public function requestRefund(User $user, Order $order): bool
    {
        return $order->user_id === $user->id && $order->isPaid();
    }

    public function manage(User $user, Order $order): bool
    {
        return $user->hasPermission(Ability::OrdersManage) || $user->isSuperAdmin();
    }
}
