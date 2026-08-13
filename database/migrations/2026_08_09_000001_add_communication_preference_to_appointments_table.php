<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('communication_preference', 20)->default('video')->after('reason');
            // video = full video call
            // audio = audio-only call (camera off)
            // text = text/chat consultation only
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('communication_preference');
        });
    }
};
