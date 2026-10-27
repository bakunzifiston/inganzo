<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * Derives the order-level fulfilment status (and completion) from its items.
 */
class OrderProgress
{
    public function sync(Order $order): void
    {
        $order->load('items');
        $items = $order->items->reject(fn ($item) => in_array($item->fulfillment_status, [OrderItemStatus::Cancelled, OrderItemStatus::Refunded], true));

        if ($items->isEmpty()) {
            return;
        }

        $count = fn (OrderItemStatus ...$statuses) => $items->filter(fn ($i) => in_array($i->fulfillment_status, $statuses, true))->count();
        $total = $items->count();
        $delivered = $count(OrderItemStatus::Delivered);
        $shipped = $count(OrderItemStatus::Shipped) + $delivered;
        $started = $count(OrderItemStatus::Assigned, OrderItemStatus::InProduction, OrderItemStatus::Ready) + $shipped;

        $status = match (true) {
            $delivered === $total => FulfillmentStatus::Delivered,
            $delivered > 0 => FulfillmentStatus::PartiallyDelivered,
            $shipped === $total => FulfillmentStatus::Shipped,
            $shipped > 0 => FulfillmentStatus::PartiallyShipped,
            $started > 0 => FulfillmentStatus::InProduction,
            default => FulfillmentStatus::Unfulfilled,
        };

        $order->fulfillment_status = $status;

        if ($status === FulfillmentStatus::Delivered && $order->isPaid() && $order->status === OrderStatus::Processing) {
            $order->status = OrderStatus::Completed;
            $order->completed_at = now();
        }

        $order->save();
    }
}
