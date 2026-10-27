<?php

namespace Tests\Feature\Admin;

use App\Enums\ArtworkStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\RefundReason;
use App\Enums\RoleName;
use App\Models\Artwork;
use App\Models\AuditLog;
use App\Models\CopyrightReport;
use App\Models\PayoutAccount;
use App\Models\PricingRule;
use App\Models\PrintingPartner;
use App\Notifications\ArtworkReviewed;
use App\Services\Finance\EarningsReleaser;
use App\Services\Finance\PayoutService;
use App\Services\Finance\RefundService;
use App\Services\Reporting\AdminMetrics;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function moderators_approve_designs_over_http(): void
    {
        Notification::fake();
        $artwork = Artwork::factory()->withVersion()->pendingReview()->create();

        $this->actingAs($this->staff(RoleName::Moderator))
            ->post(route('admin.moderation.approve', $artwork), ['notes' => 'Lovely'])
            ->assertRedirect();

        $this->assertSame(ArtworkStatus::Approved, $artwork->fresh()->status);
        $this->assertNotNull($artwork->fresh()->published_at);
        Notification::assertSentTo($artwork->artist->user, ArtworkReviewed::class);
    }

    #[Test]
    public function rejecting_a_design_requires_notes(): void
    {
        $artwork = Artwork::factory()->withVersion()->pendingReview()->create();
        $moderator = $this->staff(RoleName::Moderator);

        $this->actingAs($moderator)->post(route('admin.moderation.reject', $artwork), ['notes' => ''])->assertSessionHasErrors('notes');
        $this->assertSame(ArtworkStatus::PendingReview, $artwork->fresh()->status);

        $this->actingAs($moderator)->post(route('admin.moderation.reject', $artwork), ['notes' => 'Logo in the corner belongs to a brand.'])->assertRedirect();
        $this->assertSame(ArtworkStatus::Rejected, $artwork->fresh()->status);
        $this->assertDatabaseHas('moderation_reviews', ['artwork_id' => $artwork->id, 'decision' => 'rejected']);
    }

    #[Test]
    public function upholding_a_copyright_report_takes_the_design_down(): void
    {
        $artistProduct = $this->listedArtistProduct();
        $report = CopyrightReport::query()->create([
            'artwork_id' => $artistProduct->artwork_id, 'reporter_name' => 'R', 'reporter_email' => 'r@example.com', 'rights_holder' => 'Brand Ltd',
            'claim_details' => str_repeat('Copied logo. ', 5), 'good_faith_confirmed' => true,
        ]);

        $this->actingAs($this->staff(RoleName::Moderator))
            ->post(route('admin.copyright.resolve', $report), ['decision' => 'upheld', 'notes' => 'Clear trademark copy.'])
            ->assertRedirect();

        $this->assertSame(ArtworkStatus::Suspended, $artistProduct->artwork->fresh()->status);
        $this->assertSame('upheld', $report->fresh()->status->value);
    }

    #[Test]
    public function admins_assign_items_to_partners_over_http(): void
    {
        $order = $this->paidOrder();
        $item = $order->items->first();
        $partner = PrintingPartner::factory()->servingEverything()->create();

        $this->actingAs($this->staff(RoleName::Admin))
            ->post(route('admin.orders.items.assign', [$order, $item]), ['printing_partner_id' => $partner->id])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame($partner->id, $item->fresh()->activeJob->printing_partner_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'production.assigned']);
    }

    #[Test]
    public function finance_staff_issue_refunds_over_http(): void
    {
        $order = $this->paidOrder(quantity: 2);
        $item = $order->items->first();

        $this->actingAs($this->staff(RoleName::Finance))
            ->post(route('admin.refunds.store', $order), ['order_item_id' => $item->id, 'amount' => '9500', 'reason' => 'damaged', 'provider_reference' => 'MOMO-RF-77'])
            ->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderPaymentStatus::PartiallyRefunded, $order->payment_status);
        $this->assertSame(9500, $order->refunded_amount);
        $this->assertDatabaseHas('refunds', ['order_id' => $order->id, 'status' => 'processed', 'provider_reference' => 'MOMO-RF-77']);
    }

    #[Test]
    public function payouts_are_approved_and_marked_paid_over_http(): void
    {
        $order = $this->paidOrder(quantity: 4);
        $item = $order->items->first();
        $item->forceFill(['delivered_at' => now()->subDays(10)])->save();
        app(EarningsReleaser::class)->releaseEligible();
        $account = PayoutAccount::query()->create([
            'artist_profile_id' => $item->artist_profile_id, 'method' => 'mtn_momo', 'account_name' => 'Artist', 'account_number' => '250788000111',
            'account_last4' => '0111', 'country_id' => $this->rwanda()->id, 'is_default' => true,
        ]);
        $finance = $this->staff(RoleName::Finance);

        $this->actingAs($finance)->post(route('admin.payout-accounts.verify', $account))->assertRedirect();
        $payout = app(PayoutService::class)->request($item->artist->fresh(), 5200);

        $this->actingAs($finance)->post(route('admin.payouts.approve', $payout))->assertRedirect();
        $this->assertSame(PayoutStatus::Approved, $payout->fresh()->status);

        $this->actingAs($finance)->post(route('admin.payouts.paid', $payout), ['provider' => 'mtn_momo', 'reference' => 'TX-9001'])->assertRedirect();
        $this->assertSame(PayoutStatus::Paid, $payout->fresh()->status);
        $this->assertDatabaseHas('payout_transactions', ['payout_request_id' => $payout->id, 'reference' => 'TX-9001']);
    }

    #[Test]
    public function changing_a_pricing_rule_is_audited(): void
    {
        $rule = PricingRule::query()->firstOrFail();

        $this->actingAs($this->staff(RoleName::Finance))->put(route('admin.pricing-rules.update', $rule), [
            'name' => $rule->name, 'scope' => 'global', 'currency' => 'RWF',
            'platform_margin_percent' => '30', 'platform_fixed' => '0',
            'artist_min_percent' => '0', 'artist_default_percent' => '20', 'artist_max_percent' => '100',
            'artist_fixed_max' => '20000', 'rounding_increment' => '100', 'is_active' => '1',
        ])->assertRedirect(route('admin.pricing-rules.index'));

        $this->assertSame(3000, $rule->fresh()->platform_margin_bps);
        $log = AuditLog::query()->where('action', 'pricing_rule.updated')->firstOrFail();
        $this->assertEquals(['old' => 2500, 'new' => 3000], $log->changes['platform_margin_bps']);
    }

    #[Test]
    public function the_order_export_is_a_csv_of_filtered_orders(): void
    {
        $order = $this->paidOrder();

        $response = $this->actingAs($this->staff(RoleName::Support))->get(route('admin.orders.export', ['payment_status' => 'paid']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();
        $this->assertStringStartsWith('number,placed_at', $csv);
        $this->assertStringContainsString($order->number, $csv);
    }

    #[Test]
    public function unpaid_orders_can_be_cancelled_and_paid_ones_cannot(): void
    {
        $unpaid = $this->placeOrder([[$this->listedArtistProduct(), $this->variant(), 1]]);
        $admin = $this->staff(RoleName::Admin);

        $this->actingAs($admin)->post(route('admin.orders.cancel', $unpaid), ['reason' => 'Customer asked'])->assertSessionHas('success');
        $this->assertSame('cancelled', $unpaid->fresh()->status->value);

        $paid = $this->paidOrder();
        $this->actingAs($admin)->post(route('admin.orders.cancel', $paid), ['reason' => 'x'])->assertSessionHas('error');
        $this->assertTrue($paid->fresh()->isPaid());
    }

    #[Test]
    public function dashboard_gmv_excludes_pending_failed_and_fully_refunded_orders(): void
    {
        $paid = $this->paidOrder();
        $this->placeOrder([[$this->listedArtistProduct(), $this->variant(), 1]]); // pending payment
        $refunded = $this->paidOrder();
        app(RefundService::class)->issue($refunded, null, $refunded->total_amount, RefundReason::ProductionFailure, $this->staff(RoleName::Finance), 'RF-1');

        $summary = AdminMetrics::forDays(30)->summary();

        $this->assertSame($paid->total_amount, $summary['gmv']);
        $this->assertSame(1, $summary['completed_sales']);
        $this->assertSame(3, $summary['orders_placed']);

        $this->actingAs($this->staff())->get(route('admin.dashboard', ['days' => 7]))
            ->assertOk()
            ->assertSee(Money::format($paid->total_amount, 'RWF'));
    }
}
