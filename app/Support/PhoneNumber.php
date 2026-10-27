<?php

namespace App\Support;

use App\Models\Country;

/**
 * Country-aware phone validation and E.164 normalisation.
 * Rwanda: +250 followed by 9 digits starting with 7 (078 MTN, 072/073 Airtel).
 * Because of number portability the operator is chosen by the customer, never inferred.
 */
final class PhoneNumber
{
    public static function normalize(string $input, Country $country): ?string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        $code = ltrim($country->phone_code, '+');

        if (str_starts_with($digits, '00'.$code)) {
            $digits = substr($digits, 2 + strlen($code));
        } elseif (str_starts_with($digits, $code) && strlen($digits) > strlen($code) + 7) {
            $digits = substr($digits, strlen($code));
        }

        $digits = ltrim($digits, '0');

        if (! preg_match('/^'.$country->phone_pattern.'$/', $digits)) {
            return null;
        }

        return '+'.$code.$digits;
    }

    public static function isValid(string $input, Country $country): bool
    {
        return self::normalize($input, $country) !== null;
    }

    /**
     * National significant number without the country code, as mobile-money APIs expect.
     */
    public static function national(string $e164, Country $country): string
    {
        return substr($e164, strlen($country->phone_code));
    }

    public static function display(?string $e164): string
    {
        if (! $e164) {
            return '';
        }

        if (preg_match('/^\+(\d{3})(\d{3})(\d{3})(\d{3})$/', $e164, $m)) {
            return "+{$m[1]} {$m[2]} {$m[3]} {$m[4]}";
        }

        return $e164;
    }
}
