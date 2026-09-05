<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\RuangKegiatanUjianCbt;
use App\Services\Mobile\PelaksanaanUjianTerpusatMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PelaksanaanUjianTerpusatController extends Controller
{
    public function index(Request $request, PelaksanaanUjianTerpusatMobileService $service): JsonResponse
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
        PelaksanaanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $filter = $request->validate([
            'status_peserta' => ['nullable', Rule::in(['semua', ...array_keys(PesertaUjianCbt::DAFTAR_STATUS_PELAKSANAAN)])],
            'jadwal_id' => ['nullable', 'integer', 'exists:jadwal_ujian_cbt,id'],
            'ruang_id' => ['nullable', 'integer', 'exists:ruang_ujian_cbt,id'],
            'kata_kunci_peserta' => ['nullable', 'string', 'max:100'],
            'halaman_peserta' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->tanpaCache(['data' => $service->rincian($request->user(), $kegiatanUjianCbt, $filter)]);
    }

    public function aturPengawas(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        RuangKegiatanUjianCbt $ruangKegiatanUjianCbt,
        PelaksanaanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'peran' => ['required', Rule::in(['utama', 'pendamping'])],
            'pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'alasan' => ['nullable', 'string', 'max:1000'],
        ]);
        $hasil = $service->aturPengawas(
            $request->user(),
            $kegiatanUjianCbt,
            $jadwalUjianCbt,
            $ruangKegiatanUjianCbt,
            $data['peran'],
            (int) $data['pegawai_id'],
            $data['alasan'] ?? null,
        );

        return $this->tanpaCache([
            'pesan' => $hasil['jenis'] === 'penggantian'
                ? 'Pergantian pengawas berhasil disimpan dan riwayatnya dicatat.'
                : 'Pengawas berhasil ditugaskan.',
            'data' => $hasil,
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
