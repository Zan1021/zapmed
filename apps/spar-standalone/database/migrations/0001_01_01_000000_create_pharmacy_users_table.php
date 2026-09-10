<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone staff accounts (spec design §5.2, task 4.2). Replaces the ZapMed
 * `users` table for the standalone deploy. Patients have NO account (they use
 * the no-login tracker), so this is staff-only.
 *
 * spar_pharmacy_id is a PLAIN nullable column — the spar_pharmacies table is
 * created by the spar-core package migrations, which may run in a different
 * pass; no cross-table DB FK is declared here to avoid ordering fragility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('pharmacy_staff')->comment('admin, pharmacy_staff');
            $table->unsignedBigInteger('spar_pharmacy_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('pharmacy_users');
    }
};
