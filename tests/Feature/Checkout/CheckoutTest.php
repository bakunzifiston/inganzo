<?php

namespace Tests\Feature\Checkout;

use App\Enums\ArtworkStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\DiscountCode;
use App\Models\District;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_order_snapshots_server_calculated_prices_and_the_financial_split(): void
    {
        $tee = $this->listedArtistProduct('classic-tee');
        $mug = $this->listedArtistProduct('ceramic-mug');

        $order = $this->placeOrder([
            [$tee, $this->variant('classic-tee', 'Black', 'M'), 2],
            [$mug, $this->variant('ceramic-mug', 'White', null), 1],
        ]);

        // Tee 9 500 × 2, mug: base 4000 + 800 + 1000 = 5 800. Kigali delivery 2 000.
        $this->assertSame(19000 + 5800, $order->subtotal_amount);
        $this->assertSame(2000, $order->delivery_amount);
        $this->assertSame(26800, $order->total_amount);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(OrderPaymentStatus::Unpaid, $order->payment_status);
        $this->assertMatchesRegularExpression('/^IGZ-\d{6}-[2-9A-HJ-NP-Z]{5}$/', $order->number);

        // VAT 18 % contained in the inclusive total.
        $this->assertSame(4088, $order->tax_amount);

        $line = $order->items->firstWhere('product_id', $tee->product_id);
        $this->assertSame(9500, $line->unit_price_amount);
        $this->assertSame(1300, $line->unit_artist_earning_amount);
        $this->assertSame(19000, $line->line_total_amount);
        $this->assertSame('front', $line->placement_snapshot[0]['print_area_key']);
        $this->assertSame($tee->artwork->current_version_id, $line->artwork_version_id);
    }

    #[Test]
    public function delivery_fee_depends_on_the_district_zone_and_is_free_over_the_threshold(): void
    {
        $tee = $this->listedArtistProduct('classic-tee');

        $provinces = $this->placeOrder([[$tee, $this->variant(), 1]], district: 'Musanze');
        $this->assertSame(3500, $provinces->delivery_amount);

        $big = $this->placeOrder([[$tee, $this->variant(), 7]], district: 'Kicukiro');
        $this->assertSame(66500, $big->subtotal_amount);
        $this->assertSame(0, $big->delivery_amount);
    }

    #[Test]
    public function discounts_are_allocated_across_lines_and_limited_per_customer(): void
    {
        $tee = $this->listedArtistProduct('classic-tee');
        $customer = User::factory()->create();
        DiscountCode::factory()->create(['code' => 'MURAKOZE10', 'percent_bps' => 1000]);

        $order = $this->placeOrder([[$tee, $this->variant(), 2]], $customer, discountCode: 'murakoze10');

        $this->assertSame(1900, $order->discount_amount);
        $this->assertSame(19000 - 1900 + 2000, $order->total_amount);
        $this->assertSame(1900, $order->items->sum('discount_amount'));
        $this->assertDatabaseHas('discount_redemptions', ['order_id' => $order->id, 'amount' => 1900]);
        $this->assertSame(1, DiscountCode::query()->where('code', 'MURAKOZE10')->value('redemptions_count'));

        $this->expectException(ValidationException::class);
        $this->placeOrder([[$tee, $this->variant(), 1]], $customer, discountCode: 'MURAKOZE10');
    }

    #[Test]
    public function an_empty_cart_cannot_be_checked_out_twice(): void
    {
        $tee = $this->listedArtistProduct('classic-tee');
        $this->placeOrder([[$tee, $this->variant(), 1]]);

        $this->expectException(ValidationException::class);
        $this->placeOrder([]);
    }

    #[Test]
    public function unpublished_designs_cannot_be_added_or_bought(): void
    {
        $tee = $this->listedArtistProduct('classic-tee');
        $tee->artwork->forceFill(['status' => ArtworkStatus::Suspended])->save();

        $this->expectException(ValidationException::class);
        $this->placeOrder([[$tee->fresh(), $this->variant(), 1]]);
        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function the_checkout_endpoint_recalculates_totals_and_ignores_client_prices(): void
    {
        $tee = $this->listedArtistProduct('classic-tee');
        $variant = $this->variant();

        $this->post(route('cart.items.store'), ['artist_product_id' => $tee->id, 'variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();

        $response = $this->post(route('checkout.store'), [
            'name' => 'Jean Bosco',
            'email' => 'bosco@example.rw',
            'phone' => '0788 123 456',
            'district_id' => District::query()->where('name', 'Gasabo')->value('id'),
            'street_address' => 'KG 7 Ave 12',
            'payment_method' => 'simulator',
            'payer_phone' => '0788123456',
            'total_amount' => 1, // must be ignored
            'unit_price' => 1,
        ]);

        $order = Order::query()->sole();
        $response->assertRedirect();
        $this->assertSame(9500 + 2000, $order->total_amount);
        $this->assertSame('+250788123456', $order->phone);
        $this->assertSame(OrderPaymentStatus::Pending, $order->payment_status);
    }
}
