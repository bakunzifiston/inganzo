<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ArtworkStatus: string implements HasBadge
{
    use HasOptions;

    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingReview => __('In review'),
            self::Approved => __('Published'),
            self::Rejected => __('Changes requested'),
            self::Suspended => __('Taken down'),
            self::Archived => __('Archived'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft, self::Archived => 'neutral',
            self::PendingReview => 'warning',
            self::Approved => 'success',
            self::Rejected, self::Suspended => 'danger',
        };
    }
}
