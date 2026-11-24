<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Doctor\TimeTracker;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * CSV of a doctor's month for the invoice. A doctor downloads their own; Operations (admin) may pass ?doctor=ID.
 * Nobody else can read it.
 */
class DoctorTimeExportController extends Controller
{
    public function __invoke(Request $http, TimeTracker $tracker)
    {
        $me = $http->user();
        $doctor = $http->filled('doctor') ? User::findOrFail((int) $http->query('doctor')) : $me;

        abort_unless($tracker->canSeeSummary($me, $doctor), 403);

        $month = (string) $http->query('month', now(config('doctor.time.timezone'))->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw ValidationException::withMessages(['month' => 'Choose a month like 2026-11.']);
        }

        return response($tracker->csv($doctor, $month), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="time-'.$month.'-doctor-'.$doctor->id.'.csv"',
        ]);
    }
}
