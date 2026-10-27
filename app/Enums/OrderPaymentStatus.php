<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum OrderPaymentStatus: string implements HasBadge
{
    use HasOptions;

    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::Pending => __('Payment pending'),
            self::Paid => __('Paid'),
            self::PartiallyRefunded => __('Partially refunded'),
            self::Refunded => __('Refunded'),
            self::Failed => __('Payment failed'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unpaid, self::Refunded => 'neutral',
            self::Pending => 'warning',
            self::Paid => 'success',
            self::PartiallyRefunded => 'accent',
            self::Failed => 'danger',
        };
    }
}
