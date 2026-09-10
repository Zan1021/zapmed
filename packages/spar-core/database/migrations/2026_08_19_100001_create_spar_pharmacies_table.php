<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spar-core package migration. Idempotent: guarded with Schema::hasTable so it
 * is a no-op where the ZapMed host has already created the table (the host's
 * original app/ migration ran first) and safe on a fresh standalone DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spar_pharmacies')) {
            return;
        }

        Schema::create('spar_pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('spar_store_id')->unique();
            $table->string('bhf_code')->nullable()->comment('Business Health Facility code');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('supports_delivery')->default(false);
            $table->integer('delivery_fee')->default(0)->comment('In cents');
            $table->json('operating_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_pharmacies');
    }
};
