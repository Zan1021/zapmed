<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the prescribing practitioner's legal identity onto the prescription
 * at issue time. HPCSA guidance + the build spec require a script to carry the
 * prescriber's registration details as they were WHEN ISSUED — not pulled live
 * from a mutable profile that can change or be deleted afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('prescriber_name')->nullable()->after('doctor_id');
            $table->string('prescriber_hpcsa_number')->nullable()->after('prescriber_name');
            $table->string('prescriber_qualification')->nullable()->after('prescriber_hpcsa_number');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn([
                'prescriber_name',
                'prescriber_hpcsa_number',
                'prescriber_qualification',
            ]);
        });
    }
};
