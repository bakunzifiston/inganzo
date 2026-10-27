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
 * MTN Mobile Money – MoMo Open API "Collections" (RequestToPay).
 *
 * Flow: POST /collection/v1_0/requesttopay with X-Reference-Id (our payment idempotency
 * key) → 202 Accepted → customer approves on their phone → MTN calls our callback once
 * (unsigned, no retries) → we always confirm with GET /collection/v1_0/requesttopay/{id}.
 * Pending payments are also reconciled by the scheduler in case the callback never arrives.
 *
 * Production use requires an MTN Rwanda merchant contract, live Collection subscription
 * key, API user/key and a registered callback host. See docs/PAYMENTS.md.
 */
class MtnMomoGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'mtn_momo';
    }

    public function label(): string
    {
        return __('MTN Mobile Money');
    }

    public function description(): string
    {
        return __('Approve the payment prompt on your MTN phone.');
    }

    public function isLive(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        $config = $this->config();

        return $config['enabled'] && filled($config['subscription_key']) && filled($config['api_user']) && filled($config['api_key']);
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
        $body = [
            'amount' => (string) $payment->amount,
            'currency' => $this->providerCurrency($payment->currency),
            'externalId' => $order->number,
            'payer' => ['partyIdType' => 'MSISDN', 'partyId' => ltrim((string) $payment->payer_phone, '+')],
            'payerMessage' => mb_substr(config('app.name').' '.$order->number, 0, 160),
            'payeeNote' => $order->number,
        ];

        try {
            $response = $this->client()
                ->withHeaders(array_filter([
                    'X-Reference-Id' => $payment->idempotency_key,
                    'X-Callback-Url' => $this->callbackUrl($payment),
                ]))
                ->post('/collection/v1_0/requesttopay', $body);
        } catch (ConnectionException $e) {
            return GatewayResult::failed(__('MTN Mobile Money could not be reached. Please try again.'), ['error' => $e->getMessage()], $payment->idempotency_key);
        }

        // 202 = accepted for processing; 409 = this reference was already submitted (safe to treat as pending).
        if ($response->status() === 202 || $response->status() === 409) {
            return GatewayResult::pending($payment->idempotency_key, ['http_status' => $response->status()]);
        }

        return GatewayResult::failed(__('MTN Mobile Money rejected the request.'), ['http_status' => $response->status(), 'body' => $response->json()], $payment->idempotency_key);
    }

    public function parseCallback(Request $request): CallbackData
    {
        $payload = $request->json()->all();
        // Our X-Reference-Id is embedded in the callback URL because MTN's body does not always echo it.
        $referenceId = $request->query('reference') ?? $payload['referenceId'] ?? null;

        return new CallbackData(
            providerReference: is_string($referenceId) ? $referenceId : null,
            dedupeKey: 'mtn_momo:'.($referenceId ?? 'unknown').':'.($payload['status'] ?? 'unknown').':'.($payload['financialTransactionId'] ?? ''),
            signatureValid: null,
            payload: $payload,
        );
    }

    public function trustsSignedCallbacks(): bool
    {
        return false;
    }

    public function fetchStatus(Payment $payment): GatewayResult
    {
        try {
            $response = $this->client()->get('/collection/v1_0/requesttopay/'.$payment->provider_reference);
        } catch (ConnectionException $e) {
            return GatewayResult::pending($payment->provider_reference, ['error' => $e->getMessage()]);
        }

        if (! $response->successful()) {
            return GatewayResult::pending($payment->provider_reference, ['http_status' => $response->status()]);
        }

        $data = $response->json();
        $status = match ($data['status'] ?? null) {
            'SUCCESSFUL' => PaymentStatus::Succeeded,
            'FAILED', 'REJECTED' => PaymentStatus::Failed,
            'TIMEOUT', 'EXPIRED' => PaymentStatus::Expired,
            default => PaymentStatus::Pending,
        };

        return new GatewayResult(
            status: $status,
            providerReference: $payment->provider_reference,
            providerStatus: $data['status'] ?? null,
            amount: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: isset($data['currency']) ? $this->platformCurrency($data['currency'], $payment->currency) : null,
            failureReason: $data['reason']['message'] ?? $data['reason'] ?? null,
            raw: $data,
        );
    }

    private function client(): PendingRequest
    {
        $config = $this->config();

        return Http::baseUrl($config['base_url'])
            ->timeout(20)
            ->withToken($this->accessToken())
            ->withHeaders([
                'X-Target-Environment' => $config['target_environment'],
                'Ocp-Apim-Subscription-Key' => $config['subscription_key'],
            ])
            ->acceptJson();
    }

    private function accessToken(): string
    {
        $config = $this->config();

        return Cache::remember('mtn-momo-token:'.md5($config['api_user']), now()->addMinutes(50), function () use ($config) {
            $response = Http::baseUrl($config['base_url'])
                ->timeout(20)
                ->withBasicAuth($config['api_user'], $config['api_key'])
                ->withHeaders(['Ocp-Apim-Subscription-Key' => $config['subscription_key']])
                ->post('/collection/token/')
                ->throw();

            return (string) $response->json('access_token');
        });
    }

    private function callbackUrl(Payment $payment): ?string
    {
        $host = $this->config()['callback_host'];

        return $host
            ? rtrim($host, '/').route('api.v1.payments.callback', ['provider' => 'mtn_momo', 'reference' => $payment->idempotency_key], false)
            : null;
    }

    private function providerCurrency(string $currency): string
    {
        return $this->config()['sandbox_currency_override'] ?: $currency;
    }

    private function platformCurrency(string $providerCurrency, string $expected): string
    {
        return $providerCurrency === $this->config()['sandbox_currency_override'] ? $expected : $providerCurrency;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return config('payments.providers.mtn_momo');
    }
}
