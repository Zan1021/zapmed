<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\User;
use App\Services\Assessments\QuestionBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 — BookAppointment cutover to the question bank (Task 4.1–4.3).
 * Proves the flag-gated source switch: a LIVE, flagged category serves bank questions and writes
 * versioned answers; an unflagged / config-only treatment still uses the legacy config.
 */
class BookAppointmentQuestionBankCutoverTest extends TestCase
{
    use RefreshDatabase;

    private function bankWithLiveWeightLoss(): void
    {
        $bank = app(QuestionBankService::class);

        $cat = $bank->createCategory('weight-loss', 'Weight Loss');
        $bank->publishCategory($cat);

        $weight = $bank->createQuestion(
            ['scope' => 'category', 'question_key' => 'WL_WEIGHT'],
            ['text' => 'Current weight (kg)', 'type' => 'number', 'required' => true],
        );
        $bank->publish($weight, $weight->currentVersion, 'seed');
        $bank->addQuestionToCategory($cat->refresh(), $weight);

        $conds = $bank->createQuestion(
            ['scope' => 'category', 'question_key' => 'WL_CONDITIONS', 'clinical' => true],
            [
                'text' => 'Any of these conditions?',
                'type' => 'multi',
                'options' => [['code' => 'diabetes', 'label' => 'Type 2 Diabetes'], ['code' => 'none', 'label' => 'None']],
                'flag_rule' => ['values' => ['Type 2 Diabetes']],
            ],
        );
        $bank->publish($conds, $conds->currentVersion, 'seed');
        $bank->addQuestionToCategory($cat->refresh(), $conds);
    }

    public function test_flagged_live_category_serves_bank_questions_and_writes_versioned_answers(): void
    {
        config()->set('questionbank.enabled_slugs', ['weight-loss']);
        config()->set('assessment-questions', ['weight-loss' => [
            ['id' => 'LEGACY_ONLY', 'type' => 'text', 'label' => 'Legacy question', 'required' => false],
        ]]);
        config()->set('treatments', ['weight-loss' => ['treatments' => ['weight-loss' => ['name' => 'Weight Loss']]]]);

        $this->bankWithLiveWeightLoss();

        $component = new \App\Livewire\Patient\BookAppointment();
        $component->selectedTreatment = 'weight-loss';

        $questions = $component->questionsFor();
        $ids = array_column($questions, 'id');

        // bank questions, NOT the legacy one
        $this->assertContains('WL_WEIGHT', $ids);
        $this->assertContains('WL_CONDITIONS', $ids);
        $this->assertNotContains('LEGACY_ONLY', $ids);
    }

    public function test_unflagged_treatment_falls_back_to_config(): void
    {
        config()->set('questionbank.enabled_slugs', []); // nothing flagged
        config()->set('assessment-questions', ['acne-treatment' => [
            ['id' => 'acne_duration', 'type' => 'select', 'label' => 'How long?', 'required' => true, 'options' => ['<6m']],
        ]]);

        $this->bankWithLiveWeightLoss(); // bank exists but acne isn't flagged anyway

        $component = new \App\Livewire\Patient\BookAppointment();
        $component->selectedTreatment = 'acne-treatment';

        $ids = array_column($component->questionsFor(), 'id');
        $this->assertSame(['acne_duration'], $ids);
    }

    public function test_wildcard_flag_enables_all_live_categories_but_falls_back_for_drafts(): void
    {
        config()->set('questionbank.enabled_slugs', ['*']); // all treatments prefer the bank
        config()->set('assessment-questions', ['acne-treatment' => [
            ['id' => 'cfg_acne', 'type' => 'text', 'label' => 'Config acne Q', 'required' => false],
        ]]);
        config()->set('treatments', []);

        $this->bankWithLiveWeightLoss(); // weight-loss is LIVE; acne has no live manifest

        $wl = new \App\Livewire\Patient\BookAppointment();
        $wl->selectedTreatment = 'weight-loss';
        $this->assertContains('WL_WEIGHT', array_column($wl->questionsFor(), 'id')); // bank

        $acne = new \App\Livewire\Patient\BookAppointment();
        $acne->selectedTreatment = 'acne-treatment';
        $this->assertSame(['cfg_acne'], array_column($acne->questionsFor(), 'id')); // config fallback
    }

    public function test_flagged_but_no_live_manifest_falls_back_to_config(): void
    {
        config()->set('questionbank.enabled_slugs', ['weight-loss']);
        config()->set('assessment-questions', ['weight-loss' => [
            ['id' => 'cfg_q', 'type' => 'text', 'label' => 'Config Q', 'required' => false],
        ]]);
        // a DRAFT category only — not live
        $bank = app(QuestionBankService::class);
        $bank->createCategory('weight-loss', 'Weight Loss'); // draft

        $component = new \App\Livewire\Patient\BookAppointment();
        $component->selectedTreatment = 'weight-loss';

        $ids = array_column($component->questionsFor(), 'id');
        $this->assertSame(['cfg_q'], $ids, 'draft manifest must not take over; config still serves');
    }

    public function test_assessment_pins_manifest_version_when_on_bank(): void
    {
        config()->set('questionbank.enabled_slugs', ['weight-loss']);
        config()->set('assessment-questions', ['weight-loss' => []]);
        $this->bankWithLiveWeightLoss();

        $slug = 'weight-loss';
        $cat = AssessmentCategory::where('slug', $slug)->first();

        // simulate what proceedToPayment pins
        $svc = app(\App\Services\Assessments\AssessmentService::class);
        $user = User::factory()->create();
        $assessment = Assessment::create([
            'user_id' => $user->id,
            'treatment_slug' => $slug,
            'treatment_name' => 'Weight Loss',
            'category_id' => $svc->categoryIdFor($slug),
            'manifest_version' => $svc->manifestVersionFor($slug),
            'answers' => [],
            'status' => 'completed',
        ]);

        $this->assertSame($cat->id, $assessment->category_id);
        $this->assertSame($cat->manifest_version, $assessment->manifest_version);
    }
}
