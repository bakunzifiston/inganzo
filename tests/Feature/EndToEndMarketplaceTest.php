<?php

namespace Tests\Feature;

use App\Enums\ArtistStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\LedgerBucket;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProductionJobStatus;
use App\Enums\QcResult;
use App\Enums\RoleName;
use App\Enums\ShipmentStatus;
use App\Models\Artwork;
use App\Models\District;
use App\Models\Order;
use App\Models\PartnerMember;
use App\Models\PayoutAccount;
use App\Models\PrintingPartner;
use App\Models\User;
use App\Services\Artwork\ArtworkModeration;
use App\Services\Finance\EarningsReleaser;
use App\Services\Finance\Ledger;
use App\Services\Finance\PayoutService;
use App\Services\Fulfillment\ProductionWorkflow;
use App\Services\Fulfillment\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The "definition of done" journey from artist sign-up to artist payout.
 */
class EndToEndMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_artwork_goes_from_upload_to_delivered_order_and_artist_payout(): void
    {
        Storage::fake('artwork');
        $partner = PrintingPartner::factory()->servingEverything()->create();
        $partnerUser = User::factory()->withRole(RoleName::PartnerStaff)->create();
        PartnerMember::query()->create(['printing_partner_id' => $partner->id, 'user_id' => $partnerUser->id]);
        $moderator = $this->staff(RoleName::Moderator);
        $finance = $this->staff(RoleName::Finance);

        // 1. Artist registers and opens a shop.
        $this->post('/register', [
            'as_artist' => '1', 'name' => 'Aline Uwase', 'email' => 'aline@example.rw', 'display_name' => 'Aline Studio',
            'password' => 'secret-password-1', 'password_confirmation' => 'secret-password-1', 'accept_terms' => '1', 'accept_agreement' => '1',
        ])->assertRedirect(route('studio.dashboard'));
        $artist = User::query()->where('email', 'aline@example.rw')->sole();

        // 2. Uploads artwork; it is offered on all products with default placements.
        $this->actingAs($artist)->post(route('studio.artworks.store'), [
            'title' => 'Imigongo Dawn', 'tags' => 'imigongo', 'file' => UploadedFile::fake()->image('dawn.png', 3000, 3000), 'rights_confirmed' => '1',
        ])->assertRedirect();
        $artwork = Artwork::query()->sole();

        // 3. Submits for review; the administrator approves the artist and the design.
        $this->actingAs($artist)->post(route('studio.artworks.submit', $artwork))->assertSessionHasNoErrors();
        $artist->artistProfile->forceFill(['status' => ArtistStatus::Approved, 'approved_at' => now()])->save();
        app(ArtworkModeration::class)->approve($artwork->fresh(), $moderator);
        auth()->logout();

        // 4. A guest finds the design, adds a hoodie to the cart and checks out with mobile money.
        $artistProduct = $artwork->artistProducts()->whereHas('product', fn ($q) => $q->where('slug', 'pullover-hoodie'))->sole();
        $this->get(route('products.show', [$artwork, $artistProduct->product]))->assertOk()->assertSee('Imigongo Dawn');
        $variant = $this->variant('pullover-hoodie', 'Black', 'L');
        $this->post(route('cart.items.store'), ['artist_product_id' => $artistProduct->id, 'variant_id' => $variant->id, 'quantity' => 2])->assertRedirect(route('cart.show'));

        $checkout = $this->post(route('checkout.store'), [
            'name' => 'Jean Bosco', 'email' => 'bosco@example.rw', 'phone' => '0788123456',
            'district_id' => District::query()->where('name', 'Kicukiro')->value('id'), 'street_address' => 'KK 15 Rd',
            'payment_method' => 'simulator', 'payer_phone' => '0788123456',
        ]);
        $order = Order::query()->sole();
        $payment = $order->payments()->sole();
        $checkout->assertRedirect(route('payments.simulator.show', $payment));

        // 5. Totals are server-calculated: hoodie base 15 000 + artist 20 % (3 000) + platform 25 % (3 750) = 21 750 → 21 800.
        $this->assertSame(43600, $order->subtotal_amount);
        $this->assertSame(2000, $order->delivery_amount);
        $this->assertSame(45600, $order->total_amount);
        $this->assertSame(3000, $order->items->sole()->unit_artist_earning_amount);

        // 6. Returning from the provider does not pay the order; the signed callback does.
        $this->get(route('orders.track', $order->tracking_token))->assertOk()->assertSee(__('Approve the payment on your phone'));
        $this->assertFalse($order->fresh()->isPaid());
        $this->post(route('payments.simulator.complete', $payment), ['outcome' => 'approve'])->assertRedirect(route('orders.track', $order->tracking_token));
        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame(OrderStatus::Processing, $order->status);

        // 7. The order item was routed to the printing partner, produced, quality checked and shipped.
        $job = $order->items->sole()->activeJob;
        $this->assertSame($partner->id, $job->printing_partner_id);
        $workflow = app(ProductionWorkflow::class);
        foreach ([ProductionJobStatus::Accepted, ProductionJobStatus::InProduction, ProductionJobStatus::QualityCheck] as $status) {
            $job = $workflow->advance($job, $status, $partnerUser);
        }
        $job = $workflow->recordQualityCheck($job, QcResult::Passed, $partnerUser);
        $shipment = app(ShipmentService::class)->dispatch($order, [$job->order_item_id], 'Kigali Express', 'KX-1', null, $partnerUser, $partner->id);
        app(ShipmentService::class)->recordEvent($shipment, ShipmentStatus::Delivered, $partnerUser, 'Kicukiro');

        $order->refresh();
        $this->assertSame(FulfillmentStatus::Delivered, $order->fulfillment_status);
        $this->assertSame(OrderStatus::Completed, $order->status);

        // 8. Earnings are held, then released after the settlement period, then paid out.
        $profile = $artist->artistProfile->fresh();
        $ledger = app(Ledger::class);
        $this->assertSame(6000, $ledger->artistBalance($profile->id, LedgerBucket::Held));

        $this->travel(8)->days();
        app(EarningsReleaser::class)->releaseEligible();
        $this->assertSame(6000, $ledger->artistBalance($profile->id, LedgerBucket::Available));

        $this->actingAs($artist)->put(route('studio.earnings.account'), [
            'method' => 'mtn_momo', 'account_name' => 'Aline Uwase', 'account_number' => '0788 555 111',
        ])->assertSessionHasNoErrors();
        PayoutAccount::query()->sole()->forceFill(['verified_at' => now()])->save();

        $this->actingAs($artist)->post(route('studio.earnings.payouts.store'), ['amount' => '6000'])->assertSessionHasNoErrors();
        $payout = $profile->payoutRequests()->sole();
        $payouts = app(PayoutService::class);
        $payouts->approve($payout, $finance);
        $payouts->markPaid($payout->fresh(), $finance, 'mtn_momo', 'MOMO-TX-1');

        $this->assertSame(PayoutStatus::Paid, $payout->fresh()->status);
        $this->assertSame(0, $ledger->artistBalance($profile->id, LedgerBucket::Available));
        $this->actingAs($artist)->get(route('studio.earnings'))->assertOk()->assertSee($payout->reference);
    }
}
