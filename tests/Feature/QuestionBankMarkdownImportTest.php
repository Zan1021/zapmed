<?php

namespace Tests\Feature;

use App\Models\AssessmentCategory;
use App\Models\AssessmentCategoryQuestion;
use App\Models\AssessmentQuestion;
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

        $this->assertCount(2, $qs);

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
