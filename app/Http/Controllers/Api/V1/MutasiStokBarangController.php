<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MutasiStokBarang;
use App\Services\Inventaris\ProsesMutasiStokBarang;
use App\Services\Mobile\StokBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MutasiStokBarangController extends Controller
{
    public function index(Request $request, StokBarangMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:160'],
            'jenis_mutasi' => ['nullable', Rule::in(['semua', ...array_keys(MutasiStokBarang::DAFTAR_JENIS)])],
            'barang_id' => ['nullable', 'integer', 'exists:barang,id'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftarMutasi($filter, $request->user()->memilikiIzin('barang.kelola')),
        ]);
    }

    public function show(MutasiStokBarang $mutasiStokBarang, StokBarangMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->detailMutasi($mutasiStokBarang)]);
    }

    public function store(
        Request $request,
        ProsesMutasiStokBarang $proses,
        StokBarangMobileService $service,
    ): JsonResponse {
        $data = $this->rapikanData($request->validate([
            'barang_id' => ['required', 'integer', 'exists:barang,id'],
            'lokasi_barang_id' => ['required', 'integer', 'exists:lokasi_barang,id'],
            'jenis_mutasi' => ['required', Rule::in(array_keys(MutasiStokBarang::DAFTAR_JENIS))],
            'kategori_mutasi' => ['required', Rule::in(array_keys(MutasiStokBarang::DAFTAR_KATEGORI))],
            'tanggal_mutasi' => ['required', 'date_format:Y-m-d'],
            'jumlah' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'referensi' => ['nullable', 'string', 'max:120'],
            'keterangan' => ['nullable', 'string'],
        ]));

        $mutasi = $proses->catat($data, $request->user()?->id);

        return $this->tanpaCache([
            'pesan' => 'Mutasi stok berhasil dicatat.',
            'data' => $service->detailMutasi($mutasi),
        ])->setStatusCode(201);
    }

    private function rapikanData(array $data): array
    {
        $data['referensi'] = filled($data['referensi'] ?? null) ? trim($data['referensi']) : null;
        $data['keterangan'] = filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null;

        return $data;
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
