<?php

namespace App\Http\Controllers\Studio;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\RefundReason;
use App\Enums\SellingType;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Services\Finance\RefundService;
use App\Services\Fulfillment\ShipmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Orders containing the signed-in artist's items, for both selling methods. Artists only ever see
 * their own lines. The customer's delivery details are shown only for ready-made items, because
 * the artist ships those; printed items are produced and delivered by printing partners.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $artist = $request->user()->artistProfile;
        $type = $request->string('type')->toString();
        $state = $request->string('state')->toString();
        $term = trim($request->string('q')->toString());

        $mine = fn ($q) => $q->where('artist_profile_id', $artist->id)
            ->when(in_array($type, [SellingType::PrintOnDemand->value, SellingType::Finished->value], true), fn ($w) => $w->where('selling_type', $type))
            ->when($state === 'to_ship', fn ($w) => $w->where('fulfillment_status', OrderItemStatus::AwaitingShipment->value))
            ->when($state === 'in_progress', fn ($w) => $w->whereIn('fulfillment_status', [OrderItemStatus::AwaitingAssignment->value, OrderItemStatus::Assigned->value, OrderItemStatus::InProduction->value, OrderItemStatus::Ready->value]))
            ->when($state === 'shipped', fn ($w) => $w->where('fulfillment_status', OrderItemStatus::Shipped->value))
            ->when($state === 'delivered', fn ($w) => $w->where('fulfillment_status', OrderItemStatus::Delivered->value))
            ->when($state === 'closed', fn ($w) => $w->whereIn('fulfillment_status', [OrderItemStatus::Cancelled->value, OrderItemStatus::Refunded->value]));

        $orders = Order::query()
            ->where('status', '!=', OrderStatus::PendingPayment->value)
            ->whereHas('items', $mine)
            ->when($term !== '', fn ($q) => $q->where('number', 'ilike', '%'.$term.'%'))
            ->with(['items' => fn ($q) => $mine($q)])
            ->latest('placed_at')
            ->paginate(20)
            ->withQueryString();

        return view('studio.orders.index', [
            'orders' => $orders,
            'filters' => ['type' => $type, 'state' => $state, 'q' => $term],
            'toShip' => OrderItem::query()->where('artist_profile_id', $artist->id)->where('selling_type', SellingType::Finished->value)->where('fulfillment_status', OrderItemStatus::AwaitingShipment->value)->count(),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $artist = $request->user()->artistProfile;
        $items = $this->ownItems($order, $artist->id);
        abort_if($items->isEmpty(), 404);

        $order->load(['address.country', 'shipments' => fn ($q) => $q->with(['items.orderItem', 'events'])->latest()]);
        $finished = $items->filter->isFinished();

        return view('studio.orders.show', [
            'order' => $order,
            'items' => $items,
            'finished' => $finished,
            'shipments' => $order->shipments->filter(fn (Shipment $s) => $s->items->isNotEmpty() && $s->items->every(fn ($line) => $line->orderItem->artist_profile_id === $artist->id)),
            'showAddress' => $finished->isNotEmpty() && $order->status !== OrderStatus::PendingPayment,
            'canShip' => $finished->where('fulfillment_status', OrderItemStatus::AwaitingShipment)->isNotEmpty(),
        ]);
    }

    public function ship(Request $request, Order $order, ShipmentService $shipments): RedirectResponse
    {
        $artist = $request->user()->artistProfile;
        abort_if($this->ownItems($order, $artist->id)->isEmpty(), 404);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
            'carrier' => ['required', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:80'],
            'tracking_url' => ['nullable', 'url', 'max:255'],
        ]);

        try {
            $shipments->dispatchFinished($order, array_map('intval', $validated['items']), $validated['carrier'], $validated['tracking_number'] ?? null, $validated['tracking_url'] ?? null, $request->user(), $artist->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', __('Marked as shipped. The customer has been notified.'));
    }

    public function delivered(Request $request, Order $order, Shipment $shipment, ShipmentService $shipments): RedirectResponse
    {
        $artist = $request->user()->artistProfile;
        $shipment->load('items.orderItem');

        abort_unless($shipment->order_id === $order->id
            && $shipment->items->isNotEmpty()
            && $shipment->items->every(fn ($line) => $line->orderItem->artist_profile_id === $artist->id && $line->orderItem->isFinished()), 404);

        try {
            $shipments->recordEvent($shipment, ShipmentStatus::Delivered, $request->user(), null, null, $request->boolean('cash_collected'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', __('Marked as delivered.'));
    }

    /**
     * The artist cannot supply a paid item. A refund request goes to the admin team, who
     * return the money; the stock reservation is released when the refund is processed.
     */
    public function cannotFulfil(Request $request, Order $order, OrderItem $item, RefundService $refunds): RedirectResponse
    {
        $artist = $request->user()->artistProfile;
        abort_unless($item->order_id === $order->id && $item->artist_profile_id === $artist->id && $item->isFinished(), 404);

        $validated = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']]);

        if ($item->fulfillment_status !== OrderItemStatus::AwaitingShipment) {
            return back()->withErrors(['note' => __('Only items that have not shipped yet can be cancelled.')]);
        }

        try {
            $refunds->request($order, $item, $refunds->maxRefundable($order, $item), RefundReason::ProductionFailure, $validated['note'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', __('Refund requested. Our team will review it and return the money to the customer.'));
    }

    /**
     * @return Collection<int, OrderItem>
     */
    private function ownItems(Order $order, int $artistId)
    {
        return $order->items()->where('artist_profile_id', $artistId)->orderBy('id')->get();
    }
}
