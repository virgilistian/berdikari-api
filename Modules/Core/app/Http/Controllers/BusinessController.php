<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\IAM\Database\Seeders\RolePermissionSeeder;
use Modules\IAM\Http\Resources\UserResource;
use Spatie\Permission\Models\Role;

/**
 * @tags Core — Bisnis
 */
class BusinessController extends Controller
{
    /**
     * Daftar bisnis yang dapat diakses pengguna yang sedang login.
     *
     * Secara default hanya menampilkan bisnis berstatus aktif. Kirim
     * `?include_inactive=1` untuk menyertakan bisnis yang dinonaktifkan
     * (dipakai halaman manajemen bisnis agar bisa diaktifkan kembali).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Business::whereHas('users', fn ($q) => $q->where('user_id', $user?->id))
            ->orderBy('name');

        if (! $request->boolean('include_inactive')) {
            $query->where('status', 'active');
        }

        $businesses = $user
            ? $query->get(['id', 'name', 'type', 'code', 'status', 'logo_path'])
            : collect();

        return response()->json(['data' => $businesses]);
    }

    /**
     * Profil bisnis yang sedang aktif milik pengguna yang sedang login.
     */
    public function show(Request $request): JsonResponse
    {
        $business = Business::find($request->user()->business_id);

        if (! $business) {
            return response()->json(['message' => 'Data usaha tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $business]);
    }

    /**
     * Perbarui profil bisnis yang sedang aktif milik pengguna yang sedang login.
     */
    public function update(Request $request): JsonResponse
    {
        $business = Business::find($request->user()->business_id);

        if (! $business) {
            return response()->json(['message' => 'Data usaha tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'name'   => ['sometimes', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
        ]);

        $business->update($data);

        return response()->json([
            'data'    => $business,
            'message' => 'Data usaha berhasil diperbarui.',
        ]);
    }

    /**
     * Buat bisnis baru. Pembuat otomatis menjadi anggota dan mendapat peran
     * Pemilik Usaha (business-owner) untuk bisnis ini, lalu bisnis baru
     * langsung dijadikan bisnis aktif.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'type'    => ['required', 'string', 'max:100'],
            'code'    => ['required', 'string', 'max:50', 'unique:businesses,code'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone'   => ['nullable', 'string', 'max:30'],
        ], [
            'name.required' => 'Nama usaha wajib diisi.',
            'type.required' => 'Jenis usaha wajib dipilih.',
            'code.required' => 'Kode usaha wajib diisi.',
            'code.unique'   => 'Kode usaha sudah digunakan, pilih kode lain.',
        ]);

        $user = $request->user();

        $business = Business::create($data + ['status' => 'active']);
        $business->users()->attach($user->id);

        // Provision the business-owner role + its standard permission set for
        // this new team (roles are business-scoped; permissions are global —
        // reuses the canonical list already defined in RolePermissionSeeder
        // instead of duplicating it here).
        setPermissionsTeamId($business->id);
        $role = Role::firstOrCreate(['name' => 'business-owner', 'guard_name' => 'web', 'business_id' => $business->id]);
        $role->syncPermissions(RolePermissionSeeder::ROLE_PERMISSIONS['business-owner']);
        $user->assignRole($role);

        $user->business_id = $business->id;
        $user->save();

        return response()->json([
            'data'    => $business,
            'message' => 'Bisnis baru berhasil dibuat.',
        ], 201);
    }

    /**
     * Detail salah satu bisnis milik pengguna yang sedang login.
     */
    public function showOne(Request $request, Business $business): JsonResponse
    {
        if ($fail = $this->memberOrFail($request, $business)) {
            return $fail;
        }

        return response()->json(['data' => $business]);
    }

    /**
     * Perbarui salah satu bisnis milik pengguna yang sedang login (tidak
     * harus bisnis yang sedang aktif).
     */
    public function updateOne(Request $request, Business $business): JsonResponse
    {
        if ($fail = $this->memberOrFail($request, $business)) {
            return $fail;
        }

        $data = $request->validate([
            'name'    => ['sometimes', 'string', 'max:255'],
            'type'    => ['sometimes', 'string', 'max:100'],
            'code'    => ['sometimes', 'string', 'max:50', Rule::unique('businesses', 'code')->ignore($business->id)],
            'address' => ['nullable', 'string', 'max:500'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'tax_id'  => ['nullable', 'string', 'max:255'],
            'status'  => ['sometimes', 'in:active,inactive'],
        ], [
            'code.unique' => 'Kode usaha sudah digunakan, pilih kode lain.',
        ]);

        $business->update($data);

        return response()->json([
            'data'    => $business,
            'message' => 'Data usaha berhasil diperbarui.',
        ]);
    }

    /**
     * Nonaktifkan bisnis (dapat dipulihkan lewat "Perbarui" -> status aktif).
     * Kirim `?permanent=1` untuk menghapus permanen — hanya diizinkan jika
     * bisnis ini belum punya cabang, anggota lain, atau data transaksi.
     */
    public function destroy(Request $request, Business $business): JsonResponse
    {
        if ($fail = $this->memberOrFail($request, $business)) {
            return $fail;
        }

        $user = $request->user();

        if ($request->boolean('permanent')) {
            $hasData = $business->users()->count() > 1
                || DB::table('branches')->where('business_id', $business->id)->exists()
                || DB::table('finance_entries')->where('business_id', $business->id)->exists()
                || DB::table('sale_orders')->where('business_id', $business->id)->exists()
                || DB::table('inventories')->where('business_id', $business->id)->exists()
                || DB::table('daily_stocks')->where('business_id', $business->id)->exists();

            if ($hasData) {
                return response()->json([
                    'message' => 'Bisnis ini masih memiliki cabang, anggota, atau data transaksi. Nonaktifkan saja — tidak bisa dihapus permanen.',
                ], 422);
            }

            $business->delete();

            return response()->json(['message' => 'Bisnis berhasil dihapus permanen.']);
        }

        $business->update(['status' => 'inactive']);

        // If the deactivated business was the caller's active one, switch
        // them to another business they still belong to (or none).
        if ($user->business_id === $business->id) {
            $next = Business::whereHas('users', fn ($q) => $q->where('user_id', $user->id))
                ->where('status', 'active')
                ->where('id', '!=', $business->id)
                ->first();

            $user->business_id = $next?->id;
            $user->save();

            setPermissionsTeamId($user->business_id);
        }

        return response()->json([
            'data'    => $business,
            'message' => 'Bisnis dinonaktifkan.',
        ]);
    }

    /**
     * Beralih bisnis aktif. Bisnis tujuan harus salah satu bisnis milik
     * pengguna dan berstatus aktif. Setelah beralih, semua data (keuangan,
     * stok, laporan, peran & izin) otomatis mengikuti bisnis yang dipilih.
     */
    public function switch(Request $request, Business $business): JsonResponse
    {
        if ($fail = $this->memberOrFail($request, $business)) {
            return $fail;
        }

        if ($business->status !== 'active') {
            return response()->json(['message' => 'Bisnis ini sedang nonaktif.'], 422);
        }

        $user = $request->user();
        $user->business_id = $business->id;
        $user->save();

        setPermissionsTeamId($business->id);

        return response()->json([
            'data'    => new UserResource($user->fresh()),
            'message' => "Berhasil beralih ke {$business->name}.",
        ]);
    }

    /**
     * Unggah / ganti logo bisnis. Gambar otomatis diubah ukurannya.
     */
    public function uploadLogo(Request $request, Business $business): JsonResponse
    {
        if ($fail = $this->memberOrFail($request, $business)) {
            return $fail;
        }

        $request->validate([
            'file' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:512'],
        ], [
            'file.required' => 'Berkas logo wajib dipilih.',
            'file.image'    => 'Berkas harus berupa gambar.',
            'file.mimes'    => 'Format gambar harus PNG atau JPG.',
            'file.max'      => 'Ukuran gambar maksimal 512 KB.',
        ]);

        $file = $request->file('file');

        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath())->scaleDown(width: 400);
        $encoded = $image->encode();

        $disk = 's3';
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $path = "businesses/{$business->id}/logo.{$extension}";

        if ($business->logo_path && Storage::disk($business->logo_disk ?? $disk)->exists($business->logo_path)) {
            Storage::disk($business->logo_disk ?? $disk)->delete($business->logo_path);
        }

        Storage::disk($disk)->put($path, (string) $encoded);

        $business->update(['logo_disk' => $disk, 'logo_path' => $path]);

        return response()->json([
            'data'    => $business,
            'message' => 'Logo berhasil diunggah.',
        ]);
    }

    private function memberOrFail(Request $request, Business $business): ?JsonResponse
    {
        $isMember = $business->users()->where('user_id', $request->user()->id)->exists();

        return $isMember ? null : response()->json(['message' => 'Data usaha tidak ditemukan.'], 404);
    }
}
