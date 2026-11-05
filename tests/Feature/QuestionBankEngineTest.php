<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestion;
use App\Models\User;
use App\Services\Assessments\AssessmentService;
use App\Services\Assessments\QuestionBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 — the assessment engine (AssessmentService + QuestionBankService).
 * Covers: flow order (CORE → category → free-text), legacy shape + show_if parity,
 * manifest-version pinning, per-answer version/timestamp/flag, admin rules (no-delete,
 * duplicate guard, CORE-in-category guard).
 */
class QuestionBankEngineTest extends TestCase
{
    use RefreshDatabase;

    private QuestionBankService $bank;
    private AssessmentService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = app(QuestionBankService::class);
        $this->svc = app(AssessmentService::class);
    }

    private function liveCore(string $key, int $order, array $v = [], ?string $profileCard = null): AssessmentQuestion
    {
        $q = $this->bank->createQuestion(
            ['scope' => 'core', 'question_key' => $key, 'core_order' => $order],
            array_merge(['text' => $key.' text', 'type' => 'text', 'profile_card' => $profileCard], $v),
        );
        $this->bank->publish($q, $q->currentVersion, 'seed');
        return $q->refresh();
    }

    private function liveCategoryQuestion(string $key, array $v = []): AssessmentQuestion
    {
        $q = $this->bank->createQuestion(
            ['scope' => 'category', 'question_key' => $key],
            array_merge(['text' => $key.' text', 'type' => 'single'], $v),
        );
        $this->bank->publish($q, $q->currentVersion, 'seed');
        return $q->refresh();
    }

    public function test_flow_order_is_core_then_category_then_free_text_tail(): void
    {
        $this->liveCore('CORE_MEDS', 1);
        $this->liveCore('CORE_ALLERGIES', 2);
        // free-text tail marked by profile_card = patient_notes
        $this->liveCore('CORE_NOTES', 99, ['type' => 'textarea'], 'patient_notes');

        $cat = $this->bank->createCategory('weight-loss', 'Weight Loss');
        $this->bank->publishCategory($cat);
        $q1 = $this->liveCategoryQuestion('WL_PREV_MED');
        $q2 = $this->liveCategoryQuestion('WL_DURATION');
        $this->bank->addQuestionToCategory($cat->refresh(), $q1);
        $this->bank->addQuestionToCategory($cat->refresh(), $q2);

        $ids = array_column($this->svc->flowFor('weight-loss'), 'id');

        $this->assertSame(
            ['CORE_MEDS', 'CORE_ALLERGIES', 'WL_PREV_MED', 'WL_DURATION', 'CORE_NOTES'],
            $ids,
            'CORE first, category middle, free-text note last.'
        );
    }

    public function test_flow_maps_to_legacy_shape_with_show_if(): void
    {
        $cat = $this->bank->createCategory('skincare', 'Skincare');
        $this->bank->publishCategory($cat);

        $parent = $this->liveCategoryQuestion('ACNE_PREV', [
            'type' => 'single',
            'options' => [['code' => 'yes', 'label' => 'Yes'], ['code' => 'no', 'label' => 'No']],
        ]);
        $child = $this->liveCategoryQuestion('ACNE_PREV_DETAIL', [
            'type' => 'text',
            'branch_rules' => ['ACNE_PREV' => ['yes']],
            'required' => true,
        ]);
        $this->bank->addQuestionToCategory($cat->refresh(), $parent);
        $this->bank->addQuestionToCategory($cat->refresh(), $child);

        $flow = collect($this->svc->flowFor('skincare'))->keyBy('id');

        // legacy type mapping: single → radio
        $this->assertSame('radio', $flow['ACNE_PREV']['type']);
        // options flattened to labels
        $this->assertSame(['Yes', 'No'], $flow['ACNE_PREV']['options']);
        // branch_rules → show_if first-code, matching current single-value behaviour
        $this->assertSame(['ACNE_PREV' => 'yes'], $flow['ACNE_PREV_DETAIL']['show_if']);
        $this->assertTrue($flow['ACNE_PREV_DETAIL']['required']);
    }

    public function test_draft_category_does_not_appear_in_flow(): void
    {
        $cat = $this->bank->createCategory('draft-cat', 'Draft Cat'); // not published
        $q = $this->liveCategoryQuestion('DC_Q1');
        $this->bank->addQuestionToCategory($cat->refresh(), $q);

        $this->assertSame([], $this->svc->flowFor('draft-cat'));
    }

    public function test_manifest_version_bumps_on_change_and_can_be_pinned(): void
    {
        $cat = $this->bank->createCategory('uti', 'UTI');
        $this->bank->publishCategory($cat);
        $this->assertSame(1, $cat->refresh()->manifest_version);

        $q = $this->liveCategoryQuestion('UTI_SYMPTOMS');
        $this->bank->addQuestionToCategory($cat->refresh(), $q);

        $this->assertSame(2, $this->svc->manifestVersionFor('uti'));
    }

    public function test_save_answer_stores_version_timestamp_and_flag(): void
    {
        $user = User::factory()->create();
        $q = $this->liveCategoryQuestion('ED_NITRATES', [
            'type' => 'single',
            'options' => [['code' => 'yes', 'label' => 'Yes'], ['code' => 'no', 'label' => 'No']],
            'flag_rule' => ['values' => ['Yes']], // a Yes is a contraindication → flag it
        ]);

        $assessment = Assessment::create([
            'user_id' => $user->id,
            'treatment_slug' => 'erectile-dysfunction-treatment',
            'treatment_name' => 'ED',
            'answers' => [], // legacy blob (NOT NULL); question-bank answers go to assessment_answers
            'status' => 'in_progress',
        ]);

        $flagged = $this->svc->saveAnswer($assessment, 'ED_NITRATES', 'Yes');
        $notFlagged = $this->svc->saveAnswer($assessment, 'ED_NITRATES', 'No');

        $this->assertSame(1, $flagged->question_version);
        $this->assertNotNull($flagged->answered_at);
        $this->assertTrue($flagged->flagged, 'Yes to nitrates must flag for the doctor.');
        $this->assertFalse($notFlagged->flagged);
        $this->assertSame('Yes', $flagged->fresh()->answer, 'answer round-trips through encryption.');
    }

    public function test_editing_creates_new_version_and_does_not_move_live_pointer_until_publish(): void
    {
        $q = $this->liveCategoryQuestion('GP_REASON', ['text' => 'Original']);
        $liveVersionId = $q->current_version_id;

        $draft = $this->bank->editQuestion($q, ['text' => 'Edited wording', 'type' => 'text']);

        $this->assertSame(2, $draft->version);
        $this->assertSame($liveVersionId, $q->refresh()->current_version_id, 'live pointer unchanged before publish');
        $this->assertSame('draft', $q->status);

        $this->bank->publish($q, $draft, 'reword');
        $this->assertSame($draft->id, $q->refresh()->current_version_id);
        $this->assertSame('Edited wording', $q->currentVersion->text);
    }

    public function test_adding_core_question_to_category_is_blocked(): void
    {
        $core = $this->liveCore('CORE_ALLERGIES', 1);
        $cat = $this->bank->createCategory('thrush', 'Thrush');

        $this->expectException(\RuntimeException::class);
        $this->bank->addQuestionToCategory($cat, $core);
    }

    public function test_adding_same_question_twice_is_blocked(): void
    {
        $cat = $this->bank->createCategory('hair-loss', 'Hair Loss');
        $q = $this->liveCategoryQuestion('HL_DURATION');
        $this->bank->addQuestionToCategory($cat->refresh(), $q);

        $this->expectException(\RuntimeException::class);
        $this->bank->addQuestionToCategory($cat->refresh(), $q);
    }

    public function test_questions_are_retired_not_deleted(): void
    {
        $q = $this->liveCategoryQuestion('OLD_Q');
        $this->bank->retire($q);

        $this->assertSame('retired', $q->refresh()->status);
        $this->assertDatabaseHas('assessment_questions', ['question_key' => 'OLD_Q']); // still there
    }
}
