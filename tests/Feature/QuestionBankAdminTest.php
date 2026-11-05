<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\CategoryBuilder;
use App\Livewire\Admin\QuestionLibrary;
use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestion;
use App\Models\User;
use App\Services\Assessments\AssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5 — admin portal (Question Library + Category Builder). Task 5.5.
 * Covers the guard rails + US-12 (new category end-to-end via the portal, no code).
 */
class QuestionBankAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'email_verified_at' => now()]);
    }

    private function doctor(): User
    {
        return User::factory()->create(['role' => UserRole::Doctor, 'email_verified_at' => now()]);
    }

    public function test_doctors_cannot_reach_the_question_bank(): void
    {
        $this->actingAs($this->doctor())
            ->get('/admin/question-bank')
            ->assertForbidden();

        $this->actingAs($this->doctor())
            ->get('/admin/question-bank/categories')
            ->assertForbidden();
    }

    public function test_admin_can_reach_the_question_bank(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/question-bank')
            ->assertOk();
    }

    public function test_library_creates_a_draft_question(): void
    {
        Livewire::actingAs($this->admin())
            ->test(QuestionLibrary::class)
            ->set('text', 'Do you have any allergies?')
            ->set('type', 'single')
            ->set('scope', 'core')
            ->set('optionsRaw', "Yes\nNo")
            ->call('save');

        $q = AssessmentQuestion::where('scope', 'core')->first();
        $this->assertNotNull($q);
        $this->assertSame('draft', $q->status);
        $this->assertSame('Do you have any allergies?', $q->currentVersion->text);
    }

    public function test_retire_does_not_delete(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(QuestionLibrary::class)
            ->set('text', 'Temp question')->set('type', 'text')->set('scope', 'category')->call('save');

        $q = AssessmentQuestion::first();
        Livewire::actingAs($admin)->test(QuestionLibrary::class)->call('retire', $q->id);

        $this->assertSame('retired', $q->fresh()->status);
        $this->assertDatabaseHas('assessment_questions', ['id' => $q->id]); // still present
    }

    public function test_duplicate_question_in_category_shows_error_not_crash(): void
    {
        $admin = $this->admin();
        $bank = app(\App\Services\Assessments\QuestionBankService::class);

        $cat = $bank->createCategory('weight-loss', 'Weight Loss', $admin);
        $q = $bank->createQuestion(['scope' => 'category', 'question_key' => 'WL_Q'], ['text' => 'Q', 'type' => 'text'], $admin);
        $bank->addQuestionToCategory($cat, $q, $admin);

        $component = Livewire::actingAs($admin)->test(CategoryBuilder::class, ['slug' => 'weight-loss'])
            ->call('addQuestion', $q->id); // already in → guard

        $this->assertStringContainsString('already in this category', $component->get('error'));
    }

    public function test_mid_flow_edit_does_not_change_an_in_progress_assessment(): void
    {
        $admin = $this->admin();
        $bank = app(\App\Services\Assessments\QuestionBankService::class);
        $svc = app(AssessmentService::class);

        $cat = $bank->createCategory('weight-loss', 'Weight Loss', $admin);
        $bank->publishCategory($cat, $admin);
        $q = $bank->createQuestion(['scope' => 'category', 'question_key' => 'WL_Q'], ['text' => 'Original', 'type' => 'text'], $admin);
        $bank->publish($q, $q->currentVersion, 'seed', $admin);
        $bank->addQuestionToCategory($cat->refresh(), $q, $admin);

        // an assessment starts now and pins whatever the current manifest version is
        $pinnedManifest = $svc->manifestVersionFor('weight-loss');
        $assessment = \App\Models\Assessment::create([
            'user_id' => User::factory()->create()->id,
            'treatment_slug' => 'weight-loss',
            'treatment_name' => 'Weight Loss',
            'category_id' => $svc->categoryIdFor('weight-loss'),
            'manifest_version' => $pinnedManifest,
            'answers' => [],
            'status' => 'in_progress',
        ]);

        // admin edits a question AND adds another after the assessment started
        $bank->editQuestion($q, ['text' => 'Edited wording', 'type' => 'text'], $admin);
        $q2 = $bank->createQuestion(['scope' => 'category', 'question_key' => 'WL_Q2'], ['text' => 'New Q', 'type' => 'text'], $admin);
        $bank->addQuestionToCategory($cat->refresh(), $q2, $admin); // bumps the live manifest

        // the in-progress assessment still carries its ORIGINAL pinned manifest version (US-3)
        $this->assertSame($pinnedManifest, $assessment->fresh()->manifest_version);
        // and the live manifest has moved on for NEW assessments
        $this->assertGreaterThan($pinnedManifest, $svc->manifestVersionFor('weight-loss'));
    }

    /**
     * US-12 — THE acceptance test: an admin builds + publishes a brand-new category entirely through
     * the portal (no code, no deploy), and the patient assessment service then serves it.
     */
    public function test_admin_builds_a_new_category_end_to_end_through_the_portal(): void
    {
        $admin = $this->admin();

        // 1) create two questions in the library
        Livewire::actingAs($admin)->test(QuestionLibrary::class)
            ->set('text', 'How severe are your symptoms?')->set('type', 'select')
            ->set('scope', 'category')->set('optionsRaw', "Mild\nModerate\nSevere")->call('save');
        Livewire::actingAs($admin)->test(QuestionLibrary::class)
            ->set('text', 'How long have you had them?')->set('type', 'text')
            ->set('scope', 'category')->call('save');

        // publish them live
        foreach (AssessmentQuestion::all() as $q) {
            app(\App\Services\Assessments\QuestionBankService::class)
                ->publish($q, $q->currentVersion, 'go live', $admin);
        }

        // 2) create a new category via the builder
        $builder = Livewire::actingAs($admin)->test(CategoryBuilder::class)
            ->set('newSlug', 'allergy-relief')
            ->set('newName', 'Allergy Relief')
            ->call('createCategory');

        $cat = AssessmentCategory::where('slug', 'allergy-relief')->first();
        $this->assertNotNull($cat);

        // 3) add both questions + publish the category
        foreach (AssessmentQuestion::all() as $q) {
            $builder->call('addQuestion', $q->id);
        }
        $builder->call('publish');

        // 4) the patient assessment service now serves the new category — WITHOUT any code change
        config()->set('questionbank.enabled_slugs', ['allergy-relief']);
        $flow = app(AssessmentService::class)->flowFor('allergy-relief');

        $this->assertCount(2, $flow);
        $this->assertSame('live', $cat->fresh()->status);
    }
}
