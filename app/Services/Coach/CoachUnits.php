<?php

namespace App\Services\Coach;

/** kg / lb conversion. Weight is ALWAYS stored in kg (0.1 precision); lb is a display and input choice only. */
class CoachUnits
{
    public const LB_PER_KG = 2.20462;

    public static function normalise(?string $unit): string
    {
        // kg unless the switch has been deliberately turned on in config (South Africa uses kg).
        return ($unit === 'lb' && config('coach.units.allow_lb')) ? 'lb' : 'kg';
    }

    public static function fromKg(float $kg, ?string $unit): float
    {
        return self::normalise($unit) === 'lb' ? round($kg * self::LB_PER_KG, 1) : round($kg, 1);
    }

    public static function toKg(float $value, ?string $unit): float
    {
        return round(self::normalise($unit) === 'lb' ? $value / self::LB_PER_KG : $value, 1);
    }

    public static function format(?float $kg, ?string $unit): ?string
    {
        if ($kg === null) {
            return null;
        }

        return number_format(self::fromKg($kg, $unit), 1).' '.self::normalise($unit);
    }
}