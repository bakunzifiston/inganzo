<?php

namespace App\Rules;

use App\Models\Country;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPhone implements ValidationRule
{
    public function __construct(private ?Country $country) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->country === null || ! is_string($value) || ! PhoneNumber::isValid($value, $this->country)) {
            $fail(__('Enter a valid :country phone number, for example :example.', [
                'country' => $this->country?->name ?? '',
                'example' => $this->country?->iso2 === 'RW' ? '078 123 4567' : $this->country?->phone_code.' …',
            ]));
        }
    }
}
