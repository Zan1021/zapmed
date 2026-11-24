<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking links (Craig's guide 6.2, 8.2): a link for ONE patient that opens the follow-up booking page, with a limit on how
 * many times and for how long it works. The link itself is shown once when it is made; only a hash of it is stored, so a leaked
 * database does not leak working links. The patient must still be signed in as themselves: the link only takes them to the page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_links', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('follow_up_request_id')->nullable()->constrained('follow_up_requests')->nullOnDelete();
            $table->unsignedSmallInteger('max_uses');
            $table->unsignedSmallInteger('uses')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_links');
    }
};
