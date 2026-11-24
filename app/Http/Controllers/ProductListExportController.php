<?php

namespace App\Http\Controllers;

use App\Services\Operations\ProductCatalogIO;
use Illuminate\Http\Request;

/** CSV download of the product catalogue. Admin only (route middleware and the service both check). */
class ProductListExportController extends Controller
{
    public function __invoke(Request $http, ProductCatalogIO $io)
    {
        $csv = $io->export($http->user(), $http->boolean('active'));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products-'.now()->format('Ymd').'.csv"',
        ]);
    }
}
