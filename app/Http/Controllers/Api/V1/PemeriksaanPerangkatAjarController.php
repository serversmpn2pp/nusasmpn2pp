<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Models\PerangkatAjar;
use App\Services\Mobile\PemeriksaanPerangkatAjarMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PemeriksaanPerangkatAjarController extends Controller
{
    public function index(Request $request, PemeriksaanPerangkatAjarMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'semester' => ['nullable', 'integer', Rule::in([1, 2])],
            'kelengkapan' => ['nullable', Rule::in(['semua', 'lengkap', 'belum_lengkap'])],
            'status_dokumen' => ['nullable', Rule::in(['semua', 'belum_diunggah', 'menunggu_pemeriksaan', 'perlu_perbaikan', 'sudah_diperiksa'])],
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function showTeacher(
        Request $request,
        Pegawai $pegawai,
        PemeriksaanPerangkatAjarMobileService $service,
    ): JsonResponse {
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'semester' => ['nullable', 'integer', Rule::in([1, 2])],
        ]);

        return $this->tanpaCache([
            'data' => $service->rincianGuru($request->user(), $pegawai, $filter),
        ]);
    }

    public function showDocument(
        Request $request,
        PerangkatAjar $perangkatAjar,
        PemeriksaanPerangkatAjarMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'data' => $service->rincianDokumen($request->user(), $perangkatAjar),
        ]);
    }

    public function file(PerangkatAjar $perangkatAjar): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($perangkatAjar->lokasi_file), 404);

        return Storage::disk('local')->download(
            $perangkatAjar->lokasi_file,
            $perangkatAjar->nama_file_asli,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function update(
        Request $request,
        PerangkatAjar $perangkatAjar,
        PemeriksaanPerangkatAjarMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'status' => ['required', Rule::in(['perlu_perbaikan', 'sudah_diperiksa'])],
            'catatan_pemeriksa' => ['nullable', 'required_if:status,perlu_perbaikan', 'string'],
        ], [
            'catatan_pemeriksa.required_if' => 'Catatan pemeriksa wajib diisi jika dokumen perlu diperbaiki.',
        ]);
        $service->simpanPemeriksaan($request->user(), $perangkatAjar, $data);

        return $this->tanpaCache([
            'pesan' => 'Hasil pemeriksaan perangkat ajar berhasil disimpan.',
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
