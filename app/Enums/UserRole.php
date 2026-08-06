<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Doctor = 'doctor';
    case Patient = 'patient';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Doctor => 'Doctor',
            self::Patient => 'Patient',
        };
    }
}
