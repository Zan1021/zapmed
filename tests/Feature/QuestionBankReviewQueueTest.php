<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\ReviewQueue;
use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestion;
use App\Models\User;
use App\Services\Assessments\QuestionBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 9 — the clinical Review Queue (Dr Anke's sign-off tool). ADMIN-ONLY.
 */
class QuestionBankReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'email_verified_at' => now()]);
    }

    /** a draft category with two draft questions */
    private function draftCategory(): AssessmentCategory
    {
        $bank = app(QuestionBankService::class);
        $cat = $bank->createCategory('acne-treatment', 'Acne');
        foreach (['ACNE_Q1' => 'How long have you had acne?', 'ACNE_Q2' => 'Where is it?'] as $key => $text) {
            $q = $bank->createQuestion(['scope' => 'category', 'question_key' => $key], ['text' => $text, 'type' => 'single'], null);
            $bank->addQuestionToCategory($cat->refresh(), $q, null);
        }
        return $cat->refresh();
    }

    public function test_doctors_cannot_reach_the_review_queue(): void
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor, 'email_verified_at' => now()]);
        $this->actingAs($doctor)->get('/admin/question-bank/review')->assertForbidden();
    }

    public function test_approve_publishes_question_and_advances(): void
    {
        $this->draftCategory();
        $admin = $this->admin();

        $component = Livewire::actingAs($admin)->test(ReviewQueue::class);
        $firstId = $component->get('currentId');
        $this->assertNotNull($firstId);

        $component->call('approve');

        $this->assertSame('live', AssessmentQuestion::find($firstId)->status);
        // queue advanced to the next pending question
        $this->assertNotSame($firstId, $component->get('currentId'));
    }

    public function test_category_auto_publishes_when_all_questions_approved(): void
    {
        $this->draftCategory();
        $admin = $this->admin();

        $component = Livewire::actingAs($admin)->test(ReviewQueue::class);
        $component->call('approve'); // Q1
        $component->call('approve'); // Q2 → last one

        $this->assertSame('live', AssessmentCategory::where('slug', 'acne-treatment')->first()->status);
        $this->assertNull($component->get('currentId')); // nothing left
    }

    public function test_reject_sends_question_back_to_draft_with_reason(): void
    {
        $this->draftCategory();
        $admin = $this->admin();

        $component = Livewire::actingAs($admin)->test(ReviewQueue::class);
        $id = $component->get('currentId');

        $component->call('startReject')
            ->set('rejectReason', 'must screen smoking in over-35s')
            ->call('confirmReject');

        $q = AssessmentQuestion::with('versions')->find($id);
        $this->assertSame('draft', $q->status);
        $this->assertStringContainsString('REJECTED', $q->versions()->latest('version')->first()->reason);
    }

    public function test_edit_creates_new_version_without_publishing(): void
    {
        $this->draftCategory();
        $admin = $this->admin();

        $component = Livewire::actingAs($admin)->test(ReviewQueue::class);
        $id = $component->get('currentId');

        $component->call('startEdit')
            ->set('editText', 'How many months have you had acne?')
            ->call('saveEdit');

        $q = AssessmentQuestion::with('versions')->find($id);
        $this->assertSame(2, (int) $q->versions()->max('version'));
        $this->assertNotSame('live', $q->status); // editing alone does not publish
    }
}
