<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BuktiLaporanPembinaanSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Services\Mobile\LaporanSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanSiswaWaliController extends Controller
{
    public function index(Request $request, LaporanSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(LaporanPembinaanSiswa::DAFTAR_STATUS)])],
            'tingkat' => ['nullable', Rule::in(['semua', ...array_keys(LaporanPembinaanSiswa::DAFTAR_TINGKAT)])],
            'jenis_laporan' => ['nullable', Rule::in(['semua', ...array_keys(LaporanPembinaanSiswa::DAFTAR_JENIS_LAPORAN)])],
            'status_verifikasi' => ['nullable', Rule::in(['semua', ...array_keys(LaporanPembinaanSiswa::DAFTAR_STATUS_VERIFIKASI)])],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftarGuruWali($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        LaporanPembinaanSiswa $laporanPembinaanSiswa,
        LaporanSiswaMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'data' => $service->rincianGuruWali($request->user(), $laporanPembinaanSiswa),
        ]);
    }

    public function evidence(
        Request $request,
        BuktiLaporanPembinaanSiswa $buktiLaporanPembinaanSiswa,
        LaporanSiswaMobileService $service,
    ): StreamedResponse {
        $service->pastikanLaporanGuruWali(
            $request->user(),
            $buktiLaporanPembinaanSiswa->laporanPembinaanSiswa,
        );
        abort_unless(Storage::disk('local')->exists($buktiLaporanPembinaanSiswa->lokasi_file), 404);

        return Storage::disk('local')->download(
            $buktiLaporanPembinaanSiswa->lokasi_file,
            $buktiLaporanPembinaanSiswa->nama_file_asli,
        );
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
