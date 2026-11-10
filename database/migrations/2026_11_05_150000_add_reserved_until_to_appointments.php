<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charge-at-booking: a slot is RESERVED on an appointment while the patient completes payment.
 * If payment isn't confirmed before reserved_until, a scheduled sweep releases it (status=expired).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('reserved_until')->nullable()->after('is_paid');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('reserved_until');
        });
    }
};
