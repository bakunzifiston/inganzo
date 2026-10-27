<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PrintingPartner;
use App\Models\ProductionJob;
use App\Models\Refund;
use App\Models\Shipment;
use App\Services\AuditLogger;
use App\Services\Checkout\CheckoutService;
use App\Services\Finance\RefundService;
use App\Services\Fulfillment\PartnerAssigner;
use App\Services\Fulfillment\ShipmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.orders.index', [
            'orders' => $this->filtered($request)->with('deliveryZone')->withCount('items')->latest('placed_at')->paginate(25)->withQueryString(),
            'filters' => $request->only(['q', 'status', 'payment_status', 'fulfillment_status', 'from', 'to']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('deliveryZone')->latest('placed_at');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['number', 'placed_at', 'paid_at', 'customer', 'email', 'phone', 'zone', 'status', 'payment_status', 'fulfillment_status', 'payment_method', 'currency', 'subtotal', 'discount', 'delivery', 'tax', 'total', 'refunded']);
            $query->chunk(500, function ($orders) use ($out) {
                foreach ($orders as $order) {
                    fputcsv($out, [
                        $order->number, $order->placed_at?->toIso8601String(), $order->paid_at?->toIso8601String(), $order->customer_name, $order->email, $order->phone,
                        $order->deliveryZone?->name, $order->status->value, $order->payment_status->value, $order->fulfillment_status->value, $order->payment_method,
                        $order->currency, $order->subtotal_amount, $order->discount_amount, $order->delivery_amount, $order->tax_amount, $order->total_amount, $order->refunded_amount,
                    ]);
                }
            });
            fclose($out);
        }, 'orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function show(Order $order, PartnerAssigner $assigner, RefundService $refunds): View
    {
        $order->load([
            'items.productionJobs.partner', 'items.productionJobs.events.user', 'items.artist', 'items.artworkVersion',
            'items.artistProduct.product.printAreas', 'items.artistProduct.product.mockups', 'items.variant.color',
            'address', 'payments.events', 'refunds.requester', 'shipments.events', 'shipments.items.orderItem', 'shipments.partner',
            'user', 'deliveryZone', 'discountCode', 'country',
        ]);

        $partnerOptions = $order->items->mapWithKeys(fn (OrderItem $item) => [
            $item->id => $item->isFinished() ? ['eligible' => [], 'all' => collect()] : [
                'eligible' => $order->isPaid() || $order->payment_method === 'cash_on_delivery' ? $assigner->eligiblePartners($item)->pluck('id')->all() : [],
                'all' => PrintingPartner::query()->where('is_active', true)
                    ->whereHas('services', fn (Builder $q) => $q->where('product_id', $item->product_id)->where('is_active', true))
                    ->orderBy('name')->get(['id', 'name']),
            ],
        ]);

        return view('admin.orders.show', [
            'order' => $order,
            'partnerOptions' => $partnerOptions,
            'maxRefund' => $refunds->maxRefundable($order, null),
            'readyItems' => $order->items->filter(fn ($i) => $i->fulfillment_status->value === 'ready'),
            'audit' => AuditLog::query()->with('user')
                ->where(fn ($q) => $q->where(fn ($q) => $q->where('subject_type', $order->getMorphClass())->where('subject_id', $order->id))
                    ->orWhere(fn ($q) => $q->where('subject_type', (new Refund)->getMorphClass())->whereIn('subject_id', $order->refunds->pluck('id')))
                    ->orWhere(fn ($q) => $q->where('subject_type', (new ProductionJob)->getMorphClass())->whereIn('subject_id', $order->items->flatMap->productionJobs->pluck('id')))
                    ->orWhere(fn ($q) => $q->where('subject_type', (new Payment)->getMorphClass())->whereIn('subject_id', $order->payments->pluck('id'))))
                ->latest('created_at')->limit(50)->get(),
        ]);
    }

    public function assign(Request $request, Order $order, OrderItem $item, PartnerAssigner $assigner): RedirectResponse
    {
        abort_unless($item->order_id === $order->id, 404);
        $validated = $request->validate(['printing_partner_id' => ['required', 'integer', Rule::exists('printing_partners', 'id')->where('is_active', true)]]);

        $job = $assigner->assign($item, PrintingPartner::query()->findOrFail($validated['printing_partner_id']), $request->user());

        return back()->with('success', __('Item assigned to :partner (:ref).', ['partner' => $job->partner->name, 'ref' => $job->reference]));
    }

    public function cancel(Request $request, Order $order, CheckoutService $checkout, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($order->isPaid() || $order->status !== OrderStatus::PendingPayment) {
            return back()->with('error', __('Only unpaid orders can be cancelled here. Paid orders must be refunded.'));
        }

        $checkout->cancelUnpaid($order, $validated['reason']);
        $audit->log('order.cancelled', $order, ['reason' => $validated['reason']]);

        return back()->with('success', __('Order cancelled.'));
    }

    public function ship(Request $request, Order $order, ShipmentService $shipments): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
            'carrier' => ['required', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:80'],
            'tracking_url' => ['nullable', 'url', 'max:255'],
        ]);

        $shipment = $shipments->dispatch($order, array_map('intval', $validated['items']), $validated['carrier'], $validated['tracking_number'] ?? null, $validated['tracking_url'] ?? null, $request->user());

        return back()->with('success', __('Shipment :ref dispatched.', ['ref' => $shipment->reference]));
    }

    public function shipmentEvent(Request $request, Shipment $shipment, ShipmentService $shipments): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ShipmentStatus::class)->except([ShipmentStatus::Dispatched])],
            'location' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'cash_collected' => ['sometimes', 'boolean'],
        ]);

        $shipments->recordEvent($shipment, ShipmentStatus::from($validated['status']), $request->user(), $validated['location'] ?? null, $validated['notes'] ?? null, $request->boolean('cash_collected'));

        return back()->with('success', __('Shipment updated.'));
    }

    /**
     * @return Builder<Order>
     */
    private function filtered(Request $request): Builder
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(OrderPaymentStatus::class)],
            'fulfillment_status' => ['nullable', Rule::enum(FulfillmentStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return Order::query()
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($request->string('q'))).'%';
                $q->where(fn (Builder $w) => $w->where('number', 'ilike', $term)->orWhere('email', 'ilike', $term)->orWhere('customer_name', 'ilike', $term)->orWhere('phone', 'ilike', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('fulfillment_status'), fn ($q) => $q->where('fulfillment_status', $request->string('fulfillment_status')))
            ->when($request->filled('from'), fn ($q) => $q->where('placed_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('placed_at', '<=', $request->date('to')->endOfDay()));
    }
}
