<?php

namespace Tests\Feature\Studio;

use App\Enums\FinishedProductStatus;
use App\Enums\RoleName;
use App\Models\ArtistProfile;
use App\Models\Artwork;
use App\Models\FinishedProduct;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinishedProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('artwork');
    }

    private function artistUser(): User
    {
        return ArtistProfile::factory()->create()->user->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Hand-woven Agaseke Basket',
            'description' => 'Sisal and sweetgrass, woven in Gicumbi.',
            'price_amount' => 25000,
            'sale_price_amount' => 20000,
            'is_handmade' => '1',
            'processing_days' => 3,
            'low_stock_threshold' => 2,
            'ships_from' => 'Kigali – Gasabo',
            'variants' => [
                ['color_name' => 'Natural', 'color_hex' => '#c9a66b', 'size_label' => 'Small', 'stock_quantity' => 4, 'is_active' => '1'],
                ['color_name' => 'Natural', 'color_hex' => '#c9a66b', 'size_label' => 'Large', 'stock_quantity' => 2, 'is_active' => '1', 'price_amount' => 32000],
            ],
            ...$overrides,
        ];
    }

    private function create(User $user, array $overrides = []): FinishedProduct
    {
        $this->actingAs($user)->post(route('studio.finished.store'), $this->payload($overrides))->assertSessionHasNoErrors();

        return FinishedProduct::query()->latest('id')->firstOrFail();
    }

    private function photo(string $name = 'basket.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 900, 700);
    }

    #[Test]
    public function an_artist_chooses_between_selling_a_design_and_a_finished_product(): void
    {
        $this->actingAs($this->artistUser())->get(route('studio.sell'))
            ->assertOk()
            ->assertSee('Sell a design')
            ->assertSee('Sell a finished product')
            ->assertSee(route('studio.finished.create'), false)
            ->assertSee(route('studio.artworks.create'), false);
    }

    #[Test]
    public function a_finished_product_is_created_without_any_artwork(): void
    {
        $user = $this->artistUser();
        $product = $this->create($user);

        $this->assertSame(FinishedProductStatus::Draft, $product->status);
        $this->assertSame($user->artistProfile->id, $product->artist_profile_id);
        $this->assertSame('RWF', $product->currency);
        $this->assertTrue($product->is_handmade);
        $this->assertSame(0, Artwork::query()->count());

        $variants = $product->variants;
        $this->assertCount(2, $variants);
        $this->assertSame(['Small', 'Large'], $variants->pluck('size_label')->all());
        $this->assertSame([4, 2], $variants->pluck('stock_quantity')->all());
        // SKUs are generated per option, unique and upper-case.
        $this->assertCount(2, $variants->pluck('sku')->unique());
        $this->assertSame(strtoupper($variants[0]->sku), $variants[0]->sku);
        // Listing price = the lowest current price: the product sale price for "Small".
        $this->assertSame(20000, $product->price_from_amount);
        $this->assertSame(32000, $variants[1]->currentPrice($product));
        $this->assertTrue($variants[0]->isOnSale());
    }

    #[Test]
    public function the_form_rejects_invalid_prices_stock_and_options(): void
    {
        $user = $this->actingAs($this->artistUser());
        $variant = ['color_name' => 'Red', 'color_hex' => '#ff0000', 'size_label' => 'M', 'stock_quantity' => 1];

        $cases = [
            'zero price' => [['price_amount' => 0, 'sale_price_amount' => null], 'price_amount'],
            'negative price' => [['price_amount' => -5, 'sale_price_amount' => null], 'price_amount'],
            'absurd price' => [['price_amount' => 999_999_999_999], 'price_amount'],
            'sale not below price' => [['price_amount' => 5000, 'sale_price_amount' => 5000], 'sale_price_amount'],
            'sale above price' => [['price_amount' => 5000, 'sale_price_amount' => 9000], 'sale_price_amount'],
            'text price' => [['price_amount' => '12abc'], 'price_amount'],
            'negative stock' => [['variants' => [[...$variant, 'stock_quantity' => -1]]], 'variants.0.stock_quantity'],
            'fractional stock' => [['variants' => [[...$variant, 'stock_quantity' => 1.5]]], 'variants.0.stock_quantity'],
            'bad colour' => [['variants' => [[...$variant, 'color_hex' => 'red']]], 'variants.0.color_hex'],
            'bad sku' => [['variants' => [[...$variant, 'sku' => 'has space!']]], 'variants.0.sku'],
            'variant sale above its price' => [['variants' => [[...$variant, 'price_amount' => 4000, 'sale_price_amount' => 4500]]], 'variants.0.sale_price_amount'],
            'duplicate option' => [['variants' => [$variant, $variant]], 'variants.1.size_label'],
            'duplicate sku in form' => [['variants' => [[...$variant, 'sku' => 'abc-1'], [...$variant, 'size_label' => 'L', 'sku' => 'ABC-1']]], 'variants.1.sku'],
            'no options' => [['variants' => []], 'variants'],
            'all options unavailable' => [['variants' => [[...$variant, 'is_active' => '0']]], 'variants'],
            'missing title' => [['title' => ''], 'title'],
        ];

        foreach ($cases as $name => [$overrides, $errorKey]) {
            $this->post(route('studio.finished.store'), $this->payload($overrides))->assertSessionHasErrors($errorKey);
            $this->assertSame(0, FinishedProduct::query()->count(), $name);
        }

        // A SKU already used by another artist's product is refused too.
        $other = $this->listedFinishedProduct([[null, null, 1]]);
        $taken = $other->variants->first()->sku;
        $this->post(route('studio.finished.store'), $this->payload(['variants' => [[...$variant, 'sku' => strtolower($taken)]]]))->assertSessionHasErrors('variants.0.sku');
    }

    #[Test]
    public function photos_are_validated_by_content_and_processed(): void
    {
        $user = $this->artistUser();
        $product = $this->create($user);

        $this->put(route('studio.finished.update', $product), $this->payload(['images' => [$this->photo(), $this->photo('side.jpg')]]))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertCount(2, $product->images);
        $image = $product->images->first();
        Storage::disk('artwork')->assertExists([$image->path, $image->preview_path, $image->thumbnail_path]);

        // A script renamed to .jpg is refused, and so are images that are too small.
        $this->put(route('studio.finished.update', $product), $this->payload(['images' => [UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;')]]))->assertSessionHasErrors();
        $this->put(route('studio.finished.update', $product), $this->payload(['images' => [UploadedFile::fake()->image('tiny.png', 100, 100)]]))->assertSessionHasErrors('images');
        $this->assertCount(2, $product->fresh()->images);

        // Remove a photo and choose a new cover.
        $second = $product->images->last();
        $this->put(route('studio.finished.update', $product), $this->payload(['remove_images' => [$image->id], 'cover_image_id' => $second->id]))->assertSessionHasNoErrors();
        $this->assertSame([$second->id], $product->fresh()->images->pluck('id')->all());
        Storage::disk('artwork')->assertMissing($image->path);
    }

    #[Test]
    public function publishing_needs_a_photo_and_goes_to_review_when_approval_is_required(): void
    {
        $user = $this->artistUser();
        $product = $this->create($user);

        $this->post(route('studio.finished.publish', $product))->assertSessionHasErrors('publish');
        $this->assertSame(FinishedProductStatus::Draft, $product->fresh()->status);

        $this->put(route('studio.finished.update', $product), $this->payload(['images' => [$this->photo()]]));
        $this->post(route('studio.finished.publish', $product))->assertSessionHasNoErrors();
        $this->assertSame(FinishedProductStatus::PendingReview, $product->fresh()->status);

        // Not visible to customers while in review.
        auth()->logout();
        $this->get(route('finished.show', $product))->assertForbidden();
        $this->get(route('shop.index', ['type' => 'ready-made']))->assertOk()->assertDontSee($product->title);
    }

    #[Test]
    public function publishing_goes_straight_to_sale_when_approval_is_switched_off(): void
    {
        app(Settings::class)->set('moderation.require_approval', false);
        $user = $this->artistUser();
        $product = $this->create($user);
        $this->put(route('studio.finished.update', $product), $this->payload(['images' => [$this->photo()]]));
        $this->post(route('studio.finished.publish', $product));

        $this->assertSame(FinishedProductStatus::Active, $product->fresh()->status);
        auth()->logout();
        $this->get(route('finished.show', $product))->assertOk()->assertSee('Hand-woven Agaseke Basket')->assertSee('Add to cart');
    }

    #[Test]
    public function admins_approve_reject_and_take_down_products(): void
    {
        $user = $this->artistUser();
        $product = $this->create($user);
        $this->put(route('studio.finished.update', $product), $this->payload(['images' => [$this->photo()]]));
        $this->post(route('studio.finished.publish', $product));

        // Artists and customers cannot moderate.
        $this->post(route('admin.finished.decide', [$product, 'approve']))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('admin.finished.decide', [$product, 'approve']))->assertForbidden();

        $admin = $this->staff();
        $this->actingAs($admin)->get(route('admin.finished.index'))->assertOk()->assertSee($product->title);
        $this->post(route('admin.finished.decide', [$product, 'reject']), ['notes' => ''])->assertSessionHasErrors('notes');
        $this->post(route('admin.finished.decide', [$product, 'reject']), ['notes' => 'Please add a clearer photo.']);
        $this->assertSame(FinishedProductStatus::Rejected, $product->fresh()->status);

        $this->post(route('admin.finished.decide', [$product, 'approve']), ['notes' => null]);
        $this->assertSame(FinishedProductStatus::Active, $product->fresh()->status);
        auth()->logout();
        $this->get(route('finished.show', $product))->assertOk();

        $this->actingAs($admin)->post(route('admin.finished.decide', [$product, 'suspend']), ['notes' => 'Counterfeit goods reported.']);
        $this->assertSame(FinishedProductStatus::Suspended, $product->fresh()->status);

        // A taken-down listing disappears from the shop and cannot be edited by the artist.
        auth()->logout();
        $this->get(route('finished.show', $product))->assertForbidden();
        $this->actingAs($user)->put(route('studio.finished.update', $product), $this->payload(['title' => 'New name']))->assertForbidden();
        $this->post(route('studio.finished.publish', $product))->assertForbidden();
    }

    #[Test]
    public function changing_the_wording_of_a_live_product_sends_it_back_for_review_but_price_and_stock_do_not(): void
    {
        $user = $this->artistUser();
        $product = $this->create($user);
        $product->forceFill(['status' => FinishedProductStatus::Active, 'published_at' => now()])->save();
        $variants = $product->variants->map(fn ($v) => ['id' => $v->id, 'color_name' => $v->color_name, 'color_hex' => $v->color_hex, 'size_label' => $v->size_label, 'stock_quantity' => 9, 'is_active' => '1'])->all();

        $this->put(route('studio.finished.update', $product), $this->payload(['price_amount' => 26000, 'sale_price_amount' => null, 'variants' => $variants]))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame(FinishedProductStatus::Active, $product->status);
        $this->assertSame(26000, $product->price_from_amount);
        $this->assertSame([9, 9], $product->variants->pluck('stock_quantity')->all());

        $this->put(route('studio.finished.update', $product), $this->payload(['title' => 'A brand new name', 'sale_price_amount' => null, 'variants' => $variants]));
        $this->assertSame(FinishedProductStatus::PendingReview, $product->fresh()->status);
    }

    #[Test]
    public function artists_can_only_manage_their_own_products(): void
    {
        $product = $this->create($this->artistUser());
        $variant = $product->variants->first();

        $intruder = $this->artistUser();
        $this->actingAs($intruder);
        $this->get(route('studio.finished.edit', $product))->assertForbidden();
        $this->put(route('studio.finished.update', $product), $this->payload(['title' => 'Hijacked']))->assertForbidden();
        $this->post(route('studio.finished.publish', $product))->assertForbidden();
        $this->post(route('studio.finished.archive', $product))->assertForbidden();
        $this->patch(route('studio.finished.stock', $product), ['stock' => [$variant->id => 99]])->assertForbidden();

        $this->assertSame('Hand-woven Agaseke Basket', $product->fresh()->title);
        $this->assertSame(4, $variant->fresh()->stock_quantity);

        // Customers have no seller tools at all.
        $this->actingAs(User::factory()->create())->get(route('studio.finished.create'))->assertForbidden();
        // Another artist's variant ids cannot be smuggled into my own product.
        $mine = $this->create($intruder);
        $this->actingAs($intruder)->patch(route('studio.finished.stock', $mine), ['stock' => [$variant->id => 99]])->assertSessionHasErrors('stock');
        $this->assertSame(4, $variant->fresh()->stock_quantity);
        $this->put(route('studio.finished.update', $mine), $this->payload(['variants' => [['id' => $variant->id, 'stock_quantity' => 50]]]))->assertSessionHasErrors();
    }

    #[Test]
    public function stock_can_be_corrected_and_products_archived_and_restored(): void
    {
        $user = $this->artistUser();
        $product = $this->create($user);
        [$small, $large] = $product->variants->all();

        $this->patch(route('studio.finished.stock', $product), ['stock' => [$small->id => 0, $large->id => 12]])->assertSessionHasNoErrors();
        $this->assertSame([0, 12], [$small->fresh()->stock_quantity, $large->fresh()->stock_quantity]);
        $this->patch(route('studio.finished.stock', $product), ['stock' => [$small->id => -3]])->assertSessionHasErrors('stock.'.$small->id);

        $this->post(route('studio.finished.archive', $product));
        $this->assertSame(FinishedProductStatus::Archived, $product->fresh()->status);
        $this->post(route('studio.finished.restore', $product));
        $this->assertSame(FinishedProductStatus::Draft, $product->fresh()->status);
    }

    #[Test]
    public function one_catalogue_lists_both_product_types_with_labels_and_filters(): void
    {
        $user = $this->artistUser();
        $design = Artwork::factory()->withVersion()->create(['artist_profile_id' => $user->artistProfile->id, 'title' => 'Imigongo Sunrise']);
        $basket = $this->listedFinishedProduct([['Natural', 'S', 5]], $user->artistProfile, ['title' => 'Cedar Spoon']);
        $empty = $this->listedFinishedProduct([[null, null, 0]], $user->artistProfile, ['title' => 'Sold Out Mug']);
        $low = $this->listedFinishedProduct([[null, null, 2]], $user->artistProfile, ['title' => 'Nearly Gone Bag']);
        $this->listedFinishedProduct([[null, null, 5]], null, ['title' => 'Someone Elses Item']);

        $this->actingAs($user)->get(route('studio.products.index'))
            ->assertOk()
            ->assertSee('Imigongo Sunrise')->assertSee('Cedar Spoon')->assertSee('Sold Out Mug')
            ->assertSee('Printed design')->assertSee('Ready-made')
            ->assertDontSee('Someone Elses Item');

        $this->get(route('studio.products.index', ['type' => 'finished']))->assertOk()->assertDontSee('Imigongo Sunrise')->assertSee('Cedar Spoon');
        $this->get(route('studio.products.index', ['type' => 'print_on_demand']))->assertOk()->assertSee('Imigongo Sunrise')->assertDontSee('Cedar Spoon');
        $this->get(route('studio.products.index', ['stock' => 'out']))->assertOk()->assertSee('Sold Out Mug')->assertDontSee('Cedar Spoon')->assertDontSee('Imigongo Sunrise');
        $this->get(route('studio.products.index', ['stock' => 'low']))->assertOk()->assertSee('Nearly Gone Bag')->assertDontSee('Cedar Spoon');
        $this->get(route('studio.products.index', ['status' => 'draft']))->assertOk()->assertSee('Imigongo Sunrise')->assertDontSee('Cedar Spoon');
        $this->get(route('studio.products.index', ['q' => 'spoon']))->assertOk()->assertSee('Cedar Spoon')->assertDontSee('Sold Out Mug');

        // The dashboard shows inventory figures and alerts for the same artist.
        $this->get(route('studio.dashboard'))->assertOk()
            ->assertSee('Units in stock')
            ->assertSee('“Sold Out Mug” is out of stock', false)
            ->assertSee('Nearly Gone Bag');

        $this->assertNotNull($basket);
        $this->assertNotNull($empty);
        $this->assertNotNull($low);
    }

    #[Test]
    public function an_unrelated_role_cannot_use_the_artist_area(): void
    {
        $partner = User::factory()->withRole(RoleName::Customer)->create();

        $this->actingAs($partner)->get(route('studio.products.index'))->assertForbidden();
        $this->get(route('studio.orders.index'))->assertForbidden();
    }
}
