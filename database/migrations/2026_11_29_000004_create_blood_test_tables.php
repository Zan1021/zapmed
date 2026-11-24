<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blood tests (Craig's guide 4.1, 4.2): a doctor asks for tests (ticked + free text + a lab form), the patient or the
 * doctor uploads results, the doctor reviews. Results are health records: private disk, no access for Operations.
 * Free text, review note and the file names are encrypted/neutral. Statuses: requested > results_received > reviewed,
 * or cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blood_test_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('tests');                                   // keys from config doctor.blood_tests.catalogue
            $table->text('free_text')->nullable();                   // encrypted
            $table->string('lab_form', 24)->nullable();              // key from config doctor.blood_tests.lab_forms
            $table->string('lab_form_path')->nullable();             // an uploaded form
            $table->string('lab_form_name')->nullable();
            $table->string('status', 16)->default('requested');
            $table->text('review_note')->nullable();                 // encrypted
            $table->string('cancel_reason', 255)->nullable();
            $table->string('order_status_before', 40)->nullable();   // set only when the order status was moved
            $table->timestamp('results_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('blood_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blood_test_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploader_role', 12);                     // patient | doctor
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blood_test_results');
        Schema::dropIfExists('blood_test_requests');
    }
};
