<?php

namespace App\Services\Mobile;

use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PengaturanBerhalanganIbadah;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\TahunPelajaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PengaturanBerhalanganIbadahMobileService
{
    public function data(): array
    {
        $tahunPelajaran = $this->tahunPelajaranAktif(false);
        if (! $tahunPelajaran) {
            return [
                'tersedia' => false,
                'tahun_pelajaran' => null,
                'pengaturan' => null,
                'ringkasan' => [
                    'pendamping_aktif' => 0,
                    'kelas_tercakup' => 0,
                    'jumlah_kelas' => 0,
                ],
                'referensi' => [
                    'pegawai_perempuan' => [],
                    'kelas' => [],
                ],
                'penugasan' => [],
            ];
        }

        $pengaturan = PengaturanBerhalanganIbadah::query()
            ->whereBelongsTo($tahunPelajaran)
            ->first();
        $pegawai = $this->daftarPegawaiPerempuan();
        $kelas = Kelas::query()
            ->whereBelongsTo($tahunPelajaran)
            ->where('aktif', true)
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get(['id', 'nama', 'tingkat']);
        $penugasan = PenugasanPendampingIbadahSiswi::query()
            ->whereBelongsTo($tahunPelajaran)
            ->where('aktif', true)
            ->with([
                'pegawai:id,nama_lengkap,nip,jabatan_utama,aktif',
                'kelas:id,nama,tingkat',
                'ditugaskanOlehPengguna:id,nama',
            ])
            ->get()
            ->sortBy(fn (PenugasanPendampingIbadahSiswi $item) => $item->pegawai?->nama_lengkap)
            ->values();
        $kelasTercakup = $penugasan->contains('semua_kelas', true)
            ? $kelas->count()
            : $penugasan->flatMap->kelas->pluck('id')->unique()->count();

        return [
            'tersedia' => true,
            'tahun_pelajaran' => [
                'id' => (int) $tahunPelajaran->id,
                'nama' => $tahunPelajaran->nama,
            ],
            'pengaturan' => [
                'batas_hari_konfirmasi' => (int) ($pengaturan?->batas_hari_konfirmasi ?? 7),
                'aktif' => (bool) ($pengaturan?->aktif ?? true),
            ],
            'ringkasan' => [
                'pendamping_aktif' => $penugasan->count(),
                'kelas_tercakup' => $kelasTercakup,
                'jumlah_kelas' => $kelas->count(),
            ],
            'referensi' => [
                'pegawai_perempuan' => $pegawai->map(fn (Pegawai $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama_lengkap,
                    'nip' => $item->nip,
                    'jabatan' => $item->jabatan_utama,
                    'akun_aktif' => (bool) $item->pengguna?->aktif,
                ])->values(),
                'kelas' => $kelas->map(fn (Kelas $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama,
                    'tingkat' => (int) $item->tingkat,
                ])->values(),
            ],
            'penugasan' => $penugasan->map(fn (PenugasanPendampingIbadahSiswi $item) => $this->ringkasPenugasan($item))->values(),
        ];
    }

    public function simpanPengaturan(array $data, int $penggunaId): void
    {
        $tahunPelajaran = $this->tahunPelajaranAktif();
        PengaturanBerhalanganIbadah::query()->updateOrCreate(
            ['tahun_pelajaran_id' => $tahunPelajaran->id],
            [
                'batas_hari_konfirmasi' => (int) $data['batas_hari_konfirmasi'],
                'aktif' => (bool) $data['aktif'],
                'diperbarui_oleh_pengguna_id' => $penggunaId,
            ],
        );
    }

    public function simpanPendamping(array $data, int $penggunaId): void
    {
        $tahunPelajaran = $this->tahunPelajaranAktif();
        $pegawai = Pegawai::query()
            ->with(['pengguna.daftarPeran:id,kode,aktif'])
            ->findOrFail($data['pegawai_id']);
        if (! $this->dapatMenjadiPendamping($pegawai)) {
            throw ValidationException::withMessages([
                'pegawai_id' => 'Pendamping harus merupakan guru perempuan atau Guru PL perempuan yang masih aktif.',
            ]);
        }

        $semuaKelas = (bool) $data['semua_kelas'];
        $kelasIds = collect($data['kelas_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        if (! $semuaKelas && $kelasIds->isEmpty()) {
            throw ValidationException::withMessages([
                'kelas_ids' => 'Pilih sedikitnya satu kelas atau gunakan cakupan seluruh kelas.',
            ]);
        }

        DB::transaction(function () use ($tahunPelajaran, $data, $semuaKelas, $kelasIds, $penggunaId) {
            $penugasan = PenugasanPendampingIbadahSiswi::query()->firstOrNew([
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'pegawai_id' => $data['pegawai_id'],
            ]);
            $penugasan->fill([
                'semua_kelas' => $semuaKelas,
                'aktif' => true,
                'ditugaskan_oleh_pengguna_id' => $penggunaId,
                'dinonaktifkan_pada' => null,
            ])->save();
            $penugasan->kelas()->sync($semuaKelas ? [] : $kelasIds->all());
        });
    }

    public function nonaktifkanPendamping(
        PenugasanPendampingIbadahSiswi $penugasan,
        int $penggunaId,
    ): void {
        $penugasan->update([
            'aktif' => false,
            'ditugaskan_oleh_pengguna_id' => $penggunaId,
            'dinonaktifkan_pada' => now(),
        ]);
    }

    private function ringkasPenugasan(PenugasanPendampingIbadahSiswi $item): array
    {
        return [
            'id' => (int) $item->id,
            'pegawai_id' => (int) $item->pegawai_id,
            'semua_kelas' => (bool) $item->semua_kelas,
            'aktif' => (bool) $item->aktif,
            'pegawai' => [
                'nama' => $item->pegawai?->nama_lengkap,
                'nip' => $item->pegawai?->nip,
                'jabatan' => $item->pegawai?->jabatan_utama,
            ],
            'kelas' => $item->kelas
                ->sortBy(fn (Kelas $kelas) => sprintf('%02d-%s', $kelas->tingkat, $kelas->nama))
                ->map(fn (Kelas $kelas) => [
                    'id' => (int) $kelas->id,
                    'nama' => $kelas->nama,
                    'tingkat' => (int) $kelas->tingkat,
                ])->values(),
            'ditugaskan_oleh' => $item->ditugaskanOlehPengguna?->nama,
            'diperbarui_pada' => $item->updated_at?->toIso8601String(),
        ];
    }

    private function tahunPelajaranAktif(bool $wajib = true): ?TahunPelajaran
    {
        $tahun = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();
        if (! $tahun && $wajib) {
            throw ValidationException::withMessages([
                'tahun_pelajaran' => 'Aktifkan tahun pelajaran terlebih dahulu.',
            ]);
        }

        return $tahun;
    }

    private function daftarPegawaiPerempuan(): Collection
    {
        return Pegawai::query()
            ->where('aktif', true)
            ->where('jenis_kelamin', 'P')
            ->with(['pengguna:id,pegawai_id,aktif', 'pengguna.daftarPeran:id,kode,aktif'])
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip', 'jenis_kelamin', 'jenis_pegawai', 'jabatan_utama', 'aktif'])
            ->filter(fn (Pegawai $pegawai) => $this->dapatMenjadiPendamping($pegawai))
            ->values();
    }

    private function dapatMenjadiPendamping(Pegawai $pegawai): bool
    {
        return $pegawai->aktif
            && $pegawai->jenis_kelamin === 'P'
            && (
                mb_strtolower(trim((string) $pegawai->jenis_pegawai)) === 'guru'
                || $pegawai->pengguna?->memilikiPeran('guru_pl')
            );
    }
}
