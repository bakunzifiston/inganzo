<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FulfillmentStatus: string implements HasBadge
{
    use HasOptions;

    case Unfulfilled = 'unfulfilled';
    case InProduction = 'in_production';
    case PartiallyShipped = 'partially_shipped';
    case Shipped = 'shipped';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Unfulfilled => __('Not started'),
            self::InProduction => __('In production'),
            self::PartiallyShipped => __('Partially shipped'),
            self::Shipped => __('Shipped'),
            self::PartiallyDelivered => __('Partially delivered'),
            self::Delivered => __('Delivered'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unfulfilled => 'neutral',
            self::InProduction, self::Shipped => 'info',
            self::PartiallyShipped, self::PartiallyDelivered => 'accent',
            self::Delivered => 'success',
        };
    }
}
