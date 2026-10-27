<?php

namespace App\Services\Fulfillment;

use App\Enums\OrderItemStatus;
use App\Enums\ProductionJobStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\ShipmentUpdated;
use App\Services\Finance\SaleRecorder;
use App\Services\Payments\PaymentService;
use App\Support\Reference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Dispatch and delivery tracking. One order may have several shipments (partial fulfilment).
 */
class ShipmentService
{
    public function __construct(
        private OrderProgress $progress,
        private SaleRecorder $sales,
        private PaymentService $payments,
    ) {}

    /**
     * @param  list<int>  $orderItemIds  items that are ready for dispatch
     * @param  int|null  $partnerId  restricts the shipment to jobs of this partner (partner staff)
     */
    public function dispatch(Order $order, array $orderItemIds, string $carrier, ?string $trackingNumber, ?string $trackingUrl, User $actor, ?int $partnerId = null): Shipment
    {
        $shipment = DB::transaction(function () use ($order, $orderItemIds, $carrier, $trackingNumber, $trackingUrl, $actor, $partnerId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $items = $order->items()->whereIn('id', $orderItemIds)->with('activeJob')->lockForUpdate()->get();

            if ($items->isEmpty() || $items->count() !== count(array_unique($orderItemIds))) {
                throw ValidationException::withMessages(['items' => __('Select items from this order to ship.')]);
            }

            $jobPartners = [];
            foreach ($items as $item) {
                $job = $item->activeJob;
                if ($job === null || $job->status !== ProductionJobStatus::ReadyForDispatch) {
                    throw ValidationException::withMessages(['items' => __(':title has not passed quality control yet.', ['title' => $item->title])]);
                }
                if ($partnerId !== null && $job->printing_partner_id !== $partnerId) {
                    throw ValidationException::withMessages(['items' => __('You can only ship items produced by your facility.')]);
                }
                $jobPartners[] = $job->printing_partner_id;
            }

            $shipment = Shipment::query()->create([
                'reference' => Reference::generate('SHP', Shipment::class),
                'order_id' => $order->id,
                'printing_partner_id' => count(array_unique($jobPartners)) === 1 ? $jobPartners[0] : null,
                'carrier' => $carrier,
                'tracking_number' => $trackingNumber ?: null,
                'tracking_url' => $trackingUrl ?: null,
                'status' => ShipmentStatus::Dispatched,
                'created_by' => $actor->id,
                'dispatched_at' => now(),
            ]);

            foreach ($items as $item) {
                $shipment->items()->create(['order_item_id' => $item->id, 'quantity' => $item->quantity]);
                $item->forceFill(['fulfillment_status' => OrderItemStatus::Shipped])->save();

                $job = $item->activeJob;
                $job->forceFill(['status' => ProductionJobStatus::Dispatched, 'dispatched_at' => now()])->save();
                $job->events()->create(['user_id' => $actor->id, 'type' => 'status', 'from_status' => ProductionJobStatus::ReadyForDispatch->value, 'to_status' => ProductionJobStatus::Dispatched->value, 'notes' => $shipment->reference, 'created_at' => now()]);
                $this->sales->recordProductionCost($job);
            }

            $shipment->events()->create(['user_id' => $actor->id, 'status' => ShipmentStatus::Dispatched, 'notes' => $carrier, 'occurred_at' => now(), 'created_at' => now()]);
            $this->progress->sync($order);

            return $shipment;
        });

        $this->notifyCustomer($shipment);

        return $shipment;
    }

    /**
     * An artist ships ready-made items from their own stock. Only that artist's finished items
     * that are paid (or cash on delivery) and still awaiting shipment can be included.
     *
     * @param  list<int>  $orderItemIds
     */
    public function dispatchFinished(Order $order, array $orderItemIds, string $carrier, ?string $trackingNumber, ?string $trackingUrl, User $actor, int $artistProfileId): Shipment
    {
        $shipment = DB::transaction(function () use ($order, $orderItemIds, $carrier, $trackingNumber, $trackingUrl, $actor, $artistProfileId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $items = $order->items()->whereIn('id', $orderItemIds)->lockForUpdate()->get();

            if ($items->isEmpty() || $items->count() !== count(array_unique($orderItemIds))) {
                throw ValidationException::withMessages(['items' => __('Select items from this order to ship.')]);
            }
            if (! $order->isPaid() && $order->payment_method !== 'cash_on_delivery') {
                throw ValidationException::withMessages(['items' => __('This order has not been paid yet.')]);
            }
            foreach ($items as $item) {
                if (! $item->isFinished() || $item->artist_profile_id !== $artistProfileId) {
                    throw ValidationException::withMessages(['items' => __('You can only ship your own ready-made products.')]);
                }
                if ($item->fulfillment_status !== OrderItemStatus::AwaitingShipment) {
                    throw ValidationException::withMessages(['items' => __(':title cannot be shipped right now.', ['title' => $item->title])]);
                }
            }

            $shipment = Shipment::query()->create([
                'reference' => Reference::generate('SHP', Shipment::class),
                'order_id' => $order->id,
                'printing_partner_id' => null,
                'carrier' => $carrier,
                'tracking_number' => $trackingNumber ?: null,
                'tracking_url' => $trackingUrl ?: null,
                'status' => ShipmentStatus::Dispatched,
                'created_by' => $actor->id,
                'dispatched_at' => now(),
            ]);

            foreach ($items as $item) {
                $shipment->items()->create(['order_item_id' => $item->id, 'quantity' => $item->quantity]);
                $item->forceFill(['fulfillment_status' => OrderItemStatus::Shipped])->save();
            }

            $shipment->events()->create(['user_id' => $actor->id, 'status' => ShipmentStatus::Dispatched, 'notes' => $carrier, 'occurred_at' => now(), 'created_at' => now()]);
            $this->progress->sync($order);

            return $shipment;
        });

        $this->notifyCustomer($shipment);

        return $shipment;
    }

    /**
     * Record a tracking event. "delivered" marks every item in the shipment delivered and,
     * for cash-on-delivery orders, confirms the collected payment.
     */
    public function recordEvent(Shipment $shipment, ShipmentStatus $status, User $actor, ?string $location = null, ?string $notes = null, bool $cashCollected = false): Shipment
    {
        $shipment = DB::transaction(function () use ($shipment, $status, $actor, $location, $notes) {
            $shipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if (in_array($shipment->status, [ShipmentStatus::Delivered, ShipmentStatus::Returned], true)) {
                throw ValidationException::withMessages(['status' => __('This shipment is closed.')]);
            }
            if ($status === ShipmentStatus::Dispatched) {
                throw ValidationException::withMessages(['status' => __('This shipment has already been dispatched.')]);
            }

            $shipment->events()->create(['user_id' => $actor->id, 'status' => $status, 'location' => $location, 'notes' => $notes, 'occurred_at' => now(), 'created_at' => now()]);
            $shipment->status = $status;

            if ($status === ShipmentStatus::Delivered) {
                $shipment->delivered_at = now();
                foreach ($shipment->items()->with('orderItem')->get() as $line) {
                    $line->orderItem->forceFill(['fulfillment_status' => OrderItemStatus::Delivered, 'delivered_at' => now()])->save();
                }
            }

            $shipment->save();

            return $shipment;
        });

        $order = $shipment->order;

        if ($status === ShipmentStatus::Delivered && $cashCollected && $order->payment_method === 'cash_on_delivery' && ! $order->isPaid()) {
            $this->payments->confirmCashCollected($order, $actor);
        }

        $this->progress->sync($order->fresh());
        $this->notifyCustomer($shipment);

        return $shipment;
    }

    private function notifyCustomer(Shipment $shipment): void
    {
        $order = $shipment->order;
        $notification = new ShipmentUpdated($shipment);

        $order->user
            ? $order->user->notify($notification)
            : Notification::route('mail', $order->email)->notify($notification);
    }
}
