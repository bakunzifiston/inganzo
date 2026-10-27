<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DiscountType: string implements HasBadge
{
    use HasOptions;

    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => __('Percentage'),
            self::Fixed => __('Fixed amount'),
        };
    }

    public function tone(): string
    {
        return 'neutral';
    }
}
