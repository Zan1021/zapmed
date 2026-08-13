<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_applications', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone', 20);
            $table->string('hpcsa_number', 20);
            $table->string('speciality', 100)->default('General Practitioner');
            $table->string('qualification', 255);
            $table->integer('years_experience')->default(0);
            $table->string('doctor_type', 20)->default('full_time'); // full_time or locum
            $table->text('motivation')->nullable();
            $table->string('hpcsa_certificate_path')->nullable();
            $table->string('id_document_path')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, more_info
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('hpcsa_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_applications');
    }
};
