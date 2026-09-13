<?php

namespace App\Enums;

/**
 * POPIA consent purposes — verbatim parity with compliance_consent_purpose_enum.
 */
enum ConsentPurpose: string
{
    case Transactional = 'transactional';   // service messages tied to active orders
    case ClinicalShare = 'clinical_share';  // share with treating doctors
    case PharmacyShare = 'pharmacy_share';  // share with dispensing pharmacies
    case MedicalAidClaim = 'medical_aid_claim';
    case Marketing = 'marketing';
    case Research = 'research';              // de-identified research use
    case Analytics = 'analytics';
    case CrossBorder = 'cross_border';       // transfer outside ZA

    public function label(): string
    {
        return match ($this) {
            self::Transactional => 'Transactional messages',
            self::ClinicalShare => 'Share with treating doctors',
            self::PharmacyShare => 'Share with dispensing pharmacies',
            self::MedicalAidClaim => 'Medical-aid claim submission',
            self::Marketing => 'Marketing',
            self::Research => 'De-identified research',
            self::Analytics => 'Product analytics',
            self::CrossBorder => 'Cross-border transfer',
        };
    }

    /**
     * Purposes granted by default on first sight (service-necessary). Marketing/research/analytics/
     * cross_border are explicit opt-ins → start pending_reconsent.
     *
     * @return array<int,self>
     */
    public static function defaultGranted(): array
    {
        return [self::Transactional, self::ClinicalShare, self::PharmacyShare, self::MedicalAidClaim];
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
