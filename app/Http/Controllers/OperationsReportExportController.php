<?php

namespace App\Http\Controllers;

use App\Services\Operations\OperationsReports;
use Illuminate\Http\Request;

/** CSV download of one Operations report. Admin only (route middleware and the service both check). */
class OperationsReportExportController extends Controller
{
    public function __invoke(Request $http, string $report, OperationsReports $reports)
    {
        $filters = $http->only(['month', 'from', 'to', 'product_id', 'state', 'status']);
        $csv = $reports->csv($http->user(), $report, array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^a-z_]/', '', $report).'-'.now()->format('Ymd').'.csv"',
        ]);
    }
}
