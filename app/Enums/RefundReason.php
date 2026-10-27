<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RefundReason: string implements HasBadge
{
    use HasOptions;

    case Damaged = 'damaged';
    case WrongItem = 'wrong_item';
    case NotDelivered = 'not_delivered';
    case QualityIssue = 'quality_issue';
    case ProductionFailure = 'production_failure';
    case CustomerRequest = 'customer_request';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => __('Item arrived damaged'),
            self::WrongItem => __('Wrong item received'),
            self::NotDelivered => __('Not delivered'),
            self::QualityIssue => __('Print quality issue'),
            self::ProductionFailure => __('Could not be produced'),
            self::CustomerRequest => __('Customer request'),
            self::Other => __('Other'),
        };
    }

    public function tone(): string
    {
        return 'neutral';
    }
}
