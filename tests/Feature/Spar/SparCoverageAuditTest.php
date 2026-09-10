<?php

namespace Tests\Feature\Spar;

use Tests\TestCase;

/**
 * Task 0.4 — dedicated SPAR test-coverage pass.
 *
 * A coverage GUARD: asserts that every SPAR capability area named in the spec
 * has at least one dedicated test class present. This turns "coverage" from an
 * incidental claim into an enforced invariant — if someone deletes or renames a
 * SPAR test suite, this fails loudly instead of coverage silently rotting.
 *
 * It does NOT re-assert behaviour (the mapped suites do that); it asserts the
 * suites EXIST and are wired into the SPAR test namespace.
 */
class SparCoverageAuditTest extends TestCase
{
    /**
     * Capability area (spec ref) => test class that must cover it.
     *
     * @return array<string, class-string>
     */
    private function coverageMap(): array
    {
        return [
            'Onboarding identity + dual-mode (FR-6)'        => SparOnboardingIdentityTest::class,
            'Pharmacist capture / Mode B (FR-6.3)'          => SparPharmacistCaptureTest::class,
            'Consent gate + no-login tracker (FR-7, FR-9)'  => SparTrackerConsentTest::class,
            'Dependant roll-up (FR-8)'                      => SparDependantRollupTest::class,
            'Messaging channels + reminders (FR-11, FR-12)' => SparMessagingTest::class,
            'Reminder gating rules'                         => SparReminderTest::class,
            'Reminder host parity (FR-11.2, AC-4)'          => SparRenewalParityTest::class,
            'Telehealth bridge (FR-3, FR-13)'               => SparTelehealthBridgeTest::class,
            'Admin consent section (FR-10, AC-10)'          => SparAdminConsentTest::class,
            'Order lifecycle'                               => SparOrderLifecycleTest::class,
            'Journey dispense tracking'                     => SparJourneyDispenseTest::class,
            'POPIA encryption + audit (NFR-1, AC-5)'        => SparPopiaAuditTest::class,
            'Phase 5.3 consent/onboarding/dependant (AC-7/8/9)' => SparPhase53VerificationTest::class,
            'Identity backfill from linked User (1.2)'      => SparBackfillIdentityTest::class,
            'WhatsApp channel — Meta Cloud API (Phase 6)'   => SparWhatsAppChannelTest::class,
        ];
    }

    public function test_every_spar_capability_area_has_a_dedicated_test_class(): void
    {
        $missing = [];

        foreach ($this->coverageMap() as $area => $class) {
            if (!class_exists($class)) {
                $missing[] = "{$area} → {$class}";
            }
        }

        $this->assertSame(
            [],
            $missing,
            "SPAR coverage gap — missing test class(es):\n" . implode("\n", $missing)
        );
    }

    public function test_coverage_map_covers_the_documented_spar_test_suite(): void
    {
        // Every *Test.php in tests/Feature/Spar (except this guard itself) must
        // appear in the coverage map, so new suites can't be added without also
        // being registered here.
        $dir = base_path('tests/Feature/Spar');
        $files = glob($dir . DIRECTORY_SEPARATOR . '*Test.php');

        $onDisk = collect($files)
            ->map(fn ($p) => 'Tests\\Feature\\Spar\\' . basename($p, '.php'))
            ->reject(fn ($c) => $c === self::class)
            ->sort()
            ->values()
            ->all();

        $mapped = collect($this->coverageMap())->values()->sort()->values()->all();

        $unregistered = array_values(array_diff($onDisk, $mapped));

        $this->assertSame(
            [],
            $unregistered,
            "These SPAR test suites exist but are not registered in the 0.4 coverage map:\n"
                . implode("\n", $unregistered)
        );
    }
}
