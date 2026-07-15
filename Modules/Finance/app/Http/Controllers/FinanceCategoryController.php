<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Finance\Models\FinanceCategory;

/**
 * @tags Finance — Kategori
 */
class FinanceCategoryController extends Controller
{
    /**
     * Daftar kategori keuangan
     *
     * Filter opsional: `type` (income/expense).
     */
    public function index(Request $request): JsonResponse
    {
        $query = FinanceCategory::query();

        if ($request->filled('type')) {
            $query->type($request->string('type'));
        }

        return response()->json([
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    /**
     * Buat kategori baru
     */
    public function store(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('finance_categories')->where(fn ($q) => $q
                    ->where('business_id', $businessId)
                    ->where('type', $request->input('type'))),
            ],
            'type' => 'required|in:income,expense',
        ], [
            'name.unique' => 'Kategori dengan nama ini sudah ada.',
        ]);

        $category = FinanceCategory::create($data);

        return response()->json([
            'message' => 'Kategori berhasil dibuat.',
            'data'    => $category,
        ], 201);
    }

    /**
     * Perbarui kategori
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = FinanceCategory::findOrFail($id);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('finance_categories')->where(fn ($q) => $q
                    ->where('business_id', $category->business_id)
                    ->where('type', $category->type))
                    ->ignore($category->id),
            ],
        ], [
            'name.unique' => 'Kategori dengan nama ini sudah ada.',
        ]);

        $category->update($data);

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'data'    => $category,
        ]);
    }

    /**
     * Hapus kategori
     */
    public function destroy(string $id): JsonResponse
    {
        $category = FinanceCategory::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }
}
