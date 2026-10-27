<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SupportTicketStatus: string implements HasBadge
{
    use HasOptions;

    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Pending => __('Waiting on customer'),
            self::Resolved => __('Resolved'),
            self::Closed => __('Closed'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Pending => 'info',
            self::Resolved => 'success',
            self::Closed => 'neutral',
        };
    }
}
