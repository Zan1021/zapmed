<?php

namespace App\Http\Controllers;

use App\Models\BloodTestRequest;
use App\Models\BloodTestResult;
use App\Services\Doctor\BloodTestService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a blood-test result or lab form only to the patient or a doctor who treats them. Not to Operations, not to
 * coaches. The stored path is never exposed.
 */
class BloodTestFileController extends Controller
{
    public function result(Request $http, BloodTestRequest $bloodTest, BloodTestResult $result, BloodTestService $service): StreamedResponse
    {
        abort_unless($result->blood_test_request_id === $bloodTest->id, 404);
        abort_unless($service->canView($http->user(), $bloodTest), 403);
        abort_unless($service->disk()->exists($result->path), 404);

        return $service->disk()->download($result->path, $result->original_name);
    }

    public function form(Request $http, BloodTestRequest $bloodTest, BloodTestService $service): StreamedResponse
    {
        abort_unless($service->canView($http->user(), $bloodTest), 403);
        abort_unless($bloodTest->lab_form_path && $service->disk()->exists($bloodTest->lab_form_path), 404);

        return $service->disk()->download($bloodTest->lab_form_path, $bloodTest->lab_form_name ?: 'lab-form');
    }
}
