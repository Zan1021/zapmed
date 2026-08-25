<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Doctor = 'doctor';
    case Patient = 'patient';
    case PharmacyStaff = 'pharmacy_staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Doctor => 'Doctor',
            self::Patient => 'Patient',
            self::PharmacyStaff => 'Pharmacy Staff',
        };
    }
}
