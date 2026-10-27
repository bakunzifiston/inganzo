<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\CallbackData;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;

/**
 * DEVELOPMENT ONLY. Simulates a mobile-money provider: the customer approves or declines
 * on a clearly labelled simulator page, which emits an HMAC-signed callback through the
 * same webhook pipeline real providers use. No money moves. Hard-disabled in production.
 */
class SimulatorGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'simulator';
    }

    public function label(): string
    {
        return __('Test payment (simulator)');
    }

    public function description(): string
    {
        return __('Development only — no real money is charged.');
    }

    public function isLive(): bool
    {
        return false;
    }

    public function isConfigured(): bool
    {
        return config('payments.providers.simulator.enabled') && ! app()->isProduction();
    }

    public function supports(string $countryIso2, string $currency): bool
    {
        return in_array($countryIso2, config('payments.providers.simulator.countries'), true)
            && in_array($currency, config('payments.providers.simulator.currencies'), true);
    }

    public function requiresPayerPhone(): bool
    {
        return true;
    }

    public function initiate(Payment $payment, Order $order): GatewayResult
    {
        $reference = 'SIM-'.$payment->idempotency_key;

        return GatewayResult::pending($reference, ['simulated' => true], route('payments.simulator.show', $payment));
    }

    public function parseCallback(Request $request): CallbackData
    {
        $payload = $request->json()->all();
        $signature = (string) $request->header('X-Simulator-Signature');

        return new CallbackData(
            providerReference: $payload['reference'] ?? null,
            dedupeKey: 'simulator:'.($payload['event_id'] ?? hash('sha256', $request->getContent())),
            signatureValid: hash_equals(self::sign($request->getContent()), $signature),
            payload: $payload,
            reportedStatus: match ($payload['status'] ?? null) {
                'SUCCESSFUL' => PaymentStatus::Succeeded,
                'FAILED' => PaymentStatus::Failed,
                default => PaymentStatus::Pending,
            },
            reportedAmount: isset($payload['amount']) ? (int) $payload['amount'] : null,
            reportedCurrency: $payload['currency'] ?? null,
        );
    }

    public function trustsSignedCallbacks(): bool
    {
        return true;
    }

    public function fetchStatus(Payment $payment): GatewayResult
    {
        // The simulator has no remote state: status changes only arrive via signed callbacks.
        return new GatewayResult($payment->status, $payment->provider_reference, $payment->provider_status, $payment->amount, $payment->currency);
    }

    public static function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('payments.providers.simulator.secret'));
    }
}
