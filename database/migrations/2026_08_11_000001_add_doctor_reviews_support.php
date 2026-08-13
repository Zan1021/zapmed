<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add doctor_id to testimonials so we can aggregate per doctor
        if (!Schema::hasColumn('testimonials', 'doctor_id')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->foreignId('doctor_id')->nullable()->after('patient_id')->constrained('users')->nullOnDelete();
                $table->index('doctor_id');
            });
        }

        // Add cached rating fields to doctor_profiles for fast queries
        if (!Schema::hasColumn('doctor_profiles', 'average_rating')) {
            Schema::table('doctor_profiles', function (Blueprint $table) {
                $table->decimal('average_rating', 3, 2)->default(0)->after('is_verified');
                $table->unsignedInteger('total_reviews')->default(0)->after('average_rating');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('testimonials', 'doctor_id')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->dropForeign(['doctor_id']);
                $table->dropColumn('doctor_id');
            });
        }

        if (Schema::hasColumn('doctor_profiles', 'average_rating')) {
            Schema::table('doctor_profiles', function (Blueprint $table) {
                $table->dropColumn(['average_rating', 'total_reviews']);
            });
        }
    }
};
