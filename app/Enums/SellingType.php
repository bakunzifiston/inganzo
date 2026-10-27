<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The two ways an artist sells: a design printed on demand, or a finished product from stock.
 */
enum SellingType: string implements HasBadge
{
    use HasOptions;

    case PrintOnDemand = 'print_on_demand';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::PrintOnDemand => __('Printed design'),
            self::Finished => __('Ready-made'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PrintOnDemand => 'info',
            self::Finished => 'accent',
        };
    }
}
