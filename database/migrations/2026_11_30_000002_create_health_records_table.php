<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medical records and results on the patient's Health Stats tab (Craig's guide 3.1): documents a patient uploads for
 * their doctors, and results a treating doctor uploads for the patient. Private disk, random file names, title encrypted.
 * category: record (patient upload) | result (doctor upload).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploader_role', 12);                 // patient | doctor
            $table->string('category', 8);                       // record | result
            $table->text('title');                               // encrypted
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamps();
            $table->index(['patient_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_records');
    }
};
