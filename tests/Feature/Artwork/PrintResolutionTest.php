<?php

namespace Tests\Feature\Artwork;

use App\Enums\ArtworkStatus;
use App\Enums\ArtworkVersionStatus;
use App\Enums\PrintCheckStatus;
use App\Jobs\ProcessArtworkVersion;
use App\Models\ArtistProduct;
use App\Models\Artwork;
use App\Models\ArtworkVersion;
use App\Models\PrintArea;
use App\Services\Artwork\ArtistProductConfigurator;
use App\Services\Artwork\ArtworkModeration;
use App\Services\Artwork\PlacementValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

class PrintResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function version(int $width, int $height): ArtworkVersion
    {
        return Artwork::factory()->withVersion($width, $height)->create()->currentVersion;
    }

    private function area(string $product = 'classic-tee'): PrintArea
    {
        return $this->product($product)->primaryPrintArea();
    }

    private function validator(): PlacementValidator
    {
        return app(PlacementValidator::class);
    }

    #[Test]
    public function dpi_and_unit_conversions_use_pixels_per_printed_inch(): void
    {
        $this->assertSame(106, PlacementValidator::dpi(1254, 300));   // the reported case
        $this->assertSame(300, PlacementValidator::dpi(3000, 254));
        $this->assertSame(250, PlacementValidator::dpi(2500, 254));
        $this->assertSame(0, PlacementValidator::dpi(1000, 0));
        $this->assertSame(2953, PlacementValidator::pixelsFor(300, 250));
        $this->assertSame(1772, PlacementValidator::pixelsFor(300, 150));
    }

    /**
     * Square files filling the 300 mm Classic T-shirt width print 300 × 300 mm.
     *
     * @return array<string, array{int, int, PrintCheckStatus}>
     */
    public static function boundaries(): array
    {
        return [
            '149 DPI fails' => [1771, 149, PrintCheckStatus::Fail],
            '150 DPI is acceptable' => [1772, 150, PrintCheckStatus::Warn],
            '249 DPI is acceptable' => [2952, 249, PrintCheckStatus::Warn],
            '250 DPI is sharp' => [2953, 250, PrintCheckStatus::Pass],
        ];
    }

    #[Test]
    #[DataProvider('boundaries')]
    public function quality_thresholds_apply_exactly_at_the_boundaries(int $pixels, int $expectedDpi, PrintCheckStatus $expected): void
    {
        $result = $this->validator()->validate($this->version($pixels, $pixels), $this->area(), 0, 0, 1.0);

        $this->assertSame($expectedDpi, $result->effectiveDpi);
        $this->assertSame($expected, $result->status);
        $this->assertSame(300.0, $result->printWidthMm);
    }

    #[Test]
    public function the_reported_1254_px_file_at_full_width_fails_with_actionable_guidance(): void
    {
        $result = $this->validator()->validate($this->version(1254, 1254), $this->area(), 0, 0, 1.0);

        $this->assertSame(106, $result->effectiveDpi);
        $this->assertSame(PrintCheckStatus::Fail, $result->status);
        $this->assertStringContainsString('212 mm', $result->message);       // largest acceptable width
        $this->assertStringContainsString('2953 × 2953 px', $result->message); // needed for 300 mm sharp
    }

    #[Test]
    public function landscape_portrait_and_square_files_keep_their_aspect_ratio_and_both_axes_are_measured(): void
    {
        $area = $this->area();

        $landscape = $this->validator()->validate($this->version(3000, 1500), $area, 0, 0, 1.0);
        $this->assertSame([300.0, 150.0], [$landscape->printWidthMm, $landscape->printHeightMm]);
        $this->assertSame(254, $landscape->effectiveDpi);

        // Portrait art is limited by the 400 mm area height: 200 × 400 mm.
        $portrait = $this->validator()->validate($this->version(1500, 3000), $area, 0, 0, 1.0);
        $this->assertSame([200.0, 400.0], [$portrait->printWidthMm, $portrait->printHeightMm]);
        $this->assertSame(190, $portrait->effectiveDpi);
        $this->assertSame($portrait->dpiX, $portrait->dpiY);

        $square = $this->validator()->validate($this->version(2000, 2000), $area, 0.1, 0.1, 0.5);
        $this->assertEqualsWithDelta(1.0, $square->printHeightMm / $square->printWidthMm, 0.001);

        // A quarter turn swaps which pixels span the printed width.
        $rotated = $this->validator()->validate($this->version(3000, 1500), $area, 0, 0, 1.0, 90);
        $this->assertEqualsWithDelta(2.0, $rotated->printHeightMm / $rotated->printWidthMm, 0.01);
        $this->assertLessThanOrEqual($area->height_mm, $rotated->printHeightMm);
    }

    #[Test]
    public function different_print_areas_give_different_maximum_sizes(): void
    {
        $version = $this->version(1254, 1254);

        // Mug wrap 200 × 85 mm: a square design is limited to 85 mm by the height.
        $mug = $this->validator()->validate($version, $this->area('ceramic-mug'), 0, 0, 1.0);
        $this->assertSame([85.0, 85.0], [$mug->printWidthMm, $mug->printHeightMm]);
        $this->assertSame(374, $mug->effectiveDpi);
        $this->assertSame(PrintCheckStatus::Pass, $mug->status);

        // Sticker 100 × 100 mm.
        $sticker = $this->validator()->validate($version, $this->area('die-cut-sticker'), 0, 0, 1.0);
        $this->assertSame(318, $sticker->effectiveDpi);
    }

    #[Test]
    public function best_print_size_targets_250_dpi_within_the_print_area(): void
    {
        $version = $this->version(1254, 1254);

        $tee = $this->validator()->bestPrintScale($version, $this->area());
        $this->assertSame(250, $tee['target_dpi']);
        $this->assertEqualsWithDelta(127.4, $tee['scale'] * 300, 0.05);
        $this->assertSame(PrintCheckStatus::Pass, $this->validator()->validate($version, $this->area(), 0, 0, $tee['scale'])->status);

        // On a 100 mm sticker the file is sharp across the whole area, so the area is the limit.
        $sticker = $this->validator()->bestPrintScale($version, $this->area('die-cut-sticker'));
        $this->assertSame(1.0, $sticker['scale']);

        // Portrait art: the height of the print area is respected as well.
        $portrait = $this->version(3000, 6000);
        $best = $this->validator()->bestPrintScale($portrait, $this->area());
        $measure = $this->validator()->measure($portrait, $this->area(), $best['scale']);
        $this->assertLessThanOrEqual(400.0, $measure['height_mm']);
        $this->assertGreaterThanOrEqual(250, $measure['dpi']);
    }

    #[Test]
    public function when_no_size_can_print_the_artist_is_told_to_upload_a_higher_resolution_file(): void
    {
        $tiny = $this->version(60, 60);

        $this->assertNull($this->validator()->bestPrintScale($tiny, $this->area()));

        $result = $this->validator()->defaultPlacement($tiny, $this->area());
        $this->assertSame(PrintCheckStatus::Fail, $result->status);
        $this->assertSame(PlacementValidator::MIN_SCALE, $result->scale);
        $this->assertStringContainsString('even at the smallest size', $result->message);
    }

    #[Test]
    public function new_uploads_start_every_product_at_a_sharp_size_when_one_exists(): void
    {
        $artwork = Artwork::factory()->withVersion(1254, 1254)->create();

        app(ArtistProductConfigurator::class)->enableDefaults($artwork->fresh());

        $products = ArtistProduct::query()->where('artwork_id', $artwork->id)->with('placements')->get();
        $this->assertCount(7, $products);
        foreach ($products as $product) {
            $this->assertTrue($product->is_enabled);
            $this->assertSame(PrintCheckStatus::Pass, $product->print_check_status);
            $this->assertGreaterThanOrEqual(250, $product->placements->first()->effective_dpi);
        }
    }

    #[Test]
    public function saved_product_sizes_are_never_changed_without_the_artist(): void
    {
        $artistProduct = $this->listedArtistProduct();
        $artwork = $artistProduct->artwork;
        $area = $artistProduct->product->primaryPrintArea();
        $configurator = app(ArtistProductConfigurator::class);

        $configurator->save($artwork, $artistProduct->product, [
            'is_enabled' => true,
            'colors' => $artistProduct->colors->pluck('id')->all(),
            'markup_type' => 'percent',
            'markup_bps' => 2000,
            'placements' => [$area->id => ['offset_x' => 0.1, 'offset_y' => 0.2, 'scale' => 0.3]],
        ]);

        $configurator->enableDefaults($artwork->fresh());
        $this->assertSame(0.3, $artistProduct->placements()->sole()->scale);

        // A new, larger file re-checks quality but keeps the artist's chosen size and position.
        $sharper = $artwork->versions()->create([
            'version_number' => 2, 'disk' => 'artwork', 'original_path' => 'x.png', 'original_filename' => 'x.png', 'mime_type' => 'image/png',
            'width' => 6000, 'height' => 6000, 'file_size' => 1, 'sha256' => str_repeat('b', 64), 'status' => ArtworkVersionStatus::Ready,
        ]);
        $configurator->rebaseOnVersion($artwork->fresh(), $sharper);

        $placement = $artistProduct->placements()->sole();
        $this->assertSame([0.1, 0.2, 0.3], [$placement->offset_x, $placement->offset_y, $placement->scale]);
        $this->assertSame($sharper->id, $placement->artwork_version_id);
    }

    #[Test]
    public function the_server_stores_failing_placements_as_not_print_ready_and_never_sells_them(): void
    {
        $artwork = Artwork::factory()->withVersion(1254, 1254)->create();
        $product = $this->product('classic-tee');
        $front = $product->printAreas->firstWhere('key', 'front');

        $this->actingAs($artwork->artist->user)->put(route('studio.artworks.products.update', [$artwork, $product]), [
            'is_enabled' => '1',
            'colors' => [$product->colors->first()->id],
            'markup_type' => 'percent',
            'markup_percent' => '20',
            // A client claiming a good result cannot bypass the server calculation.
            'placements' => json_encode([$front->id => ['enabled' => true, 'offset_x' => 0, 'offset_y' => 0, 'scale' => 1, 'effective_dpi' => 300, 'print_check_status' => 'pass']]),
        ])->assertSessionHas('error');

        $saved = ArtistProduct::query()->sole();
        $this->assertSame(PrintCheckStatus::Fail, $saved->print_check_status);
        $this->assertSame(106, $saved->placements->sole()->effective_dpi);

        $artwork->forceFill(['status' => ArtworkStatus::Approved])->save();
        $this->assertFalse($saved->fresh()->isListed());
    }

    #[Test]
    public function submitting_for_review_is_refused_while_an_enabled_product_is_not_print_ready(): void
    {
        $artwork = Artwork::factory()->withVersion(1254, 1254)->create();
        $configurator = app(ArtistProductConfigurator::class);
        $tee = $this->product('classic-tee');
        $mug = $this->product('ceramic-mug');
        $save = fn ($product, float $scale, bool $enabled = true) => $configurator->save($artwork->fresh('currentVersion'), $product, [
            'is_enabled' => $enabled,
            'colors' => [$product->colors->first()->id],
            'markup_type' => 'percent',
            'markup_bps' => 2000,
            'placements' => [$product->primaryPrintArea()->id => ['offset_x' => 0, 'offset_y' => 0, 'scale' => $scale]],
        ]);

        $save($mug, 0.4);
        $save($tee, 1.0);

        try {
            app(ArtworkModeration::class)->submit($artwork->fresh(), $artwork->artist->user);
            $this->fail('Submission with a failing product must be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Classic T-shirt is not print-ready (106 DPI)', implode(' ', $e->errors()['submit']));
        }

        $save($tee, 1.0, enabled: false);
        app(ArtworkModeration::class)->submit($artwork->fresh(), $artwork->artist->user);
        $this->assertSame(ArtworkStatus::PendingReview, $artwork->fresh()->status);
    }

    #[Test]
    public function missing_dimensions_corrupted_files_and_missing_files_are_handled_gracefully(): void
    {
        Storage::fake('artwork');
        $artwork = Artwork::factory()->withVersion()->create();
        $version = $artwork->currentVersion;

        $unknown = $version->replicate()->fill(['width' => null, 'height' => null]);
        $result = $this->validator()->validate($unknown, $this->area(), 0, 0, 0.5);
        $this->assertSame(PrintCheckStatus::Fail, $result->status);
        $this->assertStringContainsString('no readable dimensions', $result->message);

        foreach (['corrupted' => 'not an image at all', 'missing' => null] as $case => $contents) {
            $version->forceFill(['status' => ArtworkVersionStatus::Processing, 'error' => null, 'original_path' => "artworks/{$case}.png"])->save();
            if ($contents !== null) {
                Storage::disk('artwork')->put("artworks/{$case}.png", $contents);
            }

            try {
                ProcessArtworkVersion::dispatchSync($version->id);
            } catch (Throwable) {
                // The sync queue rethrows after marking the job as failed.
            }

            $version->refresh();
            $this->assertSame(ArtworkVersionStatus::Failed, $version->status, "{$case} file should mark the version as failed");
            $this->assertNotEmpty($version->error);
        }
    }
}
