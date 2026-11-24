<?php

namespace App\Http\Controllers;

use App\Models\HealthRecord;
use App\Services\Patient\HealthRecords;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves a health record only to the patient or a doctor who treats them. The stored path is never exposed. */
class HealthRecordFileController extends Controller
{
    public function __invoke(Request $http, HealthRecord $record, HealthRecords $service): StreamedResponse
    {
        abort_unless($service->canView($http->user(), $record), 403);
        abort_unless($service->disk()->exists($record->path), 404);

        return $service->disk()->download($record->path, $record->original_name);
    }
}
