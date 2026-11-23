<?php

namespace Tests\Feature;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestion;
use App\Services\Assessments\AssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Craig's final 14-question weight-loss assessment (26 Nov handoff) lives in the question bank, not in code.
 */
class CraigWeightLossQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_publishes_craigs_14_screens_live(): void
    {
        $this->artisan('questionbank:import-craig-weight-loss')->assertSuccessful();

        $flow = app(AssessmentService::class)->flowFor('weight-loss');
        $ids = array_column($flow, 'id');

        $this->assertSame('CWL_CONDITIONS', $ids[0]);
        $this->assertSame('CWL_FINAL_NOTES', end($ids));

        // 14 screens = questions that do NOT share the previous question's screen
        $screens = array_filter($flow, fn ($q) => ($q['layout'] ?? null) !== 'with_previous');
        $this->assertCount(14, $screens);

        $byId = collect($flow)->keyBy('id');
        $this->assertSame('medicines', $byId['CWL_MEDICINES']['type']);
        $this->assertSame('measurements', $byId['CWL_MEASUREMENTS']['type']);
        $this->assertSame('Your doctor needs your health history to make an informed assessment.', $byId['CWL_CONDITIONS']['why_we_ask']);
        $this->assertSame('Personal and family history', $byId['CWL_HISTORY_PERSONAL']['screen_title']);
        $this->assertSame('with_previous', $byId['CWL_HISTORY_FAMILY']['layout']);
    }

    public function test_follow_ups_are_conditional(): void
    {
        $this->artisan('questionbank:import-craig-weight-loss')->assertSuccessful();
        $byId = collect(app(AssessmentService::class)->flowFor('weight-loss'))->keyBy('id');
        $this->assertTrue(AssessmentService::branchMatches($byId['CWL_CONDITIONS_DETAIL']['show_if']['CWL_CONDITIONS'], 'Yes'));
        $this->assertFalse(AssessmentService::branchMatches($byId['CWL_CONDITIONS_DETAIL']['show_if']['CWL_CONDITIONS'], 'No'));

        // allergies detail shows for "Yes" and "I'm not sure", not for "No"
        $rule = $byId['CWL_ALLERGIES_DETAIL']['show_if']['CWL_ALLERGIES'];
        $this->assertTrue(AssessmentService::branchMatches($rule, 'Yes'));
        $this->assertTrue(AssessmentService::branchMatches($rule, "I'm not sure"));
        $this->assertFalse(AssessmentService::branchMatches($rule, 'No'));

        // "still using" only appears once past medication = Yes; "why stopped" only when still using = No
        $this->assertTrue(AssessmentService::branchMatches($byId['CWL_PAST_STILL_USING']['show_if']['CWL_PAST_MEDICATION'], 'Yes'));
        $this->assertTrue(AssessmentService::branchMatches($byId['CWL_PAST_STOPPED_WHY']['show_if']['CWL_PAST_STILL_USING'], 'No'));
        $this->assertFalse(AssessmentService::branchMatches($byId['CWL_PAST_STOPPED_WHY']['show_if']['CWL_PAST_STILL_USING'], 'Yes'));
    }

    public function test_import_is_idempotent_and_retires_the_old_questions(): void
    {
        // an old June-docx style weight-loss question that is no longer wanted
        $category = AssessmentCategory::create(['slug' => 'weight-loss', 'name' => 'Weight Loss', 'status' => 'live', 'manifest_version' => 1]);
        $old = AssessmentQuestion::create(['question_key' => 'OLD_WL_Q1', 'scope' => 'category', 'status' => 'live']);
        $category->questionLinks()->create(['question_id' => $old->id, 'position' => 1]);

        $this->artisan('questionbank:import-craig-weight-loss')->assertSuccessful();
        $versionsAfterFirst = \App\Models\AssessmentQuestionVersion::count();
        $this->artisan('questionbank:import-craig-weight-loss')->assertSuccessful();

        $this->assertSame($versionsAfterFirst, \App\Models\AssessmentQuestionVersion::count(), 'second run must not create new versions');
        $this->assertSame('retired', $old->fresh()->status);
        $this->assertNotContains('OLD_WL_Q1', array_column(app(AssessmentService::class)->flowFor('weight-loss'), 'id'));
    }

    public function test_risk_answers_raise_a_needs_attention_flag(): void
    {
        $this->artisan('questionbank:import-craig-weight-loss')->assertSuccessful();

        $assessment = \App\Models\Assessment::create([
            'user_id' => \App\Models\User::factory()->create()->id,
            'treatment_slug' => 'weight-loss',
            'treatment_name' => 'Weight Loss',
            'answers' => [],
            'status' => 'completed',
        ]);
        $svc = app(AssessmentService::class);

        $svc->saveAnswer($assessment, 'CWL_CHECKLIST', ['Pancreatitis']);
        $svc->saveAnswer($assessment, 'CWL_PREGNANCY', 'No');

        $flags = AssessmentAnswer::where('assessment_id', $assessment->id)->pluck('flagged', 'question_key');
        $this->assertTrue((bool) $flags['CWL_CHECKLIST']);
        $this->assertFalse((bool) $flags['CWL_PREGNANCY']);
    }
}
