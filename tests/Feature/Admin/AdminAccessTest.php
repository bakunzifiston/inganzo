<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\ArtistProfile;
use App\Models\PrintingPartner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_are_sent_to_login_and_customers_are_forbidden(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $customer = User::factory()->withRole(RoleName::Customer)->create();
        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();
    }

    #[Test]
    public function artists_and_partner_staff_cannot_enter_the_admin_console(): void
    {
        $artist = ArtistProfile::factory()->create()->user;
        $this->actingAs($artist)->get(route('admin.dashboard'))->assertForbidden();

        $partnerUser = User::factory()->withRole(RoleName::PartnerStaff)->create();
        $this->actingAs($partnerUser)->get(route('admin.production.index'))->assertForbidden();
    }

    #[Test]
    public function moderators_can_moderate_but_cannot_see_finance_or_orders(): void
    {
        $moderator = $this->staff(RoleName::Moderator);

        $this->actingAs($moderator)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($moderator)->get(route('admin.moderation.index'))->assertOk();
        $this->actingAs($moderator)->get(route('admin.finance.index'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.payouts.index'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.pricing-rules.index'))->assertForbidden();
    }

    #[Test]
    public function support_can_view_orders_but_not_manage_them(): void
    {
        $support = $this->staff(RoleName::Support);
        $order = $this->paidOrder();
        $item = $order->items->first();
        $partner = PrintingPartner::factory()->servingEverything()->create();

        $this->actingAs($support)->get(route('admin.orders.show', $order))->assertOk();
        $this->actingAs($support)->post(route('admin.orders.items.assign', [$order, $item]), ['printing_partner_id' => $partner->id])->assertForbidden();
        $this->actingAs($support)->post(route('admin.refunds.store', $order), ['amount' => 100, 'reason' => 'other', 'provider_reference' => 'X'])->assertForbidden();
        $this->actingAs($support)->get(route('admin.settings.edit'))->assertForbidden();
    }

    #[Test]
    public function finance_staff_cannot_moderate_or_manage_staff(): void
    {
        $finance = $this->staff(RoleName::Finance);

        $this->actingAs($finance)->get(route('admin.finance.index'))->assertOk();
        $this->actingAs($finance)->get(route('admin.pricing-rules.index'))->assertOk();
        $this->actingAs($finance)->get(route('admin.moderation.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('admin.roles.index'))->assertForbidden();
    }

    #[Test]
    public function only_super_admins_can_grant_super_admin(): void
    {
        $admin = $this->staff(RoleName::Admin);
        $target = User::factory()->withRole(RoleName::Customer)->create();
        $superRole = Role::query()->where('slug', 'super_admin')->firstOrFail();

        // Admins do not hold staff.manage by default.
        $this->actingAs($admin)->put(route('admin.users.roles', $target), ['roles' => [$superRole->id]])->assertForbidden();

        $super = $this->staff(RoleName::SuperAdmin);
        $this->actingAs($super)->put(route('admin.users.roles', $target), ['roles' => [$superRole->id]])->assertRedirect();
        $this->assertTrue($target->fresh()->isSuperAdmin());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.roles_changed', 'subject_id' => $target->id]);
    }

    #[Test]
    public function every_admin_index_page_renders_for_a_super_admin(): void
    {
        $this->paidOrder();
        $admin = $this->staff();

        foreach (['admin.dashboard', 'admin.orders.index', 'admin.production.index', 'admin.payments.index', 'admin.refunds.index', 'admin.moderation.index',
            'admin.artists.index', 'admin.payouts.index', 'admin.finance.index', 'admin.pricing-rules.index', 'admin.pricing-rules.create', 'admin.products.index',
            'admin.products.create', 'admin.product-types.index', 'admin.partners.index', 'admin.partners.create', 'admin.discounts.index', 'admin.discounts.create',
            'admin.categories.index', 'admin.collections.index', 'admin.promotions.index', 'admin.localization.index', 'admin.copyright.index',
            'admin.support.index', 'admin.users.index', 'admin.roles.index', 'admin.settings.edit', 'admin.audit.index', 'admin.system.health'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }
}
