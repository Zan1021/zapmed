<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calls: slot_key = "coach_id:UTC start" while a call is booked (NULL when cancelled), UNIQUE, so two members can never
 * hold the same coach slot even if they confirm at the same moment. purpose and reminded_at support welcome calls and
 * the reminder an hour before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_calls', function (Blueprint $table) {
            $table->string('slot_key', 40)->nullable()->unique();
            $table->string('purpose', 20)->nullable();
            $table->timestamp('reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('coach_calls', function (Blueprint $table) {
            $table->dropUnique(['slot_key']);
            $table->dropColumn(['slot_key', 'purpose', 'reminded_at']);
        });
    }
};