<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Core — Bisnis
 */
class BusinessController extends Controller
{
    /**
     * Daftar bisnis yang dapat diakses pengguna yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $ids  = array_filter([$user?->business_id]);

        $businesses = empty($ids)
            ? collect()
            : Business::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $businesses]);
    }
}
