<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Question Bank & Assessment Admin — specs/question-bank (design.md §2).
 *
 * Promotes assessment questions out of config/assessment-questions.php into a versioned,
 * admin-managed bank. Admin-only (Craig) manages everything; no clinical-approver role
 * (Captain Zan decision 2026-11-03). Front end renders only what the AssessmentService returns.
 *
 * Tables:
 *   assessment_questions          — the bank: one row per question (stable key + live-version pointer).
 *   assessment_question_versions  — immutable version history (text/options/branching per version).
 *   assessment_categories         — a category manifest (name + status + manifest_version).
 *   assessment_category_questions — ordered category→question links (category-scope questions only).
 *
 * Status strings (no DB enum, matching repo convention): draft | in_review | live | retired.
 * Type strings: single | multi | text | textarea | number | date | select | upload.
 * Driver-agnostic (json not jsonb).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── the bank: one row per question ────────────────────────────────────────────────────────
        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question_key')->unique();   // stable code e.g. CORE_ALLERGIES, WL_PREV_MED
            $table->string('scope', 16)->default('category'); // core | category
            $table->boolean('clinical')->default(false);
            $table->unsignedInteger('core_order')->nullable(); // fixed order for scope=core
            $table->string('status', 16)->default('draft');    // draft | in_review | live | retired
            $table->unsignedBigInteger('current_version_id')->nullable(); // → assessment_question_versions
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('scope');
            $table->index('status');
        });

        // ── immutable version history ───────────────────────────────────────────────────────────────
        Schema::create('assessment_question_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('text');                    // patient-facing question
            $table->string('helper_text')->nullable(); // grey line under the question
            $table->string('why_we_ask')->nullable();  // expandable one-liner
            $table->string('type', 16)->default('single');
            $table->json('options')->nullable();       // [ {code, label, flag?} ]
            $table->string('icon')->nullable();
            $table->json('branch_rules')->nullable();  // { depends_on_key: [option_code,...] }  (show_if)
            $table->json('flag_rule')->nullable();     // which answers raise a "Needs attention" flag
            $table->string('profile_card')->nullable(); // phase-2 placement hint
            $table->boolean('required')->default(false);
            $table->string('placeholder')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();      // why this version was published (changelog)
            $table->timestamps();

            $table->unique(['question_id', 'version']);
        });

        // current_version_id FK added after versions table exists
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')->on('assessment_question_versions')->nullOnDelete();
        });

        // ── a category is a manifest ──────────────────────────────────────────────────────────────
        Schema::create('assessment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // matches treatment slug e.g. weight-loss
            $table->string('name');
            $table->string('status', 16)->default('draft'); // draft | in_review | live | retired
            $table->unsignedInteger('manifest_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        // ── ordered category → question links (category-scope only; CORE is resolved at assembly) ───
        Schema::create('assessment_category_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('assessment_categories')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'question_id']); // blocks duplicate add (US-10)
            $table->index(['category_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('assessment_category_questions');
        Schema::dropIfExists('assessment_categories');
        Schema::dropIfExists('assessment_question_versions');
        Schema::dropIfExists('assessment_questions');
    }
};
