<?php

namespace Tests\Feature\Account;

use App\Enums\ArtistStatus;
use App\Enums\RefundStatus;
use App\Enums\RoleName;
use App\Models\ArtistProfile;
use App\Models\Artwork;
use App\Models\CustomerAddress;
use App\Models\District;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->withRole(RoleName::Customer)->create();
    }

    #[Test]
    public function account_pages_render_for_a_verified_customer(): void
    {
        $customer = $this->customer();
        $this->paidOrder(customer: $customer);

        foreach (['account.dashboard', 'account.orders.index', 'account.addresses.index', 'account.addresses.create', 'account.favorites.index', 'account.profile.edit', 'account.notifications', 'account.become-artist'] as $route) {
            $this->actingAs($customer)->get(route($route))->assertOk();
        }
    }

    #[Test]
    public function unverified_users_are_sent_to_verify_their_email(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('account.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function customers_see_their_own_orders_only(): void
    {
        $owner = $this->customer();
        $order = $this->paidOrder(customer: $owner);

        $this->actingAs($owner)->get(route('account.orders.show', $order))->assertOk()->assertSee($order->number);
        $this->actingAs($this->customer())->get(route('account.orders.show', $order))->assertForbidden();
    }

    #[Test]
    public function customers_can_request_a_refund_within_the_refundable_amount(): void
    {
        $owner = $this->customer();
        $order = $this->paidOrder(customer: $owner);
        $item = $order->items->first();

        $this->actingAs($owner)->post(route('account.orders.refunds.store', $order), [
            'reason' => 'damaged', 'order_item_id' => $item->id, 'amount' => $order->total_amount + 1000, 'customer_note' => 'The print peeled after one wash.',
        ])->assertSessionHasErrors('amount');
        $this->assertSame(0, Refund::query()->count());

        $this->actingAs($owner)->post(route('account.orders.refunds.store', $order), [
            'reason' => 'damaged', 'order_item_id' => $item->id, 'amount' => 5000, 'customer_note' => 'The print peeled after one wash.',
        ])->assertRedirect(route('account.orders.show', $order))->assertSessionHasNoErrors();

        $refund = Refund::query()->sole();
        $this->assertSame(RefundStatus::Requested, $refund->status);
        $this->assertSame(5000, $refund->amount);
        $this->assertSame($owner->id, $refund->requested_by);
        $this->assertFalse($order->fresh()->isPaid() === false);
    }

    #[Test]
    public function other_customers_cannot_request_refunds_on_an_order(): void
    {
        $order = $this->paidOrder(customer: $this->customer());

        $this->actingAs($this->customer())->post(route('account.orders.refunds.store', $order), [
            'reason' => 'damaged', 'amount' => 1000, 'customer_note' => 'Not my order but trying anyway.',
        ])->assertForbidden();
    }

    #[Test]
    public function addresses_can_be_managed_with_validated_rwandan_phones(): void
    {
        $customer = $this->customer();
        $district = District::query()->where('name', 'Huye')->firstOrFail();
        $payload = ['recipient_name' => 'Aline', 'phone' => '0788 123 456', 'district_id' => $district->id, 'street_address' => 'NR 2 Rd'];

        $this->actingAs($customer)->post(route('account.addresses.store'), ['phone' => '12345'] + $payload)->assertSessionHasErrors('phone');

        $this->post(route('account.addresses.store'), $payload)->assertRedirect(route('account.addresses.index'));
        $address = CustomerAddress::query()->sole();
        $this->assertSame('+250788123456', $address->phone);
        $this->assertTrue($address->is_default);

        $this->post(route('account.addresses.store'), ['street_address' => 'Second', 'is_default' => '1'] + $payload);
        $this->assertFalse($address->fresh()->is_default);

        $this->put(route('account.addresses.update', $address), ['street_address' => 'Updated'] + $payload)->assertRedirect();
        $this->assertSame('Updated', $address->fresh()->street_address);

        $this->actingAs($this->customer())->get(route('account.addresses.edit', $address))->assertNotFound();
        $this->actingAs($customer)->delete(route('account.addresses.destroy', $address))->assertRedirect();
        $this->assertSame(1, CustomerAddress::query()->count());
        $this->assertTrue(CustomerAddress::query()->sole()->is_default);
    }

    #[Test]
    public function public_designs_can_be_favourited_and_unfavourited(): void
    {
        $customer = $this->customer();
        $artwork = $this->listedArtistProduct()->artwork;

        $this->actingAs($customer)->post(route('favorites.toggle', $artwork))->assertRedirect();
        $this->assertTrue($customer->favorites()->whereKey($artwork->id)->exists());
        $this->get(route('account.favorites.index'))->assertOk()->assertSee($artwork->title);

        $this->post(route('favorites.toggle', $artwork));
        $this->assertFalse($customer->favorites()->whereKey($artwork->id)->exists());

        $draft = Artwork::factory()->withVersion()->create();
        $this->post(route('favorites.toggle', $draft))->assertNotFound();
    }

    #[Test]
    public function customers_can_become_pending_artists(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('account.become-artist.store'), ['display_name' => 'Kivu Colours'])->assertSessionHasErrors('accept_agreement');

        $this->post(route('account.become-artist.store'), ['display_name' => 'Kivu Colours', 'accept_agreement' => '1'])
            ->assertRedirect(route('studio.dashboard'));

        $profile = ArtistProfile::query()->where('user_id', $customer->id)->sole();
        $this->assertSame(ArtistStatus::Pending, $profile->status);
        $this->assertSame('kivu-colours', $profile->slug);
        $this->assertTrue($customer->fresh()->hasRole(RoleName::Artist));
        $this->get(route('account.become-artist'))->assertRedirect(route('studio.dashboard'));
    }

    #[Test]
    public function profile_and_password_can_be_updated(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->put(route('account.profile.update'), ['name' => 'New Name', 'phone' => '0722 000 111', 'locale' => 'rw'])->assertSessionHasNoErrors();
        $customer->refresh();
        $this->assertSame('+250722000111', $customer->phone);
        $this->assertSame('rw', $customer->locale);

        $this->put(route('account.profile.password'), ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasErrorsIn('password', 'current_password');
        $this->put(route('account.profile.password'), ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password-1', $customer->fresh()->password));
    }

    #[Test]
    public function delivered_items_can_be_reviewed_once(): void
    {
        $owner = $this->customer();
        $order = $this->paidOrder(customer: $owner);
        $item = $order->items->first();

        $this->actingAs($owner)->post(route('account.orders.review', [$order, $item]), ['rating' => 5])->assertSessionHasErrors('rating');

        $item->forceFill(['fulfillment_status' => 'delivered', 'delivered_at' => now()])->save();
        $this->post(route('account.orders.review', [$order, $item]), ['rating' => 4, 'title' => 'Great'])->assertSessionHasNoErrors();
        $this->post(route('account.orders.review', [$order, $item]), ['rating' => 1])->assertSessionHasErrors('rating');
        $this->assertSame(4, $item->review()->sole()->rating);
    }
}
