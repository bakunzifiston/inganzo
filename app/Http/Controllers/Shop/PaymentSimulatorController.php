<?php

namespace App\Http\Controllers\Shop;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\Gateways\SimulatorGateway;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT ONLY payment simulator. Approving or declining builds a signed callback and
 * feeds it through the same PaymentService::handleCallback pipeline as real providers.
 */
class PaymentSimulatorController extends Controller
{
    public function show(Payment $payment, SimulatorGateway $gateway): View
    {
        abort_unless($gateway->isConfigured() && $payment->provider === 'simulator', 404);

        return view('shop.payment-simulator', ['payment' => $payment->load('order')]);
    }

    public function complete(Request $request, Payment $payment, SimulatorGateway $gateway, PaymentService $payments): RedirectResponse
    {
        abort_unless($gateway->isConfigured() && $payment->provider === 'simulator', 404);

        $outcome = $request->validate(['outcome' => ['required', 'in:approve,decline']])['outcome'];

        if ($payment->status === PaymentStatus::Pending) {
            $body = json_encode([
                'event_id' => 'sim_'.Str::uuid(),
                'reference' => $payment->provider_reference,
                'status' => $outcome === 'approve' ? 'SUCCESSFUL' : 'FAILED',
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reason' => $outcome === 'approve' ? null : 'Declined in simulator',
            ]);

            $callback = Request::create(route('api.v1.payments.callback', 'simulator'), 'POST', server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIMULATOR_SIGNATURE' => SimulatorGateway::sign($body),
            ], content: $body);

            $payments->handleCallback('simulator', $callback);
        }

        return redirect()->route('orders.track', $payment->order->tracking_token);
    }
}
