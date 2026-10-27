<?php

namespace App\Services\Pricing;

/**
 * Per-unit price split. Invariant: unitPrice = baseCost + artistEarning + platformMargin.
 */
final readonly class PriceBreakdown
{
    public function __construct(
        public int $baseCost,
        public int $artistEarning,
        public int $platformMargin,
        public int $unitPrice,
        public string $currency,
        public ?int $pricingRuleId,
    ) {}

    /**
     * @return array{base_cost: int, artist_earning: int, platform_margin: int, unit_price: int, currency: string}
     */
    public function toArray(): array
    {
        return [
            'base_cost' => $this->baseCost,
            'artist_earning' => $this->artistEarning,
            'platform_margin' => $this->platformMargin,
            'unit_price' => $this->unitPrice,
            'currency' => $this->currency,
        ];
    }
}
