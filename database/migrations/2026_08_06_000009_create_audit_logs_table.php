<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50); // login, logout, view_record, create_prescription, update_patient, etc
            $table->string('resource_type', 50)->nullable(); // Model class name
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable(); // before change
            $table->json('new_values')->nullable(); // after change
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index('action');
            $table->index('created_at');
        });

        // Consent records (POPIA compliance)
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('consent_type', 50); // terms_of_service, privacy_policy, data_processing, medical_records_access
            $table->string('version', 10)->default('1.0'); // consent document version
            $table->boolean('granted')->default(true);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'consent_type']);
        });

        // Uploaded documents (ID copies, lab results, etc)
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // id_document, lab_result, imaging, referral, other
            $table->string('name');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('mime_type', 50);
            $table->integer('file_size'); // bytes
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('audit_logs');
    }
};
