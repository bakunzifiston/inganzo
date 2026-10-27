<?php

namespace App\Services\Checkout;

use App\Models\Country;
use App\Models\District;
use App\Models\User;

final readonly class CheckoutData
{
    public function __construct(
        public ?User $user,
        public string $name,
        public string $email,
        public string $phone,
        public Country $country,
        public District $district,
        public ?string $sector,
        public string $streetAddress,
        public ?string $landmark,
        public ?string $note,
        public string $paymentMethod,
        public ?string $payerPhone,
        public string $locale,
    ) {}
}
