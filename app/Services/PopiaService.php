<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PopiaService
{
    /**
     * Export all personal data for a user (POPIA right of access).
     * Returns a JSON file with all their data.
     */
    public function exportUserData(User $user): array
    {
        $data = [
            'export_date' => now()->toIso8601String(),
            'user' => [
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'gender' => $user->gender,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'address' => $user->address,
                'city' => $user->city,
                'province' => $user->province,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'medical_profile' => $user->patientProfile?->toArray(),
            'appointments' => $user->appointments()
                ->with('doctor:id,first_name,last_name')
                ->get(['id', 'reference', 'doctor_id', 'type', 'status', 'appointment_date', 'start_time'])
                ->toArray(),
            'consultations' => DB::table('consultations')
                ->where('patient_id', $user->id)
                ->get(['id', 'diagnosis', 'treatment_plan', 'status', 'started_at', 'completed_at'])
                ->toArray(),
            'prescriptions' => DB::table('prescriptions')
                ->where('patient_id', $user->id)
                ->get(['id', 'reference', 'diagnosis', 'status', 'is_chronic', 'signed_at'])
                ->toArray(),
            'payments' => DB::table('payments')
                ->where('patient_id', $user->id)
                ->get(['id', 'amount', 'currency', 'status', 'description', 'created_at'])
                ->toArray(),
            'consent_records' => DB::table('consent_records')
                ->where('user_id', $user->id)
                ->get()
                ->toArray(),
        ];

        return $data;
    }

    /**
     * Process a data deletion request (POPIA right to erasure).
     * Anonymises medical records (legal retention required) and deletes PII.
     */
    public function processDataDeletion(User $user): void
    {
        // Anonymise (not delete) medical records — retention required by law (5 years)
        DB::table('consultations')
            ->where('patient_id', $user->id)
            ->update(['patient_id' => null]);

        // Anonymise prescriptions (keep for pharmacy/legal records)
        DB::table('prescriptions')
            ->where('patient_id', $user->id)
            ->update([
                'patient_id' => null,
                'delivery_address' => null,
                'delivery_city' => null,
                'delivery_province' => null,
                'delivery_postal_code' => null,
                'delivery_phone' => null,
            ]);

        // Delete personal data
        DB::table('patient_profiles')->where('user_id', $user->id)->delete();
        DB::table('assessments')->where('user_id', $user->id)->delete();
        DB::table('newsletter_subscribers')->where('email', $user->email)->delete();
        DB::table('consent_records')->where('user_id', $user->id)->delete();
        DB::table('messages')->where('sender_id', $user->id)->orWhere('receiver_id', $user->id)->delete();

        // Cancel appointments
        DB::table('appointments')
            ->where('patient_id', $user->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->update(['status' => 'cancelled', 'cancellation_reason' => 'Account deleted']);

        // Delete the user account
        $user->update([
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'email' => 'deleted_' . $user->id . '@removed.zapmed.co.za',
            'phone' => null,
            'address' => null,
            'city' => null,
            'province' => null,
            'postal_code' => null,
            'date_of_birth' => null,
            'gender' => null,
        ]);

        $user->delete(); // Soft delete if available, hard delete otherwise
    }
}
