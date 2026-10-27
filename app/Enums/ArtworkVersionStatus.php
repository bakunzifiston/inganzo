<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ArtworkVersionStatus: string implements HasBadge
{
    use HasOptions;

    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Processing => __('Processing'),
            self::Ready => __('Ready'),
            self::Failed => __('Failed'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Processing => 'info',
            self::Ready => 'success',
            self::Failed => 'danger',
        };
    }
}
