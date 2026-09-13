<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Patient\BookAppointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression guard for the booking-assessment checkbox binding defect (Defect #4).
 *
 * Checkbox questions (e.g. weight-loss "Do you have any of these?") must bind to an
 * ARRAY of selected option labels — not an index=>bool map — so that:
 *   - conditional show_if questions (e.g. "Which diabetes medication?") are correctly
 *     shown/required only when the parent condition is selected, and
 *   - the assessment saved for the doctor stores readable labels.
 *
 * Previously the blade bound "assessmentAnswers.{id}.{index}" producing {"5":true},
 * which made every checkbox-with-conditional treatment un-submittable.
 */
class BookingAssessmentCheckboxTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): User
    {
        return User::factory()->create([
            'role' => UserRole::Patient,
            'email_verified_at' => now(),
        ]);
    }

    public function test_checkbox_answers_bind_as_array_of_labels_not_index_bool_map(): void
    {
        // The core of Defect #4: a checkbox selection must land as an array of the
        // chosen option label(s), matching what isQuestionVisible() / the saved
        // assessment expect — never an index=>bool map like {"5":true}.
        $component = Livewire::actingAs($this->patient())
            ->test(BookAppointment::class)
            ->set('appointmentType', 'weight-loss')
            ->set('selectedTreatment', 'weight-loss')
            ->call('proceedToAssessment')
            ->set('assessmentAnswers.medical_conditions', ['None of the above']);

        $answers = $component->get('assessmentAnswers');
        $this->assertIsArray($answers['medical_conditions']);
        $this->assertSame(['None of the above'], $answers['medical_conditions']);
    }

    public function test_assessment_advances_when_none_of_the_above_selected_no_phantom_med_requirement(): void
    {
        // With "None of the above" chosen, the conditional medication questions must NOT
        // be required, so the step advances to communication preference (step 3).
        Livewire::actingAs($this->patient())
            ->test(BookAppointment::class)
            ->set('appointmentType', 'weight-loss')
            ->set('selectedTreatment', 'weight-loss')
            ->call('proceedToAssessment')
            ->set('assessmentAnswers.current_weight', '95')
            ->set('assessmentAnswers.height', '168')
            ->set('assessmentAnswers.target_weight', '72')
            ->set('assessmentAnswers.medical_conditions', ['None of the above'])
            ->set('assessmentAnswers.smoker', 'No')
            ->set('assessmentAnswers.alcohol', 'No')
            ->set('assessmentAnswers.glp1_experience', 'No')
            ->set('assessmentAnswers.eating_habits', 'Emotional/snack eater')
            ->call('proceedToPayment')
            ->assertHasNoErrors()
            ->assertSet('step', 3);
    }

    public function test_conditional_med_question_is_required_only_when_its_condition_is_selected(): void
    {
        // Selecting "Type 2 Diabetes" but leaving diabetes_meds blank must block.
        Livewire::actingAs($this->patient())
            ->test(BookAppointment::class)
            ->set('appointmentType', 'weight-loss')
            ->set('selectedTreatment', 'weight-loss')
            ->call('proceedToAssessment')
            ->set('assessmentAnswers.current_weight', '95')
            ->set('assessmentAnswers.height', '168')
            ->set('assessmentAnswers.target_weight', '72')
            ->set('assessmentAnswers.medical_conditions', ['Type 2 Diabetes'])
            ->set('assessmentAnswers.smoker', 'No')
            ->set('assessmentAnswers.alcohol', 'No')
            ->set('assessmentAnswers.glp1_experience', 'No')
            ->set('assessmentAnswers.eating_habits', 'Healthy but big portions')
            ->call('proceedToPayment')
            ->assertHasErrors('assessmentAnswers.diabetes_meds')
            ->assertSet('step', 2);

        // Now supply it -> advances.
        Livewire::actingAs($this->patient())
            ->test(BookAppointment::class)
            ->set('appointmentType', 'weight-loss')
            ->set('selectedTreatment', 'weight-loss')
            ->call('proceedToAssessment')
            ->set('assessmentAnswers.current_weight', '95')
            ->set('assessmentAnswers.height', '168')
            ->set('assessmentAnswers.target_weight', '72')
            ->set('assessmentAnswers.medical_conditions', ['Type 2 Diabetes'])
            ->set('assessmentAnswers.diabetes_meds', 'Metformin 500mg')
            ->set('assessmentAnswers.smoker', 'No')
            ->set('assessmentAnswers.alcohol', 'No')
            ->set('assessmentAnswers.glp1_experience', 'No')
            ->set('assessmentAnswers.eating_habits', 'Healthy but big portions')
            ->call('proceedToPayment')
            ->assertHasNoErrors()
            ->assertSet('step', 3);
    }
}
