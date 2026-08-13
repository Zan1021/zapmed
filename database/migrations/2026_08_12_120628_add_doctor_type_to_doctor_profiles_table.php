<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->string('doctor_type', 20)->default('full_time')->after('user_id'); // full_time or locum
            $table->decimal('revenue_share_percent', 5, 2)->nullable()->after('followup_fee'); // for locum doctors (e.g. 70.00 = 70%)
            $table->boolean('can_manage_own_schedule')->default(false)->after('available_to'); // locums manage their own slots
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->dropColumn(['doctor_type', 'revenue_share_percent', 'can_manage_own_schedule']);
        });
    }
};
