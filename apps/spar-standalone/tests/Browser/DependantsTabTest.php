<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Regression: clicking the Dependants tab on the staff patient-detail page must
 * NOT throw Livewire's "This page has expired" 419.
 *
 * Root cause (fixed): the spar-core package's Livewire components were only
 * auto-registered when routed to by class (the render request). On the
 * /livewire/update POST, ComponentRegistry couldn't resolve the component name
 * back to its class → ComponentNotFoundException → LivewireReleaseTokenMismatch
 * → 419. Fixed by explicitly registering the components in SparCoreServiceProvider.
 *
 * Drives a REAL headless Chrome against the running dev server (real demo data).
 */
class DependantsTabTest extends DuskTestCase
{
    public function test_login_and_click_dependants(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitFor('#email', 10)
                ->type('#email', 'superadmin@sparmeds.test')
                ->type('#password', 'Testing123!')
                ->press('Sign in')
                ->waitForText('SPAR Medication Management', 15)
                ->visit('/spar/patients/1')
                ->waitForText('What the patient sees', 15)
                ->click('@dependants-tab')
                ->pause(2000);

            $alert = null;
            try { $alert = $browser->driver->switchTo()->alert()->getText(); } catch (\Throwable $e) {}
            $this->assertNull($alert, 'Livewire "page expired" dialog appeared: ' . (string) $alert);

            $browser->assertSee('Dependants under this profile');
            $browser->assertSee('NAIDOO');
        });
    }
}
