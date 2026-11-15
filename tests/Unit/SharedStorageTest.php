<?php

namespace Tests\Unit;

use App\Support\SharedStorage;
use PHPUnit\Framework\TestCase;

class SharedStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/shared-storage-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/releases/1/storage/app/public', 0777, true);
        mkdir($this->root . '/shared/storage/app/public/blog', 0777, true);
        file_put_contents($this->root . '/shared/storage/app/public/blog/a.webp', 'img');
        file_put_contents($this->root . '/releases/1/storage/app/public/.gitignore', "*\n");
    }

    protected function tearDown(): void
    {
        $this->rm($this->root);
        parent::tearDown();
    }

    private function rm(string $p): void
    {
        if (is_link($p) || is_file($p)) {
            @unlink($p);
            return;
        }
        foreach (array_diff(scandir($p) ?: [], ['.', '..']) as $e) {
            $this->rm("$p/$e");
        }
        @rmdir($p);
    }

    private function requireSymlinks(): void
    {
        $probe = $this->root . '/probe';
        set_error_handler(fn () => true);
        $ok = symlink($this->root . '/shared', $probe);
        restore_error_handler();
        if (! $ok) {
            $this->markTestSkipped('Symlinks not permitted on this machine (Windows without developer mode).');
        }
        @unlink($probe);
    }

    public function test_links_fresh_release_to_shared_folder(): void
    {
        $this->requireSymlinks();
        $rel = $this->root . '/releases/1/storage/app/public';
        $sh = $this->root . '/shared/storage/app/public';

        $this->assertTrue(SharedStorage::link($rel, $sh));
        $this->assertTrue(is_link($rel));
        $this->assertFileExists($rel . '/blog/a.webp');
    }

    public function test_is_idempotent_and_ignores_stale_symlinks(): void
    {
        $this->requireSymlinks();
        $rel = $this->root . '/releases/1/storage/app/public';
        $sh = $this->root . '/shared/storage/app/public';
        symlink($sh . '/blog', $rel . '/blog');

        $this->assertTrue(SharedStorage::link($rel, $sh));
        $this->assertTrue(SharedStorage::link($rel, $sh));
        $this->assertFileExists($rel . '/blog/a.webp');
    }

    public function test_moves_real_uploads_into_shared_instead_of_losing_them(): void
    {
        $this->requireSymlinks();
        $rel = $this->root . '/releases/1/storage/app/public';
        $sh = $this->root . '/shared/storage/app/public';
        mkdir($rel . '/avatars');
        file_put_contents($rel . '/avatars/me.png', 'x');

        $this->assertTrue(SharedStorage::link($rel, $sh));
        $this->assertFileExists($sh . '/avatars/me.png');
        $this->assertFileExists($rel . '/avatars/me.png');
    }

    public function test_does_nothing_without_shared_folder(): void
    {
        $rel = $this->root . '/releases/1/storage/app/public';

        $this->assertFalse(SharedStorage::link($rel, $this->root . '/nope'));
        $this->assertFalse(is_link($rel));
    }

    public function test_shared_path_only_resolved_on_forge_layout(): void
    {
        $this->assertSame(
            realpath($this->root) . '/shared/storage/app/public',
            SharedStorage::sharedPublicFor($this->root . '/releases/1')
        );
        $this->assertNull(SharedStorage::sharedPublicFor($this->root));
    }
}
