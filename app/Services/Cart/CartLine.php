<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use App\Services\Pricing\PriceBreakdown;

final readonly class CartLine
{
    public function __construct(
        public CartItem $item,
        public ?PriceBreakdown $price,
        public bool $available,
        public ?string $problem = null,
    ) {}

    public function total(): int
    {
        return $this->price ? $this->price->unitPrice * $this->item->quantity : 0;
    }
}
