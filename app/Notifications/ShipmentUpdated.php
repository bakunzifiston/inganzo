<?php

namespace App\Notifications;

use App\Models\Shipment;

class ShipmentUpdated extends MarketplaceNotification
{
    public function __construct(public Shipment $shipment) {}

    protected function subject(): string
    {
        return __('Order :number: :status', ['number' => $this->shipment->order->number, 'status' => $this->shipment->status->label()]);
    }

    protected function line(): string
    {
        return __('Shipment :ref via :carrier is now ":status".', [
            'ref' => $this->shipment->reference,
            'carrier' => $this->shipment->carrier,
            'status' => $this->shipment->status->label(),
        ]);
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('orders.track', $this->shipment->order->tracking_token);
    }
}
