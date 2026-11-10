<?php

namespace Tests\Feature;

use App\Models\AssessmentCategory;
use App\Models\AssessmentCategoryQuestion;
use App\Models\AssessmentQuestion;
use App\Services\Assessments\AssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Markdown importer behaviour (questionbank:import-md), using a self-contained fixture so it runs in
 * any environment. Locks in the parser enhancements made for the weight-loss cleanup:
 *   - a conditional "[IF YES] <q>?" followed by option lines becomes a single/multi CHOICE
 *     (not a free-text box) and still carries the branch to its parent;
 *   - a question line may end with a widget hint, e.g. "... ? (number)" / "... ? (calendar date)",
 *     and still be recognised as a question and resolve to the right widget type;
 *   - --only=<slug> rebuilds a single category without touching the rest of the bank.
 */
class QuestionBankMarkdownImportTest extends TestCase
{
    use RefreshDatabase;

    private function writeFixture(string $body): string
    {
        $path = storage_path('app/testing/qbank-'.uniqid().'.md');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $body);
        return $path;
    }

    /** @return array<int,array{position:int,type:string,text:string,options:?array,branch:?array}> */
    private function dump(string $slug): array
    {
        $cat = AssessmentCategory::where('slug', $slug)->firstOrFail();

        return AssessmentCategoryQuestion::where('category_id', $cat->id)
            ->orderBy('position')->get()
            ->map(function ($link) {
                $v = $link->question->currentVersion;
                return [
                    'position' => $link->position,
                    'type' => $v->type,
                    'text' => $v->text,
                    'options' => $v->options,
                    'branch' => $v->branch_rules,
                ];
            })->values()->all();
    }

    public function test_conditional_question_with_options_becomes_a_choice_and_keeps_its_branch(): void
    {
        $path = $this->writeFixture(<<<'MD'
# Fixture

## Weight Loss Health Assessment

Have you ever taken weight loss medications?
[1] Yes
[2] No
[IF YES] Which of the following weight loss medications have you tried?
Semaglutide
Tirzepatide
Other - [Please specify]
MD);

        $this->artisan('questionbank:import-md', ['path' => $path, '--only' => 'weight-loss'])
            ->assertSuccessful();

        $qs = $this->dump('weight-loss');

        $this->assertCount(3, $qs);

        // the parent Yes/No
        $this->assertSame('single', $qs[0]['type']);

        // the conditional is now a MULTI choice (bare option lines), NOT free text …
        $this->assertSame('multi', $qs[1]['type']);
        $this->assertSame(
            ['Semaglutide', 'Tirzepatide', 'Other'],
            array_map(fn ($o) => $o['label'], $qs[1]['options'])
        );

        // … and still branches off the parent's "Yes"
        $this->assertNotNull($qs[1]['branch']);
        $parentKey = array_key_first($qs[1]['branch']);
        $this->assertSame(['Yes'], $qs[1]['branch'][$parentKey]);

        // … and "Other - [Please specify]" became a free-text follow-up shown only when "Other" is ticked
        $this->assertSame('text', $qs[2]['type']);
        $this->assertSame('Please specify', $qs[2]['text']);
        $this->assertSame([array_key_first($qs[2]['branch']) => ['Other']], $qs[2]['branch']);
    }

    public function test_if_selected_and_if_no_compile_to_real_branches(): void
    {
        $path = $this->writeFixture(<<<'MD'
## Acne Consultation

Do you have any of the following conditions?
Diabetes
Asthma
None of the above

[IF SELECTED] Please provide further details about the above condition(s)

Are you pregnant?
[1] Yes
[2] No

[IF NO] Are you using contraception?
[1] Yes
[2] No
MD);

        $this->artisan('questionbank:import-md', ['path' => $path, '--only' => 'acne-treatment'])->assertSuccessful();
        $qs = $this->dump('acne-treatment');

        // [IF SELECTED] is no longer an unconditional, forced question
        $this->assertSame([AssessmentService::BRANCH_ANY_SELECTED], array_values($qs[1]['branch'])[0]);

        // [IF NO] is conditional on "No" (it used to be shown to everyone)
        $this->assertSame(['No'], array_values($qs[3]['branch'])[0]);
        $this->assertSame(array_key_first($qs[3]['branch']), AssessmentQuestion::whereHas('currentVersion', fn ($v) => $v->where('text', 'Are you pregnant?'))->value('question_key'));
    }

    public function test_then_hangs_off_the_previous_question_so_flows_can_nest(): void
    {
        $path = $this->writeFixture(<<<'MD'
## Birth Control health assessment

Are you currently using a method of birth control?
[1] Yes
[2] No

[IF NO] Have you previously used a method of birth control?
[1] Yes
[2] No

[THEN YES] Which method did you last use?
[1] Pill
[2] Ring

[THEN PILL] Which brand?
MD);

        $this->artisan('questionbank:import-md', ['path' => $path, '--only' => 'birth-control'])->assertSuccessful();
        $qs = $this->dump('birth-control');
        $key = fn (string $text) => AssessmentQuestion::whereHas('currentVersion', fn ($v) => $v->where('text', $text))->value('question_key');

        $this->assertSame([$key('Are you currently using a method of birth control?') => ['No']], $qs[1]['branch']);
        $this->assertSame([$key('Have you previously used a method of birth control?') => ['Yes']], $qs[2]['branch']);
        // a [THEN <option>] picks the matching option of the question above it
        $this->assertSame([$key('Which method did you last use?') => ['Pill']], $qs[3]['branch']);
    }

    public function test_jump_skips_the_questions_in_between_when_that_option_is_chosen(): void
    {
        $path = $this->writeFixture(<<<'MD'
## Cold sores health assessment

Do you currently have any symptoms?
[1] Yes
[2] No - [JUMP: How many outbreaks have you had in the last 12 months?]

Where are the symptoms?
Lip
Mouth

Is this your first time?
[1] Yes
[2] No

How many outbreaks have you had in the last 12 months?
[1] One
[2] Two
MD);

        $this->artisan('questionbank:import-md', ['path' => $path, '--only' => 'cold-sores-treatment'])->assertSuccessful();
        $qs = $this->dump('cold-sores-treatment');

        $this->assertNull($qs[0]['branch']);
        $this->assertSame(['!No'], array_values($qs[1]['branch'])[0]);   // skipped when "No"
        $this->assertSame(['!No'], array_values($qs[2]['branch'])[0]);
        $this->assertNull($qs[3]['branch']);                            // the jump target is always shown
    }

    public function test_html_review_comments_never_become_answer_options(): void
    {
        $path = $this->writeFixture(<<<'MD'
## Acne Consultation

Is your skin sensitive?
[1] Yes
[2] No
<!-- REVIEW (Dr Anke): confirm wording.
     multi-line note -->
[IF YES] Please provide further details
MD);

        $this->artisan('questionbank:import-md', ['path' => $path, '--only' => 'acne-treatment'])->assertSuccessful();
        $qs = $this->dump('acne-treatment');

        $this->assertSame(['Yes', 'No'], array_map(fn ($o) => $o['label'], $qs[0]['options']));
        $this->assertCount(2, $qs);
    }

    public function test_stop_message_and_calendar_questions(): void
    {
        $path = $this->writeFixture(<<<'MD'
## Rosacea Consultation

Have you been diagnosed by a medical professional?
[1] Yes
[2] No

[IF NO] [STOP] Unfortunately we cannot assist unless you have had a physical diagnosis.

SELECT CALENDAR: Please provide the closest date possible of your last period
MD);

        $this->artisan('questionbank:import-md', ['path' => $path, '--only' => 'rosacea-treatment'])->assertSuccessful();
        $qs = $this->dump('rosacea-treatment');

        $this->assertSame('stop', $qs[1]['type']);
        $this->assertSame(['No'], array_values($qs[1]['branch'])[0]);
        $this->assertSame('date', $qs[2]['type']);
        $this->assertNull($qs[2]['branch']);
    }

    public function test_branch_matching_rules(): void
    {
        $any = AssessmentService::BRANCH_ANY_SELECTED;

        $this->assertTrue(AssessmentService::branchMatches('Yes', 'Yes'));
        $this->assertFalse(AssessmentService::branchMatches('Yes', 'No'));
        $this->assertFalse(AssessmentService::branchMatches('Yes', null));
        $this->assertTrue(AssessmentService::branchMatches('Other', ['Asthma', 'Other']));

        // "anything but a negative answer"
        $this->assertTrue(AssessmentService::branchMatches($any, ['Diabetes']));
        $this->assertTrue(AssessmentService::branchMatches($any, ['None of the above', 'Asthma']));
        $this->assertFalse(AssessmentService::branchMatches($any, ['None of the above']));
        $this->assertFalse(AssessmentService::branchMatches($any, 'No'));
        $this->assertFalse(AssessmentService::branchMatches($any, "I've never been prescribed medication"));
        $this->assertFalse(AssessmentService::branchMatches($any, []));
        $this->assertTrue(AssessmentService::branchMatches($any, 'Yes, but there were issues'));

        // "!No" = answered and not "No"
        $this->assertTrue(AssessmentService::branchMatches('!No', 'Yes'));
        $this->assertFalse(AssessmentService::branchMatches('!No', 'No'));
        $this->assertFalse(AssessmentService::branchMatches('!No', null));
    }

    /**
     * Safety net over the REAL clinical source: import every treatment and make sure none of them
     * can ship a broken question (the class of bug found while bringing every treatment up to the
     * weight-loss standard).
     */
    public function test_every_treatment_in_the_real_source_is_structurally_sound(): void
    {
        $this->artisan('questionbank:import-md')->assertSuccessful();

        $categories = AssessmentCategory::all();
        $this->assertCount(18, $categories);

        foreach ($categories as $cat) {
            $seen = [];
            $links = AssessmentCategoryQuestion::where('category_id', $cat->id)->orderBy('position')->get();
            $this->assertGreaterThan(5, $links->count(), "{$cat->slug} is suspiciously short");

            foreach ($links as $link) {
                $q = $link->question;
                $v = $q->currentVersion;
                $labels = array_map(fn ($o) => $o['label'], $v->options ?? []);
                $where = "{$cat->slug} #{$link->position} \"{$v->text}\"";

                // choice questions need something to choose between
                if (in_array($v->type, ['single', 'multi'], true)) {
                    $this->assertGreaterThanOrEqual(2, count($labels), "$where has <2 options");
                }
                // review notes / questions must never leak into answers
                foreach ($labels as $l) {
                    $this->assertStringNotContainsString('<!--', $l, "$where leaks a review comment");
                    $this->assertFalse(str_ends_with($l, '?'), "$where has a question as an option: $l");
                    $this->assertLessThan(160, mb_strlen($l), "$where has a paragraph as an option");
                }
                // a hard-stop message needs a condition, and is never "required"
                if ($v->type === 'stop') {
                    $this->assertNotEmpty($v->branch_rules, "$where");
                    $this->assertFalse((bool) $v->required);
                }

                // every branch points at an EARLIER question in this category, at a value it can have
                foreach ($v->branch_rules ?? [] as $depKey => $vals) {
                    $this->assertArrayHasKey($depKey, $seen, "$where branches on a question that does not come before it");
                    $want = ltrim($vals[0], '!');
                    if ($want !== AssessmentService::BRANCH_ANY_SELECTED) {
                        $this->assertContains($want, $seen[$depKey], "$where waits for '{$vals[0]}' which is not an option of its parent");
                    }
                }

                $seen[$q->question_key] = $labels;
            }
        }
    }

    public function test_bare_conditional_without_options_stays_free_text(): void
    {
        $path = $this->writeFixture(<<<'MD'
## Weight Loss Health Assessment

Have you ever had an allergic reaction to a medication?
[1] Yes
[2] No
[IF YES] Please provide further details
MD);

        $this->artisan('questionbank:import-md', ['path' => $path, '--only' => 'weight-loss'])
            ->assertSuccessful();

        $qs = $this->dump('weight-loss');

        $this->assertSame('text', $qs[1]['type']);
        $this->assertSame('Please provide further details', $qs[1]['text']);
        $this->assertNotNull($qs[1]['branch']);
    }

    public function test_trailing_widget_hint_is_recognised_and_stripped(): void
    {
        $path = $this->writeFixture(<<<'MD'
## Weight Loss Health Assessment

What is your current weight? (number)

When did you start this medication? (calendar date)
MD);

        $this->artisan('questionbank:import-md', ['path' => $path, '--only' => 'weight-loss'])
            ->assertSuccessful();

        $qs = $this->dump('weight-loss');

        $this->assertCount(2, $qs);

        $this->assertSame('number', $qs[0]['type']);
        $this->assertSame('What is your current weight?', $qs[0]['text']); // hint stripped from label

        $this->assertSame('date', $qs[1]['type']);
        $this->assertSame('When did you start this medication?', $qs[1]['text']);
    }

    public function test_only_flag_rebuilds_one_category_and_leaves_others_untouched(): void
    {
        // Seed two categories.
        $seed = $this->writeFixture(<<<'MD'
## Weight Loss Health Assessment

Do you smoke?
[1] Yes
[2] No

## Acne Consultation

Would you describe your skin as sensitive?
[1] Yes
[2] No
MD);
        $this->artisan('questionbank:import-md', ['path' => $seed])->assertSuccessful();

        $acneBefore = AssessmentQuestion::whereHas(
            'categoryLinks',
            fn ($l) => $l->where('category_id', AssessmentCategory::where('slug', 'acne-treatment')->value('id'))
        )->pluck('id')->sort()->values()->all();

        // Re-import ONLY weight-loss with a different question set.
        $only = $this->writeFixture(<<<'MD'
## Weight Loss Health Assessment

Do you drink alcohol?
[1] Yes
[2] No
Do you exercise?
[1] Yes
[2] No
MD);
        $this->artisan('questionbank:import-md', ['path' => $only, '--only' => 'weight-loss'])
            ->assertSuccessful();

        // weight-loss rebuilt (now 2 questions, new wording)
        $wl = $this->dump('weight-loss');
        $this->assertCount(2, $wl);
        $this->assertSame('Do you drink alcohol?', $wl[0]['text']);

        // acne completely untouched (same question rows)
        $acneAfter = AssessmentQuestion::whereHas(
            'categoryLinks',
            fn ($l) => $l->where('category_id', AssessmentCategory::where('slug', 'acne-treatment')->value('id'))
        )->pluck('id')->sort()->values()->all();

        $this->assertSame($acneBefore, $acneAfter);
    }
}
