<?php

use Illuminate\Support\Number;

if (!function_exists('country')) {
    /**
     * Get a country config value.
     */
    function country(?string $key = null, $default = null)
    {
        if ($key === null) {
            return config('country');
        }
        return config("country.{$key}", $default);
    }
}

if (!function_exists('currency')) {
    /**
     * Format an amount in cents to the country's currency.
     * e.g. currency(45000) => "R450.00" (SA) or "₦45,000" (NG)
     */
    function currency(int $amountInCents, bool $showSymbol = true): string
    {
        $amount = $amountInCents / 100;
        $decimals = country('currency_decimals', 2);
        $symbol = country('currency_symbol', 'R');
        $position = country('currency_position', 'before');

        $formatted = number_format($amount, $decimals, '.', ',');

        if (!$showSymbol) {
            return $formatted;
        }

        return $position === 'before'
            ? $symbol . $formatted
            : $formatted . $symbol;
    }
}

if (!function_exists('country_phone')) {
    /**
     * Format a phone number to the country's international format.
     */
    function country_phone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);
        $prefix = country('phone_prefix', '+27');

        // Remove leading 0, add country prefix
        if (str_starts_with($phone, '0')) {
            $phone = $prefix . substr($phone, 1);
        }

        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}

if (!function_exists('payment_gateway')) {
    /**
     * Get the configured payment gateway for this country.
     */
    function payment_gateway(): string
    {
        return country('payment_gateway', 'payfast');
    }
}

if (!function_exists('sms_provider')) {
    /**
     * Get the configured SMS provider for this country.
     */
    function sms_provider(): string
    {
        return country('sms_provider', 'bulksms');
    }
}

if (!function_exists('provinces')) {
    /**
     * Get the list of provinces/states for this country.
     */
    function provinces(): array
    {
        return country('provinces', []);
    }
}

if (!function_exists('tax_amount')) {
    /**
     * Calculate tax on an amount (in cents).
     */
    function tax_amount(int $amountInCents): int
    {
        $rate = country('tax_rate', 15);
        return (int) round($amountInCents * ($rate / (100 + $rate))); // Extract VAT from inclusive price
    }
}
