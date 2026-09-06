<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PengajuanBarang;
use App\Services\Inventaris\ProsesPengajuanBarang;
use App\Services\Mobile\PengajuanBarangSayaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengajuanBarangSayaController extends Controller
{
    public function index(Request $request, PengajuanBarangSayaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::in(['semua', ...array_keys(PengajuanBarang::DAFTAR_STATUS)])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->respons(['data' => $service->daftar($this->pegawaiId($request), $filter)]);
    }

    public function katalog(Request $request, PengajuanBarangSayaMobileService $service): JsonResponse
    {
        $this->pegawaiId($request);
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->respons(['data' => $service->katalog($filter)]);
    }

    public function store(
        Request $request,
        ProsesPengajuanBarang $proses,
        PengajuanBarangSayaMobileService $service,
    ): JsonResponse {
        $pegawaiId = $this->pegawaiId($request);
        $data = $request->validate([
            'barang_id' => ['required', 'integer', 'exists:barang,id'],
            'jumlah' => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'tanggal_dibutuhkan' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'rencana_kembali' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_dibutuhkan'],
            'tujuan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $data['tujuan'] = trim($data['tujuan']);
        $pengajuan = $proses->ajukan($pegawaiId, $data, $request->user()?->id);

        return $this->respons([
            'pesan' => 'Pengajuan berhasil dikirim kepada petugas inventaris.',
            'data' => $service->detail($pengajuan),
        ])->setStatusCode(201);
    }

    public function show(
        Request $request,
        PengajuanBarang $pengajuanBarang,
        PengajuanBarangSayaMobileService $service,
    ): JsonResponse {
        $this->pastikanMilik($request, $pengajuanBarang);

        return $this->respons(['data' => $service->detail($pengajuanBarang)]);
    }

    public function batalkan(
        Request $request,
        PengajuanBarang $pengajuanBarang,
        ProsesPengajuanBarang $proses,
        PengajuanBarangSayaMobileService $service,
    ): JsonResponse {
        $pegawaiId = $this->pegawaiId($request);
        $this->pastikanMilik($request, $pengajuanBarang);
        $pengajuan = $proses->batalkan($pengajuanBarang, $pegawaiId, $request->user()?->id);

        return $this->respons([
            'pesan' => 'Pengajuan berhasil dibatalkan.',
            'data' => $service->detail($pengajuan),
        ]);
    }

    private function pegawaiId(Request $request): int
    {
        abort_unless($request->user()?->akunPegawai() && $request->user()->pegawai_id, 403);

        return (int) $request->user()->pegawai_id;
    }

    private function pastikanMilik(Request $request, PengajuanBarang $pengajuan): void
    {
        abort_unless($pengajuan->pegawai_id === $this->pegawaiId($request), 403);
    }

    private function respons(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
