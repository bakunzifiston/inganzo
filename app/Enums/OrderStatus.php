<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum OrderStatus: string implements HasBadge
{
    use HasOptions;

    case PendingPayment = 'pending_payment';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => __('Awaiting payment'),
            self::Processing => __('Processing'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Processing => 'info',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
