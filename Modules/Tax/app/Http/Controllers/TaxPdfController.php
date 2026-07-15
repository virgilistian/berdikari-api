<?php

namespace Modules\Tax\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Modules\Tax\Models\TaxReport;
use Modules\Tax\Services\TaxPdfService;

/**
 * @tags Pajak — PDF
 */
class TaxPdfController extends Controller
{
    public function __construct(
        private readonly TaxPdfService $pdfService,
    ) {
    }

    public function show(Request $request, string $id): Response
    {
        $businessId = Auth::user()?->business_id ?? (string) $request->input('business_id');
        $report = TaxReport::where('business_id', $businessId)->findOrFail($id);

        return $this->pdfService->render($report);
    }
}
