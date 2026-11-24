<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff profile kept by Operations for every doctor, health coach and Operations user (Craig's guide 6.4): registration
 * number, board, practice address, registration status, country, booking links and the signature image.
 * Doctors also have a DoctorProfile (fees, calendar). Marking a doctor "verified" here switches DoctorProfile.is_verified on,
 * which is what lets them appear in booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('registration_number', 40)->nullable();
            $table->string('board', 40)->nullable();
            $table->text('practice_address')->nullable();
            $table->string('registration_status', 16)->default('pending');   // pending | verified | lapsed | not_required
            $table->string('country', 2)->default('ZA');
            $table->string('booking_url', 255)->nullable();
            $table->string('authorisation_booking_url', 255)->nullable();
            $table->string('signature_path')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
