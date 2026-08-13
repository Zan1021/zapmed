<?php

/*
|--------------------------------------------------------------------------
| Payout & Revenue Split Configuration
|--------------------------------------------------------------------------
|
| All amounts in PERCENTAGES unless noted otherwise.
| These are defaults — can be overridden per doctor/pharmacy/partner.
|
| Patient pays R450 consultation:
|   Doctor gets: 55% = R247.50
|   Platform (Zapmed): 45% = R202.50
|   Partner commission (if referred): 10% of R450 = R45 (from Zapmed's cut)
|
| Patient pays for medication (e.g. R1,210):
|   Pharmacy gets: 70% (cost of goods + dispensing)
|   Platform (Zapmed): 30% (margin)
|   Partner commission (if referred): 5% of sale (from Zapmed's cut)
|   Delivery fee: R50 flat (passed to courier)
|
*/

return [

    // ─── CONSULTATION SPLIT ──────────────────────────────────────────────────
    'consultation' => [
        'doctor_percentage' => 55,       // Doctor gets 55% of consultation fee
        'platform_percentage' => 45,     // Zapmed keeps 45%
    ],

    // ─── MEDICATION SPLIT ────────────────────────────────────────────────────
    'medication' => [
        'pharmacy_percentage' => 70,     // Pharmacy gets 70% (their cost + dispensing)
        'platform_percentage' => 30,     // Zapmed keeps 30% (margin)
        'delivery_fee' => 5000,          // R50 flat delivery fee in cents
    ],

    // ─── SUBSCRIPTION SPLIT ──────────────────────────────────────────────────
    'subscription' => [
        'doctor_percentage' => 40,       // Doctor gets 40% of monthly sub
        'coach_percentage' => 20,        // Health Coach gets 20%
        'platform_percentage' => 40,     // Zapmed keeps 40%
    ],

    // ─── PARTNER/AFFILIATE COMMISSIONS ───────────────────────────────────────
    // (These come OUT of Zapmed's platform cut, not on top of it)
    'partner' => [
        'consultation_percentage' => 10, // 10% of consultation fee
        'medication_percentage' => 5,    // 5% of medication sale
    ],

    // ─── PAYOUT SCHEDULE ─────────────────────────────────────────────────────
    'payout_schedule' => [
        'doctors' => 'monthly',          // Paid on 25th of each month
        'pharmacies' => 'weekly',        // Paid every Friday
        'partners' => 'monthly',         // Paid on 1st of each month (min R100)
        'coaches' => 'monthly',          // Paid on 25th with doctors
    ],

    // ─── MINIMUM PAYOUT ──────────────────────────────────────────────────────
    'minimum_payout' => 10000,           // R100 minimum before payout (in cents)

];
