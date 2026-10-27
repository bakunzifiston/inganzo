<?php

namespace App\Services\Payments\Gateways;

use App\Models\Country;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\CallbackData;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGateway;
use App\Services\Settings;
use Illuminate\Http\Request;
use LogicException;

/**
 * Cash collected by the courier. Available only when an administrator enables it
 * (platform setting + country flag). Payment is confirmed when delivery is recorded
 * with "cash collected", never at checkout.
 */
class CashOnDeliveryGateway implements PaymentGateway
{
    public function __construct(private Settings $settings) {}

    public function key(): string
    {
        return 'cash_on_delivery';
    }

    public function label(): string
    {
        return __('Cash on delivery');
    }

    public function description(): string
    {
        return __('Pay the courier in cash when your order arrives.');
    }

    public function isLive(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return config('payments.providers.cash_on_delivery.enabled') && $this->settings->boolean('checkout.cod_enabled');
    }

    public function supports(string $countryIso2, string $currency): bool
    {
        return in_array($countryIso2, config('payments.providers.cash_on_delivery.countries'), true)
            && in_array($currency, config('payments.providers.cash_on_delivery.currencies'), true)
            && (bool) Country::query()->where('iso2', $countryIso2)->value('cod_enabled');
    }

    public function requiresPayerPhone(): bool
    {
        return false;
    }

    public function initiate(Payment $payment, Order $order): GatewayResult
    {
        return GatewayResult::pending('COD-'.$order->number);
    }

    public function parseCallback(Request $request): CallbackData
    {
        throw new LogicException('Cash on delivery has no provider callbacks.');
    }

    public function trustsSignedCallbacks(): bool
    {
        return false;
    }

    public function fetchStatus(Payment $payment): GatewayResult
    {
        return new GatewayResult($payment->status, $payment->provider_reference, $payment->provider_status, $payment->amount, $payment->currency);
    }
}
