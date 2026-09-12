<?php

namespace App\Services\Contro;

/**
 * Money conversion for Contro import (blueprint §3 rule 7).
 * Contro sends doubles in RANDS; canonical stores MINOR units (cents, integer). Banker's rounding
 * (round-half-to-even) on the cent boundary, matching Mark's money/v1 semantics.
 */
class Money
{
    /** Rands (float|string|null) -> integer cents, or null. */
    public static function randsToMinor(int|float|string|null $rands): ?int
    {
        if ($rands === null || $rands === '') {
            return null;
        }

        $value = (float) $rands;
        // PHP_ROUND_HALF_EVEN = banker's rounding.
        return (int) round($value * 100, 0, PHP_ROUND_HALF_EVEN);
    }
}
