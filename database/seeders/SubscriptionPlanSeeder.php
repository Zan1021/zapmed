<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Essential',
                'slug' => 'essential',
                'description' => 'Perfect for once-off consultations and basic healthcare needs.',
                'price' => 22000, // R220/month
                'billing_cycle' => 'monthly',
                'cycle_frequency' => 1,
                'consultations_per_month' => 1,
                'includes_chronic_renewals' => false,
                'includes_priority_booking' => false,
                'includes_messaging' => true,
                'features' => [
                    '1 GP consultation per month',
                    'Prescription delivery included',
                    'Secure messaging with your doctor',
                    'Medical certificates & sick notes',
                    'Digital health records',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Plus',
                'slug' => 'plus',
                'description' => 'For ongoing care, chronic medication management, and regular check-ups.',
                'price' => 35000, // R350/month
                'billing_cycle' => 'monthly',
                'cycle_frequency' => 1,
                'consultations_per_month' => 2,
                'includes_chronic_renewals' => true,
                'includes_priority_booking' => true,
                'includes_messaging' => true,
                'features' => [
                    '2 consultations per month',
                    'Chronic medication renewals',
                    'Priority booking (same-day)',
                    'Prescription delivery included',
                    'Secure messaging with your doctor',
                    'Medical certificates & sick notes',
                    'Digital health records',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Weight Loss Programme',
                'slug' => 'weight-loss',
                'description' => 'Doctor-guided GLP-1 weight loss with dedicated Health Coach support.',
                'price' => 45000, // R450/month
                'billing_cycle' => 'monthly',
                'cycle_frequency' => 1,
                'consultations_per_month' => 2,
                'includes_chronic_renewals' => true,
                'includes_priority_booking' => true,
                'includes_messaging' => true,
                'features' => [
                    'Partner Doctor consultations',
                    'Dedicated Health Coach (registered dietitian)',
                    'GLP-1 medication prescribed (billed separately)',
                    'Weekly WhatsApp check-ins',
                    'Personalised nutrition guidance',
                    'Priority booking',
                    'Prescription delivery included',
                    'Digital health records',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Family',
                'slug' => 'family',
                'description' => 'Cover your whole family — up to 4 members on one plan.',
                'price' => 65000, // R650/month
                'billing_cycle' => 'monthly',
                'cycle_frequency' => 1,
                'consultations_per_month' => 4,
                'includes_chronic_renewals' => true,
                'includes_priority_booking' => true,
                'includes_messaging' => true,
                'features' => [
                    'Up to 4 family members',
                    '4 consultations per month (shared)',
                    'Chronic medication renewals for all members',
                    'Priority booking (same-day)',
                    'Prescription delivery included',
                    'Secure messaging with your doctor',
                    'Medical certificates & sick notes',
                    'Digital health records',
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
