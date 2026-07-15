<?php

namespace Modules\Tax\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Tax\Models\TaxBusinessProfile;
use Modules\Tax\Models\TaxReport;
use Modules\Tax\Services\TaxGenerationService;

/**
 * @tags Pajak — Laporan
 */
class TaxReportController extends Controller
{
    public function __construct(
        private readonly TaxGenerationService $generationService,
    ) {
    }

    private function businessId(Request $request): string
    {
        return Auth::user()?->business_id ?? (string) $request->input('business_id');
    }

    /**
     * Riwayat laporan pajak
     *
     * Filter opsional: `business_type`, `period_year`.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TaxReport::query()->where('business_id', $this->businessId($request));

        if ($request->filled('business_type')) {
            $query->where('business_type', $request->input('business_type'));
        }

        if ($request->filled('period_year')) {
            $query->where('period_year', $request->input('period_year'));
        }

        return response()->json([
            'data' => $query->orderByDesc('period_year')->orderByDesc('period_month')->get(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $report = TaxReport::with('entries')
            ->where('business_id', $this->businessId($request))
            ->findOrFail($id);

        return response()->json(['data' => $report]);
    }

    /**
     * Buat / hasilkan ulang laporan pajak bulanan
     *
     * Meng-upsert draft laporan untuk bulan/tahun/jenis usaha yang dipilih —
     * hasilnya langsung menjadi pratinjau yang bisa diedit di frontend.
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_type' => ['required', 'string'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $businessId = $this->businessId($request);

        $profile = TaxBusinessProfile::query()
            ->where('business_id', $businessId)
            ->where('business_type', $data['business_type'])
            ->first();

        if (! $profile) {
            return response()->json([
                'message' => 'Profil pajak untuk jenis usaha ini belum diatur. Lengkapi dahulu di Pengaturan > Pajak.',
            ], 422);
        }

        $report = $this->generationService->generate(
            profile: $profile,
            month: $data['month'],
            year: $data['year'],
            generatedBy: (string) Auth::id(),
        );

        return response()->json([
            'message' => 'Data pajak berhasil dibuat.',
            'data' => $report,
        ]);
    }

    /**
     * Edit manual entri laporan
     *
     * Body: `entries[]` berisi `day_number` + salah satu dari `sales`,
     * `ticket_qty`, `ticket_price`. Set `finalize=true` untuk menyimpan
     * laporan sebagai final.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $report = TaxReport::where('business_id', $this->businessId($request))->findOrFail($id);

        $data = $request->validate([
            'entries' => ['sometimes', 'array'],
            'entries.*.day_number' => ['required_with:entries', 'integer'],
            'entries.*.sales' => ['nullable', 'numeric', 'min:0'],
            'entries.*.ticket_qty' => ['nullable', 'integer', 'min:0'],
            'entries.*.ticket_price' => ['nullable', 'numeric', 'min:0'],
            'finalize' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['entries'])) {
            $report = $this->generationService->recompute($report, $data['entries']);
        }

        if ($request->boolean('finalize')) {
            $report->update(['status' => 'final']);
        }

        return response()->json([
            'message' => 'Laporan pajak berhasil disimpan.',
            'data' => $report->fresh('entries'),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $report = TaxReport::where('business_id', $this->businessId($request))->findOrFail($id);
        $report->delete();

        return response()->json(['message' => 'Laporan pajak berhasil dihapus.']);
    }
}
