<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 5 — patient_profile + prescription extensions for Contro (blueprint §2.1 / §2.7).
 *
 * patient_profiles ALREADY has medical_aid_name/number/plan (so those Contro fields land as-is).
 * This migration adds the remaining Contro patient fields, a proper labelled/geocoded address table,
 * and the remaining Contro prescription fields.
 *
 * NON-DUPLICATION NOTE (prescription_items):
 *   prescription_items already has `unit_price` and `line_total`, BOTH in cents. Contro's per-line
 *   `unitPrice` / `totalPrice` map directly onto those existing columns. We deliberately do NOT add
 *   `unit_price_minor` / `total_price_minor` duplicates — the existing cents columns ARE the minor-unit
 *   fields. Documented so the reconciler maps Contro unitPrice->unit_price, totalPrice->line_total.
 *
 * prescriptions ALREADY has: total_amount (cents), delivery_* fields, pharmacy_status/reference,
 *   is_chronic, repeats/repeats_used, prescriber_hpcsa_number. We add only the genuinely-missing
 *   Contro fields below. `pharmacy_script_ref` is Contro's pharmacyScriptReference and is kept DISTINCT
 *   from our existing `pharmacy_reference` (our own dispatch ref) — they are different identifiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- patient_profiles: remaining Contro patient fields ----------------------------------
        Schema::table('patient_profiles', function (Blueprint $table) {
            $table->string('payment_type')->nullable()->after('medical_aid_plan'); // Contro paymentType
            $table->string('mobile_msisdn', 20)->nullable()->after('payment_type'); // E.164, distinct from users.phone
        });

        // ---- patient_addresses: labelled + geocoded, primary-per-label --------------------------
        // New Contro recipient (Contro PatientDto.deliveryAddress). A patient can have multiple
        // addresses; exactly one primary per label.
        Schema::create('patient_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_profile_id')->constrained()->cascadeOnDelete();
            $table->string('label', 20)->default('delivery'); // home|work|delivery|billing|other
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('suburb', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 50)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 2)->default('ZA');
            $table->text('delivery_notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();   // geocode (filled later)
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);

            // Contro crosswalk.
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['patient_profile_id', 'label']);
        });

        // ---- prescriptions: remaining Contro fields ---------------------------------------------
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->integer('repeat_cycle_days')->nullable()->after('repeats_used');
            $table->date('next_repeat_date')->nullable()->after('repeat_cycle_days');
            $table->string('pharmacy_script_ref')->nullable()->after('next_repeat_date'); // Contro pharmacyScriptReference (distinct from pharmacy_reference)
            $table->integer('total_medication_cost_minor')->nullable()->after('pharmacy_script_ref'); // Contro totalMedicationCost (cents)
            $table->integer('service_fee_minor')->nullable()->after('total_medication_cost_minor');    // Contro serviceFee (cents)
            $table->string('delivery_method')->nullable()->after('service_fee_minor');                  // Contro deliveryMethod
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn([
                'repeat_cycle_days', 'next_repeat_date', 'pharmacy_script_ref',
                'total_medication_cost_minor', 'service_fee_minor', 'delivery_method',
            ]);
        });

        Schema::dropIfExists('patient_addresses');

        Schema::table('patient_profiles', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'mobile_msisdn']);
        });
    }
};
