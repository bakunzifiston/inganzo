<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ProductionJobStatus: string implements HasBadge
{
    use HasOptions;

    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case InProduction = 'in_production';
    case QualityCheck = 'quality_check';
    case ReadyForDispatch = 'ready_for_dispatch';
    case Dispatched = 'dispatched';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => __('New job'),
            self::Accepted => __('Accepted'),
            self::InProduction => __('Printing'),
            self::QualityCheck => __('Quality check'),
            self::ReadyForDispatch => __('Ready for dispatch'),
            self::Dispatched => __('Dispatched'),
            self::Failed => __('Failed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Assigned => 'warning',
            self::Accepted, self::InProduction, self::QualityCheck => 'info',
            self::ReadyForDispatch => 'accent',
            self::Dispatched => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * Statuses a printing partner may move a job to from this status.
     * Quality-check outcomes and dispatch are recorded through dedicated actions.
     *
     * @return list<self>
     */
    public function partnerTransitions(): array
    {
        return match ($this) {
            self::Assigned => [self::Accepted],
            self::Accepted => [self::InProduction],
            self::InProduction => [self::QualityCheck],
            default => [],
        };
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Failed, self::Cancelled, self::Dispatched], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Failed, self::Cancelled], true);
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Assigned->value, self::Accepted->value, self::InProduction->value, self::QualityCheck->value, self::ReadyForDispatch->value];
    }
}
