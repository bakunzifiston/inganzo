<?php

namespace App\Http\Resources\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'placed_at' => $this->placed_at->toIso8601String(),
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'fulfillment_status' => $this->fulfillment_status->value,
            'currency' => $this->currency,
            'totals' => [
                'subtotal' => $this->subtotal_amount,
                'discount' => $this->discount_amount,
                'delivery' => $this->delivery_amount,
                'tax_included' => $this->tax_amount,
                'total' => $this->total_amount,
                'refunded' => $this->refunded_amount,
            ],
            'tracking_url' => route('orders.track', $this->tracking_token),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'title' => $item->title,
                'product' => $item->product_name,
                'variant' => $item->variantLabel(),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price_amount,
                'line_total' => $item->line_total_amount,
                'status' => $item->fulfillment_status->value,
            ])),
            'shipments' => $this->whenLoaded('shipments', fn () => $this->shipments->map(fn ($shipment) => [
                'reference' => $shipment->reference,
                'carrier' => $shipment->carrier,
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status->value,
                'events' => $shipment->events->map(fn ($e) => ['status' => $e->status->value, 'location' => $e->location, 'at' => $e->occurred_at->toIso8601String()]),
            ])),
        ];
    }
}
