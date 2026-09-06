<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PengajuanBarang;
use App\Services\Inventaris\ProsesPengajuanBarang;
use App\Services\Mobile\PengajuanBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengajuanBarangController extends Controller
{
    public function index(Request $request, PengajuanBarangMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', Rule::in(['semua', ...array_keys(PengajuanBarang::DAFTAR_JENIS)])],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(PengajuanBarang::DAFTAR_STATUS)])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->respons(['data' => $service->daftar($filter)]);
    }

    public function show(PengajuanBarang $pengajuanBarang, PengajuanBarangMobileService $service): JsonResponse
    {
        return $this->respons(['data' => $service->detail($pengajuanBarang)]);
    }

    public function penuhi(
        Request $request,
        PengajuanBarang $pengajuanBarang,
        ProsesPengajuanBarang $proses,
        PengajuanBarangMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'unit_barang_ids' => ['nullable', 'array', 'max:100'],
            'unit_barang_ids.*' => ['integer', 'distinct', 'exists:unit_barang,id'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'catatan_petugas' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['catatan_petugas'] = filled($data['catatan_petugas'] ?? null)
            ? trim($data['catatan_petugas'])
            : null;
        $pengajuan = $proses->penuhi($pengajuanBarang, $data, (int) $request->user()->id);

        return $this->respons([
            'pesan' => 'Pengajuan dipenuhi dan transaksi barang berhasil dicatat.',
            'data' => $service->detail($pengajuan),
        ]);
    }

    public function tolak(
        Request $request,
        PengajuanBarang $pengajuanBarang,
        ProsesPengajuanBarang $proses,
        PengajuanBarangMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'catatan_petugas' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $pengajuan = $proses->tolak(
            $pengajuanBarang,
            trim($data['catatan_petugas']),
            (int) $request->user()->id,
        );

        return $this->respons([
            'pesan' => 'Pengajuan barang berhasil ditolak.',
            'data' => $service->detail($pengajuan),
        ]);
    }

    private function respons(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
