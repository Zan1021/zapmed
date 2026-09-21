<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Health Coach v1 (spec FR-2). A message in a conversation. `body` is TEXT
 * because it is ENCRYPTED at rest (EncryptsSensitiveFields) — it may carry
 * health context (NFR-5). Encrypted columns cannot be queried with where();
 * always scope by spar_conversation_id.
 *
 * Staff authorship is stored as a plain id + snapshot name + role string, never
 * a host User FK (package purity, NFR-1 / AC-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spar_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spar_conversation_id')->constrained('spar_conversations')->cascadeOnDelete();
            $table->string('direction'); // from_patient | from_staff | system
            $table->string('kind')->default('text'); // text | product_suggestion | system
            $table->text('body'); // encrypted
            $table->unsignedBigInteger('author_id')->nullable(); // host staff id (from_staff only)
            $table->string('author_name')->nullable(); // snapshot
            $table->string('author_role')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['spar_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_messages');
    }
};
