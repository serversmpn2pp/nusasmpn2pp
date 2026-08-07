<?php

namespace App\Services\Nilai;

use App\Models\GuruMataPelajaran;
use App\Models\KomponenNilai;
use App\Models\NilaiSiswa;
use App\Models\PublikasiNilaiSiswa;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Support\Facades\DB;

class PublikasiNilaiService
{
    public function __construct(private NotifikasiPenggunaService $notifikasi) {}

    public function publikasikan(
        int $guruMataPelajaranId,
        string $semester,
        ?int $penggunaId,
    ): PublikasiNilaiSiswa {
        return DB::transaction(function () use ($guruMataPelajaranId, $semester, $penggunaId) {
            $publikasi = PublikasiNilaiSiswa::query()->firstOrNew([
                'guru_mata_pelajaran_id' => $guruMataPelajaranId,
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
                $this->kirimNotifikasiNilaiDipublikasikan($publikasi);
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

    private function kirimNotifikasiNilaiDipublikasikan(PublikasiNilaiSiswa $publikasi): void
    {
        $penugasan = GuruMataPelajaran::query()
            ->with(['mataPelajaran:id,nama', 'kelas:id,nama'])
            ->find($publikasi->guru_mata_pelajaran_id);

        if (! $penugasan) {
            return;
        }

        $komponenIds = KomponenNilai::query()
            ->where('guru_mata_pelajaran_id', $penugasan->id)
            ->where('semester', $publikasi->semester)
            ->where('aktif', true)
            ->pluck('id');
        $siswaIds = NilaiSiswa::query()
            ->whereIn('komponen_nilai_id', $komponenIds)
            ->where(function ($query) {
                $query->whereNotNull('nilai')->orWhereNotNull('predikat');
            })
            ->distinct()
            ->pluck('siswa_id');
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
            'Nilai '.$namaMataPelajaran.' telah tersedia',
            sprintf(
                'Nilai %s untuk %s semester %s telah dipublikasikan. Isi survei pembelajaran untuk membuka rincian nilai.',
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
    }
}
