<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Human-friendly unique references such as IGZ-261026-7KQ4M. Ambiguous characters
 * (0/O, 1/I/L) are excluded so references can be read over the phone.
 */
final class Reference
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * @param  class-string<Model>  $model
     */
    public static function generate(string $prefix, string $model, string $column = 'reference', int $length = 5): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $prefix.'-'.now()->format('ymd').'-'.self::random($length);

            if (! $model::query()->where($column, $candidate)->exists()) {
                return $candidate;
            }
        }

        return $prefix.'-'.now()->format('ymd').'-'.self::random($length + 3);
    }

    public static function random(int $length): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
