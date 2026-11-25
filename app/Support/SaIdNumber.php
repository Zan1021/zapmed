<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A South African ID number: YYMMDD SSSS C A Z (13 digits). The date of birth and the sex are inside the number, and the last
 * digit is a check digit (the Luhn rule). Used to fill in a new patient's date of birth and gender, so the doctor sees their age.
 */
class SaIdNumber
{
    public static function clean(?string $id): string
    {
        return preg_replace('/\s+/', '', (string) $id);
    }

    public static function isValid(?string $id): bool
    {
        $id = self::clean($id);

        return preg_match('/^\d{13}$/', $id) === 1 && self::luhn($id) && self::dateOfBirth($id) !== null;
    }

    /** Null when the number is not a valid ID. Two digit years up to this year are 20xx, later ones are 19xx. */
    public static function dateOfBirth(?string $id): ?CarbonImmutable
    {
        $id = self::clean($id);
        if (preg_match('/^(\d{2})(\d{2})(\d{2})\d{7}$/', $id, $m) !== 1) {
            return null;
        }
        $yy = (int) $m[1];
        $year = ($yy <= (int) now()->format('y') ? 2000 : 1900) + $yy;

        return checkdate((int) $m[2], (int) $m[3], $year) ? CarbonImmutable::create($year, (int) $m[2], (int) $m[3])->startOfDay() : null;
    }

    /** 'female' (0000 to 4999) or 'male' (5000 to 9999), null for a number that is not valid. */
    public static function gender(?string $id): ?string
    {
        $id = self::clean($id);
        if (! self::isValid($id)) {
            return null;
        }

        return (int) substr($id, 6, 4) >= 5000 ? 'male' : 'female';
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $d) {
            $n = (int) $d;
            if ($i % 2 === 1) {
                $n *= 2;
                $n = $n > 9 ? $n - 9 : $n;
            }
            $sum += $n;
        }

        return $sum % 10 === 0;
    }
}