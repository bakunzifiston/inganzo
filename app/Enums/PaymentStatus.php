<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentStatus: string implements HasBadge
{
    use HasOptions;

    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Duplicate = 'duplicate';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Succeeded => __('Succeeded'),
            self::Failed => __('Failed'),
            self::Cancelled => __('Cancelled'),
            self::Expired => __('Expired'),
            self::Duplicate => __('Duplicate — refund due'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Succeeded => 'success',
            self::Failed, self::Duplicate => 'danger',
            self::Cancelled, self::Expired => 'neutral',
        };
    }
}
