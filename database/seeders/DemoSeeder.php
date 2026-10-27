<?php

namespace Database\Seeders;

use App\Enums\ArtistStatus;
use App\Enums\FinishedProductStatus;
use App\Enums\ProductionJobStatus;
use App\Enums\QcResult;
use App\Enums\RefundReason;
use App\Enums\RoleName;
use App\Enums\ShipmentStatus;
use App\Models\ArtCollection;
use App\Models\ArtistProduct;
use App\Models\ArtistProfile;
use App\Models\Artwork;
use App\Models\Category;
use App\Models\CopyrightReport;
use App\Models\Country;
use App\Models\DiscountCode;
use App\Models\District;
use App\Models\Order;
use App\Models\PartnerMember;
use App\Models\PayoutAccount;
use App\Models\PrintingPartner;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Artwork\ArtistProductConfigurator;
use App\Services\Artwork\ArtworkModeration;
use App\Services\Artwork\ArtworkUploader;
use App\Services\Cart\CartManager;
use App\Services\Checkout\CheckoutData;
use App\Services\Checkout\CheckoutService;
use App\Services\Finance\EarningsReleaser;
use App\Services\Finance\PayoutService;
use App\Services\Finance\RefundService;
use App\Services\Fulfillment\ProductionWorkflow;
use App\Services\Fulfillment\ShipmentService;
use App\Services\Inventory\FinishedProductService;
use App\Services\Payments\Gateways\SimulatorGateway;
use App\Services\Payments\PaymentService;
use App\Support\Reference;
use Database\Seeders\Support\DemoArtwork;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * DEMONSTRATION DATA for local development only (never runs in production).
 *
 * Every account uses the password "password" and an @inganzo.test address. Artwork is
 * procedurally generated. Orders are created through the real checkout, payment
 * simulator, production and finance services so dashboards show internally consistent
 * numbers rather than invented statistics.
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /**
     * @var array<string, User>
     */
    private array $users = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoSeeder refuses to run in production.');

            return;
        }

        config([
            'queue.default' => 'sync',
            'mail.default' => 'array',
            'payments.providers.simulator.enabled' => true,
        ]);

        $this->users();
        $partners = $this->partners();
        $artistProducts = $this->artists();
        $this->finishedProducts();
        $this->merchandising();
        $this->orders($artistProducts, $partners);
        $this->finance();
        $this->extras();

        Auth::forgetUser();
        Carbon::setTestNow();

        $this->command?->info('Demo data ready. Sign in with any *@inganzo.test account and the password "password".');
    }

    private function user(string $key, string $name, string $email, RoleName ...$roles): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => self::PASSWORD,
            'locale' => 'en',
            'phone' => '+25078'.str_pad((string) crc32($email) % 10000000, 7, '0', STR_PAD_LEFT),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $this->users[$key] = $user;
    }

    private function users(): void
    {
        $this->user('admin', 'Demo Super Admin', 'admin@inganzo.test', RoleName::SuperAdmin);
        $this->user('finance', 'Demo Finance', 'finance@inganzo.test', RoleName::Finance);
        $this->user('moderator', 'Demo Moderator', 'moderator@inganzo.test', RoleName::Moderator);
        $this->user('support', 'Demo Support', 'support@inganzo.test', RoleName::Support);
        $this->user('customer', 'Demo Customer (Grace Mukamana)', 'customer@inganzo.test', RoleName::Customer);
        foreach (range(1, 6) as $i) {
            $this->user("customer{$i}", "Demo Customer {$i}", "customer{$i}@inganzo.test", RoleName::Customer);
        }
    }

    /**
     * @return Collection<int, PrintingPartner>
     */
    private function partners(): Collection
    {
        $rwanda = Country::query()->where('iso2', 'RW')->firstOrFail();
        $definitions = [
            ['Kigali Print Hub (demo)', 'Kigali', 'partner@inganzo.test', 1, 80, null, null],
            ['Musanze Merch Works (demo)', 'Musanze', 'partner2@inganzo.test', 2, 30, 'Provinces', ['classic-tee', 'oversized-tee', 'pullover-hoodie', 'crew-sweatshirt']],
        ];

        return collect($definitions)->map(function ($definition) use ($rwanda) {
            [$name, $city, $email, $priority, $capacity, $zoneName, $products] = $definition;

            $partner = PrintingPartner::query()->firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'email' => 'ops+'.Str::slug($city).'@inganzo.test',
                'phone' => '+250788100'.str_pad((string) $priority, 3, '0', STR_PAD_LEFT),
                'country_id' => $rwanda->id,
                'city' => $city,
                'address' => $city.' industrial zone',
                'daily_capacity' => $capacity,
                'priority' => $priority,
                'is_active' => true,
                'notes' => 'Demonstration partner.',
            ]);

            foreach (Product::query()->get() as $product) {
                if ($products !== null && ! in_array($product->slug, $products, true)) {
                    continue;
                }
                $partner->services()->firstOrCreate(['product_id' => $product->id], [
                    'unit_cost_amount' => (int) round($product->base_cost_amount * 0.88 / 50) * 50,
                    'production_days' => $product->production_days,
                    'is_active' => true,
                ]);
            }

            $zone = $zoneName ? $rwanda->deliveryZones()->where('name', $zoneName)->first() : null;
            $partner->serviceAreas()->firstOrCreate(['country_id' => $rwanda->id, 'delivery_zone_id' => $zone?->id]);

            $staff = $this->user('partner_'.$priority, 'Demo Partner Staff '.$city, $email, RoleName::PartnerStaff);
            PartnerMember::query()->firstOrCreate(['user_id' => $staff->id], ['printing_partner_id' => $partner->id, 'role' => 'manager']);

            return $partner;
        });
    }

    /**
     * @return Collection<int, ArtistProduct>
     */
    private function artists(): Collection
    {
        $uploader = app(ArtworkUploader::class);
        $configurator = app(ArtistProductConfigurator::class);
        $moderation = app(ArtworkModeration::class);
        $moderator = $this->users['moderator'];
        $categories = Category::query()->pluck('id', 'slug');

        $artists = [
            ['Aline Uwase', 'Uwase Studio', 'Contemporary Imigongo patterns and craft-inspired geometry.', 'Kigali', true, [
                ['imigongo', 'Imigongo Spiral', 'culture', 'A modern take on the cow-dung relief art of eastern Rwanda.', ['imigongo', 'pattern', 'heritage', 'geometric']],
                ['agaseke', 'Agaseke Weave', 'culture', 'Inspired by the woven lids of the Agaseke peace basket.', ['agaseke', 'basket', 'weave', 'heritage']],
            ]],
            ['Eric Niyonzima', 'Hills & Hues', 'Landscapes of the land of a thousand hills.', 'Musanze', true, [
                ['hills', 'Thousand Hills at Dusk', 'nature', 'Layered hills glowing at sunset.', ['hills', 'sunset', 'landscape', 'rwanda']],
                ['volcanoes', 'Virunga Range', 'nature', 'The volcano chain above Musanze, wrapped in morning mist.', ['volcano', 'virunga', 'mountains']],
                ['kivu', 'Lake Kivu Waves', 'abstract', 'Rhythmic waves from the shores of Rubavu.', ['lake', 'kivu', 'waves', 'blue']],
            ]],
            ['Diane Nyiramana', 'Nyiramana Prints', 'Botanical and coffee-culture prints.', 'Huye', false, [
                ['coffee', 'Coffee Bloom', 'nature', 'Rings of roast colours with coffee leaves.', ['coffee', 'botanical', 'circle']],
                ['canopy', 'Nyungwe Canopy', 'nature', 'Leaves from the rainforest canopy.', ['forest', 'leaves', 'nyungwe', 'green']],
                ['sunburst', 'Intore Sunrise', 'music', 'Energy of the intore dance in a burst of gold.', ['dance', 'intore', 'sun', 'gold']],
            ]],
            ['Patrick Habimana', 'Kigali Type Co.', 'Pixel lettering and city nights.', 'Kigali', false, [
                ['murakoze', 'Murakoze Pixels', 'typography', '“Thank you” in Kinyarwanda, set in chunky pixels.', ['murakoze', 'kinyarwanda', 'typography', 'pixel']],
                ['skyline', 'Kigali Nights', 'city-life', 'The city skyline under a full moon.', ['kigali', 'city', 'night', 'skyline']],
            ]],
        ];

        $tmp = storage_path('app/demo-art');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $all = collect();

        foreach ($artists as $index => [$name, $shop, $bio, $location, $featured, $designs]) {
            $user = $this->user('artist'.$index, $name.' (demo)', 'artist'.($index + 1).'@inganzo.test', RoleName::Customer, RoleName::Artist);

            $profile = ArtistProfile::query()->firstOrNew(['user_id' => $user->id]);
            $profile->fill([
                'display_name' => $shop,
                'slug' => Str::slug($shop),
                'tagline' => $bio,
                'bio' => "{$bio}\n\nDemo artist profile generated for development.",
                'location' => $location,
                'instagram_handle' => Str::slug($shop, ''),
                'agreement_accepted_at' => now(),
                'agreement_version' => config('marketplace.agreement_version'),
            ]);
            $profile->status = ArtistStatus::Approved;
            $profile->approved_at = now()->subMonths(3);
            $profile->approved_by = $this->users['admin']->id;
            $profile->is_featured = $featured;
            $profile->save();

            foreach ($designs as [$key, $title, $category, $description, $tags]) {
                if (Artwork::query()->where('artist_profile_id', $profile->id)->where('title', $title)->exists()) {
                    continue;
                }

                $path = "{$tmp}/{$key}.png";
                DemoArtwork::render($key, $path);
                $artwork = $uploader->create($profile, [
                    'title' => $title,
                    'description' => $description,
                    'category_id' => $categories[$category] ?? null,
                    'tags' => $tags,
                ], new UploadedFile($path, "{$key}.png", 'image/png', null, true));

                $artwork->refresh();
                $configurator->enableDefaults($artwork);
                $moderation->submit($artwork->fresh(), $user);
                $moderation->approve($artwork->fresh(), $moderator, 'Demo approval');
                $artwork->forceFill(['published_at' => now()->subDays(random_int(5, 70))])->save();
            }

            $all = $all->merge(ArtistProduct::query()->whereHas('artwork', fn ($q) => $q->where('artist_profile_id', $profile->id))->where('is_enabled', true)->get());
        }

        $this->pendingArtist($uploader, $configurator, $moderation, $tmp);

        return $all;
    }

    /**
     * Ready-made products sold from the artists' own stock: some in stock, one low, one sold out and
     * one waiting in the moderation queue. Photos are generated, like the demo artwork.
     */
    private function finishedProducts(): void
    {
        $service = app(FinishedProductService::class);
        $products = [
            ['artist1@inganzo.test', 'Hand-woven Agaseke Basket', 'Sisal and sweetgrass, woven by hand in Gicumbi. A traditional peace basket with a lid, perfect for gifts or storage.', 25000, 21000, true, 'active', [[200, 150, 90], [150, 90, 50]], [['Natural', 'Small', 8, null, null], ['Natural', 'Large', 3, 32000, 28000], ['Terracotta', 'Large', 0, 32000, null]]],
            ['artist2@inganzo.test', 'Banana Fibre Coasters (set of 4)', 'Four coasters made from sun-dried banana fibre and finished with natural beeswax.', 6500, null, true, 'active', [[120, 160, 110], [90, 120, 80]], [[null, null, 25, null, null]]],
            ['artist2@inganzo.test', 'Kitenge Tote Bag', 'Lined cotton tote in vibrant kitenge fabric with an inner pocket. Sewn in Kigali.', 18000, 15000, true, 'active', [[210, 90, 60], [240, 190, 70]], [['Sunset', null, 2, null, null], ['Ocean', null, 6, null, null]]],
            ['artist3@inganzo.test', 'Cedar Serving Spoon', 'Hand-carved cedar spoon, smooth finish, food safe.', 4500, null, true, 'active', [[170, 110, 70]], [[null, 'Standard', 14, null, null]]],
            ['artist3@inganzo.test', 'Printed Linen Cushion Cover', 'Linen cushion cover with a screen-printed imigongo pattern. Insert not included.', 22000, null, false, 'active', [[60, 60, 70], [200, 70, 50]], [['Charcoal', '45 × 45 cm', 5, null, null], ['Charcoal', '60 × 60 cm', 4, 26000, null]]],
            ['artist4@inganzo.test', 'Leather Notebook Cover', 'Full-grain leather cover for A5 notebooks. Waiting for review (demo).', 28000, null, true, 'pending_review', [[110, 70, 50]], [['Brown', 'A5', 6, null, null]]],
        ];

        foreach ($products as [$email, $title, $description, $price, $sale, $handmade, $state, $tones, $variants]) {
            $artist = ArtistProfile::query()->whereHas('user', fn ($q) => $q->where('email', $email))->first();
            if ($artist === null || $artist->finishedProducts()->where('title', $title)->exists()) {
                continue;
            }

            $product = $service->create($artist, [
                'title' => $title, 'description' => $description, 'price_amount' => $price, 'sale_price_amount' => $sale,
                'is_handmade' => $handmade, 'processing_days' => 2, 'ships_from' => 'Kigali', 'low_stock_threshold' => 3,
                'variants' => array_map(fn ($v) => ['color_name' => $v[0], 'color_hex' => $v[0] ? sprintf('#%02x%02x%02x', ...$tones[0]) : null, 'size_label' => $v[1], 'stock_quantity' => $v[2], 'price_amount' => $v[3], 'sale_price_amount' => $v[4], 'is_active' => true], $variants),
            ]);

            $files = [];
            foreach ($tones as $index => $rgb) {
                $path = sys_get_temp_dir().'/demo-product-'.Str::random(8).'.jpg';
                $image = imagecreatetruecolor(1000, 1000);
                imagefill($image, 0, 0, imagecolorallocate($image, ...array_map(fn ($c) => min(255, $c + 40), $rgb)));
                imagefilledellipse($image, 500, 540, 640 - $index * 120, 640 - $index * 120, imagecolorallocate($image, ...$rgb));
                imagefilledrectangle($image, 330, 700, 670, 760, imagecolorallocate($image, ...array_map(fn ($c) => max(0, $c - 50), $rgb)));
                imagejpeg($image, $path, 88);
                imagedestroy($image);
                $files[] = new UploadedFile($path, 'photo-'.$index.'.jpg', 'image/jpeg', null, true);
            }
            $service->addImages($product, $files);

            $published = $service->publish($product->refresh());
            if ($state === 'active') {
                $published->forceFill(['status' => FinishedProductStatus::Active, 'published_at' => now()->subDays(mt_rand(3, 40))])->save();
            }
        }
    }

    /**
     * A new artist awaiting approval, with a design in the moderation queue.
     */
    private function pendingArtist(ArtworkUploader $uploader, ArtistProductConfigurator $configurator, ArtworkModeration $moderation, string $tmp): void
    {
        $user = $this->user('pending_artist', 'Jean Mugisha (demo)', 'newartist@inganzo.test', RoleName::Customer, RoleName::Artist);
        $profile = ArtistProfile::query()->firstOrNew(['user_id' => $user->id]);
        $profile->fill(['display_name' => 'Mugisha Graphics', 'slug' => 'mugisha-graphics', 'tagline' => 'New artist awaiting review (demo).', 'agreement_accepted_at' => now(), 'agreement_version' => config('marketplace.agreement_version')]);
        $profile->status = ArtistStatus::Pending;
        $profile->save();

        if ($profile->artworks()->exists()) {
            return;
        }

        DemoArtwork::render('kivu', "{$tmp}/pending.png");
        $artwork = $uploader->create($profile, ['title' => 'Blue Hour', 'description' => 'Waiting for moderation (demo).', 'tags' => ['blue', 'abstract']], new UploadedFile("{$tmp}/pending.png", 'pending.png', 'image/png', null, true));
        $configurator->enableDefaults($artwork->fresh());
        $moderation->submit($artwork->fresh(), $user);
    }

    private function merchandising(): void
    {
        $collections = [
            ['imigongo-heritage', 'Imigongo & heritage', 'Imigongo n\'umurage', 'Patterns rooted in Rwandan craft traditions.', ['Imigongo Spiral', 'Agaseke Weave', 'Intore Sunrise']],
            ['thousand-hills', 'Land of a thousand hills', 'Igihugu cy\'imisozi igihumbi', 'Volcanoes, lakes and rolling hills.', ['Thousand Hills at Dusk', 'Virunga Range', 'Lake Kivu Waves', 'Nyungwe Canopy']],
            ['kigali-streets', 'Kigali streets', 'Imihanda ya Kigali', 'City life, coffee and Kinyarwanda type.', ['Kigali Nights', 'Murakoze Pixels', 'Coffee Bloom']],
        ];

        foreach ($collections as $position => [$slug, $en, $rw, $description, $titles]) {
            $collection = ArtCollection::query()->updateOrCreate(['slug' => $slug], [
                'title' => ['en' => $en, 'rw' => $rw],
                'description' => ['en' => $description],
                'is_featured' => true,
                'is_active' => true,
                'position' => $position,
            ]);
            $ids = Artwork::query()->whereIn('title', $titles)->pluck('id');
            $collection->artworks()->sync($ids->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i]])->all());
        }

        Promotion::query()->firstOrCreate(['placement' => 'home_banner', 'position' => 0], [
            'title' => ['en' => 'Free delivery in Kigali on orders over RWF 60,000', 'rw' => 'Kugeza ku buntu muri Kigali ku byaguzwe birenze RWF 60,000'],
            'subtitle' => ['en' => 'Use code MURAKOZE10 for 10% off your first order. (Demo promotion)'],
            'cta_label' => ['en' => 'Shop the collections'],
            'cta_url' => '/shop',
            'theme' => 'ink',
            'is_active' => true,
        ]);

        DiscountCode::query()->firstOrCreate(['code' => 'MURAKOZE10'], [
            'description' => 'Demo: 10% off first order',
            'type' => 'percent',
            'percent_bps' => 1000,
            'currency' => 'RWF',
            'min_subtotal_amount' => 5000,
            'max_per_customer' => 1,
            'is_active' => true,
            'created_by' => $this->users['admin']->id,
        ]);
    }

    /**
     * @param  Collection<int, ArtistProduct>  $artistProducts
     * @param  Collection<int, PrintingPartner>  $partners
     */
    private function orders(Collection $artistProducts, Collection $partners): void
    {
        if (Order::query()->exists()) {
            return;
        }

        mt_srand(2026);
        $rwanda = Country::query()->where('iso2', 'RW')->firstOrFail();
        $districts = District::query()->get();
        $kigaliDistricts = $districts->whereIn('name', ['Gasabo', 'Kicukiro', 'Nyarugenge'])->values();
        $customers = collect($this->users)->filter(fn ($u, $key) => str_starts_with($key, 'customer'))->values();
        $partnerStaff = [$partners[0]->id => $this->users['partner_1'], $partners[1]->id => $this->users['partner_2']];
        $artistProducts = ArtistProduct::query()->whereIn('id', $artistProducts->pluck('id'))->listed()->with(['product.variants', 'colors'])->get()->values();
        $start = now()->subDays(60)->startOfDay();

        for ($n = 0; $n < 42; $n++) {
            Carbon::setTestNow($start->copy()->addDays((int) floor($n * 60 / 42))->addHours(mt_rand(8, 20))->addMinutes(mt_rand(0, 59)));
            $customer = mt_rand(0, 3) === 0 ? null : $customers[mt_rand(0, $customers->count() - 1)];
            Auth::forgetUser();
            session()->forget('cart_token');
            if ($customer) {
                Auth::setUser($customer);
            }

            $carts = app(CartManager::class);
            foreach (range(1, mt_rand(1, 3)) as $line) {
                $ap = $artistProducts[mt_rand(0, $artistProducts->count() - 1)];
                $variants = $ap->product->variants->whereIn('product_color_id', $ap->colors->pluck('id'))->where('is_active', true)->values();
                $carts->add($ap, $variants[mt_rand(0, $variants->count() - 1)], mt_rand(1, 10) > 8 ? 2 : 1);
            }

            $district = mt_rand(0, 3) === 0 ? $districts[mt_rand(0, $districts->count() - 1)] : $kigaliDistricts[mt_rand(0, 2)];
            $order = app(CheckoutService::class)->placeOrder($carts->current(), new CheckoutData(
                user: $customer,
                name: $customer?->name ?? 'Guest Buyer '.($n + 1).' (demo)',
                email: $customer?->email ?? 'guest'.($n + 1).'@inganzo.test',
                phone: '+25078'.str_pad((string) mt_rand(1000000, 9999999), 7, '0'),
                country: $rwanda,
                district: $district,
                sector: 'Demo sector',
                streetAddress: 'KG '.mt_rand(1, 700).' St, house '.mt_rand(1, 90),
                landmark: null,
                note: null,
                paymentMethod: 'simulator',
                payerPhone: '+250788123456',
                locale: 'en',
            ));

            $payment = app(PaymentService::class)->initiate($order, 'simulator', '+250788123456');

            // Most recent orders stay pending; a few fail.
            if ($n >= 40) {
                continue;
            }
            $this->simulateCallback($payment, $n % 13 === 5 ? 'FAILED' : 'SUCCESSFUL');
            $order->refresh();
            if (! $order->isPaid()) {
                continue;
            }

            $this->progressOrder($order, $partnerStaff, 60 - (int) floor($n * 60 / 42));
        }

        Carbon::setTestNow();
        Auth::forgetUser();
    }

    private function simulateCallback($payment, string $status): void
    {
        $body = json_encode(['event_id' => 'demo_'.Str::uuid(), 'reference' => $payment->provider_reference, 'status' => $status, 'amount' => $payment->amount, 'currency' => $payment->currency]);
        $request = Request::create('/api/v1/payments/callback/simulator', 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIMULATOR_SIGNATURE' => SimulatorGateway::sign($body)], content: $body);
        app(PaymentService::class)->handleCallback('simulator', $request);
    }

    /**
     * Move an order through production and delivery according to its age.
     *
     * @param  array<int, User>  $partnerStaff
     */
    private function progressOrder(Order $order, array $partnerStaff, int $ageDays): void
    {
        $workflow = app(ProductionWorkflow::class);
        $shipping = app(ShipmentService::class);
        $order->load('items.activeJob');
        $readyByPartner = [];

        foreach ($order->items as $item) {
            $job = $item->activeJob;
            if ($job === null) {
                continue;
            }
            $staff = $partnerStaff[$job->printing_partner_id];
            $steps = $ageDays > 8 ? 4 : ($ageDays > 3 ? mt_rand(1, 4) : mt_rand(0, 2));

            if ($steps >= 1) {
                $job = $workflow->advance($job, ProductionJobStatus::Accepted, $staff);
            }
            if ($steps >= 2) {
                $job = $workflow->advance($job, ProductionJobStatus::InProduction, $staff);
            }
            if ($steps >= 3) {
                $job = $workflow->advance($job, ProductionJobStatus::QualityCheck, $staff);
            }
            if ($steps >= 4) {
                $workflow->recordQualityCheck($job, QcResult::Passed, $staff, 'Colours and placement match the specification.');
                $readyByPartner[$job->printing_partner_id][] = $item->id;
            }
        }

        foreach ($readyByPartner as $partnerId => $itemIds) {
            if ($ageDays <= 4) {
                continue;
            }
            Carbon::setTestNow(now()->addDays(3));
            $shipment = $shipping->dispatch($order, $itemIds, 'Kigali Express (demo)', 'KX'.mt_rand(100000, 999999), null, $partnerStaff[$partnerId], $partnerId);
            if ($ageDays > 7) {
                Carbon::setTestNow(now()->addDay());
                $shipping->recordEvent($shipment, ShipmentStatus::InTransit, $partnerStaff[$partnerId], 'Kigali sorting centre');
                Carbon::setTestNow(now()->addDay());
                $shipping->recordEvent($shipment, ShipmentStatus::Delivered, $partnerStaff[$partnerId], 'Customer address', 'Signed by recipient');
            }
            Carbon::setTestNow(now()->subDays($ageDays > 7 ? 5 : 3));
        }
    }

    private function finance(): void
    {
        app(EarningsReleaser::class)->releaseEligible();

        // A partial refund for a damaged delivered item.
        $order = Order::query()->countableSales()->whereHas('items', fn ($q) => $q->whereNotNull('delivered_at'))->oldest('placed_at')->first();
        if ($order && $order->refunds()->doesntExist()) {
            $item = $order->items()->whereNotNull('delivered_at')->first();
            app(RefundService::class)->issue($order, $item, $item->unit_price_amount, RefundReason::Damaged, $this->users['finance'], 'DEMO-MOMO-RF-001', 'Demo: print cracked after first wash.');
        }

        // A pending refund request from a signed-in customer.
        $customerOrder = Order::query()->countableSales()->where('user_id', $this->users['customer']->id)->whereDoesntHave('refunds')->latest('placed_at')->first();
        if ($customerOrder) {
            app(RefundService::class)->request($customerOrder, $customerOrder->items->first(), min(5000, $customerOrder->items->first()->line_total_amount), RefundReason::QualityIssue, 'Demo: colour looks faded compared to the website.', $this->users['customer']);
        }

        // Payout accounts and payouts for the best-selling artist.
        $payouts = app(PayoutService::class);
        $rwanda = Country::query()->where('iso2', 'RW')->firstOrFail();

        foreach (ArtistProfile::query()->approved()->get() as $artist) {
            $account = PayoutAccount::query()->firstOrCreate(['artist_profile_id' => $artist->id], [
                'method' => 'mtn_momo',
                'account_name' => $artist->user->name,
                'account_number' => '25078'.str_pad((string) ($artist->id * 1234567 % 10000000), 7, '0', STR_PAD_LEFT),
                'account_last4' => substr(str_pad((string) ($artist->id * 1234567 % 10000000), 7, '0', STR_PAD_LEFT), -4),
                'country_id' => $rwanda->id,
                'is_default' => true,
            ]);
            if ($artist->id % 2 === 1) {
                $account->forceFill(['verified_at' => now(), 'verified_by' => $this->users['finance']->id])->save();
            }
        }

        $paid = false;
        foreach (ArtistProfile::query()->approved()->get() as $artist) {
            $check = $payouts->eligibility($artist->fresh());
            if (! $check['eligible']) {
                continue;
            }
            $payout = $payouts->request($artist->fresh(), $check['available']);
            if (! $paid) {
                $payouts->approve($payout, $this->users['finance'], 'Demo approval');
                $payouts->markPaid($payout, $this->users['finance'], 'mtn_momo', 'DEMO-MOMO-'.Str::upper(Str::random(8)), 'Demo transfer');
                $paid = true;
            }
        }
    }

    private function extras(): void
    {
        foreach (Order::query()->where('user_id', '!=', null)->whereHas('items', fn ($q) => $q->whereNotNull('delivered_at'))->with('items')->limit(8)->get() as $order) {
            $item = $order->items->firstWhere('delivered_at', '!=', null);
            Review::query()->firstOrCreate(['order_item_id' => $item->id], [
                'user_id' => $order->user_id,
                'artist_product_id' => $item->artist_product_id,
                'rating' => [5, 5, 4, 5, 4, 3, 5, 5][$order->id % 8],
                'title' => ['Beautiful print', 'Great quality', 'Love it', 'Fast delivery in Kigali', 'Good', 'Okay', 'Perfect gift', 'Amazing colours'][$order->id % 8],
                'body' => 'Demo review generated for development.',
                'status' => 'published',
            ]);
        }

        SupportTicket::query()->firstOrCreate(['email' => 'guest3@inganzo.test'], [
            'reference' => Reference::generate('SUP', SupportTicket::class),
            'name' => 'Guest Buyer 3 (demo)',
            'category' => 'delivery',
            'subject' => 'When will my order arrive?',
            'message' => 'Demo ticket: I ordered a hoodie last week and want to know the delivery date.',
        ]);

        $artwork = Artwork::query()->where('title', 'Kigali Nights')->first();
        if ($artwork && CopyrightReport::query()->doesntExist()) {
            CopyrightReport::query()->create([
                'artwork_id' => $artwork->id,
                'reporter_name' => 'Demo Rights Holder',
                'reporter_email' => 'rights@inganzo.test',
                'rights_holder' => 'Demo Photography Ltd',
                'original_work_url' => 'https://example.com/demo-photo',
                'claim_details' => 'Demo report: the skyline composition resembles one of our photographs. This report exists to demonstrate the takedown workflow.',
                'good_faith_confirmed' => true,
            ]);
        }
    }
}
