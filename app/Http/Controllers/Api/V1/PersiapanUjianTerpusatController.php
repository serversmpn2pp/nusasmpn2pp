<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\PanitiaUjianCbt;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\SesiKegiatanUjianCbt;
use App\Services\Mobile\PersiapanUjianTerpusatMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersiapanUjianTerpusatController extends Controller
{
    public function index(Request $request, PersiapanUjianTerpusatMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(KegiatanUjianCbt::DAFTAR_STATUS)])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function store(Request $request, PersiapanUjianTerpusatMobileService $service): JsonResponse
    {
        $kegiatan = $service->tambahKegiatan($request->user(), $request->validate($this->aturanKegiatan()));

        return $this->tanpaCache([
            'pesan' => 'Ujian Terpusat berhasil dibuat. Lanjutkan dengan menentukan panitia, sesi, dan ruang.',
            'data' => ['id' => (int) $kegiatan->id],
        ])->setStatusCode(201);
    }

    public function show(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache(['data' => $service->detail($request->user(), $kegiatanUjianCbt)]);
    }

    public function update(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $service->ubahKegiatan($request->user(), $kegiatanUjianCbt, $request->validate($this->aturanKegiatan()));

        return $this->tanpaCache(['pesan' => 'Informasi Ujian Terpusat berhasil diperbarui.']);
    }

    public function destroy(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $service->hapusKegiatan($request->user(), $kegiatanUjianCbt);

        return $this->tanpaCache(['pesan' => 'Ujian Terpusat berhasil dihapus.']);
    }

    public function storePanitia(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'jabatan' => ['required', Rule::in(array_keys(PanitiaUjianCbt::DAFTAR_JABATAN))],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);
        $service->simpanPanitia($request->user(), $kegiatanUjianCbt, $data);

        return $this->tanpaCache(['pesan' => 'Panitia ujian berhasil disimpan.']);
    }

    public function destroyPanitia(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        PanitiaUjianCbt $panitiaUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $service->hapusPanitia($request->user(), $kegiatanUjianCbt, $panitiaUjianCbt);

        return $this->tanpaCache(['pesan' => 'Penugasan panitia berhasil dihapus.']);
    }

    public function storeSesi(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $service->tambahSesi($request->user(), $kegiatanUjianCbt, $this->validasiSesi($request));

        return $this->tanpaCache(['pesan' => 'Sesi ujian berhasil ditambahkan.'])->setStatusCode(201);
    }

    public function updateSesi(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        SesiKegiatanUjianCbt $sesiKegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $service->ubahSesi($request->user(), $kegiatanUjianCbt, $sesiKegiatanUjianCbt, $this->validasiSesi($request));

        return $this->tanpaCache(['pesan' => 'Sesi ujian berhasil diperbarui.']);
    }

    public function destroySesi(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        SesiKegiatanUjianCbt $sesiKegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $service->hapusSesi($request->user(), $kegiatanUjianCbt, $sesiKegiatanUjianCbt);

        return $this->tanpaCache(['pesan' => 'Sesi ujian berhasil dihapus.']);
    }

    public function storeRuang(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $service->tambahRuang($request->user(), $kegiatanUjianCbt, $request->validate($this->aturanRuang()));

        return $this->tanpaCache(['pesan' => 'Ruang ujian berhasil ditambahkan.'])->setStatusCode(201);
    }

    public function updateRuang(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        RuangKegiatanUjianCbt $ruangKegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $service->ubahRuang(
            $request->user(),
            $kegiatanUjianCbt,
            $ruangKegiatanUjianCbt,
            $request->validate($this->aturanRuang()),
        );

        return $this->tanpaCache(['pesan' => 'Ruang ujian berhasil diperbarui.']);
    }

    public function destroyRuang(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        RuangKegiatanUjianCbt $ruangKegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $service->hapusRuang($request->user(), $kegiatanUjianCbt, $ruangKegiatanUjianCbt);

        return $this->tanpaCache(['pesan' => 'Ruang ujian berhasil dihapus.']);
    }

    public function aturPenetapan(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'tingkat' => ['required', 'integer', Rule::in([7, 8, 9])],
            'sesi_kegiatan_ujian_cbt_id' => ['required', 'integer'],
            'kelas' => ['required', 'array', 'min:1'],
            'kelas.*' => ['integer'],
            'ruang' => ['required', 'array', 'min:1'],
            'ruang.*' => ['integer'],
        ]);
        $kelompok = $service->aturPenetapan($request->user(), $kegiatanUjianCbt, $data);

        return $this->tanpaCache([
            'pesan' => "Kelas, sesi, dan ruang tingkat {$kelompok->tingkat} berhasil ditetapkan.",
            'data' => ['id' => (int) $kelompok->id],
        ]);
    }

    public function bangkitkanPembagian(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        KelompokPesertaKegiatanUjianCbt $kelompokPeserta,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $kelompok = $service->bangkitkanPembagian(
            $request->user(),
            $kegiatanUjianCbt,
            $kelompokPeserta,
        );

        return $this->tanpaCache([
            'pesan' => "{$kelompok->jumlah_peserta} siswa tingkat {$kelompok->tingkat} berhasil dibagi otomatis ke ruang ujian.",
            'data' => [
                'id' => (int) $kelompok->id,
                'jumlah_peserta' => (int) $kelompok->jumlah_peserta,
            ],
        ]);
    }

    public function showPembagian(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        KelompokPesertaKegiatanUjianCbt $kelompokPeserta,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'data' => $service->rincianPembagian(
                $request->user(),
                $kegiatanUjianCbt,
                $kelompokPeserta,
            ),
        ]);
    }

    public function destroyPenetapan(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        KelompokPesertaKegiatanUjianCbt $kelompokPeserta,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $tingkat = (int) $kelompokPeserta->tingkat;
        $service->hapusPenetapan($request->user(), $kegiatanUjianCbt, $kelompokPeserta);

        return $this->tanpaCache([
            'pesan' => "Penetapan ruang dan pembagian peserta tingkat {$tingkat} berhasil dikosongkan.",
        ]);
    }

    public function storeJadwal(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $jadwal = $service->tambahJadwal(
            $request->user(),
            $kegiatanUjianCbt,
            $request->validate($this->aturanJadwal(true)),
        );

        return $this->tanpaCache([
            'pesan' => "Jadwal berhasil ditambahkan untuk {$jadwal->count()} tingkat.",
            'data' => ['id' => $jadwal->pluck('id')->map(fn ($id) => (int) $id)->values()],
        ])->setStatusCode(201);
    }

    public function updateJadwal(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $service->ubahJadwal(
            $request->user(),
            $kegiatanUjianCbt,
            $jadwalUjianCbt,
            $request->validate($this->aturanJadwal(false)),
        );

        return $this->tanpaCache(['pesan' => 'Jadwal ujian berhasil diperbarui.']);
    }

    public function destroyJadwal(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        PersiapanUjianTerpusatMobileService $service,
    ): JsonResponse {
        $service->hapusJadwal($request->user(), $kegiatanUjianCbt, $jadwalUjianCbt);

        return $this->tanpaCache(['pesan' => 'Jadwal ujian berhasil dihapus.']);
    }

    private function aturanKegiatan(): array
    {
        return [
            'jenis_ujian_cbt_id' => ['required', 'integer', Rule::exists('jenis_ujian_cbt', 'id')->where(fn ($query) => $query->where('aktif', true)->where('kode', '!=', 'ASESMEN_KELAS'))],
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'nama' => ['required', 'string', 'max:180'],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'status' => ['required', Rule::in(array_keys(KegiatanUjianCbt::DAFTAR_STATUS))],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function aturanJadwal(bool $beberapaTingkat): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'tingkat' => $beberapaTingkat ? ['required', 'array', 'min:1'] : ['prohibited'],
            'tingkat.*' => $beberapaTingkat ? ['integer', Rule::in([7, 8, 9])] : ['prohibited'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function validasiSesi(Request $request): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i'],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['waktu_selesai'] <= $data['waktu_mulai']) {
            throw ValidationException::withMessages(['waktu_selesai' => 'Waktu selesai harus setelah waktu mulai.']);
        }

        return $data;
    }

    private function aturanRuang(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'lokasi' => ['nullable', 'string', 'max:180'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:100'],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
