<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UnitBarang;
use App\Services\Mobile\UnitBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitBarangController extends Controller
{
    public function index(Request $request, UnitBarangMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'kondisi' => ['nullable', Rule::in(['semua', ...array_keys(UnitBarang::DAFTAR_KONDISI)])],
            'status_unit' => ['nullable', Rule::in(['semua', ...array_keys(UnitBarang::DAFTAR_STATUS)])],
            'barang_id' => ['nullable', 'integer', 'exists:barang,id'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar(
                $filter,
                $request->user()->memilikiIzin('barang.kelola'),
            ),
        ]);
    }

    public function show(Request $request, UnitBarang $unitBarang, UnitBarangMobileService $service): JsonResponse
    {
        return $this->tanpaCache([
            'data' => [
                'unit' => $service->detail($unitBarang),
                'pilihan' => $service->pilihanForm($unitBarang),
                'hak_akses' => [
                    'dapat_kelola' => $request->user()->memilikiIzin('barang.kelola'),
                ],
            ],
        ]);
    }

    public function store(Request $request, UnitBarangMobileService $service): JsonResponse
    {
        $data = $request->validate($this->aturanValidasi(true));
        $unit = $service->tambah($data);
        $jumlah = (int) $data['jumlah_unit'];

        return $this->tanpaCache([
            'pesan' => $jumlah === 1
                ? 'Unit aset berhasil ditambahkan.'
                : $jumlah.' unit aset berhasil ditambahkan.',
            'data' => $service->detail($unit),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        UnitBarang $unitBarang,
        UnitBarangMobileService $service,
    ): JsonResponse {
        $service->ubah($unitBarang, $request->validate($this->aturanValidasi()));

        return $this->tanpaCache([
            'pesan' => 'Unit aset berhasil diperbarui.',
            'data' => $service->detail($unitBarang->fresh()),
        ]);
    }

    public function destroy(UnitBarang $unitBarang, UnitBarangMobileService $service): JsonResponse
    {
        $service->nonaktifkan($unitBarang);

        return $this->tanpaCache([
            'pesan' => 'Unit aset berhasil dinonaktifkan. Riwayat aset tetap tersimpan.',
        ]);
    }

    private function aturanValidasi(bool $tambah = false): array
    {
        return [
            'barang_id' => [$tambah ? 'required' : 'sometimes', 'integer', 'exists:barang,id'],
            'jumlah_unit' => [$tambah ? 'required' : 'sometimes', 'integer', 'min:1', 'max:100'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'nomor_seri' => ['nullable', 'string', 'max:120'],
            'merek' => ['nullable', 'string', 'max:120'],
            'tipe' => ['nullable', 'string', 'max:120'],
            'kondisi' => ['required', Rule::in(array_keys(UnitBarang::DAFTAR_KONDISI))],
            'status_unit' => ['required', Rule::in(array_keys(UnitBarang::DAFTAR_STATUS))],
            'tanggal_perolehan' => ['nullable', 'date_format:Y-m-d'],
            'tahun_perolehan' => [$tambah ? 'required' : 'nullable', 'integer', 'min:1900', 'max:2100'],
            'sumber_perolehan_barang_id' => [$tambah ? 'required' : 'nullable', 'integer', 'exists:sumber_perolehan_barang,id'],
            'harga_perolehan' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'keterangan' => ['nullable', 'string', 'max:5000'],
            'aktif' => ['required', 'boolean'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
