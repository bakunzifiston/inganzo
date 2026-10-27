<?php

namespace App\Services\Payments;

use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SellingType;
use App\Jobs\AssignOrderToPartners;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Notifications\NewSale;
use App\Notifications\OrderPaid;
use App\Services\AuditLogger;
use App\Services\Finance\SaleRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Payment orchestration.
 *
 * Guarantees:
 *  - An order is marked paid only from a verified provider status (signed callback or
 *    server-to-server status query) – never from a browser redirect.
 *  - The confirmed amount and currency must match what we requested.
 *  - Duplicate callbacks are ignored (payment_events.dedupe_key is unique).
 *  - Status transitions lock the payment and order rows, so concurrent callbacks and
 *    polling cannot double-process. A second successful payment for an already-paid
 *    order is flagged "duplicate" for refund instead of being applied.
 */
class PaymentService
{
    public function __construct(
        private GatewayRegistry $gateways,
        private SaleRecorder $sales,
        private AuditLogger $audit,
    ) {}

    public function initiate(Order $order, string $provider, ?string $payerPhone): Payment
    {
        if (! $this->gateways->isAvailableForOrder($provider, $order)) {
            throw ValidationException::withMessages(['payment_method' => __('This payment method is not available for your order.')]);
        }

        $gateway = $this->gateways->get($provider);

        if ($gateway->requiresPayerPhone() && blank($payerPhone)) {
            throw ValidationException::withMessages(['payer_phone' => __('Enter the phone number that will approve the payment.')]);
        }

        $payment = DB::transaction(function () use ($order, $provider, $payerPhone) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->isAwaitingPayment()) {
                throw ValidationException::withMessages(['payment_method' => __('This order is not awaiting payment.')]);
            }

            // Reuse a still-pending attempt with the same provider instead of prompting twice.
            $existing = $order->payments()
                ->where('provider', $provider)
                ->where('status', PaymentStatus::Pending)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where('payer_phone', $payerPhone)
                ->first();

            if ($existing) {
                return $existing;
            }

            $timeout = config("payments.providers.{$provider}.timeout_minutes");

            $payment = $order->payments()->create([
                'provider' => $provider,
                'status' => PaymentStatus::Pending,
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'payer_phone' => $payerPhone,
                'idempotency_key' => (string) Str::uuid(),
                'initiated_at' => now(),
                'expires_at' => $timeout ? now()->addMinutes($timeout) : null,
            ]);

            $order->forceFill(['payment_status' => OrderPaymentStatus::Pending, 'payment_method' => $provider])->save();

            return $payment;
        });

        if ($payment->provider_reference !== null) {
            return $payment;
        }

        $result = $gateway->initiate($payment, $order);
        $this->logEvent($payment, $provider, 'initiate', null, null, $result->raw + ['status' => $result->status->value], $result->status->value);

        $payment->forceFill([
            'provider_reference' => $result->providerReference,
            'provider_status' => $result->providerStatus,
            'meta' => array_filter(['redirect_url' => $result->redirectUrl]) ?: null,
        ])->save();

        if ($result->status === PaymentStatus::Failed) {
            $this->applyResult($payment, $result, 'initiate');
        }

        if ($provider === 'cash_on_delivery') {
            $this->startCashOnDeliveryOrder($payment->order);
        }

        return $payment->fresh();
    }

    /**
     * Entry point for provider webhooks/callbacks.
     *
     * @return array{status: int, message: string}
     */
    public function handleCallback(string $provider, Request $request): array
    {
        if (! $this->gateways->has($provider) || $provider === 'cash_on_delivery') {
            return ['status' => 404, 'message' => 'unknown provider'];
        }

        $gateway = $this->gateways->get($provider);

        if (! $gateway->isConfigured()) {
            return ['status' => 404, 'message' => 'provider disabled'];
        }

        $data = $gateway->parseCallback($request);
        $dedupeKey = mb_substr($data->dedupeKey, 0, 191);

        // A dedupe key is only claimed once a callback has produced a final outcome, so a
        // forged or premature callback can never cause the genuine one to be ignored.
        if (PaymentEvent::query()->where('dedupe_key', $dedupeKey)->exists()) {
            return ['status' => 200, 'message' => 'duplicate ignored'];
        }

        $event = PaymentEvent::query()->create([
            'provider' => $provider,
            'type' => 'callback',
            'signature_valid' => $data->signatureValid,
            'payload' => $data->payload,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        if ($data->signatureValid === false) {
            $event->forceFill(['result' => 'invalid_signature'])->saveQuietly();
            Log::warning('Rejected payment callback with invalid signature', ['provider' => $provider, 'ip' => $request->ip()]);

            return ['status' => 401, 'message' => 'invalid signature'];
        }

        $payment = $data->providerReference
            ? Payment::query()->where('provider', $provider)->where('provider_reference', $data->providerReference)->first()
            : null;

        if ($payment === null) {
            $event->forceFill(['result' => 'unknown_payment'])->saveQuietly();

            return ['status' => 404, 'message' => 'payment not found'];
        }

        $event->forceFill(['payment_id' => $payment->id])->saveQuietly();

        $result = $gateway->trustsSignedCallbacks() && $data->signatureValid === true && $data->reportedStatus !== null
            ? new GatewayResult($data->reportedStatus, $data->providerReference, $data->payload['status'] ?? null, $data->reportedAmount, $data->reportedCurrency, $data->payload['reason'] ?? null)
            : $gateway->fetchStatus($payment);

        $outcome = $this->applyResult($payment, $result, 'callback');
        $event->forceFill(['result' => $outcome])->saveQuietly();

        if ($outcome !== 'pending') {
            try {
                // Savepoint: on PostgreSQL a unique violation would otherwise abort any enclosing transaction.
                DB::transaction(fn () => $event->forceFill(['dedupe_key' => $dedupeKey])->saveQuietly());
            } catch (UniqueConstraintViolationException) {
                // A concurrent delivery of the same callback finished first; applyResult was idempotent.
            }
        }

        return ['status' => 200, 'message' => $outcome];
    }

    /**
     * Server-side status check, used by the customer's waiting page and the reconciliation job.
     */
    public function refresh(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::Pending || $payment->provider === 'cash_on_delivery') {
            return $payment;
        }

        $gateway = $this->gateways->get($payment->provider);
        $result = $gateway->fetchStatus($payment);
        $this->logEvent($payment, $payment->provider, 'status_check', null, null, $result->raw + ['status' => $result->status->value], $result->status->value);

        if ($result->status === PaymentStatus::Pending && $payment->expires_at?->isPast()) {
            $result = new GatewayResult(PaymentStatus::Expired, $payment->provider_reference, 'EXPIRED', failureReason: __('The payment request expired.'));
        }

        $this->applyResult($payment, $result, 'status_check');

        return $payment->fresh();
    }

    public function confirmCashCollected(Order $order, User $actor): void
    {
        $payment = $order->payments()->where('provider', 'cash_on_delivery')->where('status', PaymentStatus::Pending)->first();

        if ($payment === null) {
            throw ValidationException::withMessages(['payment' => __('No pending cash-on-delivery payment was found.')]);
        }

        $this->applyResult($payment, new GatewayResult(PaymentStatus::Succeeded, $payment->provider_reference, 'CASH_COLLECTED', $payment->amount, $payment->currency), 'cash_collected');
        $this->audit->log('payment.cash_collected', $payment, ['amount' => $payment->amount], $actor);
    }

    /**
     * Apply a provider status to a payment, exactly once.
     */
    public function applyResult(Payment $payment, GatewayResult $result, string $source): string
    {
        $paidOrder = null;

        $outcome = DB::transaction(function () use ($payment, $result, &$paidOrder) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

            if ($payment->status !== PaymentStatus::Pending) {
                return 'already_final';
            }

            if ($result->status === PaymentStatus::Pending) {
                $payment->forceFill(['provider_status' => $result->providerStatus ?? $payment->provider_status])->save();

                return 'pending';
            }

            if ($result->status === PaymentStatus::Succeeded) {
                $amountMatches = $result->amount === $payment->amount && ($result->currency ?? $payment->currency) === $payment->currency;

                if (! $amountMatches) {
                    $payment->forceFill([
                        'status' => PaymentStatus::Failed,
                        'failure_reason' => "Amount mismatch: expected {$payment->amount} {$payment->currency}, provider reported {$result->amount} {$result->currency}",
                        'failed_at' => now(),
                        'provider_status' => $result->providerStatus,
                    ])->save();
                    Log::critical('Payment amount mismatch', ['payment' => $payment->id, 'expected' => $payment->amount, 'reported' => $result->amount]);

                    return 'amount_mismatch';
                }

                if ($order->isPaid()) {
                    $payment->forceFill(['status' => PaymentStatus::Duplicate, 'confirmed_at' => now(), 'provider_status' => $result->providerStatus])->save();
                    Log::warning('Duplicate payment received for paid order', ['order' => $order->number, 'payment' => $payment->id]);

                    return 'duplicate_payment';
                }

                $payment->forceFill([
                    'status' => PaymentStatus::Succeeded,
                    'confirmed_at' => now(),
                    'provider_status' => $result->providerStatus,
                    'fee_amount' => $result->fee,
                ])->save();

                $this->markOrderPaid($order, $payment);
                $paidOrder = $order;

                return 'succeeded';
            }

            $payment->forceFill([
                'status' => $result->status,
                'failure_reason' => $result->failureReason,
                'failed_at' => now(),
                'provider_status' => $result->providerStatus,
            ])->save();

            $hasOtherPending = $order->payments()->where('status', PaymentStatus::Pending)->exists();
            if (! $order->isPaid() && ! $hasOtherPending && $order->payment_method !== 'cash_on_delivery') {
                $order->forceFill(['payment_status' => OrderPaymentStatus::Failed])->save();
            }

            return $result->status->value;
        });

        if ($paidOrder !== null) {
            $this->afterPaid($paidOrder->fresh(['items.artist.user', 'user']));
        }

        return $outcome;
    }

    private function markOrderPaid(Order $order, Payment $payment): void
    {
        $order->forceFill([
            'payment_status' => OrderPaymentStatus::Paid,
            'status' => OrderStatus::Processing,
            'paid_at' => now(),
        ])->save();

        $this->releaseItemsToFulfilment($order);

        $this->sales->recordPaidOrder($order, $payment);

        foreach ($order->items()->get() as $item) {
            if ($item->isFinished()) {
                DB::table('finished_products')->where('id', $item->finished_product_id)->increment('sales_count', $item->quantity);

                continue;
            }
            DB::table('artist_products')->where('id', $item->artist_product_id)->increment('sales_count', $item->quantity);
            DB::table('artworks')->where('id', $item->artwork_id)->increment('sales_count', $item->quantity);
        }

        AssignOrderToPartners::dispatch($order->id)->afterCommit();
    }

    private function afterPaid(Order $order): void
    {
        $notification = new OrderPaid($order);
        $order->user
            ? $order->user->notify($notification)
            : Notification::route('mail', $order->email)->notify($notification);

        $order->items->groupBy('artist_profile_id')->each(function ($items) use ($order) {
            $items->first()->artist?->user?->notify(new NewSale($order, $items->pluck('id')->all()));
        });
    }

    /**
     * Paid (or cash-on-delivery) items start their next step: printed items wait for a printing
     * partner, ready-made items wait for the artist to ship them from stock.
     */
    private function releaseItemsToFulfilment(Order $order): void
    {
        $pending = $order->items()->where('fulfillment_status', OrderItemStatus::Pending);

        (clone $pending)->where('selling_type', SellingType::PrintOnDemand->value)->update(['fulfillment_status' => OrderItemStatus::AwaitingAssignment->value]);
        (clone $pending)->where('selling_type', SellingType::Finished->value)->update(['fulfillment_status' => OrderItemStatus::AwaitingShipment->value]);
    }

    /**
     * Cash-on-delivery orders go to production immediately; payment is confirmed on delivery.
     */
    private function startCashOnDeliveryOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== OrderStatus::PendingPayment) {
                return;
            }
            $order->forceFill(['status' => OrderStatus::Processing])->save();
            $this->releaseItemsToFulfilment($order);
            AssignOrderToPartners::dispatch($order->id)->afterCommit();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logEvent(Payment $payment, string $provider, string $type, ?string $dedupeKey, ?bool $signatureValid, array $payload, ?string $result): void
    {
        PaymentEvent::query()->create([
            'payment_id' => $payment->id,
            'provider' => $provider,
            'type' => $type,
            'dedupe_key' => $dedupeKey,
            'signature_valid' => $signatureValid,
            'payload' => $payload,
            'result' => $result,
            'created_at' => now(),
        ]);
    }
}
