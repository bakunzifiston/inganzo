<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UserStatus: string implements HasBadge
{
    use HasOptions;

    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'danger',
        };
    }
}
