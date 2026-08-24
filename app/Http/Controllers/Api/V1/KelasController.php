<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnggotaKelas;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Services\Mobile\JadwalKelasMobileService;
use App\Services\Mobile\KelasMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KelasController extends Controller
{
    public function index(Request $request, KelasMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:semua,aktif,nonaktif'],
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar($request->user(), $filter),
        ]);
    }

    public function show(Request $request, Kelas $kelas, KelasMobileService $service): JsonResponse
    {
        return $this->tanpaCache([
            'data' => $service->detail($request->user(), $kelas),
        ]);
    }

    public function calonAnggota(Request $request, Kelas $kelas, KelasMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->tanpaCache([
            'data' => $service->calonAnggota(
                $request->user(),
                $kelas,
                (string) ($filter['cari'] ?? ''),
            ),
        ]);
    }

    public function tambahAnggota(Request $request, Kelas $kelas, KelasMobileService $service): JsonResponse
    {
        $this->pastikanDapatKelola($request, $kelas);
        $data = $request->validate([
            'siswa_id' => [
                'required',
                'integer',
                Rule::exists('siswa', 'id')->where('aktif', true),
                Rule::unique('anggota_kelas', 'siswa_id')
                    ->where('tahun_pelajaran_id', $kelas->tahun_pelajaran_id),
            ],
            'tanggal_masuk' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($kelas->kapasitas && $kelas->anggotaKelas()->count() >= $kelas->kapasitas) {
            throw ValidationException::withMessages([
                'siswa_id' => 'Kapasitas kelas sudah penuh.',
            ]);
        }

        $kelas->loadMissing('tahunPelajaran');
        $anggota = DB::transaction(fn () => AnggotaKelas::create([
            'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $data['siswa_id'],
            'nomor_absen' => null,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $data['tanggal_masuk'] ?? $kelas->tahunPelajaran?->tanggal_mulai,
            'keterangan' => $data['keterangan'] ?? null,
        ]));

        return $this->tanpaCache([
            'pesan' => 'Siswa berhasil ditambahkan ke kelas.',
            'data' => ['id' => (int) $anggota->id],
        ])->setStatusCode(201);
    }

    public function ubahAnggota(
        Request $request,
        Kelas $kelas,
        AnggotaKelas $anggotaKelas,
    ): JsonResponse {
        $this->pastikanDapatKelola($request, $kelas);
        $this->pastikanAnggotaMilikKelas($anggotaKelas, $kelas);
        $data = $request->validate([
            'tanggal_masuk' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $anggotaKelas->update($data);

        return $this->tanpaCache([
            'pesan' => 'Data anggota kelas berhasil diperbarui.',
        ]);
    }

    public function hapusAnggota(
        Request $request,
        Kelas $kelas,
        AnggotaKelas $anggotaKelas,
    ): JsonResponse {
        $this->pastikanDapatKelola($request, $kelas);
        $this->pastikanAnggotaMilikKelas($anggotaKelas, $kelas);
        $anggotaKelas->delete();

        return $this->tanpaCache([
            'pesan' => 'Siswa berhasil dikeluarkan dari kelas.',
        ]);
    }

    public function pilihanJadwal(
        Request $request,
        Kelas $kelas,
        JadwalKelasMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'data' => $service->pilihan($request->user(), $kelas),
        ]);
    }

    public function ubahSlotJadwal(
        Request $request,
        Kelas $kelas,
        JamPelajaran $jamPelajaran,
        JadwalKelasMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'pilihan_jadwal' => ['present', 'nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->simpanSlot(
            $request->user(),
            $kelas,
            $jamPelajaran,
            $data['pilihan_jadwal'],
            $data['keterangan'] ?? null,
        );

        return $this->tanpaCache([
            'pesan' => filled($data['pilihan_jadwal'])
                ? 'Slot jadwal berhasil diperbarui.'
                : 'Slot jadwal berhasil dikosongkan.',
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()
            ->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    private function pastikanDapatKelola(Request $request, Kelas $kelas): void
    {
        abort_unless($request->user()?->memilikiIzin('kelas.kelola') ?? false, 403);
        abort_unless($request->user()?->dapatMengaksesKelasSebagaiWali($kelas->id) ?? false, 403);
    }

    private function pastikanAnggotaMilikKelas(AnggotaKelas $anggotaKelas, Kelas $kelas): void
    {
        abort_unless((int) $anggotaKelas->kelas_id === (int) $kelas->id, 404);
    }
}
