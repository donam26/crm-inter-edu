<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\RevenueReportService;
use Illuminate\Http\Request;

class RevenueReportController extends Controller
{
    public function __construct(private RevenueReportService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $report = $this->service->summary(
            from: $request->input('from'),
            to: $request->input('to'),
            viewer: $request->user(),
        );

        return view('revenues.report', compact('report'));
    }
}
