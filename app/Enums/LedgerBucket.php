<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LedgerBucket: string implements HasBadge
{
    use HasOptions;

    case Held = 'held';
    case Available = 'available';
    case Revenue = 'revenue';
    case Payable = 'payable';
    case Fees = 'fees';

    public function label(): string
    {
        return match ($this) {
            self::Held => __('Held'),
            self::Available => __('Available'),
            self::Revenue => __('Revenue'),
            self::Payable => __('Payable'),
            self::Fees => __('Fees'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Held => 'warning',
            self::Available => 'success',
            self::Revenue, self::Payable, self::Fees => 'neutral',
        };
    }
}
