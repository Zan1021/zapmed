<?php

namespace Zapmed\SparCore\Contracts;

use Zapmed\SparCore\Models\SparPrescriptionJourney;

/**
 * The one genuine business coupling between SPAR and telehealth (spec FR-3,
 * FR-13). Integrated ZapMed provides a real bridge (renewal → online consult →
 * new script back to SPAR). Standalone binds NullTelehealthBridge (own-doctor
 * renewal only). SPAR renewal code depends only on this contract.
 */
interface TelehealthBridge
{
    /**
     * Whether an online-consultation renewal option is offered in this host.
     */
    public function offersOnlineConsult(): bool;

    /**
     * Renewal options to present to the patient (labels/keys), host-dependent.
     *
     * @return array<int, array{key:string, label:string, url?:string}>
     */
    public function renewalOptions(SparPrescriptionJourney $journey): array;

    /**
     * Build the deep-link context handed to ZapMed to pre-fill a chronic
     * renewal consultation (identity + medications + pharmacy + signed token).
     * Returns null when no online consult is offered (standalone).
     *
     * @return array<string, mixed>|null
     */
    public function handoffContext(SparPrescriptionJourney $journey): ?array;

    /**
     * Loop-closer: a ZapMed renewal consult issued a new script — create a new
     * SPAR journey at the originating pharmacy so the cycle restarts. No-op in
     * standalone. Returns the new journey when created.
     */
    public function returnPrescription(
        SparPrescriptionJourney $previous,
        array $medications,
        array $meta = []
    ): ?SparPrescriptionJourney;
}
