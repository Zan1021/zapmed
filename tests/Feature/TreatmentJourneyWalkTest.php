<?php

namespace Tests\Feature;

use App\Livewire\Patient\BookingJourney;
use App\Models\AssessmentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Every treatment works like weight-loss."
 *
 * Imports the REAL clinical question source, publishes it, then drives the real patient screen
 * (Livewire BookingJourney) through every one of the 18 treatments, answering each visible question
 * the way a patient would, until it reaches the Review step. Two walks per treatment:
 *   - NEGATIVE: "No" / "None of the above" wherever offered → the shortest legitimate path
 *   - POSITIVE: "Yes" / first option / tick something → the longest path, every follow-up shown
 * Catches: a required question that can never be satisfied, a loop, a dead end, a forced
 * "provide details" for someone who said "None", a missing hard-stop, and unreachable follow-ups.
 */
class TreatmentJourneyWalkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('onboarding.enabled_slugs', ['*']);
        $this->artisan('questionbank:import-md')->assertSuccessful();
        $this->artisan('questionbank:approve-all', ['--force' => true])->assertSuccessful();
    }

    /** @return array<string,array{0:string}> */
    public static function treatmentSlugs(): array
    {
        return array_combine(
            $slugs = [
                'acid-reflux-treatment', 'acne-treatment', 'anti-aging-treatment', 'bacterial-vaginosis-treatment',
                'birth-control', 'cold-sores-treatment', 'erectile-dysfunction-treatment', 'genital-herpes-101',
                'genital-warts-treatment', 'gp-consult', 'haemorrhoids-treatment', 'hair-loss-treatment',
                'menopause-management', 'premature-ejaculation-treatment', 'rosacea-treatment', 'sti-treatment',
                'uti-treatment', 'weight-loss',
            ],
            array_map(fn ($s) => [$s], $slugs)
        );
    }

    public function test_every_treatment_has_a_live_question_set(): void
    {
        foreach (self::treatmentSlugs() as [$slug]) {
            $this->assertTrue(
                AssessmentCategory::where('slug', $slug)->where('status', 'live')->exists(),
                "{$slug} is not live"
            );
        }
    }

    #[DataProvider('treatmentSlugs')]
    public function test_negative_path_reaches_review_and_never_forces_follow_ups(string $slug): void
    {
        $walk = $this->walk($slug, positive: false);

        // Rosacea is the one treatment that (correctly) refuses a patient with no physical diagnosis:
        // answering "No" ends in a hard-stop message instead of the review step.
        if ($slug === 'rosacea-treatment') {
            $this->assertSame('hit a hard-stop message', $walk['why']);
            return;
        }

        $this->assertSame('review', $walk['stage'], "{$slug}: stopped at '{$walk['last']}' ({$walk['why']})");

        // Someone who said "None of the above" / "No" must never be forced through a "details" box.
        foreach ($walk['seen'] as $label) {
            $this->assertStringNotContainsString(
                'about the above condition',
                $label,
                "{$slug}: asked for details after a negative answer → \"{$label}\""
            );
        }
    }

    #[DataProvider('treatmentSlugs')]
    public function test_positive_path_reaches_review_and_shows_the_follow_ups(string $slug): void
    {
        $neg = $this->walk($slug, positive: false);
        $pos = $this->walk($slug, positive: true);

        $this->assertSame('review', $pos['stage'], "{$slug}: stopped at '{$pos['last']}' ({$pos['why']})");
        $this->assertGreaterThanOrEqual(
            count($neg['seen']),
            count($pos['seen']),
            "{$slug}: saying Yes should never show FEWER questions than saying No"
        );
    }

    public function test_rosacea_without_a_diagnosis_gets_a_stop_message_and_cannot_continue(): void
    {
        $c = Livewire::test(BookingJourney::class, ['slug' => 'rosacea-treatment']);
        $first = $c->instance()->currentQuestion;
        $this->assertSame('Have you been diagnosed with rosacea by a medical professional?', $first['label']);

        $c->set("answers.{$first['id']}", 'No')->call('nextQuestion');

        $stop = $c->instance()->currentQuestion;
        $this->assertSame('stop', $stop['type']);
        $this->assertStringContainsString('cannot assist', $stop['label']);

        // pressing "next" again (e.g. a crafted request) must not get past the message
        $c->call('nextQuestion');
        $this->assertSame('assessment', $c->get('stage'));
        $this->assertSame($stop['id'], $c->instance()->currentQuestion['id']);
    }

    public function test_no_current_symptoms_skips_the_symptom_questions(): void
    {
        $c = Livewire::test(BookingJourney::class, ['slug' => 'cold-sores-treatment']);
        $labels = fn () => array_column($c->instance()->visibleQuestions(), 'label');

        $symptoms = $this->idByLabel($c, 'Do you currently have any symptoms?');
        $c->set("answers.{$symptoms}", 'No');
        $this->assertNotContains('Is this your first time experiencing these symptoms?', $labels());
        $this->assertNotContains('Where are you currently experiencing these symptoms?', $labels());
        $this->assertContains('How many cold sore outbreaks have you had in the last 12 months?', $labels());

        $c->set("answers.{$symptoms}", 'Yes');
        $this->assertContains('Which symptoms do you currently have?', $labels());
        $this->assertContains('Is this your first time experiencing these symptoms?', $labels());
    }

    public function test_birth_control_nested_flow(): void
    {
        $c = Livewire::test(BookingJourney::class, ['slug' => 'birth-control']);
        $labels = fn () => array_column($c->instance()->visibleQuestions(), 'label');

        $using = $this->idByLabel($c, 'Are you currently using a method of birth control?');

        $c->set("answers.{$using}", 'No');
        $this->assertContains('Have you previously used a method of birth control?', $labels());
        $this->assertNotContains('Which method of birth control are you currently using?', $labels());
        $this->assertNotContains('Which method of birth control did you last use?', $labels());

        $prev = $this->idByLabel($c, 'Have you previously used a method of birth control?');
        $c->set("answers.{$prev}", 'Yes');
        $this->assertContains('Which method of birth control did you last use?', $labels());

        $c->set("answers.{$using}", 'Yes');
        $this->assertContains('Which method of birth control are you currently using?', $labels());
        $this->assertNotContains('Have you previously used a method of birth control?', $labels());

        $current = $this->idByLabel($c, 'Which method of birth control are you currently using?');
        $this->assertNotContains('Which brand of contraceptive pill are you currently taking?', $labels());
        $c->set("answers.{$current}", 'Contraceptive pill');
        $this->assertContains('Which brand of contraceptive pill are you currently taking?', $labels());
        $this->assertContains('Do you want to continue using this method of contraception?', $labels());

        $cont = $this->idByLabel($c, 'Do you want to continue using this method of contraception?');
        $this->assertNotContains('Why do you want to change?', $labels());
        $c->set("answers.{$cont}", 'No');
        $this->assertContains('Why do you want to change?', $labels());
    }

    // ──────────────────────────────────────────────────────────────────────────────────────────

    private function idByLabel($component, string $label): string
    {
        foreach ($component->instance()->questions() as $q) {
            if (($q['label'] ?? null) === $label) {
                return $q['id'];
            }
        }
        $this->fail("No question labelled \"{$label}\"");
    }

    /**
     * Answer every visible question like a patient and advance until the Review step (or until stuck).
     *
     * @return array{stage:string,last:string,why:string,seen:array<int,string>}
     */
    private function walk(string $slug, bool $positive): array
    {
        $c = Livewire::test(BookingJourney::class, ['slug' => $slug]);
        $seen = [];
        $why = '';
        $last = '';

        for ($i = 0; $i < 120; $i++) {
            if ($c->get('stage') !== 'assessment') {
                break;
            }
            $q = $c->instance()->currentQuestion;
            if (! $q) {
                $why = 'no current question';
                break;
            }
            $last = $q['label'] ?? $q['id'];
            $seen[] = $last;
            $id = $q['id'];
            $type = $q['type'] ?? 'text';
            $opts = array_map(fn ($o) => is_array($o) ? ($o['label'] ?? '') : $o, $q['options'] ?? []);

            switch ($type) {
                case 'stop':
                    $why = 'hit a hard-stop message';
                    break 2;
                case 'radio':
                case 'single':
                    $c->set("answers.{$id}", $this->pickSingle($opts, $positive));
                    break;
                case 'checkbox':
                case 'multi':
                    $c->set("answers.{$id}", $this->pickMulti($opts, $positive));
                    break;
                case 'number':
                    $c->set("answers.{$id}", '70');
                    break;
                case 'date':
                    $c->set("answers.{$id}_month", 'March')->set("answers.{$id}_year", (string) (date('Y') - 1));
                    break;
                default:
                    $c->set("answers.{$id}", 'Test answer');
            }

            $before = $c->get('cursor').'|'.$c->get('stage');
            $c->call('nextQuestion');
            if ($c->get('errors') && $c->errors()->has('answer')) {
                $why = 'blocked: '.$c->errors()->first('answer');
                break;
            }
            if ($before === $c->get('cursor').'|'.$c->get('stage')) {
                $why = 'did not advance';
                break;
            }
        }

        return ['stage' => $c->get('stage'), 'last' => $last, 'why' => $why ?: 'ok', 'seen' => $seen];
    }

    /** @param array<int,string> $opts */
    private function pickSingle(array $opts, bool $positive): string
    {
        if ($positive) {
            foreach ($opts as $o) {
                if ($o === 'Yes') {
                    return $o;
                }
            }
            return $opts[0];
        }
        foreach ($opts as $o) {
            if ($o === 'No' || str_starts_with($o, 'No,') || str_starts_with($o, 'No ')) {
                return $o;
            }
        }
        return end($opts);
    }

    /**
     * @param  array<int,string>  $opts
     * @return array<int,string>
     */
    private function pickMulti(array $opts, bool $positive): array
    {
        if (! $positive) {
            foreach ($opts as $o) {
                if (preg_match('/^none\b/i', $o)) {
                    return [$o];
                }
            }
        }
        // positive (or no "None" option): tick the first real option, plus "Other" if it exists
        $pick = [$opts[0]];
        foreach ($opts as $o) {
            if ($positive && $o === 'Other') {
                $pick[] = $o;
            }
        }
        return array_values(array_unique($pick));
    }
}
