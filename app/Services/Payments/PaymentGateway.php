<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Contract for payment providers. Adding a provider means implementing this interface
 * and registering it in GatewayRegistry – checkout itself does not change.
 *
 * Security rules every implementation must follow:
 *  - initiate() must never report success unless the provider confirmed it synchronously.
 *  - parseCallback() must verify signatures where the provider supports them.
 *  - fetchStatus() is the source of truth; callbacks from providers that do not sign them
 *    (e.g. MTN MoMo) are only a trigger to query status.
 */
interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    /**
     * Shown to customers at checkout.
     */
    public function description(): string;

    /**
     * True for real-money providers. The development simulator returns false.
     */
    public function isLive(): bool;

    public function isConfigured(): bool;

    public function supports(string $countryIso2, string $currency): bool;

    public function requiresPayerPhone(): bool;

    public function initiate(Payment $payment, Order $order): GatewayResult;

    public function parseCallback(Request $request): CallbackData;

    /**
     * Whether a callback that passed signature verification may be applied without
     * querying the provider. Only true for providers with cryptographically signed callbacks.
     */
    public function trustsSignedCallbacks(): bool;

    public function fetchStatus(Payment $payment): GatewayResult;
}
