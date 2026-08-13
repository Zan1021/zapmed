<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // partner login account
            $table->string('name'); // Business name
            $table->string('slug', 50)->unique(); // used in ?ref=slug
            $table->string('website_url')->nullable();
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->unsignedTinyInteger('commission_consultation')->default(10); // percentage
            $table->unsignedTinyInteger('commission_medication')->default(5); // percentage
            $table->integer('cookie_days')->default(30); // referral cookie lifetime
            $table->string('payout_method', 30)->default('eft'); // eft, paypal
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_branch_code')->nullable();
            $table->string('status', 20)->default('pending'); // pending, active, suspended
            $table->json('allowed_treatments')->nullable(); // null = all treatments
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('landing_url')->nullable(); // where they landed
            $table->string('source_url')->nullable(); // where they came from
            $table->string('ip_address', 45)->nullable();
            $table->string('status', 20)->default('clicked'); // clicked, registered, converted
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('converted_at')->nullable(); // first paid consultation
            $table->timestamp('cookie_expires_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
            $table->index('patient_id');
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('referral_id');
            $table->foreign('referral_id')->references('id')->on('partner_referrals')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // consultation, medication
            $table->string('reference')->nullable(); // appointment ref or prescription ref
            $table->integer('sale_amount'); // in cents
            $table->unsignedTinyInteger('commission_rate'); // percentage at time of sale
            $table->integer('commission_amount'); // in cents
            $table->string('status', 20)->default('pending'); // pending, approved, paid
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('partner_referrals');
        Schema::dropIfExists('partners');
    }
};
