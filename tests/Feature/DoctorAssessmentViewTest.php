<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Doctor\ConsultationScreen;
use App\Models\Assessment;
use App\Models\User;
use App\Services\Assessments\AssessmentService;
use App\Services\Assessments\QuestionBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 — doctor's assessment view (US-16 grouping + timestamps, US-17 needs-attention panel).
 * Exercises the getAssessmentViewProperty() computed accessor directly over per-answer rows.
 */
class DoctorAssessmentViewTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessmentWithAnswers(): Assessment
    {
        $bank = app(QuestionBankService::class);
        $svc = app(AssessmentService::class);

        $cat = $bank->createCategory('weight-loss', 'Weight Loss');
        $bank->publishCategory($cat);

        $allergy = $bank->createQuestion(
            ['scope' => 'category', 'question_key' => 'WL_ALLERGIES', 'clinical' => true],
            ['text' => 'Any medication allergies?', 'type' => 'text'],
        );
        $bank->publish($allergy, $allergy->currentVersion, 'seed');

        $nitrates = $bank->createQuestion(
            ['scope' => 'category', 'question_key' => 'WL_NITRATES', 'clinical' => true],
            [
                'text' => 'Do you take nitrates?',
                'type' => 'single',
                'options' => [['code' => 'yes', 'label' => 'Yes'], ['code' => 'no', 'label' => 'No']],
                'flag_rule' => ['values' => ['Yes']],
            ],
        );
        $bank->publish($nitrates, $nitrates->currentVersion, 'seed');

        $patient = User::factory()->create(['role' => UserRole::Patient]);
        $assessment = Assessment::create([
            'user_id' => $patient->id,
            'treatment_slug' => 'weight-loss',
            'treatment_name' => 'Weight Loss',
            'answers' => [],
            'status' => 'completed',
        ]);

        $svc->saveAnswer($assessment, 'WL_ALLERGIES', 'Penicillin'); // free-text → needs attention
        $svc->saveAnswer($assessment, 'WL_NITRATES', 'Yes');         // flagged → needs attention

        return $assessment;
    }

    public function test_doctor_view_groups_answers_flags_and_timestamps(): void
    {
        $assessment = $this->makeAssessmentWithAnswers();

        $component = new ConsultationScreen();
        $component->assessment = $assessment->fresh();

        $view = $component->getAssessmentViewProperty();

        $this->assertTrue($view['has']);
        $this->assertSame('Weight Loss', $view['treatment']);

        // grouped: allergies in its own section, nitrates under medical history / assessment
        $this->assertArrayHasKey('Allergies', $view['sections']);

        // needs attention has BOTH the flagged nitrates answer and the free-text allergy answer
        $reasons = array_column($view['needsAttention'], 'reason');
        $this->assertContains('flagged', $reasons);
        $this->assertContains('free-text', $reasons);

        // per-answer timestamps present
        $allergyItem = collect($view['sections']['Allergies'])->first();
        $this->assertNotNull($allergyItem['at']);
    }

    public function test_doctor_view_falls_back_to_legacy_blob(): void
    {
        $patient = User::factory()->create(['role' => UserRole::Patient]);
        $assessment = Assessment::create([
            'user_id' => $patient->id,
            'treatment_slug' => 'acne-treatment',
            'treatment_name' => 'Acne',
            'answers' => [
                ['question' => 'How long have you had acne?', 'answer' => '1-3 years', 'type' => 'select'],
                ['question' => 'Anything else for your doctor?', 'answer' => 'Worse in winter', 'type' => 'textarea'],
            ],
            'status' => 'completed',
        ]);

        $component = new ConsultationScreen();
        $component->assessment = $assessment;

        $view = $component->getAssessmentViewProperty();

        $this->assertTrue($view['has']);
        // the free-text blob answer surfaces in needs-attention
        $this->assertContains('free-text', array_column($view['needsAttention'], 'reason'));
    }
}
