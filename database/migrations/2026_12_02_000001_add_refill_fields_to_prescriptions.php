<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A repeat (refill) that has been paid is its own prescription row: a copy of the signed one, marked with which prescription it
 * repeats and which repeat number it is. That way the pharmacy log, retries and the "never send twice" rules apply to it as they
 * do to any prescription. (refill_of_id, refill_no) is unique, so one repeat can never be created twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('refill_of_id')->nullable()->after('consultation_id')->constrained('prescriptions')->nullOnDelete();
            $table->unsignedSmallInteger('refill_no')->nullable()->after('refill_of_id');
            $table->unique(['refill_of_id', 'refill_no']);
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropUnique(['refill_of_id', 'refill_no']);
            $table->dropConstrainedForeignId('refill_of_id');
            $table->dropColumn('refill_no');
        });
    }
};