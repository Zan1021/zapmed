<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WpPluginController extends Controller
{
    /**
     * Return current plugin version info for the WP auto-updater.
     * Called by partner WordPress sites to check for updates.
     */
    public function info()
    {
        return response()->json([
            'name' => 'Zapmed Booking Widget',
            'version' => config('zapmed-plugin.version', '1.0.0'),
            'homepage' => config('app.url') . '/partners',
            'download_url' => config('app.url') . '/downloads/zapmed-booking-widget.zip',
            'requires' => '5.8',
            'tested' => '6.7',
            'requires_php' => '7.4',
            'description' => 'Embed Zapmed online doctor consultation booking widgets on your WordPress site. Earn commissions as a Zapmed partner.',
            'changelog' => $this->getChangelog(),
        ]);
    }

    /**
     * Download the latest plugin ZIP.
     */
    public function download()
    {
        $zipPath = storage_path('app/downloads/zapmed-booking-widget.zip');

        if (!file_exists($zipPath)) {
            abort(404, 'Plugin download not available yet.');
        }

        return response()->download($zipPath, 'zapmed-booking-widget.zip');
    }

    private function getChangelog(): string
    {
        return '<h4>1.0.0</h4><ul><li>Initial release</li><li>Shortcode and Gutenberg block support</li><li>Button, card, and floating widget types</li><li>Partner referral tracking</li></ul>';
    }
}
