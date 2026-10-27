<?php

namespace App\Notifications;

use App\Models\Refund;
use App\Support\Money;

class RefundUpdated extends MarketplaceNotification
{
    public function __construct(public Refund $refund) {}

    protected function subject(): string
    {
        return __('Refund :ref: :status', ['ref' => $this->refund->reference, 'status' => $this->refund->status->label()]);
    }

    protected function line(): string
    {
        return __('Your refund of :amount for order :number is now ":status".', [
            'amount' => Money::format($this->refund->amount, $this->refund->currency),
            'number' => $this->refund->order->number,
            'status' => $this->refund->status->label(),
        ]);
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('orders.track', $this->refund->order->tracking_token);
    }
}
