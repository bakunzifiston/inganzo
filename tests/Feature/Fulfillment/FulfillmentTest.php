<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductionJobStatus;
use App\Enums\QcResult;
use App\Enums\RoleName;
use App\Enums\ShipmentStatus;
use App\Models\LedgerEntry;
use App\Models\PartnerMember;
use App\Models\PrintingPartner;
use App\Models\ProductionJob;
use App\Models\User;
use App\Services\Fulfillment\PartnerAssigner;
use App\Services\Fulfillment\ProductionWorkflow;
use App\Services\Fulfillment\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function partnerUser(PrintingPartner $partner): User
    {
        $user = User::factory()->withRole(RoleName::PartnerStaff)->create();
        PartnerMember::query()->create(['printing_partner_id' => $partner->id, 'user_id' => $user->id]);

        return $user->fresh();
    }

    #[Test]
    public function paid_items_are_assigned_to_an_eligible_partner(): void
    {
        $busy = PrintingPartner::factory()->servingEverything()->create(['daily_capacity' => 1, 'name' => 'Busy']);
        $free = PrintingPartner::factory()->servingEverything()->create(['daily_capacity' => 100, 'name' => 'Free']);
        ProductionJob::unguarded(fn () => null);

        $order = $this->paidOrder();
        $job = $order->items->first()->activeJob;

        $this->assertNotNull($job);
        $this->assertSame(ProductionJobStatus::Assigned, $job->status);
        $this->assertContains($job->printing_partner_id, [$busy->id, $free->id]);
        $this->assertSame(FulfillmentStatus::InProduction, $order->fresh()->fulfillment_status);
    }

    #[Test]
    public function items_wait_for_manual_assignment_when_no_partner_serves_them(): void
    {
        $order = $this->paidOrder();

        $this->assertSame(OrderItemStatus::AwaitingAssignment, $order->items->first()->fulfillment_status);

        $partner = PrintingPartner::factory()->servingEverything()->create();
        $job = app(PartnerAssigner::class)->assign($order->items->first(), $partner, $this->staff());
        $this->assertSame($partner->id, $job->printing_partner_id);
    }

    #[Test]
    public function the_full_production_and_delivery_flow_completes_the_order(): void
    {
        $partner = PrintingPartner::factory()->servingEverything()->create();
        $staff = $this->partnerUser($partner);
        $order = $this->paidOrder(quantity: 2);
        $job = $order->items->first()->activeJob;
        $workflow = app(ProductionWorkflow::class);

        $job = $workflow->advance($job, ProductionJobStatus::Accepted, $staff);
        $job = $workflow->advance($job, ProductionJobStatus::InProduction, $staff);
        $job = $workflow->advance($job, ProductionJobStatus::QualityCheck, $staff);

        $job = $workflow->recordQualityCheck($job, QcResult::Failed, $staff, 'Ink smudge on the sleeve');
        $this->assertSame(ProductionJobStatus::InProduction, $job->status);
        $this->assertTrue($job->has_issue);

        $job = $workflow->advance($job, ProductionJobStatus::QualityCheck, $staff);
        $job = $workflow->recordQualityCheck($job, QcResult::Passed, $staff);
        $this->assertSame(ProductionJobStatus::ReadyForDispatch, $job->status);

        $shipment = app(ShipmentService::class)->dispatch($order, [$job->order_item_id], 'Kigali Express', 'KE-123', null, $staff, $partner->id);
        $this->assertSame(FulfillmentStatus::Shipped, $order->fresh()->fulfillment_status);
        $this->assertSame(-$job->unit_cost_amount * 2, (int) LedgerEntry::query()->where('type', 'production_cost')->where('account_type', 'platform')->sum('amount'));

        app(ShipmentService::class)->recordEvent($shipment, ShipmentStatus::Delivered, $staff, 'Kimironko');
        $order->refresh();
        $this->assertSame(FulfillmentStatus::Delivered, $order->fulfillment_status);
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->items->first()->delivered_at);
    }

    #[Test]
    public function invalid_production_transitions_are_refused(): void
    {
        $partner = PrintingPartner::factory()->servingEverything()->create();
        $staff = $this->partnerUser($partner);
        $job = $this->paidOrder()->items->first()->activeJob;

        $this->expectException(ValidationException::class);
        app(ProductionWorkflow::class)->advance($job, ProductionJobStatus::QualityCheck, $staff);
    }

    #[Test]
    public function orders_can_be_fulfilled_partially(): void
    {
        $partner = PrintingPartner::factory()->servingEverything()->create();
        $staff = $this->partnerUser($partner);
        $order = $this->placeOrder([
            [$this->listedArtistProduct('classic-tee'), $this->variant(), 1],
            [$this->listedArtistProduct('ceramic-mug'), $this->variant('ceramic-mug', 'White', null), 1],
        ]);
        $this->simulatorCallback($this->initiatePayment($order))->assertOk();
        $order->refresh();

        $workflow = app(ProductionWorkflow::class);
        $job = $order->items->first()->activeJob;
        foreach ([ProductionJobStatus::Accepted, ProductionJobStatus::InProduction, ProductionJobStatus::QualityCheck] as $status) {
            $job = $workflow->advance($job, $status, $staff);
        }
        $job = $workflow->recordQualityCheck($job, QcResult::Passed, $staff);

        app(ShipmentService::class)->dispatch($order, [$job->order_item_id], 'Moto courier', null, null, $staff, $partner->id);

        $this->assertSame(FulfillmentStatus::PartiallyShipped, $order->fresh()->fulfillment_status);
    }

    #[Test]
    public function failed_jobs_can_be_reprinted_at_another_partner(): void
    {
        $first = PrintingPartner::factory()->servingEverything()->create(['priority' => 1]);
        $second = PrintingPartner::factory()->servingEverything()->create(['priority' => 2]);
        $admin = $this->staff(RoleName::Admin);
        $job = $this->paidOrder()->items->first()->activeJob;
        $workflow = app(ProductionWorkflow::class);

        $failed = $workflow->fail($job, $admin, 'Machine breakdown', costIncurred: true);
        $this->assertSame(OrderItemStatus::AwaitingAssignment, $failed->orderItem->fresh()->fulfillment_status);

        $reprint = $workflow->reprint($failed, $admin, $second->id);
        $this->assertSame($failed->id, $reprint->reprint_of_id);
        $this->assertSame($second->id, $reprint->printing_partner_id);
        $this->assertSame(1, LedgerEntry::query()->where('production_job_id', $failed->id)->where('account_type', 'partner')->count());
    }
}
