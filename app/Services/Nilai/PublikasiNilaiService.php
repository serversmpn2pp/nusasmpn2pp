<?php

namespace App\Services\Nilai;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\PublikasiNilaiSiswa;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublikasiNilaiService
{
    public function __construct(private NotifikasiPenggunaService $notifikasi) {}

    public function komponenAktif(GuruMataPelajaran $penugasan, string $semester): HasMany
    {
        return $penugasan->komponenNilai()->where('semester', $semester)->where('aktif', true);
    }

    public function nilaiTerisi(GuruMataPelajaran $penugasan, string $semester): Builder
    {
        return NilaiSiswa::query()
            ->whereIn('komponen_nilai_id', $this->komponenAktif($penugasan, $semester)->select('id')->getQuery())
            ->whereIn('siswa_id', AnggotaKelas::query()->select('siswa_id')
                ->where('kelas_id', $penugasan->kelas_id)
                ->where('tahun_pelajaran_id', $penugasan->tahun_pelajaran_id)
                ->where('status_keanggotaan', 'aktif'))
            ->where(fn (Builder $query) => $query->whereNotNull('nilai')->orWhereNotNull('predikat'));
    }

    public function publikasikan(
        GuruMataPelajaran $penugasan,
        string $semester,
        ?int $penggunaId,
    ): PublikasiNilaiSiswa {
        return DB::transaction(function () use ($penugasan, $semester, $penggunaId) {
            if (! $this->komponenAktif($penugasan, $semester)->exists()) {
                throw ValidationException::withMessages(['publikasi' => 'Belum ada komponen nilai aktif untuk semester ini.']);
            }

            $siswaIds = $this->nilaiTerisi($penugasan, $semester)->distinct()->pluck('siswa_id');
            if ($siswaIds->isEmpty()) {
                throw ValidationException::withMessages(['publikasi' => 'Isi dan simpan minimal satu nilai siswa sebelum mempublikasikan.']);
            }

            $publikasi = PublikasiNilaiSiswa::query()->firstOrNew([
                'guru_mata_pelajaran_id' => $penugasan->id,
                'semester' => $semester,
            ]);
            $baruDipublikasikan = ! $publikasi->exists || ! $publikasi->dipublikasikan;

            $publikasi->fill([
                'dipublikasikan' => true,
                'dipublikasikan_pada' => $baruDipublikasikan
                    ? now()
                    : $publikasi->dipublikasikan_pada,
                'dipublikasikan_oleh_pengguna_id' => $penggunaId,
            ])->save();

            if ($baruDipublikasikan) {
                $this->kirimNotifikasiNilaiDipublikasikan($publikasi, $penugasan, $siswaIds);
            }

            return $publikasi;
        });
    }

    public function tandaiDraf(int $guruMataPelajaranId, string $semester): bool
    {
        return PublikasiNilaiSiswa::query()
            ->where('guru_mata_pelajaran_id', $guruMataPelajaranId)
            ->where('semester', $semester)
            ->where('dipublikasikan', true)
            ->update([
                'dipublikasikan' => false,
                'dipublikasikan_pada' => null,
                'dipublikasikan_oleh_pengguna_id' => null,
                'updated_at' => now(),
            ]) > 0;
    }

    public function tandaiDrafUntukSkema(
        int $tahunPelajaranId,
        string $semester,
        ?int $tingkat,
    ): int {
        return PublikasiNilaiSiswa::query()
            ->where('semester', $semester)
            ->where('dipublikasikan', true)
            ->whereHas('guruMataPelajaran', function ($query) use ($tahunPelajaranId, $tingkat) {
                $query->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->when($tingkat, function ($query, $tingkat) {
                        $query->whereHas('kelas', fn ($query) => $query->where('tingkat', $tingkat));
                    });
            })
            ->update([
                'dipublikasikan' => false,
                'dipublikasikan_pada' => null,
                'dipublikasikan_oleh_pengguna_id' => null,
                'updated_at' => now(),
            ]);
    }

    private function kirimNotifikasiNilaiDipublikasikan(
        PublikasiNilaiSiswa $publikasi,
        GuruMataPelajaran $penugasan,
        Collection $siswaIds,
    ): void
    {
        $penugasan->loadMissing(['mataPelajaran:id,nama', 'kelas:id,nama']);
        $namaMataPelajaran = $penugasan->mataPelajaran?->nama ?? 'mata pelajaran';
        $namaKelas = $penugasan->kelas?->nama ?? 'kelas Anda';
        $labelSemester = ucfirst($publikasi->semester);
        $tautan = route('nilai-saya.index', [
            'tahun_pelajaran_id' => $penugasan->tahun_pelajaran_id,
            'semester' => $publikasi->semester,
        ], false).'#mapel-'.$penugasan->id;
        $kunciWaktu = $publikasi->dipublikasikan_pada?->format('YmdHisv')
            ?? $publikasi->updated_at?->format('YmdHisv');

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaUntukDaftarSiswa($siswaIds),
            'berhasil',
            'Nilai '.$namaMataPelajaran.' Anda telah tersedia',
            sprintf(
                'Nilai %s Anda untuk %s semester %s telah dipublikasikan. Isi survei pembelajaran untuk membuka rincian nilai.',
                $namaMataPelajaran,
                $namaKelas,
                $labelSemester,
            ),
            $tautan,
            "nilai-dipublikasikan:{$publikasi->id}:{$kunciWaktu}",
            [
                'guru_mata_pelajaran_id' => $penugasan->id,
                'semester' => $publikasi->semester,
            ],
        );

        $tautanOrangTua = route('akademik-anak.index', [
            'tab' => 'nilai',
            'semester' => $publikasi->semester,
        ], false).'#mapel-'.$penugasan->id;

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaOrangTuaUntukDaftarSiswa($siswaIds),
            'berhasil',
            'Nilai '.$namaMataPelajaran.' anak Anda telah tersedia',
            sprintf(
                'Nilai %s anak Anda untuk %s semester %s telah dipublikasikan. Rincian nilai dapat dilihat setelah anak mengisi survei pembelajaran.',
                $namaMataPelajaran,
                $namaKelas,
                $labelSemester,
            ),
            $tautanOrangTua,
            "nilai-anak-dipublikasikan:{$publikasi->id}:{$kunciWaktu}",
            [
                'guru_mata_pelajaran_id' => $penugasan->id,
                'semester' => $publikasi->semester,
            ],
        );
    }
}
