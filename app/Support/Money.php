<?php

namespace App\Support;

/**
 * Presentation-only money formatter.
 *
 * This helper never changes stored values or calculations. It only renders
 * amounts with the CRM convention: comma thousands, dot decimals, two
 * decimals and the currency symbol when the operation provides one.
 */
final class Money
{
    public static function format(float|int|string|null $amount, ?string $currency = 'PEN', bool $withSymbol = true): string
    {
        $formatted = number_format((float) ($amount ?? 0), 2, '.', ',');

        if (! $withSymbol) {
            return $formatted;
        }

        $symbol = self::symbol($currency);

        return $symbol === null ? $formatted : $symbol.' '.$formatted;
    }

    public static function symbol(?string $currency): ?string
    {
        if ($currency === null) {
            return null;
        }

        $code = strtoupper(trim($currency));

        if ($code === '') {
            return 'S/';
        }

        // A grouped currency (for example "PEN, USD") cannot be represented by
        // one symbol without inventing a conversion. Keep the amount numeric and
        // let the currency column explain the context.
        if (str_contains($code, ',') || str_contains($code, '/')) {
            return null;
        }

        return match ($code) {
            'PEN', 'SOL', 'SOLES' => 'S/',
            'USD', 'US$', 'US' => 'US$',
            'EUR' => '€',
            default => $code,
        };
    }
}
