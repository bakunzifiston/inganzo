<?php

namespace App\Support;

use App\Models\Currency;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Integer minor-unit money helpers.
 *
 * Amounts are always integers in the currency's minor unit (RWF has 0 decimals,
 * so 12 500 RWF is stored as 12500). Arithmetic never uses floats; percentages are
 * expressed in basis points (1 bps = 0.01 %).
 */
final class Money
{
    /**
     * @var array<string, array{symbol: string, minor_unit: int, symbol_first: bool}>
     */
    private static array $definitions = [];

    public static function format(?int $amount, string $currency, bool $withSymbol = true): string
    {
        if ($amount === null) {
            return '—';
        }

        $definition = self::definition($currency);
        $decimals = $definition['minor_unit'];
        $negative = $amount < 0;
        $absolute = abs($amount);

        $major = intdiv($absolute, 10 ** $decimals);
        $minor = $absolute % (10 ** $decimals);
        $number = number_format($major, 0, '.', ',');

        if ($decimals > 0) {
            $number .= '.'.str_pad((string) $minor, $decimals, '0', STR_PAD_LEFT);
        }

        if ($negative) {
            $number = '−'.$number;
        }

        if (! $withSymbol) {
            return $number;
        }

        return $definition['symbol_first']
            ? $definition['symbol'].' '.$number
            : $number.' '.$definition['symbol'];
    }

    /**
     * Apply basis points, rounding half up. 1000 RWF × 2500 bps = 250 RWF.
     */
    public static function percentage(int $amount, int $basisPoints): int
    {
        if ($basisPoints < 0) {
            throw new InvalidArgumentException('Basis points must not be negative.');
        }

        return intdiv($amount * $basisPoints + 5000, 10000);
    }

    /**
     * Round up to the next multiple of the increment (e.g. 100 RWF price points).
     */
    public static function roundUp(int $amount, int $increment): int
    {
        if ($increment <= 1) {
            return $amount;
        }

        $remainder = $amount % $increment;

        return $remainder === 0 ? $amount : $amount + ($increment - $remainder);
    }

    /**
     * Split an amount across weights so the parts always sum exactly to the amount
     * (largest-remainder method). Used to allocate order-level discounts to lines.
     *
     * @param  array<int|string, int>  $weights
     * @return array<int|string, int>
     */
    public static function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);

        if ($total <= 0) {
            return array_map(fn () => 0, $weights);
        }

        $allocated = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            $allocated[$key] = intdiv($amount * $weight, $total);
            $remainders[$key] = ($amount * $weight) % $total;
        }

        $left = $amount - array_sum($allocated);
        arsort($remainders);

        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }
            $allocated[$key]++;
            $left--;
        }

        return $allocated;
    }

    /**
     * Convert a decimal major-unit string from a form ("12500" or "12.50") to minor units.
     */
    public static function toMinor(string|int|null $value, string $currency): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $decimals = self::definition($currency)['minor_unit'];
        $value = str_replace([',', ' '], '', (string) $value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Invalid money value [{$value}].");
        }

        [$major, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);
        $negative = str_starts_with($major, '-');
        $minor = abs((int) $major) * (10 ** $decimals) + (int) ($fraction === '' ? 0 : $fraction);

        return $negative ? -$minor : $minor;
    }

    /**
     * Major-unit string for form inputs.
     */
    public static function toMajor(?int $amount, string $currency): string
    {
        if ($amount === null) {
            return '';
        }

        return str_replace([',', '−'], ['', '-'], self::format($amount, $currency, false));
    }

    /**
     * @return array{symbol: string, minor_unit: int, symbol_first: bool}
     */
    public static function definition(string $currency): array
    {
        return self::$definitions[$currency] ??= Cache::remember(
            "currency-definition:{$currency}",
            now()->addHour(),
            function () use ($currency) {
                $model = Currency::query()->find($currency);

                return [
                    'symbol' => $model?->symbol ?? $currency,
                    'minor_unit' => $model?->minor_unit ?? 0,
                    'symbol_first' => $model?->symbol_first ?? true,
                ];
            },
        );
    }

    public static function flushDefinitions(): void
    {
        self::$definitions = [];
    }
}
