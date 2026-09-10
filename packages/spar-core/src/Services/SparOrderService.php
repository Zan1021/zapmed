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

        // TODO: Send notification to patient
        Log::info('SPAR order ready', [
            'order' => $order->reference,
            'type' => $order->type,
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
