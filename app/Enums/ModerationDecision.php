<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ModerationDecision: string implements HasBadge
{
    use HasOptions;

    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Reinstated = 'reinstated';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => __('Submitted for review'),
            self::Approved => __('Approved'),
            self::Rejected => __('Changes requested'),
            self::Suspended => __('Taken down'),
            self::Reinstated => __('Reinstated'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::Approved, self::Reinstated => 'success',
            self::Rejected, self::Suspended => 'danger',
        };
    }
}
