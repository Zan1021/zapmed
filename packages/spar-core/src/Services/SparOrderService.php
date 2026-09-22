<?php

namespace Zapmed\SparCore\Services;

use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Illuminate\Support\Facades\Log;

class SparOrderService
{
    /**
     * Create a collection request for a dispense.
     */
    public function requestCollection(SparDispenseRecord $dispense): SparOrder
    {
        $patient = $dispense->patient;
        $journey = $dispense->journey;

        $order = SparOrder::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $journey->spar_pharmacy_id,
            'dispense_record_id' => $dispense->id,
            'type' => 'collection',
            'status' => 'requested',
        ]);

        $dispense->update(['collection_requested_at' => now()]);

        Log::info('SPAR collection requested', [
            'order' => $order->reference,
            'patient' => $patient->display_name,
            'pharmacy' => $journey->pharmacy->name,
        ]);

        return $order;
    }

    /**
     * Create a delivery request for a dispense.
     */
    public function requestDelivery(
        SparDispenseRecord $dispense,
        string $address,
        string $city,
        string $postalCode,
        string $phone,
        ?\DateTime $deliveryDate = null
    ): SparOrder {
        $patient = $dispense->patient;
        $journey = $dispense->journey;

        // Check if pharmacy supports delivery
        if (!$journey->pharmacy->supports_delivery) {
            throw new \Exception('This pharmacy does not support delivery.');
        }

        $order = SparOrder::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $journey->spar_pharmacy_id,
            'dispense_record_id' => $dispense->id,
            'type' => 'delivery',
            'status' => 'requested',
            'delivery_address' => $address,
            'delivery_city' => $city,
            'delivery_postal_code' => $postalCode,
            'delivery_phone' => $phone,
            'delivery_date' => $deliveryDate,
        ]);

        $dispense->update(['collection_requested_at' => now()]);

        Log::info('SPAR delivery requested', [
            'order' => $order->reference,
            'patient' => $patient->display_name,
            'pharmacy' => $journey->pharmacy->name,
            'address' => $address,
        ]);

        return $order;
    }

    /**
     * Patient-initiated "Order next meds" (FR-C1). The patient picks a
     * fulfilment mode (collect/deliver, pay now / pay at store); we translate it
     * into the concrete order attributes and create a requested order against
     * the dispense's pharmacy. Delivery details are optional here (demo-grade —
     * the pharmacy captures/confirms address on processing); NFR-5 means we only
     * record the payment INTENT, no gateway is wired.
     *
     * @param  array<string, mixed>  $delivery  optional address/city/postal/phone
     */
    public function placePatientOrder(SparDispenseRecord $dispense, string $mode, array $delivery = []): SparOrder
    {
        $patient = $dispense->patient;
        $journey = $dispense->journey;

        $attributes = SparOrder::attributesForMode($mode);

        if ($attributes['type'] === 'delivery' && ! $journey->pharmacy->supports_delivery) {
            throw new \RuntimeException('This pharmacy does not support delivery.');
        }

        $order = SparOrder::create(array_merge([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $journey->spar_pharmacy_id,
            'dispense_record_id' => $dispense->id,
            'status' => 'requested',
            'delivery_address' => $delivery['address'] ?? null,
            'delivery_city' => $delivery['city'] ?? null,
            'delivery_postal_code' => $delivery['postal_code'] ?? null,
            'delivery_phone' => $delivery['phone'] ?? null,
        ], $attributes));

        $dispense->update(['collection_requested_at' => now()]);

        Log::info('SPAR patient order placed', [
            'order' => $order->reference,
            'patient' => $patient->display_name,
            'pharmacy' => $journey->pharmacy->name,
            'mode' => $order->fulfilment_mode,
        ]);

        return $order;
    }

    /**
     * Pharmacy marks order as being prepared.
     */
    public function startPreparing(SparOrder $order): void
    {
        $order->markPreparing();

        Log::info('SPAR order preparing', ['order' => $order->reference]);
    }

    /**
     * Pharmacy marks order as ready for collection/delivery.
     */
    public function markReady(SparOrder $order): void
    {
        $order->markReady();

        // C2b: notify the patient their order is ready. Consent-gated by the
        // dispatcher (NFR-1) — a non-consented patient simply isn't messaged.
        $this->notifyPatientReady($order);

        Log::info('SPAR order ready', [
            'order' => $order->reference,
            'type' => $order->type,
        ]);
    }

    /**
     * "Order processed — come collect" alert (FR-C2). Routed through the
     * consent-gated MessagingDispatcher so it can never reach a non-consented
     * patient. Delivery orders get an on-the-way message instead of collect.
     */
    private function notifyPatientReady(SparOrder $order): void
    {
        $patient = $order->patient;
        if (! $patient) {
            return;
        }

        $pharmacyName = $order->pharmacy?->name ?? 'your SPAR pharmacy';

        $body = $order->isDelivery()
            ? "Good news — your order {$order->reference} is packed and on its way from {$pharmacyName}."
            : "Good news — your order {$order->reference} is ready to collect at {$pharmacyName}.";

        app(MessagingDispatcher::class)->send($patient, [
            'subject' => 'Your SPAR order is ready',
            'body' => $body,
            'link' => null,
        ]);
    }

    /**
     * Complete the order (patient collected or delivery confirmed).
     */
    public function completeOrder(SparOrder $order): void
    {
        $order->markCompleted();

        Log::info('SPAR order completed', [
            'order' => $order->reference,
            'type' => $order->type,
        ]);
    }

    /**
     * Cancel an order.
     */
    public function cancelOrder(SparOrder $order, string $reason = null): void
    {
        $order->cancel($reason);

        Log::info('SPAR order cancelled', [
            'order' => $order->reference,
            'reason' => $reason,
        ]);
    }

    /**
     * Get pending orders for a pharmacy.
     */
    public function getPendingOrders(int $pharmacyId): \Illuminate\Database\Eloquent\Collection
    {
        return SparOrder::forPharmacy($pharmacyId)
            ->pending()
            ->with($this->patientWith(['dispenseRecord']))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get today's ready-for-collection orders for a pharmacy.
     */
    public function getReadyOrders(int $pharmacyId): \Illuminate\Database\Eloquent\Collection
    {
        return SparOrder::forPharmacy($pharmacyId)
            ->where('status', 'ready')
            ->with($this->patientWith())
            ->orderBy('ready_at')
            ->get();
    }

    /**
     * Build the eager-load list for a patient, including the integrated-host
     * `user` relation ONLY when the SparPatient model actually defines it.
     * The package base SparPatient has no telehealth `user()` relation; the
     * ZapMed subclass re-adds it. This keeps the query portable to standalone.
     *
     * @param  array<int, string>  $extra
     * @return array<int, string>
     */
    private function patientWith(array $extra = []): array
    {
        $patientLoad = method_exists(SparPatient::class, 'user') ? 'patient.user' : 'patient';

        return array_merge([$patientLoad], $extra);
    }
}
