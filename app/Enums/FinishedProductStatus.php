<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FinishedProductStatus: string implements HasBadge
{
    use HasOptions;

    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingReview => __('In review'),
            self::Active => __('On sale'),
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
            self::Active => 'success',
            self::Rejected, self::Suspended => 'danger',
        };
    }

    /**
     * Statuses in which the artist may edit the listing (suspended listings are locked).
     */
    public function isEditable(): bool
    {
        return $this !== self::Suspended;
    }
}
