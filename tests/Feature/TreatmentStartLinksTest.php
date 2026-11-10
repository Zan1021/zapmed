<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Start your assessment" buttons must lead into the new /start/{slug} journey for enabled treatments. */
class TreatmentStartLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_treatments_link_to_the_new_journey(): void
    {
        config()->set('onboarding.enabled_slugs', ['acne-treatment']);

        $this->assertSame(url('/start/acne-treatment'), treatment_start_url('acne-treatment', 'skincare'));
    }

    public function test_other_treatments_keep_the_legacy_booking_page(): void
    {
        config()->set('onboarding.enabled_slugs', ['acne-treatment']);

        $this->assertStringContainsString('/book', treatment_start_url('thrush-treatment', 'general-health'));
        $this->assertStringNotContainsString('/start/', treatment_start_url('thrush-treatment', 'general-health'));
    }

    public function test_treatment_pages_render_the_new_link(): void
    {
        foreach (['acne-treatment', 'weight-loss', 'uti-treatment'] as $slug) {
            $html = $this->get('/'.$slug)->assertOk()->getContent();
            $this->assertStringContainsString('/start/'.$slug, $html, "{$slug} page has no link to the new journey");
        }
    }
}
