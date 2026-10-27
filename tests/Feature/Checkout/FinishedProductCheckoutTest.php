<?php

namespace Tests\Feature\Checkout;

use App\Enums\FinishedProductStatus;
use App\Enums\LedgerAccount;
use App\Enums\LedgerBucket;
use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\RefundReason;
use App\Enums\SellingType;
use App\Enums\ShipmentStatus;
use App\Models\ArtistProfile;
use App\Models\CartItem;
use App\Models\District;
use App\Models\FinishedProduct;
use App\Models\FinishedProductVariant;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Cart\CartManager;
use App\Services\Checkout\CheckoutData;
use App\Services\Checkout\CheckoutService;
use App\Services\Finance\Ledger;
use App\Services\Finance\RefundService;
use App\Services\Inventory\FinishedProductService;
use App\Services\Reporting\ArtistMetrics;
use App\Services\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinishedProductCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function stockOf(FinishedProductVariant $variant): int
    {
        return $variant->fresh()->stock_quantity;
    }

    private function pay(Order $order): Order
    {
        $this->simulatorCallback($this->initiatePayment($order))->assertOk();

        return $order->fresh(['items', 'shipments']);
    }

    private function finishedItem(Order $order): OrderItem
    {
        return $order->items->firstWhere('selling_type', SellingType::Finished);
    }

    #[Test]
    public function a_ready_made_item_is_added_to_the_cart_and_capped_at_the_stock_on_hand(): void
    {
        $product = $this->listedFinishedProduct([['Natural', 'S', 3]]);
        $variant = $product->variants->first();

        $this->post(route('cart.items.store'), ['finished_variant_id' => $variant->id, 'quantity' => 2])->assertRedirect(route('cart.show'));
        $this->post(route('cart.items.store'), ['finished_variant_id' => $variant->id, 'quantity' => 5])->assertRedirect(route('cart.show'));

        // 2 + 5 is capped at the 3 in stock; adding more is refused.
        $this->assertSame(3, CartItem::query()->sole()->quantity);
        $this->post(route('cart.items.store'), ['finished_variant_id' => $variant->id, 'quantity' => 1])->assertSessionHasErrors('variant');

        $this->get(route('cart.show'))->assertOk()->assertSee($product->title)->assertSee('Natural · S')->assertSee('Ready-made');

        // The quantity selector cannot exceed stock either.
        $this->patch(route('cart.items.update', CartItem::query()->sole()), ['quantity' => 9]);
        $this->assertSame(3, CartItem::query()->sole()->quantity);
    }

    #[Test]
    public function unavailable_items_cannot_be_added(): void
    {
        $soldOut = $this->listedFinishedProduct([[null, null, 0]]);
        $draft = FinishedProduct::factory()->withVariants([[null, null, 5]])->create();
        $off = $this->listedFinishedProduct([['Red', 'M', 5]]);
        $off->variants->first()->update(['is_active' => false]);

        foreach ([$soldOut->variants->first(), $draft->variants->first(), $off->variants->first()] as $variant) {
            $this->post(route('cart.items.store'), ['finished_variant_id' => $variant->id, 'quantity' => 1])->assertSessionHasErrors('variant');
        }
        $this->assertSame(0, CartItem::query()->count());
    }

    #[Test]
    public function checkout_reserves_stock_and_prices_the_order_on_the_server(): void
    {
        $product = $this->listedFinishedProduct([['Natural', 'S', 5, 20000, 15000]], attributes: ['price_amount' => 18000]);
        $variant = $product->variants->first();

        $order = $this->placeOrder([[$variant, 2]]);
        $item = $order->items->sole();

        $this->assertSame(SellingType::Finished, $item->selling_type);
        $this->assertSame(3, $this->stockOf($variant), 'stock is reserved when the order is placed');
        $this->assertNull($item->artist_product_id);
        $this->assertNull($item->artwork_version_id);
        $this->assertSame($product->id, $item->finished_product_id);
        $this->assertSame($variant->sku, $item->sku);
        $this->assertSame('Natural · S', $item->variantLabel());
        // The sale price (15 000) wins over the option price (20 000); the commission is 10 % by default.
        $this->assertSame([15000, 0, 13500, 1500, 30000], [$item->unit_price_amount, $item->unit_base_cost_amount, $item->unit_artist_earning_amount, $item->unit_platform_margin_amount, $item->line_total_amount]);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(OrderItemStatus::Pending, $item->fulfillment_status);
        $this->assertSame(30000, $order->subtotal_amount);
        $this->assertSame($order->subtotal_amount + $order->delivery_amount, $order->total_amount);
    }

    #[Test]
    public function the_price_is_always_the_current_price_not_the_one_seen_when_adding_to_the_cart(): void
    {
        $product = $this->listedFinishedProduct([[null, null, 5]], attributes: ['price_amount' => 10000]);
        $variant = $product->variants->first();

        app(CartManager::class)->addFinished($variant, 1);
        $product->forceFill(['price_amount' => 12000, 'sale_price_amount' => 11000])->save();

        $order = $this->placeOrder([]);
        $this->assertSame(11000, $order->items->sole()->unit_price_amount);
    }

    #[Test]
    public function the_last_unit_cannot_be_sold_twice(): void
    {
        $product = $this->listedFinishedProduct([[null, null, 1]]);
        $variant = $product->variants->first();

        // Both customers put the last unit in a cart before either checks out.
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first);
        app(CartManager::class)->addFinished($variant, 1);
        $cartFirst = app(CartManager::class)->current();
        $this->actingAs($second);
        app(CartManager::class)->addFinished($variant, 1);
        $cartSecond = app(CartManager::class)->current();

        $checkout = app(CheckoutService::class);
        $data = fn (User $user) => new CheckoutData(
            user: $user, name: 'Test', email: $user->email, phone: '+250788123456', country: $this->rwanda(),
            district: District::query()->where('name', 'Gasabo')->firstOrFail(), sector: 'K', streetAddress: 'KG 1', landmark: null,
            note: null, paymentMethod: 'simulator', payerPhone: '+250788123456', locale: 'en',
        );

        $checkout->placeOrder($cartFirst, $data($first));

        try {
            $checkout->placeOrder($cartSecond, $data($second));
            $this->fail('The second checkout must not succeed.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cart', $e->errors());
        }

        $this->assertSame(0, $this->stockOf($variant));
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, CartItem::query()->count(), 'the losing customer keeps their cart');
    }

    #[Test]
    public function stock_that_dropped_after_adding_to_the_cart_blocks_checkout_with_a_clear_message(): void
    {
        $product = $this->listedFinishedProduct([[null, null, 5]]);
        $variant = $product->variants->first();
        app(CartManager::class)->addFinished($variant, 4);
        $variant->forceFill(['stock_quantity' => 2])->save();

        $summary = app(CartManager::class)->summarize(app(CartManager::class)->current());
        $this->assertTrue($summary->hasUnavailableItems());
        $this->assertStringContainsString('Only 2 left', $summary->lines[0]->problem);

        try {
            $this->placeOrder([]);
            $this->fail('Checkout should have been refused.');
        } catch (ValidationException $e) {
            $this->assertSame(2, $this->stockOf($variant));
            $this->assertSame(0, Order::query()->count());
        }
    }

    #[Test]
    public function cancelling_an_unpaid_order_returns_the_stock_exactly_once(): void
    {
        $variant = $this->listedFinishedProduct([[null, null, 5]])->variants->first();
        $order = $this->placeOrder([[$variant, 3]]);
        $this->assertSame(2, $this->stockOf($variant));

        app(CheckoutService::class)->cancelUnpaid($order, 'customer_changed_mind');
        $this->assertSame(5, $this->stockOf($variant));
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertNotNull($order->items->sole()->fresh()->stock_released_at);

        app(CheckoutService::class)->cancelUnpaid($order->fresh(), 'again');
        $this->assertSame(5, $this->stockOf($variant), 'a second cancellation must not add stock twice');
    }

    #[Test]
    public function payment_sends_ready_made_items_to_the_artist_not_to_a_printing_partner(): void
    {
        $product = $this->listedFinishedProduct([[null, null, 5]], attributes: ['price_amount' => 20000]);
        $variant = $product->variants->first();
        $order = $this->pay($this->placeOrder([[$variant, 2]]));
        $item = $this->finishedItem($order);

        $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertSame(OrderItemStatus::AwaitingShipment, $item->fulfillment_status);
        $this->assertSame(0, $item->productionJobs()->count());
        $this->assertSame(2, $product->fresh()->sales_count);
        $this->assertSame(3, $this->stockOf($variant), 'payment does not take stock a second time');

        // Earnings: 40 000 sold, 10 % commission, 36 000 held for the artist; no base cost or production cost.
        $balances = app(Ledger::class)->itemArtistBalances($item->id);
        $this->assertSame(['held' => 36000, 'available' => 0], $balances);
        $this->assertSame(0, LedgerEntry::query()->where('order_item_id', $item->id)->where('account_type', LedgerAccount::Partner)->count());
        $this->assertSame(4000, (int) LedgerEntry::query()->where('order_item_id', $item->id)->where('account_type', LedgerAccount::Platform)->where('bucket', LedgerBucket::Revenue)->sum('amount'));
    }

    #[Test]
    public function a_mixed_cart_routes_each_item_by_its_selling_type(): void
    {
        $design = $this->listedArtistProduct();
        $finished = $this->listedFinishedProduct([[null, null, 5]])->variants->first();

        $order = $this->pay($this->placeOrder([[$design, $this->variant(), 1], [$finished, 1]]));

        $this->assertSame(OrderItemStatus::AwaitingAssignment, $order->items->firstWhere('selling_type', SellingType::PrintOnDemand)->fulfillment_status);
        $this->assertSame(OrderItemStatus::AwaitingShipment, $this->finishedItem($order)->fulfillment_status);
        $this->assertNotNull($order->items->firstWhere('selling_type', SellingType::PrintOnDemand)->artwork_version_id, 'printed items keep their artwork snapshot');
        $this->assertSame(2, $order->items->count());
    }

    #[Test]
    public function printed_items_still_need_a_print_ready_design_while_ready_made_items_do_not(): void
    {
        $design = $this->listedArtistProduct();
        $design->forceFill(['print_check_status' => 'fail'])->save();

        $this->expectException(ValidationException::class);
        app(CartManager::class)->add($design, $this->variant(), 1);
    }

    #[Test]
    public function an_artist_ships_their_own_items_and_the_order_completes_on_delivery(): void
    {
        $artist = ArtistProfile::factory()->create();
        $variant = $this->listedFinishedProduct([[null, null, 5]], $artist)->variants->first();
        $customer = User::factory()->create();
        $order = $this->pay($this->placeOrder([[$variant, 1]], $customer));
        $item = $this->finishedItem($order);

        $this->actingAs($artist->user->fresh());
        $this->get(route('studio.orders.index'))->assertOk()->assertSee($order->number)->assertSee('Ready-made');
        $this->get(route('studio.orders.show', $order))->assertOk()
            ->assertSee('Aline Uwase')->assertSee('KG 11 Ave')->assertSee('Mark as shipped');

        $this->post(route('studio.orders.ship', $order), ['items' => [$item->id], 'carrier' => ''])->assertSessionHasErrors('carrier');
        $this->post(route('studio.orders.ship', $order), ['items' => [$item->id], 'carrier' => 'Volcano Express', 'tracking_number' => 'VE123', 'tracking_url' => 'https://track.example/VE123'])->assertSessionHasNoErrors();

        $item->refresh();
        $order->refresh();
        $shipment = $order->shipments()->sole();
        $this->assertSame(OrderItemStatus::Shipped, $item->fulfillment_status);
        $this->assertSame(ShipmentStatus::Dispatched, $shipment->status);
        $this->assertNull($shipment->printing_partner_id);
        $this->assertSame('VE123', $shipment->tracking_number);

        // Shipping twice is refused.
        $this->post(route('studio.orders.ship', $order), ['items' => [$item->id], 'carrier' => 'X'])->assertSessionHasErrors('items');

        $this->post(route('studio.orders.delivered', [$order, $shipment]))->assertSessionHasNoErrors();
        $this->assertSame(OrderItemStatus::Delivered, $item->fresh()->fulfillment_status);
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertNotNull($item->fresh()->delivered_at, 'delivery starts the earnings settlement hold');
    }

    #[Test]
    public function artists_only_see_and_ship_their_own_items(): void
    {
        $mine = ArtistProfile::factory()->create();
        $theirs = ArtistProfile::factory()->create();
        $myVariant = $this->listedFinishedProduct([[null, null, 5]], $mine)->variants->first();
        $theirVariant = $this->listedFinishedProduct([[null, null, 5]], $theirs)->variants->first();

        $order = $this->pay($this->placeOrder([[$myVariant, 1], [$theirVariant, 1]]));
        $myItem = $order->items->firstWhere('artist_profile_id', $mine->id);
        $theirItem = $order->items->firstWhere('artist_profile_id', $theirs->id);

        $this->actingAs($mine->user->fresh())->get(route('studio.orders.show', $order))->assertOk()->assertSee($myItem->title)->assertDontSee($theirItem->title);

        // Another artist's item cannot be included in my shipment, nor can I cancel it.
        $this->post(route('studio.orders.ship', $order), ['items' => [$theirItem->id], 'carrier' => 'DHL'])->assertSessionHasErrors('items');
        $this->post(route('studio.orders.cannot-fulfil', [$order, $theirItem]), ['note' => 'not mine to cancel'])->assertNotFound();
        $this->assertSame(OrderItemStatus::AwaitingShipment, $theirItem->fresh()->fulfillment_status);

        // An artist with no items in the order cannot open it at all.
        $stranger = $this->actingAs(ArtistProfile::factory()->create()->user->fresh());
        $stranger->get(route('studio.orders.show', $order))->assertNotFound();
        $this->post(route('studio.orders.ship', $order), ['items' => [$myItem->id], 'carrier' => 'DHL'])->assertNotFound();
        $this->get(route('studio.orders.index'))->assertOk()->assertDontSee($order->number);
    }

    #[Test]
    public function printed_only_orders_never_reveal_the_customers_address_to_the_artist(): void
    {
        $artist = ArtistProfile::factory()->create();
        $design = $this->listedArtistProduct('classic-tee', $artist);
        $order = $this->pay($this->placeOrder([[$design, $this->variant(), 1]]));

        $this->actingAs($artist->user->fresh())->get(route('studio.orders.show', $order))->assertOk()
            ->assertDontSee('Aline Uwase')->assertDontSee('KG 11 Ave')->assertDontSee('Mark as shipped');
    }

    #[Test]
    public function an_artist_who_cannot_supply_an_item_requests_a_refund_and_the_stock_returns_when_it_is_processed(): void
    {
        $artist = ArtistProfile::factory()->create();
        $variant = $this->listedFinishedProduct([[null, null, 5]], $artist, ['price_amount' => 10000])->variants->first();
        $order = $this->pay($this->placeOrder([[$variant, 2]]));
        $item = $this->finishedItem($order);
        $this->assertSame(3, $this->stockOf($variant));

        $this->actingAs($artist->user->fresh())->post(route('studio.orders.cannot-fulfil', [$order, $item]), ['note' => 'The item broke in storage.'])->assertSessionHasNoErrors();
        $refund = $order->refunds()->sole();
        $this->assertSame(20000, $refund->amount);
        $this->assertSame(RefundReason::ProductionFailure, $refund->reason);
        $this->assertSame(3, $this->stockOf($variant), 'nothing changes until the refund is processed');

        $admin = $this->staff();
        $refunds = app(RefundService::class);
        $refunds->approve($refund, $admin);
        $refunds->process($refund->fresh(), $admin, 'MOMO-REF-1');

        $this->assertSame(5, $this->stockOf($variant));
        $this->assertSame(OrderItemStatus::Refunded, $item->fresh()->fulfillment_status);
        $this->assertSame(OrderPaymentStatus::PartiallyRefunded, $order->fresh()->payment_status);
        // The artist's held earning for this item is reversed in full.
        $this->assertSame(['held' => 0, 'available' => 0], app(Ledger::class)->itemArtistBalances($item->id));
    }

    #[Test]
    public function refunding_an_item_that_already_shipped_does_not_restock_it(): void
    {
        $artist = ArtistProfile::factory()->create();
        $variant = $this->listedFinishedProduct([[null, null, 5]], $artist)->variants->first();
        $order = $this->pay($this->placeOrder([[$variant, 1]]));
        $item = $this->finishedItem($order);

        $this->actingAs($artist->user->fresh())->post(route('studio.orders.ship', $order), ['items' => [$item->id], 'carrier' => 'Volcano']);
        $this->post(route('studio.orders.cannot-fulfil', [$order, $item]), ['note' => 'Already sent out'])->assertSessionHasErrors('note');

        $admin = $this->staff();
        app(RefundService::class)->issue($order->fresh(), $item->fresh(), $item->fresh()->netLineAmount(), RefundReason::Damaged, $admin, 'MOMO-2');

        $this->assertSame(4, $this->stockOf($variant), 'goods already in transit are not put back on the shelf');
        $this->assertNull($item->fresh()->stock_released_at);
    }

    #[Test]
    public function cash_on_delivery_orders_wait_for_the_artist_and_are_paid_when_delivered(): void
    {
        config(['payments.providers.cash_on_delivery.enabled' => true]);
        app(Settings::class)->set('checkout.cod_enabled', true);
        $this->rwanda()->forceFill(['cod_enabled' => true])->save();

        $artist = ArtistProfile::factory()->create();
        $variant = $this->listedFinishedProduct([[null, null, 5]], $artist)->variants->first();
        $order = $this->placeOrder([[$variant, 1]], null, 'Gasabo', null, 'cash_on_delivery');
        $this->initiatePayment($order, 'cash_on_delivery');
        $order->refresh();
        $item = $this->finishedItem($order->load('items'));

        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertSame(OrderItemStatus::AwaitingShipment, $item->fresh()->fulfillment_status);
        $this->assertFalse($order->isPaid());

        $this->actingAs($artist->user->fresh())->post(route('studio.orders.ship', $order), ['items' => [$item->id], 'carrier' => 'Moto courier'])->assertSessionHasNoErrors();
        $shipment = $order->shipments()->sole();
        $this->post(route('studio.orders.delivered', [$order, $shipment]), ['cash_collected' => '1'])->assertSessionHasNoErrors();

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(OrderItemStatus::Delivered, $item->fresh()->fulfillment_status);
    }

    #[Test]
    public function unpaid_ready_made_orders_cannot_be_shipped(): void
    {
        $artist = ArtistProfile::factory()->create();
        $variant = $this->listedFinishedProduct([[null, null, 5]], $artist)->variants->first();
        $order = $this->placeOrder([[$variant, 1]]);
        $item = $order->items->sole();

        $this->actingAs($artist->user->fresh())->get(route('studio.orders.index'))->assertOk()->assertDontSee($order->number);
        $this->post(route('studio.orders.ship', $order), ['items' => [$item->id], 'carrier' => 'DHL'])->assertSessionHasErrors('items');
        $this->assertSame(0, $order->shipments()->count());
    }

    #[Test]
    public function options_that_were_ordered_are_switched_off_instead_of_deleted(): void
    {
        $artist = ArtistProfile::factory()->create();
        $product = $this->listedFinishedProduct([['Red', 'M', 5], ['Blue', 'M', 5]], $artist);
        [$red, $blue] = $product->variants->all();
        $this->placeOrder([[$red, 1]]);

        $service = app(FinishedProductService::class);
        $service->update($product, [
            'title' => $product->title, 'description' => $product->description, 'price_amount' => 15000, 'processing_days' => 2, 'low_stock_threshold' => 3,
            'variants' => [['id' => $blue->id, 'color_name' => 'Blue', 'color_hex' => '#0000ff', 'size_label' => 'M', 'stock_quantity' => 5, 'is_active' => true]],
        ]);

        $this->assertFalse($red->fresh()->is_active, 'an ordered option is kept but switched off');
        $this->assertTrue($blue->fresh()->is_active);
        $this->assertSame(2, $product->variants()->count(), 'the ordered option still exists for order history');
        $this->assertSame(FinishedProductStatus::Active, $product->fresh()->status);
    }

    #[Test]
    public function checkout_tracking_and_account_pages_render_ready_made_items(): void
    {
        $product = $this->listedFinishedProduct([['Natural', 'S', 5]], attributes: ['title' => 'Sisal Floor Mat']);
        $variant = $product->variants->first();
        $customer = User::factory()->create();

        $this->actingAs($customer);
        app(CartManager::class)->addFinished($variant, 2);
        $this->get(route('checkout.show'))->assertOk()->assertSee('Sisal Floor Mat')->assertSee('Natural · S');

        $order = $this->pay($this->placeOrder([], $customer));
        $this->get(route('orders.track', $order->tracking_token))->assertOk()->assertSee('Sisal Floor Mat')->assertSee('Artist preparing');
        $this->get(route('account.orders.show', $order))->assertOk()->assertSee('Sisal Floor Mat');
        $this->get(route('account.orders.index'))->assertOk()->assertSee($order->number);
    }

    #[Test]
    public function the_database_itself_refuses_negative_stock_and_inconsistent_order_rows(): void
    {
        $variant = $this->listedFinishedProduct([[null, null, 1]])->variants->first();

        try {
            DB::transaction(fn () => DB::table('finished_product_variants')->where('id', $variant->id)->decrement('stock_quantity', 2));
            $this->fail('Negative stock must be impossible.');
        } catch (QueryException) {
            $this->assertSame(1, $this->stockOf($variant));
        }

        // An order item must be exactly one kind: a "finished" row may not carry print data.
        $order = $this->placeOrder([[$variant, 1]]);
        $this->expectException(QueryException::class);
        DB::table('order_items')->where('id', $order->items->sole()->id)->update(['selling_type' => 'print_on_demand']);
    }

    #[Test]
    public function the_marketplace_lists_printed_and_ready_made_products_together(): void
    {
        $design = $this->listedArtistProduct();
        $cheap = $this->listedFinishedProduct([[null, null, 5]], attributes: ['title' => 'Banana Fibre Coasters', 'price_amount' => 3000]);
        $this->listedFinishedProduct([[null, null, 0]], attributes: ['title' => 'Sold Out Sandals', 'price_amount' => 40000]);
        FinishedProduct::factory()->withVariants([[null, null, 5]])->create(['title' => 'Unpublished Secret']);

        $this->get(route('shop.index'))->assertOk()
            ->assertSee($design->artwork->title)->assertSee('Banana Fibre Coasters')->assertSee('Sold Out Sandals')->assertSee('Out of stock')
            ->assertDontSee('Unpublished Secret');

        $this->get(route('shop.index', ['type' => 'ready-made']))->assertOk()->assertSee('Banana Fibre Coasters')->assertDontSee($design->artwork->title);
        $this->get(route('shop.index', ['type' => 't-shirts']))->assertOk()->assertDontSee('Banana Fibre Coasters');
        $this->get(route('shop.index', ['q' => 'coasters']))->assertOk()->assertSee('Banana Fibre Coasters')->assertDontSee('Sold Out Sandals');
        $this->get(route('shop.index', ['max' => 5000, 'sort' => 'price_asc']))->assertOk()->assertSee('Banana Fibre Coasters')->assertDontSee('Sold Out Sandals');

        $this->get(route('finished.show', $cheap))->assertOk()->assertSee('Banana Fibre Coasters')->assertSee('In stock')->assertSee('Add to cart');
        $this->get(route('home'))->assertOk()->assertSee('Ready-made by artists')->assertSee('Banana Fibre Coasters');
    }

    #[Test]
    public function the_product_page_shows_sale_prices_and_low_stock(): void
    {
        $product = $this->listedFinishedProduct([['Natural', 'S', 2, 20000, 15000]], attributes: ['low_stock_threshold' => 3]);

        $this->get(route('finished.show', $product))->assertOk()
            ->assertSee('Only')->assertSee('line-through', false)->assertSee('Natural');
        $this->assertTrue($product->fresh('variants')->isLowStock());
        $this->assertTrue($product->isOnSale());
    }

    #[Test]
    public function the_super_admin_sees_ready_made_items_in_orders_and_artist_totals_include_them(): void
    {
        $artist = ArtistProfile::factory()->create();
        $variant = $this->listedFinishedProduct([['Natural', 'S', 5]], $artist, ['price_amount' => 10000])->variants->first();
        $order = $this->pay($this->placeOrder([[$variant, 3]]));

        $this->actingAs($this->staff())->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee($variant->product->title)->assertSee('shipped by the artist');

        $this->actingAs($artist->user->fresh());
        $this->get(route('studio.sales'))->assertOk()->assertSee($variant->product->title)->assertSee('Ready-made');
        $this->get(route('studio.dashboard'))->assertOk()->assertSee('Orders to ship');
        $metrics = app(ArtistMetrics::class);
        $this->assertSame(3, (int) $metrics->soldItems($artist)->sum('quantity'));
        $this->assertSame(1, $metrics->finishedInventory($artist)['to_ship']);
    }
}
