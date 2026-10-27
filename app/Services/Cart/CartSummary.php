<?php

namespace App\Services\Cart;

use App\Models\DeliveryZone;
use App\Models\DiscountCode;

final readonly class CartSummary
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public array $lines,
        public string $currency,
        public int $subtotal,
        public int $discount,
        public ?DiscountCode $discountCode,
        public ?string $discountError,
        public ?DeliveryZone $zone,
        public int $delivery,
        public int $taxRateBps,
    ) {}

    public function total(): int
    {
        return $this->subtotal - $this->discount + $this->delivery;
    }

    /**
     * VAT contained in the (tax-inclusive) total.
     */
    public function tax(): int
    {
        if ($this->taxRateBps <= 0) {
            return 0;
        }

        return intdiv($this->total() * $this->taxRateBps + intdiv(10000 + $this->taxRateBps, 2), 10000 + $this->taxRateBps);
    }

    public function itemCount(): int
    {
        return array_sum(array_map(fn (CartLine $line) => $line->item->quantity, $this->lines));
    }

    public function hasUnavailableItems(): bool
    {
        return array_filter($this->lines, fn (CartLine $line) => ! $line->available) !== [];
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }
}
