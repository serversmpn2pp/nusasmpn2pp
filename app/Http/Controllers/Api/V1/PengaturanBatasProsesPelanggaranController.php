<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TahunPelajaran;
use App\Services\Mobile\PengaturanBatasProsesPelanggaranMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengaturanBatasProsesPelanggaranController extends Controller
{
    public function index(Request $request, PengaturanBatasProsesPelanggaranMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'diatur', 'bawaan'])],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($filter)]);
    }

    public function update(
        Request $request,
        TahunPelajaran $tahunPelajaran,
        PengaturanBatasProsesPelanggaranMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'batas_hari_pemeriksaan_bk' => ['required', 'integer', 'min:1', 'max:30'],
            'batas_hari_persetujuan' => ['required', 'integer', 'min:1', 'max:30'],
            'pengingat_hari_sebelum_batas' => ['required', 'integer', 'min:0', 'max:29'],
            'notifikasi_pengingat_aktif' => ['required', 'boolean'],
            'notifikasi_terlambat_aktif' => ['required', 'boolean'],
        ]);

        if ((int) $data['pengingat_hari_sebelum_batas'] >= min(
            (int) $data['batas_hari_pemeriksaan_bk'],
            (int) $data['batas_hari_persetujuan'],
        )) {
            throw ValidationException::withMessages([
                'pengingat_hari_sebelum_batas' => 'Pengingat harus lebih kecil daripada batas hari terpendek.',
            ]);
        }

        $service->simpan($tahunPelajaran, $data, $request->user()?->id);

        return $this->tanpaCache([
            'pesan' => 'Batas proses pelanggaran tahun '.$tahunPelajaran->nama.' berhasil disimpan.',
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
