<?php

namespace App\Services\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\CallbackData;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Airtel Money – Airtel Africa Open API collections (USSD push).
 *
 * Flow: POST /merchant/v1/payments/ → customer enters PIN → callback
 * {transaction: {id, status_code, airtel_money_id}, hash}. When callback authentication
 * is enabled the hash is an HMAC-SHA256 that we verify; in every case the final status is
 * confirmed with GET /standard/v1/payments/{id} (TS = success, TF = failed, TA/TIP = pending).
 *
 * Rwanda availability and endpoint versions must be confirmed with Airtel before go-live.
 */
class AirtelMoneyGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'airtel_money';
    }

    public function label(): string
    {
        return __('Airtel Money');
    }

    public function description(): string
    {
        return __('Enter your Airtel Money PIN on the prompt sent to your phone.');
    }

    public function isLive(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        $config = $this->config();

        return $config['enabled'] && filled($config['client_id']) && filled($config['client_secret']);
    }

    public function supports(string $countryIso2, string $currency): bool
    {
        return in_array($countryIso2, $this->config()['countries'], true) && in_array($currency, $this->config()['currencies'], true);
    }

    public function requiresPayerPhone(): bool
    {
        return true;
    }

    public function initiate(Payment $payment, Order $order): GatewayResult
    {
        $country = $order->country;
        $transactionId = str_replace('-', '', $payment->idempotency_key);

        try {
            $response = $this->client($country->iso2, $payment->currency)->post('/merchant/v1/payments/', [
                'reference' => $order->number,
                'subscriber' => [
                    'country' => $country->iso2,
                    'currency' => $payment->currency,
                    'msisdn' => substr((string) $payment->payer_phone, strlen($country->phone_code)),
                ],
                'transaction' => [
                    'amount' => $payment->amount,
                    'country' => $country->iso2,
                    'currency' => $payment->currency,
                    'id' => $transactionId,
                ],
            ]);
        } catch (ConnectionException $e) {
            return GatewayResult::failed(__('Airtel Money could not be reached. Please try again.'), ['error' => $e->getMessage()], $transactionId);
        }

        if ($response->successful() && ($response->json('status.success') ?? true)) {
            return GatewayResult::pending($transactionId, ['body' => $response->json()]);
        }

        return GatewayResult::failed(__('Airtel Money rejected the request.'), ['http_status' => $response->status(), 'body' => $response->json()], $transactionId);
    }

    public function parseCallback(Request $request): CallbackData
    {
        $payload = $request->json()->all();
        $transaction = $payload['transaction'] ?? [];
        $secret = $this->config()['callback_secret'];
        $signatureValid = null;

        if (filled($secret)) {
            $expected = base64_encode(hash_hmac('sha256', json_encode($transaction, JSON_UNESCAPED_SLASHES), $secret, true));
            $signatureValid = is_string($payload['hash'] ?? null) && hash_equals($expected, $payload['hash']);
        }

        return new CallbackData(
            providerReference: $transaction['id'] ?? null,
            dedupeKey: 'airtel_money:'.($transaction['id'] ?? 'unknown').':'.($transaction['status_code'] ?? 'unknown'),
            signatureValid: $signatureValid,
            payload: $payload,
        );
    }

    public function trustsSignedCallbacks(): bool
    {
        return false;
    }

    public function fetchStatus(Payment $payment): GatewayResult
    {
        $order = $payment->order;

        try {
            $response = $this->client($order->country->iso2, $payment->currency)->get('/standard/v1/payments/'.$payment->provider_reference);
        } catch (ConnectionException $e) {
            return GatewayResult::pending($payment->provider_reference, ['error' => $e->getMessage()]);
        }

        if (! $response->successful()) {
            return GatewayResult::pending($payment->provider_reference, ['http_status' => $response->status()]);
        }

        $transaction = $response->json('data.transaction', []);
        $code = $transaction['status'] ?? null;

        return new GatewayResult(
            status: match ($code) {
                'TS' => PaymentStatus::Succeeded,
                'TF' => PaymentStatus::Failed,
                'TE' => PaymentStatus::Expired,
                default => PaymentStatus::Pending,
            },
            providerReference: $payment->provider_reference,
            providerStatus: $code,
            // The enquiry response does not reliably echo the amount; trust our own record only when absent.
            amount: isset($transaction['amount']) ? (int) $transaction['amount'] : $payment->amount,
            currency: $payment->currency,
            failureReason: $transaction['message'] ?? null,
            raw: $response->json(),
        );
    }

    private function client(string $country, string $currency): PendingRequest
    {
        return Http::baseUrl($this->config()['base_url'])
            ->timeout(20)
            ->withToken($this->accessToken())
            ->withHeaders(['X-Country' => $country, 'X-Currency' => $currency])
            ->acceptJson()
            ->asJson();
    }

    private function accessToken(): string
    {
        $config = $this->config();

        return Cache::remember('airtel-money-token:'.md5($config['client_id']), now()->addMinutes(50), function () use ($config) {
            return (string) Http::baseUrl($config['base_url'])
                ->timeout(20)
                ->asJson()
                ->post('/auth/oauth2/token', [
                    'client_id' => $config['client_id'],
                    'client_secret' => $config['client_secret'],
                    'grant_type' => 'client_credentials',
                ])
                ->throw()
                ->json('access_token');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return config('payments.providers.airtel_money');
    }
}
