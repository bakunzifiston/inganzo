<?php

namespace Tests\Feature\Finance;

use App\Enums\LedgerBucket;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\RefundReason;
use App\Enums\RoleName;
use App\Models\LedgerEntry;
use App\Models\PayoutAccount;
use App\Services\Finance\EarningsReleaser;
use App\Services\Finance\Ledger;
use App\Services\Finance\PayoutService;
use App\Services\Finance\RefundService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ledger_entries_cannot_be_updated_or_deleted(): void
    {
        $this->paidOrder();
        $entry = LedgerEntry::query()->firstOrFail();

        try {
            $entry->amount = 1;
            $entry->save();
            $this->fail('Eloquent update should be blocked.');
        } catch (LogicException) {
        }

        $this->expectException(QueryException::class);
        DB::table('ledger_entries')->where('id', $entry->id)->update(['amount' => 1]);
    }

    #[Test]
    public function earnings_stay_held_until_delivered_and_the_hold_period_passes(): void
    {
        $order = $this->paidOrder(quantity: 2);
        $item = $order->items->first();
        $ledger = app(Ledger::class);
        $releaser = app(EarningsReleaser::class);

        $this->assertSame(2600, $ledger->artistBalance($item->artist_profile_id, LedgerBucket::Held));
        $this->assertSame(0, $releaser->releaseEligible());

        $item->forceFill(['delivered_at' => now()->subDays(3)])->save();
        $this->assertSame(0, $releaser->releaseEligible(), 'Hold period of 7 days has not passed.');

        $item->forceFill(['delivered_at' => now()->subDays(8)])->save();
        $this->assertSame(1, $releaser->releaseEligible());
        $this->assertSame(0, $releaser->releaseEligible(), 'Release is idempotent.');

        $this->assertSame(0, $ledger->artistBalance($item->artist_profile_id, LedgerBucket::Held));
        $this->assertSame(2600, $ledger->artistBalance($item->artist_profile_id, LedgerBucket::Available));
    }

    #[Test]
    public function a_partial_refund_reverses_the_artist_share_proportionally(): void
    {
        $order = $this->paidOrder(quantity: 2);
        $item = $order->items->first();
        $admin = $this->staff(RoleName::Finance);

        // Refund one of two shirts (9 500 of 19 000): half of the 2 600 earning is reversed.
        app(RefundService::class)->issue($order, $item, 9500, RefundReason::Damaged, $admin, 'MTN-RF-1');

        $order->refresh();
        $this->assertSame(OrderPaymentStatus::PartiallyRefunded, $order->payment_status);
        $this->assertSame(9500, $order->refunded_amount);
        $this->assertSame(1300, app(Ledger::class)->artistBalance($item->artist_profile_id, LedgerBucket::Held));

        $platformRefund = LedgerEntry::query()->where('account_type', 'platform')->where('type', 'refund_reversal')->sum('amount');
        $this->assertSame(-8200, (int) $platformRefund);
    }

    #[Test]
    public function a_full_refund_reverses_everything_and_cancels_an_undelivered_order(): void
    {
        $order = $this->paidOrder();
        $admin = $this->staff(RoleName::Finance);

        app(RefundService::class)->issue($order, null, $order->total_amount, RefundReason::ProductionFailure, $admin, 'MTN-RF-2');

        $order->refresh();
        $this->assertSame(OrderPaymentStatus::Refunded, $order->payment_status);
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(0, (int) LedgerEntry::query()->where('account_type', 'artist')->sum('amount'));
        $this->assertSame(0, (int) LedgerEntry::query()->where('account_type', 'platform')->sum('amount'));
        $this->assertSame(0, $order->newQuery()->countableSales()->count(), 'Fully refunded orders are not counted as sales.');
    }

    #[Test]
    public function refunds_cannot_exceed_what_was_paid(): void
    {
        $order = $this->paidOrder();
        $admin = $this->staff(RoleName::Finance);

        $this->expectException(ValidationException::class);
        app(RefundService::class)->issue($order, null, $order->total_amount + 1, RefundReason::Other, $admin, 'X');
    }

    #[Test]
    public function payouts_reserve_available_balance_and_are_reversed_when_rejected(): void
    {
        $order = $this->paidOrder(quantity: 4);
        $item = $order->items->first();
        $artist = $item->artist;
        $item->forceFill(['delivered_at' => now()->subDays(10)])->save();
        app(EarningsReleaser::class)->releaseEligible();

        $payouts = app(PayoutService::class);
        $this->assertFalse($payouts->eligibility($artist)['eligible'], 'No payout account yet.');

        $account = PayoutAccount::query()->create([
            'artist_profile_id' => $artist->id, 'method' => 'mtn_momo', 'account_name' => 'Test Artist',
            'account_number' => '250788000111', 'account_last4' => '0111', 'country_id' => $this->rwanda()->id, 'is_default' => true,
        ]);
        $account->forceFill(['verified_at' => now()])->save();
        $this->assertTrue($payouts->eligibility($artist->fresh())['eligible']);

        $payout = $payouts->request($artist->fresh(), 5200);
        $this->assertSame(0, app(Ledger::class)->artistBalance($artist->id, LedgerBucket::Available));

        try {
            $payouts->request($artist->fresh(), 5000);
            $this->fail('A second open payout must be refused.');
        } catch (ValidationException) {
        }

        $payouts->reject($payout, $this->staff(RoleName::Finance), 'Name mismatch');
        $this->assertSame(PayoutStatus::Rejected, $payout->fresh()->status);
        $this->assertSame(5200, app(Ledger::class)->artistBalance($artist->id, LedgerBucket::Available));
    }

    #[Test]
    public function payouts_below_the_minimum_are_refused(): void
    {
        $order = $this->paidOrder();
        $item = $order->items->first();
        $item->forceFill(['delivered_at' => now()->subDays(10)])->save();
        app(EarningsReleaser::class)->releaseEligible();

        $account = PayoutAccount::query()->create([
            'artist_profile_id' => $item->artist_profile_id, 'method' => 'mtn_momo', 'account_name' => 'Test', 'account_number' => '250788000111',
            'account_last4' => '0111', 'country_id' => $this->rwanda()->id, 'is_default' => true,
        ]);
        $account->forceFill(['verified_at' => now()])->save();

        $check = app(PayoutService::class)->eligibility($item->artist->fresh());
        $this->assertFalse($check['eligible']);
        $this->assertSame(1300, $check['available']);
    }
}
