<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Core — Cabang
 */
class BranchController extends Controller
{
    /**
     * Daftar cabang milik bisnis pengguna yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $branches = Branch::where('business_id', $request->user()->business_id)
            ->orderBy('name')
            ->get(['id', 'business_id', 'name', 'address']);

        return response()->json(['data' => $branches]);
    }

    /**
     * Tambah cabang baru untuk bisnis pengguna yang sedang login.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = Branch::create([
            'business_id' => $request->user()->business_id,
            ...$data,
        ]);

        return response()->json([
            'data'    => $branch,
            'message' => 'Cabang berhasil ditambahkan.',
        ], 201);
    }

    /**
     * Perbarui cabang.
     */
    public function update(Request $request, Branch $branch): JsonResponse
    {
        if ($branch->business_id !== $request->user()->business_id) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'name'    => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $branch->update($data);

        return response()->json([
            'data'    => $branch,
            'message' => 'Cabang berhasil diperbarui.',
        ]);
    }

    /**
     * Hapus cabang.
     */
    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        if ($branch->business_id !== $request->user()->business_id) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $branch->delete();

        return response()->json(['message' => 'Cabang berhasil dihapus.']);
    }
}
