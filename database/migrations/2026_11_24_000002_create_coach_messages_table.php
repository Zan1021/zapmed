<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** In-app thread between a member (patient_id) and their coach. One row per message. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from', 8);                 // patient | coach
            $table->text('body');
            $table->timestamp('read_at')->nullable();  // read by the other side
            $table->timestamps();
            $table->index(['patient_id', 'id']);
            $table->index(['patient_id', 'from', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_messages');
    }
};