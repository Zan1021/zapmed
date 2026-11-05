<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Question Bank — per-answer storage + assessment version pinning (design.md §2, US-3, US-18).
 *
 * - assessments gains category_id + manifest_version (pinned at start so questions don't change
 *   under a patient mid-flow).
 * - assessment_answers: ONE row per answered question, each carrying the question_version it answered,
 *   its own timestamp, and a computed `flagged` bit. This is the phase-2 cross-consult enabler:
 *   the health-profile timeline becomes a view over existing rows with no later migration.
 *
 * The legacy assessments.answers encrypted blob is LEFT IN PLACE (read-only for historical rows);
 * new assessments write assessment_answers. `answer` is encrypted at the model layer (encrypted cast),
 * matching the EncryptsSensitiveFields / assessments.answers pattern — so the column is plain text here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('treatment_name')
                ->constrained('assessment_categories')->nullOnDelete();
            $table->unsignedInteger('manifest_version')->nullable()->after('category_id');
        });

        // Legacy blob becomes nullable: question-bank assessments store answers in assessment_answers,
        // not the single encrypted blob. Historical rows keep their blob. (SQLite needs a rebuild to
        // change nullability; guarded so the migration is driver-safe.)
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('assessments', function (Blueprint $table) {
                $table->json('answers')->nullable()->change();
            });
        }

        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('assessment_questions')->nullOnDelete();
            $table->string('question_key');            // denormalised for stable reference if retired
            $table->unsignedInteger('question_version')->default(1);
            $table->text('answer')->nullable();        // encrypted at model layer (encrypted cast)
            $table->boolean('flagged')->default(false); // computed from version flag_rule at save
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['assessment_id']);
            $table->index(['question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('manifest_version');
        });
    }
};
