<?php

namespace Tests\Feature\Payments;

use App\Enums\LedgerAccount;
use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PrintingPartner;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_verified_success_callback_marks_the_order_paid_and_posts_the_ledger(): void
    {
        PrintingPartner::factory()->servingEverything()->create();
        $order = $this->paidOrder(quantity: 2);

        $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(OrderItemStatus::Assigned, $order->items->first()->fulfillment_status);

        $artist = LedgerEntry::query()->where('account_type', LedgerAccount::Artist)->sum('amount');
        $platform = LedgerEntry::query()->where('account_type', LedgerAccount::Platform)->sum('amount');
        $this->assertSame(2600, (int) $artist);
        // Platform keeps margin + base cost + delivery: total − artist earnings.
        $this->assertSame($order->total_amount - 2600, (int) $platform);
    }

    #[Test]
    public function duplicate_callbacks_are_ignored(): void
    {
        $order = $this->placeOrder([[$this->listedArtistProduct(), $this->variant(), 1]]);
        $payment = $this->initiatePayment($order);

        $this->simulatorCallback($payment, eventId: 'evt-1')->assertOk()->assertJson(['message' => 'succeeded']);
        $this->simulatorCallback($payment, eventId: 'evt-1')->assertOk()->assertJson(['message' => 'duplicate ignored']);
        $this->simulatorCallback($payment, eventId: 'evt-2')->assertOk()->assertJson(['message' => 'already_final']);

        // One item (artist, margin, base cost) + delivery income, posted exactly once.
        $this->assertSame(4, LedgerEntry::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, Payment::query()->where('status', PaymentStatus::Succeeded)->count());
    }

    #[Test]
    public function failed_payments_leave_the_order_unpaid_and_retryable(): void
    {
        $order = $this->placeOrder([[$this->listedArtistProduct(), $this->variant(), 1]]);
        $payment = $this->initiatePayment($order);

        $this->simulatorCallback($payment, 'FAILED')->assertOk();

        $order->refresh();
        $this->assertSame(OrderPaymentStatus::Failed, $order->payment_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(0, LedgerEntry::query()->count());

        $retry = $this->initiatePayment($order);
        $this->assertNotSame($payment->id, $retry->id);
        $this->simulatorCallback($retry)->assertOk();
        $this->assertTrue($order->fresh()->isPaid());
    }

    #[Test]
    public function callbacks_with_invalid_signatures_are_rejected(): void
    {
        $order = $this->placeOrder([[$this->listedArtistProduct(), $this->variant(), 1]]);
        $payment = $this->initiatePayment($order);

        $body = json_encode(['event_id' => 'forged', 'reference' => $payment->provider_reference, 'status' => 'SUCCESSFUL', 'amount' => $payment->amount, 'currency' => 'RWF']);
        $this->call('POST', route('api.v1.payments.callback', 'simulator'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIMULATOR_SIGNATURE' => 'bad'], $body)
            ->assertStatus(401);

        $this->assertFalse($order->fresh()->isPaid());
    }

    #[Test]
    public function an_amount_mismatch_never_marks_the_order_paid(): void
    {
        $order = $this->placeOrder([[$this->listedArtistProduct(), $this->variant(), 1]]);
        $payment = $this->initiatePayment($order);

        $this->simulatorCallback($payment, amount: 100)->assertOk()->assertJson(['message' => 'amount_mismatch']);

        $this->assertFalse($order->fresh()->isPaid());
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    #[Test]
    public function a_second_successful_payment_is_flagged_as_duplicate(): void
    {
        $order = $this->placeOrder([[$this->listedArtistProduct(), $this->variant(), 1]]);
        $first = $this->initiatePayment($order);
        $second = Payment::query()->create([
            'order_id' => $order->id, 'provider' => 'simulator', 'status' => PaymentStatus::Pending, 'amount' => $order->total_amount,
            'currency' => 'RWF', 'idempotency_key' => (string) Str::uuid(), 'provider_reference' => 'SIM-second',
        ]);

        $this->simulatorCallback($first)->assertOk();
        $this->simulatorCallback($second)->assertOk()->assertJson(['message' => 'duplicate_payment']);

        $this->assertSame(PaymentStatus::Duplicate, $second->fresh()->status);
        $this->assertSame(1, LedgerEntry::query()->where('type', 'sale_earning')->count());
    }

    #[Test]
    public function returning_from_the_provider_does_not_mark_an_order_paid(): void
    {
        $order = $this->placeOrder([[$this->listedArtistProduct(), $this->variant(), 1]]);
        $this->initiatePayment($order);

        $this->get(route('orders.track', $order->tracking_token).'?status=success&paid=1')->assertOk();
        $this->getJson(route('orders.payment-status', $order->tracking_token))->assertOk()->assertJson(['paid' => false]);

        $this->assertFalse($order->fresh()->isPaid());
    }

    #[Test]
    public function mtn_callbacks_are_verified_against_the_status_api(): void
    {
        config([
            'payments.providers.mtn_momo.enabled' => true,
            'payments.providers.mtn_momo.subscription_key' => 'sub',
            'payments.providers.mtn_momo.api_user' => 'user',
            'payments.providers.mtn_momo.api_key' => 'key',
            'payments.providers.mtn_momo.base_url' => 'https://mtn.test',
        ]);

        $order = $this->placeOrder([[$this->listedArtistProduct(), $this->variant(), 1]]);

        Http::fake([
            'mtn.test/collection/token/' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'mtn.test/collection/v1_0/requesttopay' => Http::response(null, 202),
            'mtn.test/collection/v1_0/requesttopay/*' => Http::sequence()
                ->push(['status' => 'PENDING', 'amount' => '11500', 'currency' => 'RWF'])
                ->push(['status' => 'SUCCESSFUL', 'amount' => (string) $order->total_amount, 'currency' => 'RWF', 'financialTransactionId' => '991']),
        ]);

        $payment = $this->initiatePayment($order, 'mtn_momo');
        $this->assertSame($payment->idempotency_key, $payment->provider_reference);

        // A forged "SUCCESSFUL" body is not trusted: the status API still says PENDING.
        $this->putJson(route('api.v1.payments.callback', ['provider' => 'mtn_momo', 'reference' => $payment->provider_reference]), ['status' => 'SUCCESSFUL', 'amount' => '1'])
            ->assertOk()->assertJson(['message' => 'pending']);
        $this->assertFalse($order->fresh()->isPaid());

        $this->postJson(route('api.v1.payments.callback', ['provider' => 'mtn_momo', 'reference' => $payment->provider_reference]), ['status' => 'SUCCESSFUL', 'financialTransactionId' => '991'])
            ->assertOk()->assertJson(['message' => 'succeeded']);
        $this->assertTrue($order->fresh()->isPaid());

        Http::assertSent(fn ($request) => $request->hasHeader('X-Reference-Id', $payment->idempotency_key) && $request['payer']['partyId'] === '250788123456');
    }

    #[Test]
    public function airtel_callbacks_require_a_valid_hash_and_are_confirmed_by_enquiry(): void
    {
        config([
            'payments.providers.airtel_money.enabled' => true,
            'payments.providers.airtel_money.client_id' => 'id',
            'payments.providers.airtel_money.client_secret' => 'secret',
            'payments.providers.airtel_money.callback_secret' => 'hash-secret',
            'payments.providers.airtel_money.base_url' => 'https://airtel.test',
        ]);

        $order = $this->placeOrder([[$this->listedArtistProduct(), $this->variant(), 1]]);

        Http::fake([
            'airtel.test/auth/oauth2/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'airtel.test/merchant/v1/payments/' => Http::response(['status' => ['success' => true, 'code' => '200']]),
            'airtel.test/standard/v1/payments/*' => Http::response(['data' => ['transaction' => ['id' => 'x', 'status' => 'TS']]]),
        ]);

        $payment = $this->initiatePayment($order, 'airtel_money');
        Http::assertSent(fn ($request) => $request->hasHeader('X-Country', 'RW') && ($request['subscriber']['msisdn'] ?? null) === '788123456');

        $transaction = ['id' => $payment->provider_reference, 'message' => 'Paid', 'status_code' => 'TS', 'airtel_money_id' => 'MP123'];
        $this->postJson(route('api.v1.payments.callback', 'airtel_money'), ['transaction' => $transaction, 'hash' => 'forged'])->assertStatus(401);
        $this->assertFalse($order->fresh()->isPaid());

        $hash = base64_encode(hash_hmac('sha256', json_encode($transaction, JSON_UNESCAPED_SLASHES), 'hash-secret', true));
        $this->postJson(route('api.v1.payments.callback', 'airtel_money'), ['transaction' => $transaction, 'hash' => $hash])
            ->assertOk()->assertJson(['message' => 'succeeded']);
        $this->assertTrue($order->fresh()->isPaid());
    }

    #[Test]
    public function pending_payments_expire_during_reconciliation(): void
    {
        $order = $this->placeOrder([[$this->listedArtistProduct(), $this->variant(), 1]]);
        $payment = $this->initiatePayment($order);
        $payment->forceFill(['expires_at' => now()->subMinute()])->save();

        app(PaymentService::class)->refresh($payment);

        $this->assertSame(PaymentStatus::Expired, $payment->fresh()->status);
        $this->assertSame(OrderPaymentStatus::Failed, $order->fresh()->payment_status);
    }
}
