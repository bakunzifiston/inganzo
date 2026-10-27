<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;

class OrderPlaced extends MarketplaceNotification
{
    public function __construct(public Order $order) {}

    protected function subject(): string
    {
        return __('Order :number received', ['number' => $this->order->number]);
    }

    protected function line(): string
    {
        return __('We have received your order for :total. Production starts as soon as payment is confirmed.', ['total' => Money::format($this->order->total_amount, $this->order->currency)]);
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('orders.track', $this->order->tracking_token);
    }
}
