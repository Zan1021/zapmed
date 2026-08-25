<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spar_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('source')->default('manual')->comment('manual, ftp, api');
            $table->string('status')->default('pending')->comment('pending, processing, completed, failed');
            $table->integer('records_total')->default(0);
            $table->integer('records_processed')->default(0);
            $table->integer('records_created')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->integer('records_failed')->default(0);
            $table->json('errors')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_import_batches');
    }
};
