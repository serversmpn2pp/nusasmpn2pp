<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JadwalKegiatanIbadah;
use App\Services\Mobile\JadwalKegiatanIbadahMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JadwalKegiatanIbadahController extends Controller
{
    public function index(Request $request, JadwalKegiatanIbadahMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kegiatan_ibadah_id' => ['nullable', 'integer', 'exists:kegiatan_ibadah,id'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($filter)]);
    }

    public function store(Request $request, JadwalKegiatanIbadahMobileService $service): JsonResponse
    {
        $data = $request->validate($this->aturanTambah());
        $jumlah = $service->terapkan($data);

        return $this->tanpaCache([
            'pesan' => $jumlah.' jadwal kegiatan ibadah berhasil diterapkan.',
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        JadwalKegiatanIbadah $jadwalKegiatanIbadah,
        JadwalKegiatanIbadahMobileService $service,
    ): JsonResponse {
        $service->ubah($jadwalKegiatanIbadah, $request->validate($this->aturanWaktu()));

        return $this->tanpaCache([
            'pesan' => 'Jadwal '.$jadwalKegiatanIbadah->labelHari().' berhasil diperbarui.',
        ]);
    }

    public function destroy(
        JadwalKegiatanIbadah $jadwalKegiatanIbadah,
        JadwalKegiatanIbadahMobileService $service,
    ): JsonResponse {
        $service->nonaktifkan($jadwalKegiatanIbadah);

        return $this->tanpaCache(['pesan' => 'Jadwal kegiatan ibadah berhasil dinonaktifkan.']);
    }

    private function aturanTambah(): array
    {
        return [
            'kegiatan_ibadah_id' => ['required', 'integer', 'exists:kegiatan_ibadah,id'],
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'hari' => ['required', 'array', 'min:1'],
            'hari.*' => ['required', 'distinct', Rule::in(array_keys(JadwalKegiatanIbadah::DAFTAR_HARI))],
            ...$this->aturanWaktu(),
        ];
    }

    private function aturanWaktu(): array
    {
        return [
            'jam_scan_mulai' => ['required', 'date_format:H:i'],
            'jam_pelaksanaan' => ['required', 'date_format:H:i'],
            'jam_scan_selesai' => ['required', 'date_format:H:i'],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
