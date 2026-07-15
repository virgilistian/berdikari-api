<?php

namespace Modules\Tax\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Tax\Models\TaxBusinessProfile;
use Modules\Tax\Support\TaxGeneratorRegistry;

/**
 * @tags Pajak — Profil Usaha
 */
class TaxProfileController extends Controller
{
    public function __construct(
        private readonly TaxGeneratorRegistry $registry,
    ) {
    }

    private function businessId(Request $request): string
    {
        return Auth::user()?->business_id ?? (string) $request->input('business_id');
    }

    /**
     * Jenis usaha yang terdaftar (untuk dropdown/tab di frontend)
     */
    public function businessTypes(): JsonResponse
    {
        return response()->json(['data' => $this->registry->all()]);
    }

    /**
     * Profil pajak (per jenis usaha) milik bisnis ini
     */
    public function index(Request $request): JsonResponse
    {
        $profiles = TaxBusinessProfile::where('business_id', $this->businessId($request))->get();

        return response()->json(['data' => $profiles]);
    }

    /**
     * Buat / perbarui profil pajak untuk satu jenis usaha
     */
    public function update(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, array_keys(config('tax.generators', [])), true)) {
            return response()->json(['message' => "Jenis usaha \"{$type}\" tidak dikenali."], 422);
        }

        $data = $request->validate([
            'npwpd' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'config_overrides' => ['nullable', 'array'],
        ]);

        $profile = TaxBusinessProfile::updateOrCreate(
            ['business_id' => $this->businessId($request), 'business_type' => $type],
            $data,
        );

        return response()->json([
            'message' => 'Profil pajak berhasil disimpan.',
            'data' => $profile,
        ]);
    }
}
