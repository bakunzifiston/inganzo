<?php

namespace App\Notifications;

use App\Models\Order;

class OrderPaid extends MarketplaceNotification
{
    public function __construct(public Order $order) {}

    protected function subject(): string
    {
        return __('Payment confirmed for order :number', ['number' => $this->order->number]);
    }

    protected function line(): string
    {
        return __('Thank you! Your payment was confirmed and your items are being sent to our printing partner.');
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('orders.track', $this->order->tracking_token);
    }
}
