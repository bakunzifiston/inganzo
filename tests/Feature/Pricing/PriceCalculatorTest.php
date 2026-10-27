<?php

namespace Tests\Feature\Pricing;

use App\Enums\MarkupType;
use App\Models\PricingRule;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PriceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_price_is_base_cost_plus_artist_earning_plus_platform_margin(): void
    {
        $artistProduct = $this->listedArtistProduct('classic-tee', markupBps: 2000);
        $price = app(PriceCalculator::class)->quote($artistProduct, $this->variant('classic-tee', 'Black', 'M'));

        // base 6500, artist 20 % = 1300, platform 25 % = 1625 → 9425 rounded up to 9500.
        $this->assertSame(6500, $price->baseCost);
        $this->assertSame(1300, $price->artistEarning);
        $this->assertSame(1700, $price->platformMargin);
        $this->assertSame(9500, $price->unitPrice);
        $this->assertSame($price->unitPrice, $price->baseCost + $price->artistEarning + $price->platformMargin);
    }

    #[Test]
    public function variant_extra_cost_increases_the_base_and_the_markups(): void
    {
        $artistProduct = $this->listedArtistProduct('classic-tee', markupBps: 2000);
        $price = app(PriceCalculator::class)->quote($artistProduct, $this->variant('classic-tee', 'Black', 'XXL'));

        $this->assertSame(7000, $price->baseCost);
        $this->assertSame(1400, $price->artistEarning);
        $this->assertSame(10200, $price->unitPrice);
    }

    #[Test]
    public function the_most_specific_rule_wins(): void
    {
        $product = $this->product('classic-tee');
        PricingRule::query()->create([
            'name' => 'Tee promo margin',
            'product_id' => $product->id,
            'currency' => 'RWF',
            'platform_margin_bps' => 1000,
            'artist_markup_min_bps' => 0,
            'artist_markup_max_bps' => 5000,
            'artist_markup_default_bps' => 1000,
            'rounding_increment' => 50,
        ]);

        $preview = app(PriceCalculator::class)->preview($product, MarkupType::Percent, 1000, null);

        $this->assertSame(650, $preview->artistEarning);
        $this->assertSame(7800, $preview->unitPrice);
    }

    #[Test]
    public function fixed_markups_are_capped_by_the_rule(): void
    {
        $product = $this->product('classic-tee');
        $calculator = app(PriceCalculator::class);

        $preview = $calculator->preview($product, MarkupType::Fixed, null, 3000);
        $this->assertSame(3000, $preview->artistEarning);

        $this->expectException(ValidationException::class);
        $calculator->assertMarkupAllowed($product, MarkupType::Fixed, null, 50000);
    }

    #[Test]
    public function percent_markups_outside_limits_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(PriceCalculator::class)->assertMarkupAllowed($this->product('classic-tee'), MarkupType::Percent, 15000, null);
    }
}
