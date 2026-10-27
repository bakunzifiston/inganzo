<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum OrderItemStatus: string implements HasBadge
{
    use HasOptions;

    case Pending = 'pending';
    case AwaitingAssignment = 'awaiting_assignment';
    case AwaitingShipment = 'awaiting_shipment';
    case Assigned = 'assigned';
    case InProduction = 'in_production';
    case Ready = 'ready';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting payment'),
            self::AwaitingAssignment => __('Awaiting printer'),
            self::AwaitingShipment => __('Artist preparing'),
            self::Assigned => __('Sent to printer'),
            self::InProduction => __('In production'),
            self::Ready => __('Ready to ship'),
            self::Shipped => __('Shipped'),
            self::Delivered => __('Delivered'),
            self::Cancelled => __('Cancelled'),
            self::Refunded => __('Refunded'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending, self::Cancelled, self::Refunded => 'neutral',
            self::AwaitingAssignment, self::AwaitingShipment => 'warning',
            self::Assigned, self::InProduction, self::Shipped => 'info',
            self::Ready => 'accent',
            self::Delivered => 'success',
        };
    }
}
