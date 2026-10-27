<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderItemStatus;
use App\Enums\RefundReason;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Services\Finance\RefundService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.orders.index', [
            'orders' => $request->user()->orders()->with('items')->latest('placed_at')->paginate(15),
        ]);
    }

    public function show(Request $request, Order $order, RefundService $refunds): View
    {
        Gate::authorize('view', $order);

        $order->load([
            'items.artistProduct.product.printAreas', 'items.artistProduct.product.mockups', 'items.artworkVersion',
            'items.variant.color', 'items.review', 'address', 'shipments.events', 'shipments.items.orderItem',
            'refunds.orderItem', 'payments', 'deliveryZone',
        ]);

        return view('account.orders.show', [
            'order' => $order,
            'canRequestRefund' => $request->user()->can('requestRefund', $order)
                && $order->refunds->whereIn('status.value', ['requested', 'approved'])->isEmpty(),
            'maxRefundable' => $refunds->maxRefundable($order, null),
        ]);
    }

    public function requestRefund(Request $request, Order $order, RefundService $refunds): RedirectResponse
    {
        Gate::authorize('requestRefund', $order);

        $validated = $request->validate([
            'reason' => ['required', Rule::enum(RefundReason::class)],
            'order_item_id' => ['nullable', 'integer', Rule::exists('order_items', 'id')->where('order_id', $order->id)],
            'amount' => ['required', 'numeric', 'min:1'],
            'customer_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $item = isset($validated['order_item_id']) ? $order->items()->findOrFail($validated['order_item_id']) : null;

        $refund = $refunds->request(
            $order,
            $item,
            Money::toMinor((string) $validated['amount'], $order->currency),
            RefundReason::from($validated['reason']),
            $validated['customer_note'],
            $request->user(),
        );

        return redirect()->route('account.orders.show', $order)
            ->with('success', __('Refund request :ref received. We will review it within two business days.', ['ref' => $refund->reference]));
    }

    public function review(Request $request, Order $order, OrderItem $item): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id && $item->order_id === $order->id, 404);

        if ($item->fulfillment_status !== OrderItemStatus::Delivered) {
            return back()->withErrors(['rating' => __('You can review an item once it has been delivered.')]);
        }

        if ($item->review()->exists()) {
            return back()->withErrors(['rating' => __('You have already reviewed this item.')]);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        Review::query()->create($validated + [
            'order_item_id' => $item->id,
            'user_id' => $request->user()->id,
            'artist_product_id' => $item->artist_product_id,
            'status' => 'published',
        ]);

        return back()->with('success', __('Thank you for your review!'));
    }
}
