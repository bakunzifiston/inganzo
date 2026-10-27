<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum QcResult: string implements HasBadge
{
    use HasOptions;

    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Passed => __('Passed'),
            self::Failed => __('Failed'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Passed => 'success',
            self::Failed => 'danger',
        };
    }
}
