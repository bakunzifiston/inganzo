<?php

namespace Tests\Feature\Partner;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductionJobStatus;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\PartnerMember;
use App\Models\PrintingPartner;
use App\Models\ProductionJob;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartnerPortalTest extends TestCase
{
    use RefreshDatabase;

    private function staffFor(PrintingPartner $partner): User
    {
        $user = User::factory()->withRole(RoleName::PartnerStaff)->create();
        PartnerMember::query()->create(['printing_partner_id' => $partner->id, 'user_id' => $user->id]);

        return $user->fresh();
    }

    /**
     * @return array{0: PrintingPartner, 1: User, 2: Order, 3: ProductionJob}
     */
    private function assignedJob(): array
    {
        $partner = PrintingPartner::factory()->servingEverything()->create();
        $staff = $this->staffFor($partner);
        $order = $this->paidOrder();
        $job = $order->items->first()->activeJob;
        $this->assertSame($partner->id, $job->printing_partner_id);

        return [$partner, $staff, $order, $job];
    }

    #[Test]
    public function partner_staff_see_only_their_own_jobs(): void
    {
        [, $staff, , $job] = $this->assignedJob();
        $other = PrintingPartner::factory()->servingEverything()->create();
        $otherStaff = $this->staffFor($other);

        $this->actingAs($staff)->get(route('partner.dashboard'))->assertOk()->assertSee($job->reference);
        $this->actingAs($staff)->get(route('partner.jobs.show', $job))->assertOk()->assertSee($job->orderItem->sku);

        $this->actingAs($otherStaff)->get(route('partner.dashboard'))->assertOk()->assertDontSee($job->reference);
        $this->actingAs($otherStaff)->get(route('partner.jobs.show', $job))->assertNotFound();
        $this->actingAs($otherStaff)->post(route('partner.jobs.advance', $job), ['status' => 'accepted'])->assertNotFound();
        $this->assertSame(ProductionJobStatus::Assigned, $job->fresh()->status);
    }

    #[Test]
    public function customers_cannot_open_the_partner_portal(): void
    {
        $this->actingAs(User::factory()->withRole(RoleName::Customer)->create())
            ->get(route('partner.dashboard'))->assertForbidden();
    }

    #[Test]
    public function a_partner_can_produce_ship_and_deliver_an_order_over_http(): void
    {
        [, $staff, $order, $job] = $this->assignedJob();
        $this->actingAs($staff);

        foreach (['accepted', 'in_production', 'quality_check'] as $status) {
            $this->post(route('partner.jobs.advance', $job), ['status' => $status])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->post(route('partner.jobs.advance', $job), ['status' => 'dispatched'])->assertSessionHasErrors('status');

        $this->post(route('partner.jobs.qc', $job), ['qc_result' => 'failed'])->assertSessionHasErrors('qc_notes');
        $this->post(route('partner.jobs.qc', $job), ['qc_result' => 'passed'])->assertSessionHasNoErrors();
        $this->assertSame(ProductionJobStatus::ReadyForDispatch, $job->fresh()->status);

        $this->post(route('partner.shipments.store', $order), [
            'items' => [$job->order_item_id],
            'carrier' => 'Kigali Express',
            'tracking_number' => 'KX-1',
        ])->assertRedirect(route('partner.shipments.index'))->assertSessionHasNoErrors();

        $shipment = Shipment::query()->sole();
        $this->assertSame(FulfillmentStatus::Shipped, $order->fresh()->fulfillment_status);
        $this->get(route('partner.shipments.index'))->assertOk()->assertSee($shipment->reference);

        $this->post(route('partner.shipments.events.store', $shipment), ['status' => 'in_transit', 'location' => 'Remera'])->assertSessionHasNoErrors();
        $this->post(route('partner.shipments.events.store', $shipment), ['status' => 'delivered'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(FulfillmentStatus::Delivered, $order->fulfillment_status);
        $this->assertSame(OrderStatus::Completed, $order->status);
    }

    #[Test]
    public function partners_cannot_update_other_partners_shipments_or_ship_their_orders(): void
    {
        [, $staff, $order, $job] = $this->assignedJob();
        $otherStaff = $this->staffFor(PrintingPartner::factory()->servingEverything()->create());

        $this->actingAs($otherStaff)->post(route('partner.shipments.store', $order), [
            'items' => [$job->order_item_id], 'carrier' => 'X',
        ])->assertNotFound();

        $this->actingAs($staff);
        foreach (['accepted', 'in_production', 'quality_check'] as $status) {
            $this->post(route('partner.jobs.advance', $job), ['status' => $status]);
        }
        $this->post(route('partner.jobs.qc', $job), ['qc_result' => 'passed']);
        $this->post(route('partner.shipments.store', $order), ['items' => [$job->order_item_id], 'carrier' => 'Moto']);
        $shipment = Shipment::query()->sole();

        $this->actingAs($otherStaff)->post(route('partner.shipments.events.store', $shipment), ['status' => 'delivered'])->assertNotFound();
        $this->actingAs($otherStaff)->get(route('partner.shipments.index'))->assertOk()->assertDontSee($shipment->reference);
        $this->assertNull($order->fresh()->items->first()->delivered_at);
    }

    #[Test]
    public function issues_and_failures_are_recorded(): void
    {
        [, $staff, , $job] = $this->assignedJob();

        $this->actingAs($staff)->post(route('partner.jobs.issue', $job), ['notes' => 'Black hoodies out of stock until Friday'])->assertSessionHasNoErrors();
        $this->assertTrue($job->fresh()->has_issue);

        $this->post(route('partner.jobs.fail', $job), ['reason' => 'Blank garment damaged'])->assertSessionHasNoErrors();
        $this->assertSame(ProductionJobStatus::Failed, $job->fresh()->status);
    }

    #[Test]
    public function only_the_assigned_partner_can_download_the_original_artwork(): void
    {
        Storage::fake(config('marketplace.artwork_disk'));
        [, $staff, , $job] = $this->assignedJob();
        $version = $job->orderItem->artworkVersion;
        Storage::disk($version->disk)->put($version->original_path, 'png-bytes');
        $otherStaff = $this->staffFor(PrintingPartner::factory()->servingEverything()->create());

        $this->actingAs($staff)->get(route('media.original', $version))->assertOk()->assertDownload();
        $this->actingAs($otherStaff)->get(route('media.original', $version))->assertForbidden();
    }

    #[Test]
    public function the_job_page_shows_delivery_details_but_no_prices(): void
    {
        [, $staff, $order, $job] = $this->assignedJob();

        $this->actingAs($staff)->get(route('partner.jobs.show', $job))
            ->assertOk()
            ->assertSee($order->address->recipient_name)
            ->assertSee('KG 11 Ave')
            ->assertDontSee(Money::format($order->total_amount, $order->currency));
    }
}
