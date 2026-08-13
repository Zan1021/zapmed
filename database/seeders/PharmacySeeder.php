<?php

namespace Database\Seeders;

use App\Models\Pharmacy;
use Illuminate\Database\Seeder;

class PharmacySeeder extends Seeder
{
    public function run(): void
    {
        $pharmacies = [
            [
                'name' => 'MedDelivery Cape Town',
                'email' => 'orders@meddelivery.co.za',
                'phone' => '021 555 0100',
                'address' => '12 Long Street',
                'city' => 'Cape Town',
                'province' => 'Western Cape',
                'postal_code' => '8001',
                'is_active' => true,
                'is_default' => true,
                'supports_delivery' => true,
                'delivery_fee' => 0, // free delivery
                'delivery_area' => 'Cape Town metro (50km radius)',
                'api_type' => 'none',
                'operating_hours' => [
                    'mon' => ['08:00', '18:00'],
                    'tue' => ['08:00', '18:00'],
                    'wed' => ['08:00', '18:00'],
                    'thu' => ['08:00', '18:00'],
                    'fri' => ['08:00', '18:00'],
                    'sat' => ['08:00', '13:00'],
                    'sun' => null,
                ],
            ],
            [
                'name' => 'QuickMeds Johannesburg',
                'email' => 'dispensary@quickmeds.co.za',
                'phone' => '011 555 0200',
                'address' => '45 Commissioner Street',
                'city' => 'Johannesburg',
                'province' => 'Gauteng',
                'postal_code' => '2001',
                'is_active' => true,
                'is_default' => false,
                'supports_delivery' => true,
                'delivery_fee' => 5000, // R50
                'delivery_area' => 'Johannesburg metro (40km radius)',
                'api_type' => 'none',
                'operating_hours' => [
                    'mon' => ['07:30', '18:00'],
                    'tue' => ['07:30', '18:00'],
                    'wed' => ['07:30', '18:00'],
                    'thu' => ['07:30', '18:00'],
                    'fri' => ['07:30', '18:00'],
                    'sat' => ['08:00', '14:00'],
                    'sun' => null,
                ],
            ],
            [
                'name' => 'Nationwide Pharmacy (Courier)',
                'email' => 'scripts@nationwidepharmacy.co.za',
                'phone' => '0800 123 456',
                'address' => '88 Industrial Road',
                'city' => 'Centurion',
                'province' => 'Gauteng',
                'postal_code' => '0157',
                'is_active' => true,
                'is_default' => false,
                'supports_delivery' => true,
                'delivery_fee' => 9900, // R99 courier
                'delivery_area' => 'Nationwide (3-5 business days)',
                'api_type' => 'none',
                'operating_hours' => [
                    'mon' => ['08:00', '17:00'],
                    'tue' => ['08:00', '17:00'],
                    'wed' => ['08:00', '17:00'],
                    'thu' => ['08:00', '17:00'],
                    'fri' => ['08:00', '17:00'],
                    'sat' => null,
                    'sun' => null,
                ],
            ],
        ];

        foreach ($pharmacies as $pharmacy) {
            Pharmacy::updateOrCreate(
                ['name' => $pharmacy['name']],
                $pharmacy
            );
        }
    }
}
