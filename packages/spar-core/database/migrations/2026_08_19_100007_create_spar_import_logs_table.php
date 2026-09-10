<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** spar-core package migration. Idempotent (Schema::hasTable guard). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spar_import_logs')) {
            return;
        }

        Schema::create('spar_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('spar_import_batches')->cascadeOnDelete();
            $table->integer('row_number');
            $table->string('profile_code')->nullable();
            $table->string('store_name')->nullable();
            $table->string('status')->comment('created, updated, skipped, failed');
            $table->string('action')->nullable()->comment('patient_created, journey_created, dispense_recorded, etc.');
            $table->text('error_message')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_import_logs');
    }
};
