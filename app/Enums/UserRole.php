<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Doctor = 'doctor';
    case Patient = 'patient';
    case PharmacyStaff = 'pharmacy_staff';
    case HealthCoach = 'health_coach';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Doctor => 'Doctor',
            self::Patient => 'Patient',
            self::PharmacyStaff => 'Pharmacy Staff',
            self::HealthCoach => 'Health Coach',
        };
    }
}
