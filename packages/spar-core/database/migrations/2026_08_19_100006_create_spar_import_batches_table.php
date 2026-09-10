<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spar-core package migration. Idempotent (Schema::hasTable guard).
 *
 * NOTE (portability): `imported_by` is a PLAIN nullable column — NOT a foreign
 * key to `users`. The importing actor is host-defined; standalone has its own
 * staff table, so no DB FK to ZapMed `users` is created here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spar_import_batches')) {
            return;
        }

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
            $table->unsignedBigInteger('imported_by')->nullable();
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
