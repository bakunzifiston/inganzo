<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CopyrightReportStatus: string implements HasBadge
{
    use HasOptions;

    case Open = 'open';
    case UnderReview = 'under_review';
    case Upheld = 'upheld';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::UnderReview => __('Under review'),
            self::Upheld => __('Upheld — taken down'),
            self::Dismissed => __('Dismissed'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::UnderReview => 'info',
            self::Upheld => 'danger',
            self::Dismissed => 'neutral',
        };
    }
}
