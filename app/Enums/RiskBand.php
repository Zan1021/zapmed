<?php

namespace App\Enums;

/**
 * CRM risk band — verbatim parity with Mark's crm_risk_band_enum + the AI risk rubric
 * (ai_assist/src/application/risk-score.usecase.ts):
 *   0–24 low · 25–49 medium · 50–74 high · 75–100 critical.
 */
enum RiskBand: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /** Derive the band from a 0–100 score using the reference rubric. */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 75 => self::Critical,
            $score >= 50 => self::High,
            $score >= 25 => self::Medium,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function colour(): string
    {
        return match ($this) {
            self::Low => 'emerald',
            self::Medium => 'amber',
            self::High => 'orange',
            self::Critical => 'red',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
