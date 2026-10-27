<?php

namespace Tests\Feature\Artwork;

use App\Enums\PrintCheckStatus;
use App\Models\Artwork;
use App\Models\Product;
use App\Services\Artwork\PlacementValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlacementValidatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function placements_are_clamped_inside_the_print_area(): void
    {
        $version = Artwork::factory()->withVersion(3000, 3000)->create()->currentVersion;
        $area = $this->product('classic-tee')->primaryPrintArea();

        $result = app(PlacementValidator::class)->validate($version, $area, 0.9, -0.5, 2.0);

        // Square art in a 300×400 mm area: max width fraction 1.0, height fraction 0.75.
        $this->assertSame(1.0, $result->scale);
        $this->assertSame(0.0, $result->offsetX);
        $this->assertSame(0.0, $result->offsetY);
        $this->assertLessThanOrEqual(1.0, $result->offsetY + $result->scale * (300 / 400));
    }

    #[Test]
    public function low_resolution_artwork_fails_the_print_check(): void
    {
        $version = Artwork::factory()->withVersion(1000, 1000)->create()->currentVersion;
        $area = $this->product('classic-tee')->primaryPrintArea();

        $result = app(PlacementValidator::class)->validate($version, $area, 0, 0, 1.0);

        // 1000 px across 300 mm (11.81 in) ≈ 84 DPI, below the 150 DPI minimum.
        $this->assertSame(PrintCheckStatus::Fail, $result->status);
        $this->assertSame(84, $result->effectiveDpi);
    }

    #[Test]
    public function shrinking_the_design_raises_effective_dpi(): void
    {
        $version = Artwork::factory()->withVersion(1500, 1500)->create()->currentVersion;
        $area = $this->product('classic-tee')->primaryPrintArea();
        $validator = app(PlacementValidator::class);

        $this->assertSame(PrintCheckStatus::Warn, $validator->validate($version, $area, 0, 0, 0.8)->status);
        $this->assertSame(PrintCheckStatus::Pass, $validator->validate($version, $area, 0, 0, 0.5)->status);
    }

    #[Test]
    public function quarter_turns_swap_the_dimensions(): void
    {
        $version = Artwork::factory()->withVersion(3000, 1000)->create()->currentVersion;
        $area = $this->product('classic-tee')->primaryPrintArea();

        $result = app(PlacementValidator::class)->validate($version, $area, 0, 0, 1.0, 90);

        // Rotated, the design is 1000 px wide and 3000 px tall: it must shrink to fit 400 mm of height.
        $this->assertSame(90, $result->rotation);
        $this->assertEqualsWithDelta(0.444444, $result->scale, 0.00001);
    }

    #[Test]
    public function default_placements_shrink_modest_files_to_a_printable_size(): void
    {
        $version = Artwork::factory()->withVersion(1254, 1254)->create()->currentVersion;
        $validator = app(PlacementValidator::class);

        foreach (Product::query()->with('printAreas')->get() as $product) {
            $area = $product->primaryPrintArea();
            $result = $validator->defaultPlacement($version, $area);

            $this->assertNotSame(PrintCheckStatus::Fail, $result->status, "{$product->slug} should get a printable default");
            $this->assertGreaterThanOrEqual($area->min_dpi, $result->effectiveDpi);
        }

        // 1254 px reaches 150 DPI at 212 mm: the maximum acceptable size on a 300 mm area.
        $tee = $this->product('classic-tee')->primaryPrintArea();
        $this->assertEqualsWithDelta(212.3 / 300, $validator->maxScaleForDpi($version, $tee, 150), 0.001);
    }
}
