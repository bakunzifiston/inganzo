<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PrintCheckStatus: string implements HasBadge
{
    use HasOptions;

    case Pending = 'pending';
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Not checked'),
            self::Pass => __('Print ready'),
            self::Warn => __('Low resolution'),
            self::Fail => __('Not printable'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Pass => 'success',
            self::Warn => 'warning',
            self::Fail => 'danger',
        };
    }
}
