<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Finance\RefundService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(RefundStatus::class)]]);

        return view('admin.refunds.index', [
            'refunds' => Refund::query()->with(['order', 'orderItem', 'requester'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')), fn ($q) => $q->orderByRaw("CASE status WHEN 'requested' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END"))
                ->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'order_item_id' => ['nullable', 'integer', Rule::exists('order_items', 'id')->where('order_id', $order->id)],
            'amount' => ['required', 'string', 'max:20'],
            'reason' => ['required', Rule::enum(RefundReason::class)],
            'provider_reference' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $item = isset($validated['order_item_id']) ? $order->items()->findOrFail($validated['order_item_id']) : null;
        $refund = $this->refunds->issue($order, $item, Money::toMinor($validated['amount'], $order->currency), RefundReason::from($validated['reason']), $request->user(), $validated['provider_reference'], $validated['note'] ?? null);

        return back()->with('success', __('Refund :ref processed.', ['ref' => $refund->reference]));
    }

    public function approve(Request $request, Refund $refund): RedirectResponse
    {
        $this->refunds->approve($refund, $request->user(), $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null);

        return back()->with('success', __('Refund approved. Send the money, then record the provider reference.'));
    }

    public function process(Request $request, Refund $refund): RedirectResponse
    {
        $validated = $request->validate(['provider_reference' => ['required', 'string', 'max:120']]);
        $this->refunds->process($refund, $request->user(), $validated['provider_reference']);

        return back()->with('success', __('Refund recorded as paid.'));
    }

    public function reject(Request $request, Refund $refund): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $this->refunds->reject($refund, $request->user(), $validated['note']);

        return back()->with('success', __('Refund declined.'));
    }
}
