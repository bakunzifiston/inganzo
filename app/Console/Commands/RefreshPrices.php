<?php

namespace App\Console\Commands;

use App\Models\ArtistProduct;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Recomputes the cached "price from" used for listing and filtering after pricing rules
 * or base costs change. Checkout never uses the cache; it always recalculates.
 */
#[Signature('prices:refresh')]
#[Description('Recalculate cached listing prices for all artist products')]
class RefreshPrices extends Command
{
    public function handle(PriceCalculator $pricing): int
    {
        $count = 0;
        ArtistProduct::query()->with(['product.variants', 'colors'])->chunkById(200, function ($products) use ($pricing, &$count) {
            foreach ($products as $artistProduct) {
                $pricing->refreshCachedPrice($artistProduct);
                $count++;
            }
        });
        $pricing->clearCache();

        $this->info("Refreshed {$count} prices.");

        return self::SUCCESS;
    }
}
