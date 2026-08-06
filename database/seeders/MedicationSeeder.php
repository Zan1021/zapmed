<?php

namespace Database\Seeders;

use App\Models\Medication;
use Illuminate\Database\Seeder;

class MedicationSeeder extends Seeder
{
    public function run(): void
    {
        $medications = [
            // PDE5 Inhibitors
            ['name' => 'Sildenafil 25mg', 'generic_name' => 'Sildenafil', 'brand_name' => 'Viagra', 'form' => 'tablet', 'strength' => '25mg', 'schedule' => 'S4'],
            ['name' => 'Sildenafil 50mg', 'generic_name' => 'Sildenafil', 'brand_name' => 'Viagra', 'form' => 'tablet', 'strength' => '50mg', 'schedule' => 'S4'],
            ['name' => 'Sildenafil 100mg', 'generic_name' => 'Sildenafil', 'brand_name' => 'Viagra', 'form' => 'tablet', 'strength' => '100mg', 'schedule' => 'S4'],
            ['name' => 'Tadalafil 5mg', 'generic_name' => 'Tadalafil', 'brand_name' => 'Cialis', 'form' => 'tablet', 'strength' => '5mg', 'schedule' => 'S4'],
            ['name' => 'Tadalafil 10mg', 'generic_name' => 'Tadalafil', 'brand_name' => 'Cialis', 'form' => 'tablet', 'strength' => '10mg', 'schedule' => 'S4'],
            ['name' => 'Tadalafil 20mg', 'generic_name' => 'Tadalafil', 'brand_name' => 'Cialis', 'form' => 'tablet', 'strength' => '20mg', 'schedule' => 'S4'],

            // Antihypertensives
            ['name' => 'Amlodipine 5mg', 'generic_name' => 'Amlodipine', 'brand_name' => 'Norvasc', 'form' => 'tablet', 'strength' => '5mg', 'schedule' => 'S3'],
            ['name' => 'Amlodipine 10mg', 'generic_name' => 'Amlodipine', 'brand_name' => 'Norvasc', 'form' => 'tablet', 'strength' => '10mg', 'schedule' => 'S3'],
            ['name' => 'Enalapril 10mg', 'generic_name' => 'Enalapril', 'brand_name' => 'Renitec', 'form' => 'tablet', 'strength' => '10mg', 'schedule' => 'S3'],
            ['name' => 'Enalapril 20mg', 'generic_name' => 'Enalapril', 'brand_name' => 'Renitec', 'form' => 'tablet', 'strength' => '20mg', 'schedule' => 'S3'],
            ['name' => 'Losartan 50mg', 'generic_name' => 'Losartan', 'brand_name' => 'Cozaar', 'form' => 'tablet', 'strength' => '50mg', 'schedule' => 'S3'],
            ['name' => 'Losartan 100mg', 'generic_name' => 'Losartan', 'brand_name' => 'Cozaar', 'form' => 'tablet', 'strength' => '100mg', 'schedule' => 'S3'],

            // Diabetes
            ['name' => 'Metformin 500mg', 'generic_name' => 'Metformin', 'brand_name' => 'Glucophage', 'form' => 'tablet', 'strength' => '500mg', 'schedule' => 'S3'],
            ['name' => 'Metformin 850mg', 'generic_name' => 'Metformin', 'brand_name' => 'Glucophage', 'form' => 'tablet', 'strength' => '850mg', 'schedule' => 'S3'],

            // Contraceptives
            ['name' => 'Yasmin', 'generic_name' => 'Drospirenone/Ethinyl Estradiol', 'brand_name' => 'Yasmin', 'form' => 'tablet', 'strength' => '3mg/0.03mg', 'schedule' => 'S3'],
            ['name' => 'Nordette', 'generic_name' => 'Levonorgestrel/Ethinyl Estradiol', 'brand_name' => 'Nordette', 'form' => 'tablet', 'strength' => '0.15mg/0.03mg', 'schedule' => 'S3'],
            ['name' => 'Triphasil', 'generic_name' => 'Levonorgestrel/Ethinyl Estradiol', 'brand_name' => 'Triphasil', 'form' => 'tablet', 'strength' => 'Triphasic', 'schedule' => 'S3'],

            // Hair Loss
            ['name' => 'Finasteride 1mg', 'generic_name' => 'Finasteride', 'brand_name' => 'Propecia', 'form' => 'tablet', 'strength' => '1mg', 'schedule' => 'S4'],
            ['name' => 'Minoxidil 5%', 'generic_name' => 'Minoxidil', 'brand_name' => 'Regaine', 'form' => 'topical', 'strength' => '5%', 'schedule' => 'S2'],

            // Weight Loss
            ['name' => 'Semaglutide 0.25mg', 'generic_name' => 'Semaglutide', 'brand_name' => 'Ozempic', 'form' => 'injection', 'strength' => '0.25mg', 'schedule' => 'S4'],
            ['name' => 'Semaglutide 0.5mg', 'generic_name' => 'Semaglutide', 'brand_name' => 'Ozempic', 'form' => 'injection', 'strength' => '0.5mg', 'schedule' => 'S4'],
            ['name' => 'Semaglutide 1mg', 'generic_name' => 'Semaglutide', 'brand_name' => 'Ozempic', 'form' => 'injection', 'strength' => '1mg', 'schedule' => 'S4'],

            // Antidepressants
            ['name' => 'Fluoxetine 20mg', 'generic_name' => 'Fluoxetine', 'brand_name' => 'Prozac', 'form' => 'capsule', 'strength' => '20mg', 'schedule' => 'S5'],
            ['name' => 'Sertraline 50mg', 'generic_name' => 'Sertraline', 'brand_name' => 'Zoloft', 'form' => 'tablet', 'strength' => '50mg', 'schedule' => 'S5'],

            // Antibiotics
            ['name' => 'Amoxicillin 500mg', 'generic_name' => 'Amoxicillin', 'brand_name' => null, 'form' => 'capsule', 'strength' => '500mg', 'schedule' => 'S4'],
            ['name' => 'Azithromycin 500mg', 'generic_name' => 'Azithromycin', 'brand_name' => 'Zithromax', 'form' => 'tablet', 'strength' => '500mg', 'schedule' => 'S4'],
            ['name' => 'Doxycycline 100mg', 'generic_name' => 'Doxycycline', 'brand_name' => null, 'form' => 'capsule', 'strength' => '100mg', 'schedule' => 'S4'],

            // Pain
            ['name' => 'Paracetamol 500mg', 'generic_name' => 'Paracetamol', 'brand_name' => 'Panado', 'form' => 'tablet', 'strength' => '500mg', 'schedule' => 'S1'],
            ['name' => 'Ibuprofen 400mg', 'generic_name' => 'Ibuprofen', 'brand_name' => 'Nurofen', 'form' => 'tablet', 'strength' => '400mg', 'schedule' => 'S2'],

            // Antivirals
            ['name' => 'Valacyclovir 500mg', 'generic_name' => 'Valacyclovir', 'brand_name' => 'Valtrex', 'form' => 'tablet', 'strength' => '500mg', 'schedule' => 'S4'],
            ['name' => 'Acyclovir 400mg', 'generic_name' => 'Acyclovir', 'brand_name' => 'Zovirax', 'form' => 'tablet', 'strength' => '400mg', 'schedule' => 'S4'],

            // PPIs
            ['name' => 'Omeprazole 20mg', 'generic_name' => 'Omeprazole', 'brand_name' => 'Losec', 'form' => 'capsule', 'strength' => '20mg', 'schedule' => 'S3'],
            ['name' => 'Pantoprazole 40mg', 'generic_name' => 'Pantoprazole', 'brand_name' => 'Pantoloc', 'form' => 'tablet', 'strength' => '40mg', 'schedule' => 'S3'],
        ];

        foreach ($medications as $medication) {
            Medication::create(array_merge($medication, ['is_active' => true]));
        }
    }
}
