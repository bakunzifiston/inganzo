<?php

namespace Tests\Feature\Dashboards;

use App\Enums\RoleName;
use App\Models\Artwork;
use App\Models\PartnerMember;
use App\Models\PrintingPartner;
use App\Models\ProductionJob;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Artwork\ArtistProductConfigurator;
use App\Services\Reporting\AdminMetrics;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleDashboardsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admins_see_money_platform_overview_queues_and_activity(): void
    {
        $order = $this->paidOrder(quantity: 2);

        $this->actingAs($this->staff(RoleName::SuperAdmin))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('Gross merchandise value'))
            ->assertSee(Money::format($order->total_amount, 'RWF'))
            ->assertSeeInOrder([__('Key metrics'), __('Analytics'), __('Alerts'), __('Details')])
            ->assertSee(__('Products for sale'))
            ->assertSee(__('Recent activity'))
            ->assertSee($order->number)
            ->assertSee(__('Super administrator'));
    }

    #[Test]
    public function moderators_see_review_work_but_no_money_or_orders(): void
    {
        $this->paidOrder();
        Artwork::factory()->withVersion()->pendingReview()->create(['title' => 'Waiting Design']);

        $this->actingAs($this->staff(RoleName::Moderator))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('Designs to review'))
            ->assertSee('Waiting Design')
            ->assertDontSee(__('Gross merchandise value'))
            ->assertDontSee(__('Net platform revenue'))
            ->assertDontSee(__('Sales trend'))
            ->assertDontSee(__('Refunds to review'));
    }

    #[Test]
    public function finance_sees_revenue_and_liabilities_but_not_moderation(): void
    {
        $this->paidOrder();

        $this->actingAs($this->staff(RoleName::Finance))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('Net platform revenue'))
            ->assertSee(__('Platform revenue breakdown'))
            ->assertSee(__('Owed to printing partners'))
            ->assertSee(__('Artist payouts'))
            ->assertDontSee(__('Designs to review'))
            ->assertDontSee(__('Products for sale'));
    }

    #[Test]
    public function support_sees_tickets_and_order_counts_without_revenue(): void
    {
        SupportTicket::query()->create(['reference' => 'SUP-1', 'name' => 'A', 'email' => 'a@example.rw', 'category' => 'order', 'subject' => 'Where is my hoodie', 'message' => 'Please help me.']);

        $this->actingAs($this->staff(RoleName::Support))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Where is my hoodie')
            ->assertSee(__('Orders placed'))
            ->assertDontSee(__('Gross merchandise value'))
            ->assertDontSee(__('Platform revenue breakdown'));
    }

    #[Test]
    public function period_comparisons_use_the_previous_period_of_the_same_length(): void
    {
        $metrics = AdminMetrics::forDays(30);
        $previous = $metrics->previous();

        $this->assertTrue($previous->to->lessThan($metrics->from));
        $this->assertEqualsWithDelta($metrics->from->diffInDays($metrics->to), $previous->from->diffInDays($previous->to), 0.01);
        $this->assertSame(50.0, AdminMetrics::change(150, 100));
        $this->assertSame(-25.0, AdminMetrics::change(75, 100));
        $this->assertNull(AdminMetrics::change(10, 0));
    }

    #[Test]
    public function artists_see_their_own_print_readiness_sales_and_only_their_data(): void
    {
        $mine = $this->listedArtistProduct();
        $this->paidOrder($mine, 2);
        $other = $this->listedArtistProduct();
        $other->artwork->forceFill(['title' => 'Someone Elses Art'])->save();
        $failing = Artwork::factory()->for($mine->artwork->artist, 'artist')->withVersion(1000, 1000)->create(['title' => 'Too Small Art']);
        app(ArtistProductConfigurator::class)->save($failing->fresh('currentVersion'), $this->product('classic-tee'), [
            'is_enabled' => true, 'colors' => [$this->product('classic-tee')->colors->first()->id], 'markup_type' => 'percent', 'markup_bps' => 2000,
            'placements' => [$this->product('classic-tee')->primaryPrintArea()->id => ['offset_x' => 0, 'offset_y' => 0, 'scale' => 1]],
        ]);

        $this->actingAs($mine->artwork->artist->user)->get(route('studio.dashboard'))
            ->assertOk()
            ->assertSee(__('Print readiness'))
            ->assertSee('Too Small Art')
            ->assertSee(__('Fix'))
            ->assertSee(__('Recent orders'))
            ->assertSee(Money::format(2600, 'RWF'))
            ->assertDontSee('Someone Elses Art');
    }

    #[Test]
    public function the_artist_dashboard_shows_quick_actions_wallet_checklist_and_latest_designs(): void
    {
        $artistProduct = $this->listedArtistProduct();
        $this->paidOrder($artistProduct, 2);
        $artist = $artistProduct->artwork->artist;

        $response = $this->actingAs($artist->user)->get(route('studio.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([__('Quick actions'), __('Key metrics'), __('Analytics'), __('Alerts'), __('Details')], false)
            ->assertSee(__('Upload a design'))
            ->assertSee(__('Available to withdraw'))
            ->assertSee(__('Get your shop ready'))
            ->assertSee(__('Add a profile picture and bio'))
            ->assertSee(__('Your latest designs'))
            ->assertSee($artistProduct->artwork->title);

        $this->assertSame(1, $response->viewData('summary')['orders']);
        $checklist = collect($response->viewData('checklist'))->keyBy('label');
        $this->assertTrue($checklist[__('Make your first sale')]['done']);
        $this->assertFalse($checklist[__('Add a payout account')]['done']);
    }

    #[Test]
    public function partners_see_production_kpis_scoped_to_their_facility(): void
    {
        $partner = PrintingPartner::factory()->servingEverything()->create();
        $other = PrintingPartner::factory()->servingEverything()->create(['priority' => 1]);
        $this->paidOrder(); // assigned to $other (better priority)
        $staff = User::factory()->withRole(RoleName::PartnerStaff)->create();
        PartnerMember::query()->create(['printing_partner_id' => $partner->id, 'user_id' => $staff->id]);

        $response = $this->actingAs($staff->fresh())->get(route('partner.dashboard'))->assertOk()->assertSee(__('New jobs to accept'));
        $this->assertSame(0, $response->viewData('stats')['new']);

        $this->assertSame(1, ProductionJob::query()->where('printing_partner_id', $other->id)->count());
    }

    #[Test]
    public function customers_see_their_orders_progress_and_spend(): void
    {
        $customer = User::factory()->withRole(RoleName::Customer)->create();
        $order = $this->paidOrder(customer: $customer);

        $this->actingAs($customer)->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee(__('On the way'))
            ->assertSee($order->number)
            ->assertSee(Money::format($order->total_amount, 'RWF'))
            ->assertSee(__('Are you an artist?'));
    }

    #[Test]
    public function the_workspace_switcher_only_lists_areas_the_user_can_open(): void
    {
        $artist = $this->listedArtistProduct()->artwork->artist->user;

        $this->actingAs($artist)->get(route('studio.dashboard'))
            ->assertSee(__('Artist studio'))
            ->assertSee(route('account.dashboard'), false)
            ->assertDontSee(route('admin.dashboard'), false)
            ->assertDontSee(route('partner.dashboard'), false);
    }
}
