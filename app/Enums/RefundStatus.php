<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RefundStatus: string implements HasBadge
{
    use HasOptions;

    case Requested = 'requested';
    case Approved = 'approved';
    case Processed = 'processed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Requested'),
            self::Approved => __('Approved'),
            self::Processed => __('Refunded'),
            self::Rejected => __('Declined'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Approved => 'info',
            self::Processed => 'success',
            self::Rejected => 'neutral',
        };
    }
}
