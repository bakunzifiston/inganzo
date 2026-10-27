<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PayoutStatus: string implements HasBadge
{
    use HasOptions;

    case Requested = 'requested';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Requested'),
            self::Approved => __('Approved'),
            self::Paid => __('Paid'),
            self::Rejected => __('Rejected'),
            self::Failed => __('Failed'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Approved => 'info',
            self::Paid => 'success',
            self::Rejected, self::Failed => 'danger',
        };
    }
}
