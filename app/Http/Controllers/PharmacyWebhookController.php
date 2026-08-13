<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PharmacyWebhookController extends Controller
{
    /**
     * Handle pharmacy status updates (webhook from partner pharmacy).
     *
     * Expected payload:
     * {
     *   "prescription_reference": "RX-XXXXXXXXX",
     *   "status": "dispensed|dispatched|delivered|returned|out_of_stock",
     *   "tracking_number": "optional",
     *   "courier": "optional",
     *   "notes": "optional",
     *   "timestamp": "ISO 8601"
     * }
     */
    public function statusUpdate(Request $request)
    {
        $data = $request->validate([
            'prescription_reference' => 'required|string',
            'status' => 'required|in:dispensed,dispatched,delivered,returned,out_of_stock',
            'tracking_number' => 'nullable|string',
            'courier' => 'nullable|string',
            'notes' => 'nullable|string',
            'timestamp' => 'nullable|date',
        ]);

        $prescription = Prescription::where('reference', $data['prescription_reference'])->first();

        if (!$prescription) {
            Log::warning('Pharmacy webhook: prescription not found', $data);
            return response()->json(['error' => 'Prescription not found'], 404);
        }

        // Map pharmacy status to our internal status
        $pharmacyStatus = match ($data['status']) {
            'dispensed' => 'dispensed',
            'dispatched' => 'in_transit',
            'delivered' => 'delivered',
            'returned' => 'returned',
            'out_of_stock' => 'out_of_stock',
        };

        $updateData = [
            'pharmacy_status' => $pharmacyStatus,
            'pharmacy_response' => array_merge(
                $prescription->pharmacy_response ?? [],
                ['latest_update' => $data]
            ),
        ];

        if ($data['status'] === 'dispatched') {
            $updateData['dispatched_at'] = $data['timestamp'] ?? now();
        }

        // Store tracking info in metadata
        if ($data['tracking_number'] || $data['courier']) {
            $metadata = $prescription->metadata ?? [];
            $metadata['tracking_number'] = $data['tracking_number'] ?? $metadata['tracking_number'] ?? null;
            $metadata['courier'] = $data['courier'] ?? $metadata['courier'] ?? null;
            $updateData['metadata'] = $metadata;
        }

        $prescription->update($updateData);

        Log::info('Pharmacy webhook: status updated', [
            'prescription' => $prescription->reference,
            'status' => $pharmacyStatus,
        ]);

        // Notify patient of delivery updates
        if (in_array($data['status'], ['dispatched', 'delivered'])) {
            $prescription->loadMissing('patient');
            // Could dispatch a notification/email here
        }

        return response()->json(['message' => 'Status updated', 'pharmacy_status' => $pharmacyStatus]);
    }
}
