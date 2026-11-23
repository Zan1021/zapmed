<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Craig's weight-loss journey (26 Nov handoff) logs every consent with its exact wording + version,
 * and asks how the patient wants booking messages (WhatsApp or email).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('consent_records', function (Blueprint $table) {
            $table->text('wording')->nullable()->after('version');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_contact', 16)->nullable()->after('phone'); // whatsapp | email
        });
    }

    public function down(): void
    {
        Schema::table('consent_records', fn (Blueprint $t) => $t->dropColumn('wording'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('preferred_contact'));
    }
};
