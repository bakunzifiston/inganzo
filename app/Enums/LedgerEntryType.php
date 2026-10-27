<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LedgerEntryType: string implements HasBadge
{
    use HasOptions;

    case SaleEarning = 'sale_earning';
    case EarningRelease = 'earning_release';
    case RefundReversal = 'refund_reversal';
    case PlatformMargin = 'platform_margin';
    case CostRecovery = 'cost_recovery';
    case DeliveryIncome = 'delivery_income';
    case DiscountCost = 'discount_cost';
    case ProductionCost = 'production_cost';
    case ProcessingFee = 'processing_fee';
    case Payout = 'payout';
    case PayoutReversal = 'payout_reversal';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::SaleEarning => __('Sale earning'),
            self::EarningRelease => __('Earning released'),
            self::RefundReversal => __('Refund reversal'),
            self::PlatformMargin => __('Platform margin'),
            self::CostRecovery => __('Base cost collected'),
            self::DeliveryIncome => __('Delivery income'),
            self::DiscountCost => __('Discount'),
            self::ProductionCost => __('Production cost'),
            self::ProcessingFee => __('Processing fee'),
            self::Payout => __('Payout'),
            self::PayoutReversal => __('Payout reversed'),
            self::Adjustment => __('Adjustment'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::SaleEarning => 'success',
            self::EarningRelease, self::PayoutReversal => 'info',
            self::RefundReversal => 'danger',
            self::PlatformMargin, self::CostRecovery, self::DeliveryIncome, self::DiscountCost, self::ProductionCost, self::ProcessingFee, self::Adjustment => 'neutral',
            self::Payout => 'accent',
        };
    }
}
