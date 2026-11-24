<?php

namespace App\Http\Controllers;

use App\Models\DoctorRequest;
use App\Models\DoctorRequestFile;
use App\Services\Doctor\DoctorRequestService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves a request attachment only to someone who may see the request. The stored path is never exposed. */
class DoctorRequestFileController extends Controller
{
    public function __invoke(Request $http, DoctorRequest $doctorRequest, DoctorRequestFile $file, DoctorRequestService $service): StreamedResponse
    {
        abort_unless($file->doctor_request_id === $doctorRequest->id, 404);
        abort_unless($service->canView($http->user(), $doctorRequest), 403);

        $disk = $service->disk();
        abort_unless($disk->exists($file->path), 404);

        return $disk->download($file->path, $file->original_name);
    }
}
