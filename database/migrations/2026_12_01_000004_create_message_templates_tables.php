<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Message wording Operations can change (Craig's guide 6.4, 8.3). A row replaces the built-in text for that message key and
 * can switch the message off; no row = the built-in text. Every save keeps the old wording in message_template_versions and
 * the version number goes into the notification log so a message can be traced to the exact wording that was sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('subject', 120);
            $table->text('body');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('message_template_versions', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 60);
            $table->unsignedInteger('version');
            $table->string('subject', 120)->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->string('change', 12);                      // saved | reset
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['template_key', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_template_versions');
        Schema::dropIfExists('message_templates');
    }
};
