<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ShipmentStatus: string implements HasBadge
{
    use HasOptions;

    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case FailedDelivery = 'failed_delivery';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Dispatched => __('Dispatched'),
            self::InTransit => __('In transit'),
            self::OutForDelivery => __('Out for delivery'),
            self::Delivered => __('Delivered'),
            self::FailedDelivery => __('Delivery failed'),
            self::Returned => __('Returned to sender'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Dispatched, self::InTransit => 'info',
            self::OutForDelivery => 'accent',
            self::Delivered => 'success',
            self::FailedDelivery, self::Returned => 'danger',
        };
    }
}
