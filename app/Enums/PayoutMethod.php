<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PayoutMethod: string implements HasBadge
{
    use HasOptions;

    case MtnMomo = 'mtn_momo';
    case AirtelMoney = 'airtel_money';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::MtnMomo => __('MTN Mobile Money'),
            self::AirtelMoney => __('Airtel Money'),
            self::BankTransfer => __('Bank transfer'),
        };
    }

    public function tone(): string
    {
        return 'neutral';
    }
}
