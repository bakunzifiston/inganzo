<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ArtistStatus: string implements HasBadge
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending approval'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Suspended => __('Suspended'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected, self::Suspended => 'danger',
        };
    }
}
