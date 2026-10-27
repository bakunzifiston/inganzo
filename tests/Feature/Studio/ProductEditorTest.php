<?php

namespace Tests\Feature\Studio;

use App\Enums\ArtworkStatus;
use App\Enums\PrintCheckStatus;
use App\Enums\RoleName;
use App\Models\ArtistProduct;
use App\Models\ArtistProfile;
use App\Models\Artwork;
use App\Services\Artwork\ArtworkModeration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductEditorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function saving_a_placement_clamps_it_inside_the_print_area_and_runs_the_print_check(): void
    {
        $artwork = Artwork::factory()->withVersion(2000, 2000)->create();
        $product = $this->product('classic-tee');
        $front = $product->printAreas->firstWhere('key', 'front');

        $this->actingAs($artwork->artist->user)
            ->get(route('studio.artworks.products.edit', [$artwork, $product]))->assertOk()->assertSee(__('Print on this area'), false);

        $this->actingAs($artwork->artist->user)->put(route('studio.artworks.products.update', [$artwork, $product]), [
            'is_enabled' => '1',
            'colors' => $product->colors->take(2)->pluck('id')->all(),
            'markup_type' => 'percent',
            'markup_percent' => '30',
            'placements' => json_encode([$front->id => ['enabled' => true, 'offset_x' => 0.9, 'offset_y' => -1, 'scale' => 1.5, 'rotation' => 0]]),
        ])->assertRedirect(route('studio.artworks.show', $artwork));

        $artistProduct = ArtistProduct::query()->sole();
        $placement = $artistProduct->placements->sole();

        $this->assertSame(1.0, $placement->scale);
        $this->assertSame(0.0, $placement->offset_x);
        $this->assertSame(0.0, $placement->offset_y);
        // 2000 px over 300 mm ≈ 169 DPI: printable but below the 250 DPI recommendation.
        $this->assertSame(PrintCheckStatus::Warn, $placement->print_check_status);
        $this->assertSame(3000, $artistProduct->markup_bps);
        $this->assertSame(2, $artistProduct->colors->count());
        // 6 500 base + 1 950 (30 %) + 1 625 platform = 10 075, rounded up to the 100 RWF price point.
        $this->assertSame(10100, $artistProduct->price_from_amount);
    }

    #[Test]
    public function markups_outside_platform_limits_are_rejected(): void
    {
        $artwork = Artwork::factory()->withVersion()->create();
        $product = $this->product('classic-tee');
        $front = $product->printAreas->firstWhere('key', 'front');

        $this->actingAs($artwork->artist->user)->put(route('studio.artworks.products.update', [$artwork, $product]), [
            'is_enabled' => '1',
            'colors' => [$product->colors->first()->id],
            'markup_type' => 'fixed',
            'markup_amount' => '999999',
            'placements' => json_encode([$front->id => ['enabled' => true, 'offset_x' => 0, 'offset_y' => 0, 'scale' => 0.5]]),
        ])->assertSessionHasErrors('markup');
    }

    #[Test]
    public function only_print_ready_products_count_when_submitting_for_review(): void
    {
        $artwork = Artwork::factory()->withVersion(1000, 1000)->create();
        $product = $this->product('classic-tee');
        $front = $product->printAreas->firstWhere('key', 'front');

        $this->actingAs($artwork->artist->user)->put(route('studio.artworks.products.update', [$artwork, $product]), [
            'is_enabled' => '1',
            'colors' => [$product->colors->first()->id],
            'markup_type' => 'percent',
            'markup_percent' => '20',
            'placements' => json_encode([$front->id => ['enabled' => true, 'offset_x' => 0, 'offset_y' => 0, 'scale' => 1]]),
        ]);

        $this->assertSame(PrintCheckStatus::Fail, ArtistProduct::query()->sole()->print_check_status);

        $this->actingAs($artwork->artist->user)->post(route('studio.artworks.submit', $artwork))->assertSessionHasErrors('submit');
        $this->assertSame(ArtworkStatus::Draft, $artwork->fresh()->status);
    }

    #[Test]
    public function a_submitted_design_is_published_only_after_moderator_approval(): void
    {
        $artistProduct = $this->listedArtistProduct();
        $artwork = $artistProduct->artwork;
        $artwork->forceFill(['status' => ArtworkStatus::Draft, 'published_at' => null])->save();
        $url = route('products.show', [$artwork, $artistProduct->product]);

        $this->get($url)->assertNotFound();

        $this->actingAs($artwork->artist->user)->post(route('studio.artworks.submit', $artwork))->assertSessionHasNoErrors();
        $this->assertSame(ArtworkStatus::PendingReview, $artwork->fresh()->status);
        $this->get($url)->assertNotFound();

        app(ArtworkModeration::class)->approve($artwork->fresh(), $this->staff(RoleName::Moderator));
        auth()->logout();
        $this->get($url)->assertOk()->assertSee($artwork->title);
    }

    #[Test]
    public function archived_designs_can_be_restored(): void
    {
        $artistProduct = $this->listedArtistProduct();
        $published = $artistProduct->artwork;
        $published->forceFill(['status' => ArtworkStatus::Draft])->save();
        app(ArtworkModeration::class)->submit($published->fresh(), $published->artist->user);
        app(ArtworkModeration::class)->approve($published->fresh(), $this->staff(RoleName::Moderator));
        $user = $published->artist->user;
        $productUrl = route('products.show', [$published, $artistProduct->product]);

        $this->actingAs($user)->post(route('studio.artworks.archive', $published))->assertRedirect();
        $this->assertSame(ArtworkStatus::Archived, $published->fresh()->status);
        auth()->logout();
        $this->get($productUrl)->assertNotFound();

        // Previously approved: straight back on sale.
        $this->actingAs($user)->post(route('studio.artworks.restore', $published))->assertSessionHas('success');
        $this->assertSame(ArtworkStatus::Approved, $published->fresh()->status);
        auth()->logout();
        $this->get($productUrl)->assertOk();

        // Never approved: back to draft for review.
        $draft = Artwork::factory()->for($published->artist, 'artist')->withVersion()->create(['status' => ArtworkStatus::Archived]);
        $this->actingAs($user)->post(route('studio.artworks.restore', $draft));
        $this->assertSame(ArtworkStatus::Draft, $draft->fresh()->status);

        // Only the owner can restore, and only archived designs.
        $this->actingAs(ArtistProfile::factory()->create()->user)->post(route('studio.artworks.restore', $draft))->assertForbidden();
        $this->actingAs($user)->post(route('studio.artworks.restore', $draft))->assertSessionHasErrors('status');
    }
}
