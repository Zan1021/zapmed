<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Craig's weight-loss journey (26 Nov handoff) shows follow-up questions and grouped questions on the
 * SAME screen as their parent (e.g. "Yes" -> a text box under it; "personal" + "family" history together).
 *
 *  - layout       : null (own screen) | 'with_previous' (rendered on the same screen as the question above)
 *  - screen_title : optional heading for a grouped screen (the individual labels become sub-headings)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('assessment_question_versions', function (Blueprint $table) {
            $table->string('layout', 24)->nullable()->after('type');
            $table->string('screen_title')->nullable()->after('layout');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_question_versions', function (Blueprint $table) {
            $table->dropColumn(['layout', 'screen_title']);
        });
    }
};
