<?php

namespace App\Services\Doctor;

/**
 * The "Your current measurements" answer in the assessment ("85 kg, 170 cm") as numbers, with the BMI the doctor needs.
 * Pure: it only reads the text and does arithmetic.
 */
class BodyMeasurements
{
    /** @return array{weight_kg: float, height_cm: float, bmi: float, category: string}|null */
    public static function fromAnswer(mixed $answer): ?array
    {
        if (! is_string($answer) || preg_match('/(\d+(?:\.\d+)?)\s*kg\D+(\d+(?:\.\d+)?)\s*cm/i', $answer, $m) !== 1) {
            return null;
        }

        $kg = (float) $m[1];
        $cm = (float) $m[2];
        if ($kg < 20 || $kg > 400 || $cm < 80 || $cm > 250) {
            return null;
        }

        $bmi = round($kg / (($cm / 100) ** 2), 1);

        return ['weight_kg' => $kg, 'height_cm' => $cm, 'bmi' => $bmi, 'category' => self::category($bmi)];
    }

    /** The standard adult bands (WHO). */
    public static function category(float $bmi): string
    {
        return match (true) {
            $bmi < 18.5 => 'Underweight',
            $bmi < 25 => 'Healthy weight',
            $bmi < 30 => 'Overweight',
            $bmi < 35 => 'Obesity class 1',
            $bmi < 40 => 'Obesity class 2',
            default => 'Obesity class 3',
        };
    }
}