<?php

namespace App\Http\Controllers\Shop;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Rules\ValidPhone;
use App\Services\Payments\GatewayRegistry;
use App\Services\Payments\PaymentService;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Order status for anyone holding the secret tracking link (guests included).
 * Payment state shown here always comes from the server – query parameters added by
 * a provider redirect are ignored.
 */
class OrderTrackingController extends Controller
{
    public function lookup(): View
    {
        return view('shop.track-lookup');
    }

    public function find(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email'],
        ]);

        $order = Order::query()
            ->where('number', mb_strtoupper(trim($validated['number'])))
            ->where('email', mb_strtolower(trim($validated['email'])))
            ->first();

        if ($order === null) {
            return back()->withInput()->withErrors(['number' => __('We could not find an order with that number and e-mail address.')]);
        }

        return redirect()->route('orders.track', $order->tracking_token);
    }

    public function show(string $token, GatewayRegistry $gateways): View
    {
        $order = $this->order($token);
        $order->load([
            'items.artistProduct.product.printAreas', 'items.artistProduct.product.mockups', 'items.artworkVersion',
            'items.artistProduct.placements', 'items.variant.color', 'address', 'payments', 'shipments.events', 'shipments.items',
            'deliveryZone', 'refunds',
        ]);

        return view('shop.track', [
            'order' => $order,
            'pendingPayment' => $order->payments->first(fn ($p) => $p->status === PaymentStatus::Pending && $p->provider !== 'cash_on_delivery'),
            'gateways' => $order->isAwaitingPayment() ? $gateways->availableFor($order->country->iso2, $order->currency) : [],
        ]);
    }

    public function paymentStatus(string $token, PaymentService $payments): JsonResponse
    {
        $order = $this->order($token);
        $payment = $order->payments()->where('status', PaymentStatus::Pending)->where('provider', '!=', 'cash_on_delivery')->latest('id')->first();

        // Ask the provider only every few seconds per payment to respect their rate limits.
        if ($payment && cache()->add('payment-poll:'.$payment->id, true, 5)) {
            $payment = $payments->refresh($payment);
        }

        $order->refresh();

        return response()->json([
            'paid' => $order->isPaid(),
            'status' => $payment?->status->value ?? ($order->isPaid() ? 'succeeded' : $order->payment_status->value),
            'message' => $payment?->failure_reason,
        ]);
    }

    public function pay(Request $request, string $token, PaymentService $payments, GatewayRegistry $gateways): RedirectResponse
    {
        $order = $this->order($token);
        $country = $order->country;
        $methods = array_map(fn ($g) => $g->key(), $gateways->availableFor($country->iso2, $order->currency));

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in($methods)],
            'payer_phone' => ['nullable', 'string', new ValidPhone($country)],
        ]);

        $payment = $payments->initiate($order, $validated['payment_method'], isset($validated['payer_phone']) ? PhoneNumber::normalize($validated['payer_phone'], $country) : null);

        return isset($payment->meta['redirect_url'])
            ? redirect()->to($payment->meta['redirect_url'])
            : redirect()->route('orders.track', $order->tracking_token);
    }

    private function order(string $token): Order
    {
        abort_unless(strlen($token) === 48, 404);

        return Order::query()->where('tracking_token', $token)->firstOrFail();
    }
}
