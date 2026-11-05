<?php

namespace Tests\Feature;

use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 — import from Craig's source Word document (the official question set).
 *
 * The parser + python-docx extraction need the real file, so this test runs against the actual
 * document when present and is skipped otherwise (so CI without the file stays green). It guards the
 * big-picture invariants: ~18 categories on booking slugs, hundreds of questions, all Draft, and
 * weight-loss branching survived.
 */
class QuestionBankDocxImportTest extends TestCase
{
    use RefreshDatabase;

    private string $docx = 'C:\\Users\\zande\\Documents\\Zapmed - Craig_New_Files\\Health Assessments - Zapmed Full Services - June 2026 (1).docx';

    protected function setUp(): void
    {
        parent::setUp();
        if (! is_file($this->docx)) {
            $this->markTestSkipped('Source .docx not present in this environment.');
        }
    }

    public function test_official_set_imports_onto_booking_slugs_as_draft(): void
    {
        $this->artisan('questionbank:import-docx', ['path' => $this->docx, '--purge-all' => true])
            ->assertSuccessful();

        // 18 categories, hundreds of questions, nothing live
        $this->assertGreaterThanOrEqual(17, AssessmentCategory::count());
        $this->assertGreaterThan(300, AssessmentQuestion::count());
        $this->assertSame(0, AssessmentQuestion::where('status', 'live')->count());

        // landed on the booking slug, not the clinical name
        $this->assertNotNull(AssessmentCategory::where('slug', 'weight-loss')->first());
        $this->assertNotNull(AssessmentCategory::where('slug', 'acne-treatment')->first());
        $this->assertNull(AssessmentCategory::where('slug', 'acne')->first());
    }

    public function test_conditional_branches_are_parsed(): void
    {
        $this->artisan('questionbank:import-docx', ['path' => $this->docx, '--purge-all' => true])
            ->assertSuccessful();

        // at least some questions carry a branch_rule (the [IF YES] follow-ups)
        $withBranch = AssessmentQuestion::whereHas('currentVersion', fn ($v) => $v->whereNotNull('branch_rules'))->count();
        $this->assertGreaterThan(0, $withBranch);
    }

    public function test_rerun_purge_all_is_idempotent(): void
    {
        $this->artisan('questionbank:import-docx', ['path' => $this->docx, '--purge-all' => true])->assertSuccessful();
        $first = AssessmentQuestion::count();
        $this->artisan('questionbank:import-docx', ['path' => $this->docx, '--purge-all' => true])->assertSuccessful();
        $this->assertSame($first, AssessmentQuestion::count());
    }
}
