<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LedgerAccount: string implements HasBadge
{
    use HasOptions;

    case Artist = 'artist';
    case Platform = 'platform';
    case Partner = 'partner';
    case Processor = 'processor';

    public function label(): string
    {
        return match ($this) {
            self::Artist => __('Artist'),
            self::Platform => __('Platform'),
            self::Partner => __('Printing partner'),
            self::Processor => __('Payment processor'),
        };
    }

    public function tone(): string
    {
        return 'neutral';
    }
}
