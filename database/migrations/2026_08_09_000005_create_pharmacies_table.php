<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('group')->nullable();
                $table->string('license_number')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->text('address');
                $table->string('city');
                $table->string('province', 30);
                $table->string('postal_code', 10)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('api_type', 30)->default('none');
                $table->string('api_endpoint')->nullable();
                $table->string('api_key')->nullable();
                $table->boolean('supports_delivery')->default(false);
                $table->integer('delivery_fee')->default(0);
                $table->string('delivery_area')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('operating_hours')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'city']);
                $table->index(['latitude', 'longitude']);
                $table->index('group');
            });

        if (!Schema::hasColumn('prescriptions', 'pharmacy_id')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->foreignId('pharmacy_id')->nullable()->after('doctor_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prescriptions', 'pharmacy_id')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->dropForeign(['pharmacy_id']);
                $table->dropColumn('pharmacy_id');
            });
        }
        Schema::dropIfExists('pharmacies');
    }
};
