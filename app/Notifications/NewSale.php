<?php

namespace App\Notifications;

use App\Models\Order;

class NewSale extends MarketplaceNotification
{
    /**
     * @param  list<int>  $orderItemIds
     */
    public function __construct(public Order $order, public array $orderItemIds) {}

    protected function subject(): string
    {
        return __('You made a sale!');
    }

    protected function line(): string
    {
        return trans_choice('A customer bought :count item featuring your designs.|A customer bought :count items featuring your designs.', count($this->orderItemIds), ['count' => count($this->orderItemIds)]);
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('studio.sales');
    }
}
