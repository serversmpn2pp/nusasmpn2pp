<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SaksiLaporanPembinaanSiswa;
use App\Services\Mobile\LaporkanKejadianMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LaporkanKejadianController extends Controller
{
    public function referensi(LaporkanKejadianMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->referensi()]);
    }

    public function store(Request $request, LaporkanKejadianMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'tanggal_kejadian' => ['required', 'date'],
            'waktu_kejadian' => ['nullable', 'date_format:H:i'],
            'tempat_kejadian' => ['nullable', 'string', 'max:150'],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
            'siswa_ids' => ['required', 'array', 'min:1', 'max:100'],
            'siswa_ids.*' => ['required', 'integer', 'distinct', Rule::exists('siswa', 'id')->where('aktif', true)],
            'kronologi' => ['required', 'string', 'max:20000'],
            'tindakan_awal' => ['nullable', 'string', 'max:10000'],
            'bukti_laporan' => ['nullable', 'array', 'max:5'],
            'bukti_laporan.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'keterangan_bukti' => ['nullable', 'string', 'max:500'],
            'daftar_saksi' => ['nullable', 'array', 'max:10'],
            'daftar_saksi.*.jenis_saksi' => ['nullable', Rule::in(array_keys(SaksiLaporanPembinaanSiswa::DAFTAR_JENIS))],
            'daftar_saksi.*.nama_saksi' => ['nullable', 'string', 'max:160'],
            'daftar_saksi.*.pernyataan' => ['nullable', 'string', 'max:5000'],
        ]);

        $hasil = $service->simpan(
            $data,
            $request->file('bukti_laporan', []),
            $request->user(),
        );

        return response()->json([
            'pesan' => $hasil['jumlah_laporan'] === 1
                ? 'Laporan kejadian berhasil dikirim ke BK untuk diperiksa.'
                : $hasil['jumlah_laporan'].' laporan siswa berhasil dibuat dan dikirim ke BK.',
            'data' => $hasil,
        ], 201);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
