<?php

namespace App\Console\Commands;

use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestion;
use App\Services\Assessments\QuestionBankService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads Craig's FINAL weight-loss assessment (26 Nov handoff, dist/weight-loss/assessment/journey.js,
 * 14 questions) into the question bank as the LIVE `weight-loss` category.
 *
 * The 24 older June-docx weight-loss questions are retired (kept for history, not deleted). Follow-up
 * questions that Craig shows under their parent on the same screen use layout = 'with_previous'.
 *
 * Idempotent: re-running publishes a new version of each question only when its content changed.
 * Flag rules (the "Needs attention" list shown to the doctor) are a clinical PROPOSAL for Dr Anke.
 *
 * Usage: php artisan questionbank:import-craig-weight-loss
 */
class ImportCraigWeightLossQuestions extends Command
{
    protected $signature = 'questionbank:import-craig-weight-loss';

    protected $description = "Load Craig's final 14-question weight-loss assessment into the question bank (Live)";

    private const YNU = ['Yes', 'No', "I'm not sure"];

    public function handle(QuestionBankService $bank): int
    {
        $specs = $this->specs();

        DB::transaction(function () use ($bank, $specs) {
            $category = AssessmentCategory::firstOrCreate(
                ['slug' => 'weight-loss'],
                ['name' => 'Weight Loss', 'status' => 'draft', 'manifest_version' => 1],
            );

            $keep = collect($specs)->pluck('key')->all();

            // Retire everything else currently in the weight-loss manifest and unlink it.
            foreach ($category->questionLinks()->with('question')->get() as $link) {
                if ($link->question && ! in_array($link->question->question_key, $keep, true)) {
                    if ($link->question->status !== 'retired') {
                        $bank->retire($link->question);
                    }
                    $link->delete();
                }
            }

            $ids = [];
            foreach ($specs as $s) {
                $attrs = [
                    'text' => $s['text'],
                    'type' => $s['type'],
                    'options' => isset($s['options']) ? array_map(fn ($o) => ['code' => $o, 'label' => $o], $s['options']) : null,
                    'why_we_ask' => $s['why'] ?? null,
                    'helper_text' => $s['helper'] ?? null,
                    'placeholder' => $s['placeholder'] ?? null,
                    'required' => $s['required'] ?? true,
                    'branch_rules' => $s['show_if'] ?? null,
                    'flag_rule' => isset($s['flag']) ? ['values' => $s['flag']] : null,
                    'layout' => $s['layout'] ?? null,
                    'screen_title' => $s['screen_title'] ?? null,
                ];

                $question = AssessmentQuestion::with('currentVersion')->where('question_key', $s['key'])->first();

                if (! $question) {
                    $question = $bank->createQuestion(
                        ['scope' => 'category', 'question_key' => $s['key'], 'clinical' => true],
                        $attrs,
                    );
                    $version = $question->currentVersion;
                } elseif ($this->changed($question, $attrs) || $question->status === 'retired') {
                    $version = $bank->editQuestion($question, $attrs);
                } else {
                    $version = $question->currentVersion;
                }

                if ($question->status !== 'live' || $question->current_version_id !== $version->id) {
                    $bank->publish($question, $version, "Craig final handoff 26 Nov 2026", null);
                }

                if (! $category->questionLinks()->where('question_id', $question->id)->exists()) {
                    $bank->addQuestionToCategory($category->refresh(), $question);
                }
                $ids[] = $question->id;
            }

            $bank->reorderCategory($category->refresh(), $ids);
            $bank->publishCategory($category->refresh());
        });

        $this->info('Weight-loss assessment loaded: '.count($specs).' questions Live (14 screens).');

        return self::SUCCESS;
    }

    /** Did the content change versus the current version? */
    private function changed(AssessmentQuestion $question, array $attrs): bool
    {
        $v = $question->currentVersion;
        if (! $v) {
            return true;
        }
        foreach ($attrs as $field => $value) {
            $old = $v->{$field};
            if ($field === 'required') {
                $old = (bool) $old;
                $value = (bool) $value;
            }
            if (json_encode($old) !== json_encode($value)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,array<string,mixed>> */
    private function specs(): array
    {
        $dash = "\u{2013}";
        $groups = ['Type 2 diabetes', 'PCOS', 'Thyroid disease', 'Pancreatitis', 'Severe stomach or gut condition',
            'Gallbladder disease or gallstones', 'Previous bariatric surgery', 'Heart disease or stroke',
            'High blood pressure', 'Kidney disease', 'Liver disease', 'Eating disorder', 'None of these'];
        $hist = [
            "Medullary thyroid carcinoma (MTC) {$dash} a rare type of thyroid cancer",
            "Multiple endocrine neoplasia type 2 (MEN2) {$dash} a rare inherited condition",
            'Other thyroid cancer or inherited thyroid condition',
            'None of these',
            "I'm not sure",
        ];
        $histFlag = array_slice($hist, 0, 3);
        $eat = ['I usually eat regular meals.', 'I often skip meals.', 'My meal times change from day to day.',
            'I snack a lot during the day.', 'I often feel very hungry, or I overeat later in the day.',
            'I follow a particular diet or eating plan.', 'I find it hard to plan or prepare meals.', 'Other',
            "I'm not sure how to describe it"];
        $well = ['I generally feel well.', 'I feel anxious or worry a lot.', 'My mood has been low.',
            'I sometimes eat when I am stressed or upset.', 'Sleep or stress is affecting me.',
            'I would rather talk about this with my doctor.', 'Prefer not to answer', 'Other'];

        return [
            // 1
            ['key' => 'CWL_CONDITIONS', 'type' => 'single', 'text' => 'Do you have any existing medical conditions, or have you had any previous surgery?',
                'options' => self::YNU, 'why' => 'Your doctor needs your health history to make an informed assessment.'],
            ['key' => 'CWL_CONDITIONS_DETAIL', 'type' => 'textarea', 'text' => 'Please tell us about any conditions or surgeries.',
                'placeholder' => 'Please tell us about any conditions or surgeries.',
                'show_if' => ['CWL_CONDITIONS' => ['Yes']], 'layout' => 'with_previous'],
            // 2
            ['key' => 'CWL_MEDICINES', 'type' => 'medicines', 'text' => 'What medicines or supplements do you take now?',
                'helper' => 'Include prescription medicines, over-the-counter medicines, vitamins and supplements. Add names and doses if known.',
                'why' => 'Some medicines and supplements can affect which options may suit you.'],
            // 3
            ['key' => 'CWL_ALLERGIES', 'type' => 'single', 'text' => 'Do you have any allergies?',
                'options' => self::YNU, 'why' => 'Allergies can affect which options are safe.', 'flag' => ['Yes']],
            ['key' => 'CWL_ALLERGIES_DETAIL', 'type' => 'textarea', 'text' => 'What are you allergic to and what reaction do you get?',
                'placeholder' => 'What are you allergic to and what reaction do you get?', 'required' => false,
                'show_if' => ['CWL_ALLERGIES' => ['*selected']], 'layout' => 'with_previous'],
            // 4
            ['key' => 'CWL_MEASUREMENTS', 'type' => 'measurements', 'text' => 'Your current measurements',
                'why' => 'Your doctor considers your weight and height with your wider health history.'],
            // 5
            ['key' => 'CWL_ALCOHOL', 'type' => 'single', 'text' => 'How often do you drink alcohol?',
                'options' => ['Never', 'Less than once a week', '1 to 3 days a week', '4 or more days a week', 'Prefer not to say'],
                'why' => 'Alcohol can affect medicine interactions and liver health.'],
            // 6
            ['key' => 'CWL_PAST_MEDICATION', 'type' => 'single', 'text' => 'Have you used prescription medication to support weight management before?',
                'options' => ['Yes', 'No'], 'why' => 'Your experience helps your doctor understand what you have tried.'],
            ['key' => 'CWL_PAST_EXPERIENCE', 'type' => 'textarea', 'text' => 'Which medication did you use? What was your experience?',
                'show_if' => ['CWL_PAST_MEDICATION' => ['Yes']], 'layout' => 'with_previous'],
            ['key' => 'CWL_PAST_STILL_USING', 'type' => 'single', 'text' => 'Do you still use it?', 'options' => ['Yes', 'No'],
                'show_if' => ['CWL_PAST_MEDICATION' => ['Yes']], 'layout' => 'with_previous'],
            ['key' => 'CWL_PAST_STOPPED_WHY', 'type' => 'text', 'text' => 'If no, why did you stop? (optional)', 'required' => false,
                'placeholder' => 'Type your answer', 'show_if' => ['CWL_PAST_STILL_USING' => ['No']], 'layout' => 'with_previous'],
            // 7
            ['key' => 'CWL_CHECKLIST', 'type' => 'multi', 'text' => 'Do any of these apply to you?', 'options' => $groups,
                'helper' => 'Some health conditions can affect which options may suit you.',
                'flag' => ['Pancreatitis', 'Eating disorder']],
            // 8
            ['key' => 'CWL_HISTORY_PERSONAL', 'type' => 'multi', 'text' => 'Have YOU been diagnosed with any of these?', 'options' => $hist,
                'screen_title' => 'Personal and family history',
                'why' => 'Your doctor needs this history before discussing some weight-management options.', 'flag' => $histFlag],
            ['key' => 'CWL_HISTORY_FAMILY', 'type' => 'multi', 'text' => 'Has a close family member been diagnosed with any of these?', 'options' => $hist,
                'layout' => 'with_previous', 'flag' => $histFlag],
            // 9
            ['key' => 'CWL_PREGNANCY', 'type' => 'single', 'text' => 'Are you pregnant, breastfeeding, or planning to become pregnant?',
                'options' => ['Yes', 'No', 'Not applicable'], 'why' => 'This can affect which options may suit you.', 'flag' => ['Yes']],
            // 10
            ['key' => 'CWL_ACTIVITY', 'type' => 'single', 'text' => 'How often do you do physical activity or exercise?',
                'options' => ['Rarely or never', '1 to 2 days a week', '3 to 4 days a week', '5 or more days a week'],
                'why' => 'There is no right answer. This helps your doctor understand your routine.'],
            // 11
            ['key' => 'CWL_EATING', 'type' => 'multi', 'text' => 'Which of these best describe how you usually eat?', 'options' => $eat,
                'why' => 'Choose all that apply.'],
            ['key' => 'CWL_EATING_DIET', 'type' => 'text', 'text' => 'If you follow a particular diet, which approach?', 'required' => false,
                'placeholder' => 'Low-carb, vegetarian, vegan, intermittent fasting or other', 'layout' => 'with_previous'],
            // 12
            ['key' => 'CWL_WELLBEING', 'type' => 'multi', 'text' => 'How would you describe your overall wellbeing right now?', 'options' => $well,
                'required' => false, 'why' => 'This question is optional. Choose what you feel comfortable sharing.'],
            // 13
            ['key' => 'CWL_BLOOD_TESTS_12M', 'type' => 'single', 'text' => 'Have you had any blood tests in the past 12 months?',
                'options' => self::YNU, 'why' => 'Recent results may help your doctor decide what is needed next.'],
            ['key' => 'CWL_BLOOD_TESTS_WHICH', 'type' => 'multi', 'text' => 'Which blood tests were done?',
                'options' => ['Glucose or HbA1c', 'Lipid profile', 'Liver function', 'Thyroid function', 'Other', "I'm not sure which tests were done"],
                'show_if' => ['CWL_BLOOD_TESTS_12M' => ['Yes']], 'layout' => 'with_previous'],
            // 14
            ['key' => 'CWL_FINAL_NOTES', 'type' => 'textarea', 'text' => 'What would you like your doctor to know?', 'required' => false,
                'helper' => 'Is there anything about your health, your past experience with weight management, or your biggest challenge so far that you want your doctor to know?',
                'placeholder' => 'Type your answer'],
        ];
    }
}
