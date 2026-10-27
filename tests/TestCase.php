<?php

namespace Tests;

use App\Enums\ArtworkStatus;
use App\Enums\RoleName;
use App\Models\ArtistProduct;
use App\Models\ArtistProfile;
use App\Models\Artwork;
use App\Models\Country;
use App\Models\District;
use App\Models\FinishedProduct;
use App\Models\FinishedProductVariant;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Artwork\ArtistProductConfigurator;
use App\Services\Artwork\PlacementValidator;
use App\Services\Cart\CartManager;
use App\Services\Checkout\CheckoutData;
use App\Services\Checkout\CheckoutService;
use App\Services\Payments\Gateways\SimulatorGateway;
use App\Services\Payments\PaymentService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Feature tests run against PostgreSQL with production reference data seeded.
     */
    protected bool $seed = true;

    protected string $seeder = ReferenceDataSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function rwanda(): Country
    {
        return Country::query()->where('iso2', 'RW')->firstOrFail();
    }

    protected function product(string $slug = 'classic-tee'): Product
    {
        return Product::query()->where('slug', $slug)->with(['printAreas', 'colors', 'variants'])->firstOrFail();
    }

    protected function variant(string $productSlug = 'classic-tee', string $color = 'Black', ?string $size = 'M'): ProductVariant
    {
        $product = $this->product($productSlug);

        return $product->variants()
            ->whereHas('color', fn ($q) => $q->where('name', $color))
            ->when($size, fn ($q) => $q->whereHas('size', fn ($s) => $s->where('label', $size)), fn ($q) => $q->whereNull('product_size_id'))
            ->firstOrFail();
    }

    protected function staff(RoleName $role = RoleName::SuperAdmin): User
    {
        return User::factory()->withRole($role)->create();
    }

    /**
     * An approved artwork offered on a product with the default placement.
     */
    protected function listedArtistProduct(string $productSlug = 'classic-tee', ?ArtistProfile $artist = null, int $markupBps = 2000): ArtistProduct
    {
        $artwork = Artwork::factory()
            ->for($artist ?? ArtistProfile::factory(), 'artist')
            ->withVersion()
            ->create();

        $product = $this->product($productSlug);
        $area = $product->primaryPrintArea();
        $placement = app(PlacementValidator::class)->defaultPlacement($artwork->currentVersion, $area);

        $artistProduct = app(ArtistProductConfigurator::class)->save($artwork->fresh('currentVersion'), $product, [
            'is_enabled' => true,
            'colors' => $product->colors->pluck('id')->all(),
            'markup_type' => 'percent',
            'markup_bps' => $markupBps,
            'placements' => [$area->id => ['offset_x' => $placement->offsetX, 'offset_y' => $placement->offsetY, 'scale' => $placement->scale]],
        ]);

        $artwork->forceFill(['status' => ArtworkStatus::Approved, 'published_at' => now()])->save();

        return $artistProduct->fresh(['artwork.artist', 'product', 'colors', 'placements']);
    }

    /**
     * Place an order through the real cart and checkout services.
     *
     * Lines are [ArtistProduct, ProductVariant, quantity] for printed items or
     * [FinishedProductVariant, quantity] for ready-made items.
     *
     * @param  list<array{0: ArtistProduct|FinishedProductVariant, 1: ProductVariant|int, 2?: int}>  $lines
     */
    protected function placeOrder(array $lines, ?User $customer = null, string $district = 'Gasabo', ?string $discountCode = null, string $paymentMethod = 'simulator'): Order
    {
        if ($customer) {
            $this->actingAs($customer);
        }

        $carts = app(CartManager::class);
        foreach ($lines as $line) {
            $line[0] instanceof FinishedProductVariant
                ? $carts->addFinished($line[0], $line[1])
                : $carts->add($line[0], $line[1], $line[2]);
        }
        if ($discountCode) {
            $carts->applyDiscount($discountCode);
        }

        $rwanda = $this->rwanda();

        return app(CheckoutService::class)->placeOrder($carts->current(), new CheckoutData(
            user: $customer,
            name: 'Aline Uwase',
            email: $customer?->email ?? 'guest@example.rw',
            phone: '+250788123456',
            country: $rwanda,
            district: District::query()->where('name', $district)->firstOrFail(),
            sector: 'Kimironko',
            streetAddress: 'KG 11 Ave',
            landmark: null,
            note: null,
            paymentMethod: $paymentMethod,
            payerPhone: '+250788123456',
            locale: 'en',
        ));
    }

    /**
     * A ready-made product on sale. Each option is [colour, size, stock] with optional price and sale price.
     *
     * @param  list<array{0: string|null, 1: string|null, 2: int, 3?: int|null, 4?: int|null}>  $options
     * @param  array<string, mixed>  $attributes
     */
    protected function listedFinishedProduct(array $options = [[null, null, 5]], ?ArtistProfile $artist = null, array $attributes = []): FinishedProduct
    {
        return FinishedProduct::factory()
            ->for($artist ?? ArtistProfile::factory(), 'artist')
            ->active()
            ->withVariants($options)
            ->create($attributes)
            ->load(['variants', 'artist']);
    }

    protected function initiatePayment(Order $order, string $provider = 'simulator'): Payment
    {
        return app(PaymentService::class)->initiate($order, $provider, '+250788123456');
    }

    /**
     * Send a signed simulator callback through the public webhook endpoint.
     */
    protected function simulatorCallback(Payment $payment, string $status = 'SUCCESSFUL', ?int $amount = null, ?string $eventId = null): TestResponse
    {
        $body = json_encode([
            'event_id' => $eventId ?? 'evt_'.$payment->id.'_'.$status,
            'reference' => $payment->provider_reference,
            'status' => $status,
            'amount' => $amount ?? $payment->amount,
            'currency' => $payment->currency,
        ]);

        return $this->call('POST', route('api.v1.payments.callback', 'simulator'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIMULATOR_SIGNATURE' => SimulatorGateway::sign($body),
        ], $body);
    }

    protected function paidOrder(?ArtistProduct $artistProduct = null, int $quantity = 1, ?User $customer = null): Order
    {
        $artistProduct ??= $this->listedArtistProduct();
        $order = $this->placeOrder([[$artistProduct, $this->variant(), $quantity]], $customer);
        $payment = $this->initiatePayment($order);
        $this->simulatorCallback($payment)->assertOk();

        return $order->fresh(['items', 'payments']);
    }
}
