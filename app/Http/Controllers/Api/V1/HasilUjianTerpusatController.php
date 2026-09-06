<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Services\Mobile\HasilUjianTerpusatMobileService;
use App\Services\Mobile\KoreksiUraianAsesmenKelasMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HasilUjianTerpusatController extends Controller
{
    public function index(Request $request, HasilUjianTerpusatMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(KegiatanUjianCbt::DAFTAR_STATUS)])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        HasilUjianTerpusatMobileService $service,
    ): JsonResponse {
        $filter = $request->validate([
            'jadwal_id' => ['nullable', 'integer', 'exists:jadwal_ujian_cbt,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in([
                'semua', 'tuntas', 'belum_tuntas', 'perlu_koreksi_otomatis',
                'perlu_koreksi_manual', 'belum_selesai',
            ])],
        ]);

        return $this->tanpaCache(['data' => $service->rincian($request->user(), $kegiatanUjianCbt, $filter)]);
    }

    public function terapkanNilai(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        HasilUjianTerpusatMobileService $service,
    ): JsonResponse {
        $hasil = $service->terapkanNilai($request->user(), $kegiatanUjianCbt, $jadwalUjianCbt);

        return $this->tanpaCache(['pesan' => $hasil['pesan'], 'data' => $hasil]);
    }

    public function finalisasi(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        HasilUjianTerpusatMobileService $service,
    ): JsonResponse {
        $hasil = $service->ubahFinalisasi(
            $request->user(),
            $kegiatanUjianCbt,
            $jadwalUjianCbt,
            true,
        );

        return $this->tanpaCache($hasil);
    }

    public function batalkanFinalisasi(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        HasilUjianTerpusatMobileService $service,
    ): JsonResponse {
        $hasil = $service->ubahFinalisasi(
            $request->user(),
            $kegiatanUjianCbt,
            $jadwalUjianCbt,
            false,
        );

        return $this->tanpaCache($hasil);
    }

    public function publikasikan(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        HasilUjianTerpusatMobileService $service,
    ): JsonResponse {
        $hasil = $service->ubahPublikasi(
            $request->user(),
            $kegiatanUjianCbt,
            $jadwalUjianCbt,
            true,
        );

        return $this->tanpaCache($hasil);
    }

    public function batalkanPublikasi(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        HasilUjianTerpusatMobileService $service,
    ): JsonResponse {
        $hasil = $service->ubahPublikasi(
            $request->user(),
            $kegiatanUjianCbt,
            $jadwalUjianCbt,
            false,
        );

        return $this->tanpaCache($hasil);
    }

    public function koreksiUraian(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        KoreksiUraianAsesmenKelasMobileService $service,
    ): JsonResponse {
        $filter = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', 'belum_dikoreksi', 'sudah_dikoreksi'])],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftarUjianTerpusat(
                $request->user(),
                $kegiatanUjianCbt,
                $jadwalUjianCbt,
                $filter,
            ),
        ]);
    }

    public function simpanKoreksiUraian(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        KoreksiUraianAsesmenKelasMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'skor' => ['required', 'array', 'min:1'],
            'skor.*.jawaban_id' => ['required', 'integer', 'distinct'],
            'skor.*.nilai' => ['nullable', 'numeric', 'min:0'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', 'belum_dikoreksi', 'sudah_dikoreksi'])],
        ]);
        $jumlah = $service->simpanUjianTerpusat(
            $request->user(),
            $kegiatanUjianCbt,
            $jadwalUjianCbt,
            $data['skor'],
        );

        return $this->tanpaCache([
            'pesan' => "{$jumlah} koreksi jawaban berhasil disimpan otomatis.",
            'data' => $service->daftarUjianTerpusat(
                $request->user(),
                $kegiatanUjianCbt,
                $jadwalUjianCbt,
                $data,
            ),
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
