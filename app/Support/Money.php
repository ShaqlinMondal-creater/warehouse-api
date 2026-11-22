<?php

namespace App\Support;

final class Money
{
    public static function label(mixed $amount, string $currency): string
    {
        if ($amount === null) {
            return '—';
        }

        $value = (float) $amount;
        $symbol = match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => '₹',
        };

        if ($value >= 1000) {
            return $symbol.rtrim(rtrim(number_format($value / 1000, 1, '.', ''), '0'), '.').'k';
        }

        return $symbol.number_format($value, $value == floor($value) ? 0 : 2);
    }
}
