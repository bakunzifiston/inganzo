<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Services\Payments\Gateways\AirtelMoneyGateway;
use App\Services\Payments\Gateways\CashOnDeliveryGateway;
use App\Services\Payments\Gateways\MtnMomoGateway;
use App\Services\Payments\Gateways\SimulatorGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class GatewayRegistry
{
    /**
     * @var array<string, class-string<PaymentGateway>>
     */
    private const GATEWAYS = [
        'mtn_momo' => MtnMomoGateway::class,
        'airtel_money' => AirtelMoneyGateway::class,
        'cash_on_delivery' => CashOnDeliveryGateway::class,
        'simulator' => SimulatorGateway::class,
    ];

    public function __construct(private Container $container) {}

    public function get(string $key): PaymentGateway
    {
        if (! isset(self::GATEWAYS[$key])) {
            throw new InvalidArgumentException("Unknown payment provider [{$key}].");
        }

        return $this->container->make(self::GATEWAYS[$key]);
    }

    public function has(string $key): bool
    {
        return isset(self::GATEWAYS[$key]);
    }

    /**
     * Providers offered for a country and currency, in configured display order.
     *
     * @return list<PaymentGateway>
     */
    public function availableFor(string $countryIso2, string $currency): array
    {
        $available = [];

        foreach (config('payments.checkout_order') as $key) {
            $gateway = $this->get($key);
            if ($gateway->isConfigured() && $gateway->supports($countryIso2, $currency)) {
                $available[] = $gateway;
            }
        }

        return $available;
    }

    public function isAvailableForOrder(string $key, Order $order): bool
    {
        if (! $this->has($key)) {
            return false;
        }

        $gateway = $this->get($key);

        return $gateway->isConfigured() && $gateway->supports($order->country->iso2, $order->currency);
    }
}
