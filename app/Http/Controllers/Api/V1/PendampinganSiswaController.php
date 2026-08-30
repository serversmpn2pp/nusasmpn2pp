<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PendampinganSiswa;
use App\Services\Mobile\PendampinganSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PendampinganSiswaController extends Controller
{
    public function index(Request $request, PendampinganSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate($this->aturanFilter());

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function referensi(Request $request, PendampinganSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
            'kata_kunci' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->tanpaCache(['data' => $service->referensi($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        PendampinganSiswa $pendampinganSiswa,
        PendampinganSiswaMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache(['data' => $service->rincian($request->user(), $pendampinganSiswa)]);
    }

    public function store(Request $request, PendampinganSiswaMobileService $service): JsonResponse
    {
        $pendampingan = $service->buat($request->user(), $request->validate($this->aturanSimpan()));

        return $this->tanpaCache([
            'message' => 'Pendampingan siswa berhasil dimulai.',
            'data' => $service->rincian($request->user(), $pendampingan),
        ], 201);
    }

    public function update(
        Request $request,
        PendampinganSiswa $pendampinganSiswa,
        PendampinganSiswaMobileService $service,
    ): JsonResponse {
        $pendampingan = $service->perbarui(
            $request->user(),
            $pendampinganSiswa,
            $request->validate($this->aturanSimpan(true)),
        );

        return $this->tanpaCache([
            'message' => $pendampingan->status === 'selesai'
                ? 'Pendampingan siswa telah ditandai selesai.'
                : 'Pendampingan siswa berhasil diperbarui.',
            'data' => $service->rincian($request->user(), $pendampingan),
        ]);
    }

    private function aturanFilter(): array
    {
        return [
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(PendampinganSiswa::DAFTAR_STATUS)])],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ];
    }

    private function aturanSimpan(bool $ubah = false): array
    {
        $aturan = [
            'jenis_tindakan' => ['required', Rule::in(array_keys(PendampinganSiswa::DAFTAR_JENIS))],
            'petugas_pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'tanggal_tindak_lanjut' => ['required', 'date'],
            'catatan' => ['required', 'string', 'max:3000'],
            'hasil' => [$ubah ? 'required_if:status,selesai' : 'nullable', 'nullable', 'string', 'max:3000'],
        ];
        if ($ubah) {
            $aturan['status'] = ['required', Rule::in(array_keys(PendampinganSiswa::DAFTAR_STATUS))];
        } else {
            $aturan += [
                'siswa_id' => ['required', 'integer', Rule::exists('siswa', 'id')],
                'tahun_pelajaran_id' => ['required', 'integer', Rule::exists('tahun_pelajaran', 'id')],
                'peringatan_dini_siswa_id' => ['nullable', 'integer', Rule::exists('peringatan_dini_siswa', 'id')],
            ];
        }

        return $aturan;
    }

    private function tanpaCache(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
