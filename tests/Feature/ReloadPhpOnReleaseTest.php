<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/** After a deploy PHP is reloaded once so the code cache does not fill up with old releases. */
class ReloadPhpOnReleaseTest extends TestCase
{
    private string $marker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marker = storage_path('framework/php-reloaded-for-release');
        File::delete($this->marker);
        config(['operations.server.reload_command' => ['sudo', '-n', '/usr/sbin/service', 'php8.2-fpm', 'reload']]);
    }

    protected function tearDown(): void
    {
        File::delete($this->marker);
        parent::tearDown();
    }

    public function test_it_does_nothing_unless_switched_on(): void
    {
        Process::fake();
        config(['operations.server.reload_php_on_release' => false]);

        $this->artisan('server:reload-php-on-release')->assertSuccessful();

        Process::assertNothingRan();
        $this->assertFileDoesNotExist($this->marker);
    }

    public function test_a_new_release_is_reloaded_once_and_remembered(): void
    {
        Process::fake();
        config(['operations.server.reload_php_on_release' => true]);

        $this->artisan('server:reload-php-on-release')->assertSuccessful();
        $this->artisan('server:reload-php-on-release')->assertSuccessful();

        Process::assertRanTimes(fn ($p) => $p->command === ['sudo', '-n', '/usr/sbin/service', 'php8.2-fpm', 'reload'], 1);
        $this->assertSame(realpath(base_path()), trim(File::get($this->marker)));
    }

    public function test_a_different_release_is_reloaded_again(): void
    {
        Process::fake();
        config(['operations.server.reload_php_on_release' => true]);
        File::put($this->marker, '/home/forge/zapdev.co.za/releases/1');

        $this->artisan('server:reload-php-on-release')->assertSuccessful();

        Process::assertRanTimes(fn ($p) => true, 1);
    }

    public function test_a_failed_reload_is_not_remembered_so_it_is_tried_again(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'sudo: a password is required', exitCode: 1)]);
        config(['operations.server.reload_php_on_release' => true]);

        $this->artisan('server:reload-php-on-release')->assertFailed();
        $this->assertFileDoesNotExist($this->marker);

        Process::fake();
        $this->artisan('server:reload-php-on-release')->assertSuccessful();
        $this->assertFileExists($this->marker);
    }

    public function test_it_is_off_by_default_and_scheduled_every_minute(): void
    {
        $this->assertMatchesRegularExpression('/RELOAD_PHP_ON_RELEASE.{1,8}false/', file_get_contents(base_path('config/operations.php')));
        $this->assertStringContainsString("server:reload-php-on-release')->everyMinute()", file_get_contents(base_path('routes/console.php')));
    }
}