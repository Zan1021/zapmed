<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The private "local" disk (patient records, blood-test results, doctor attachments, signatures)
 * can be pointed outside the release folder with LOCAL_DISK_ROOT, so a zero-downtime deploy does not
 * wipe it. Without the setting it must stay exactly where it always was.
 */
class PrivateFilesRootTest extends TestCase
{
    public function test_private_disk_defaults_to_the_normal_storage_folder(): void
    {
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
    }

    public function test_private_disk_follows_local_disk_root_when_set(): void
    {
        $dir = sys_get_temp_dir() . '/zm-private-' . uniqid();
        putenv("LOCAL_DISK_ROOT={$dir}");
        $_ENV['LOCAL_DISK_ROOT'] = $dir;
        $_SERVER['LOCAL_DISK_ROOT'] = $dir;

        try {
            $config = require base_path('config/filesystems.php');
            $this->assertSame($dir, $config['disks']['local']['root']);
        } finally {
            putenv('LOCAL_DISK_ROOT');
            unset($_ENV['LOCAL_DISK_ROOT'], $_SERVER['LOCAL_DISK_ROOT']);
        }
    }
}
