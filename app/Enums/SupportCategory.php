<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SupportCategory: string implements HasBadge
{
    use HasOptions;

    case Order = 'order';
    case Delivery = 'delivery';
    case Refund = 'refund';
    case Product = 'product';
    case Account = 'account';
    case Artist = 'artist';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Order => __('Order question'),
            self::Delivery => __('Delivery'),
            self::Refund => __('Return or refund'),
            self::Product => __('Product quality'),
            self::Account => __('Account'),
            self::Artist => __('Selling on Inganzo'),
            self::Other => __('Something else'),
        };
    }

    public function tone(): string
    {
        return 'neutral';
    }
}
