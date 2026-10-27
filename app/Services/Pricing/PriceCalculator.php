<?php

namespace App\Services\Pricing;

use App\Enums\MarkupType;
use App\Models\ArtistProduct;
use App\Models\FinishedProductVariant;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Settings;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Customer price = base cost + artist earning + platform margin, rounded up to the rule's
 * price increment. The rounding difference is added to the platform margin.
 *
 *   base cost       = product.base_cost_amount + variant.extra_cost_amount
 *   artist earning  = base cost × markup_bps / 10 000   (percent markup)
 *                   | markup_amount                      (fixed markup, if the rule allows it)
 *   platform margin = base cost × platform_margin_bps / 10 000 + platform_fixed_amount + rounding
 *
 * All values are integer minor units. See docs/PRICING.md.
 */
class PriceCalculator
{
    /**
     * @var array<int, PricingRule|null>
     */
    private array $ruleCache = [];

    public function ruleFor(Product $product): PricingRule
    {
        $rule = $this->ruleCache[$product->id] ??= PricingRule::query()
            ->current()
            ->where('currency', $product->currency)
            ->where(fn ($q) => $q->where('product_id', $product->id)
                ->orWhere(fn ($q) => $q->whereNull('product_id')->where('product_type_id', $product->product_type_id))
                ->orWhere(fn ($q) => $q->whereNull('product_id')->whereNull('product_type_id')))
            ->orderByRaw('CASE WHEN product_id IS NOT NULL THEN 0 WHEN product_type_id IS NOT NULL THEN 1 ELSE 2 END')
            ->orderByDesc('id')
            ->first();

        if ($rule === null) {
            throw new RuntimeException("No active pricing rule for product [{$product->slug}] in {$product->currency}.");
        }

        return $rule;
    }

    public function quote(ArtistProduct $artistProduct, ProductVariant $variant): PriceBreakdown
    {
        $product = $artistProduct->product;
        $rule = $this->ruleFor($product);
        $baseCost = $product->base_cost_amount + $variant->extra_cost_amount;

        $artistEarning = $this->artistEarning($rule, $artistProduct->markup_type, $artistProduct->markup_bps, $artistProduct->markup_amount, $baseCost);

        return $this->assemble($rule, $baseCost, $artistEarning, $product->currency);
    }

    /**
     * Finished products: the artist sets the shelf price (sale price when one applies); the
     * platform keeps a commission on it and the artist earns the rest. There is no base cost.
     */
    public function quoteFinished(FinishedProductVariant $variant): PriceBreakdown
    {
        $price = $variant->currentPrice();
        $commission = min($price, Money::percentage($price, (int) app(Settings::class)->get('marketplace.finished_commission_bps', 1000)));

        return new PriceBreakdown(0, $price - $commission, $commission, $price, $variant->product->currency, null);
    }

    /**
     * Price preview for the product editor before anything is saved.
     */
    public function preview(Product $product, MarkupType $type, ?int $markupBps, ?int $markupAmount, int $extraCost = 0): PriceBreakdown
    {
        $rule = $this->ruleFor($product);
        $baseCost = $product->base_cost_amount + $extraCost;

        return $this->assemble($rule, $baseCost, $this->artistEarning($rule, $type, $markupBps, $markupAmount, $baseCost), $product->currency);
    }

    /**
     * Lowest price across purchasable variants, cached on artist_products for listings.
     */
    public function lowestPrice(ArtistProduct $artistProduct): ?int
    {
        $colorIds = $artistProduct->colors->pluck('id');
        $variants = $artistProduct->product->variants
            ->where('is_active', true)
            ->when($colorIds->isNotEmpty(), fn ($v) => $v->whereIn('product_color_id', $colorIds));

        $extra = $variants->min('extra_cost_amount');

        if ($extra === null) {
            return null;
        }

        return $this->preview(
            $artistProduct->product,
            $artistProduct->markup_type,
            $artistProduct->markup_bps,
            $artistProduct->markup_amount,
            (int) $extra,
        )->unitPrice;
    }

    public function refreshCachedPrice(ArtistProduct $artistProduct): void
    {
        $artistProduct->loadMissing(['product.variants', 'colors']);
        $artistProduct->forceFill([
            'price_from_amount' => $this->lowestPrice($artistProduct),
            'currency' => $artistProduct->product->currency,
        ])->save();
    }

    /**
     * Validate an artist's markup choice against the applicable rule.
     *
     * @throws ValidationException
     */
    public function assertMarkupAllowed(Product $product, MarkupType $type, ?int $markupBps, ?int $markupAmount, string $field = 'markup'): void
    {
        $rule = $this->ruleFor($product);

        if ($type === MarkupType::Percent) {
            if ($markupBps === null || $markupBps < $rule->artist_markup_min_bps || $markupBps > $rule->artist_markup_max_bps) {
                throw ValidationException::withMessages([$field => __('Your markup must be between :min% and :max%.', [
                    'min' => $rule->artist_markup_min_bps / 100,
                    'max' => $rule->artist_markup_max_bps / 100,
                ])]);
            }

            return;
        }

        if (! $rule->allowsFixedMarkup()) {
            throw ValidationException::withMessages([$field => __('Fixed-amount markups are not available for this product.')]);
        }

        if ($markupAmount === null || $markupAmount < 0 || $markupAmount > $rule->artist_fixed_max_amount) {
            throw ValidationException::withMessages([$field => __('Your markup must be between :min and :max.', [
                'min' => Money::format(0, $product->currency),
                'max' => Money::format($rule->artist_fixed_max_amount, $product->currency),
            ])]);
        }
    }

    public function clearCache(): void
    {
        $this->ruleCache = [];
    }

    private function artistEarning(PricingRule $rule, MarkupType $type, ?int $markupBps, ?int $markupAmount, int $baseCost): int
    {
        if ($type === MarkupType::Fixed && $rule->allowsFixedMarkup()) {
            return max(0, min((int) $markupAmount, $rule->artist_fixed_max_amount));
        }

        $bps = $markupBps ?? $rule->artist_markup_default_bps;
        $bps = max($rule->artist_markup_min_bps, min($bps, $rule->artist_markup_max_bps));

        return Money::percentage($baseCost, $bps);
    }

    private function assemble(PricingRule $rule, int $baseCost, int $artistEarning, string $currency): PriceBreakdown
    {
        $platformMargin = Money::percentage($baseCost, $rule->platform_margin_bps) + $rule->platform_fixed_amount;
        $raw = $baseCost + $artistEarning + $platformMargin;
        $unitPrice = Money::roundUp($raw, $rule->rounding_increment);
        $platformMargin += $unitPrice - $raw;

        return new PriceBreakdown($baseCost, $artistEarning, $platformMargin, $unitPrice, $currency, $rule->id);
    }
}
