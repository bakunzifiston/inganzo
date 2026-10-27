<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;

final readonly class CallbackData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ?string $providerReference,
        public string $dedupeKey,
        public ?bool $signatureValid,
        public array $payload,
        public ?PaymentStatus $reportedStatus = null,
        public ?int $reportedAmount = null,
        public ?string $reportedCurrency = null,
    ) {}
}
