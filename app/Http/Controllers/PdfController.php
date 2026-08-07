<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PdfController extends Controller
{
    /**
     * Generate and download a sick note PDF.
     */
    public function sickNote(Request $request, Consultation $consultation)
    {
        $user = Auth::user();

        if ($user->id !== $consultation->doctor_id && $user->id !== $consultation->patient_id) {
            abort(403, 'Unauthorized access to this consultation.');
        }

        $consultation->load(['doctor.doctorProfile', 'patient']);

        $pdf = Pdf::loadView('pdf.sick-note', [
            'consultation' => $consultation,
            'dateFrom' => $request->query('from', $consultation->started_at?->format('Y-m-d')),
            'dateTo' => $request->query('to', $consultation->completed_at?->format('Y-m-d') ?? $consultation->started_at?->format('Y-m-d')),
            'showDiagnosis' => $request->boolean('diagnosis', false),
        ])->setPaper('a4');

        return $pdf->download("sick-note-{$consultation->id}.pdf");
    }

    /**
     * Generate and download a medical certificate PDF.
     */
    public function medicalCertificate(Request $request, Consultation $consultation)
    {
        $user = Auth::user();

        if ($user->id !== $consultation->doctor_id && $user->id !== $consultation->patient_id) {
            abort(403, 'Unauthorized access to this consultation.');
        }

        $consultation->load(['doctor.doctorProfile', 'patient']);

        $pdf = Pdf::loadView('pdf.medical-certificate', [
            'consultation' => $consultation,
            'findings' => $request->query('findings', ''),
            'recommendations' => $request->query('recommendations', ''),
            'validFrom' => $request->query('from', $consultation->started_at?->format('Y-m-d')),
            'validTo' => $request->query('to', $consultation->started_at?->addDays(7)->format('Y-m-d')),
        ])->setPaper('a4');

        return $pdf->download("medical-certificate-{$consultation->id}.pdf");
    }
}
