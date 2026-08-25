<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
