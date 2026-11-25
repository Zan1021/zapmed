<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A profile photo whose file is missing shows the initials, never a broken image. */
class UserAvatarUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_uploaded_photo_that_exists_gives_its_address(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('me.jpg')->store('avatars', 'public');
        $user = User::factory()->create(['role' => UserRole::Doctor, 'avatar_path' => $path]);

        $this->assertSame(asset('storage/'.$path), $user->avatar_url);
    }

    public function test_an_uploaded_photo_whose_file_is_missing_gives_nothing_so_the_initials_show(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => UserRole::Doctor, 'avatar_path' => 'avatars/gone.jpg']);

        $this->assertNull($user->avatar_url);
    }

    public function test_a_bundled_image_and_no_photo_behave_as_before(): void
    {
        $this->assertSame(asset('images/doctors/x.jpg'), User::factory()->create(['avatar_path' => 'images/doctors/x.jpg'])->avatar_url);
        $this->assertNull(User::factory()->create(['avatar_path' => null])->avatar_url);
    }
}