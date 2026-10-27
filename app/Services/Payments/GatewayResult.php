<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;

final readonly class GatewayResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public PaymentStatus $status,
        public ?string $providerReference = null,
        public ?string $providerStatus = null,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $failureReason = null,
        public int $fee = 0,
        public ?string $redirectUrl = null,
        public array $raw = [],
    ) {}

    public static function pending(?string $reference, array $raw = [], ?string $redirectUrl = null): self
    {
        return new self(PaymentStatus::Pending, $reference, 'PENDING', raw: $raw, redirectUrl: $redirectUrl);
    }

    public static function failed(string $reason, array $raw = [], ?string $reference = null): self
    {
        return new self(PaymentStatus::Failed, $reference, 'FAILED', failureReason: $reason, raw: $raw);
    }
}
