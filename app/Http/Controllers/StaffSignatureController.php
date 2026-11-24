<?php

namespace App\Http\Controllers;

use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Operations\StaffAdmin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A doctor's signature image: shown to Operations and to the doctor themselves. Nobody else. */
class StaffSignatureController extends Controller
{
    public function __invoke(Request $http, User $user, StaffAdmin $admin): StreamedResponse
    {
        $viewer = $http->user();
        abort_unless($viewer->isAdmin() || $viewer->id === $user->id, 403);

        $path = StaffProfile::where('user_id', $user->id)->value('signature_path');
        abort_unless($path && $admin->disk()->exists($path), 404);

        return $admin->disk()->response($path);
    }
}
