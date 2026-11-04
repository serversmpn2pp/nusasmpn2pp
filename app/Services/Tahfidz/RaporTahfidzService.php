<?php

namespace App\Services\Tahfidz;

use App\Models\AnggotaTahfidz;
use App\Models\KelompokTahfidz;
use App\Models\Kelas;
use App\Models\TargetRaporTahfidz;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class RaporTahfidzService
{
    public function __construct(private readonly CapaianTahfidzService $capaian) {}

    public function daftar(KelompokTahfidz $kelompok): Collection
    {
        return $this->persiapan($kelompok, $kelompok->anggotaAktif()->get())
            ->sortBy(fn ($item) => $item['anggota']->siswa->nama_lengkap, SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function siswa(KelompokTahfidz $kelompok, AnggotaTahfidz $anggota): array
    {
        $item = $this->persiapan($kelompok, new EloquentCollection([$anggota]))->first();
        if (! $item['target']) {
            return ['pesan' => $item['pesan']];
        }
        $capaian = $this->capaian->rapor($anggota->siswa_id, $item['target']);

        return [...$capaian, 'dokumen' => $this->dokumen($kelompok, $anggota, $item['kelas'], $item['target'], $capaian)];
    }

    public function dokumenKelompok(KelompokTahfidz $kelompok, Collection $daftar): Collection
    {
        $capaian = [];
        // Setoran dibaca bersama untuk siswa yang memakai target dan tanggal rapor yang sama.
        foreach ($daftar->groupBy('target.id') as $baris) {
            $capaian += $this->capaian->raporBanyak($baris->pluck('anggota.siswa_id')->all(), $baris->first()['target']);
        }

        return $daftar->map(fn ($item) => $this->dokumen(
            $kelompok, $item['anggota'], $item['kelas'], $item['target'], $capaian[$item['anggota']->siswa_id],
        ));
    }

    private function persiapan(KelompokTahfidz $kelompok, EloquentCollection $anggota): Collection
    {
        $anggota->loadMissing(['siswa.anggotaKelas' => fn ($q) => $q->where('tahun_pelajaran_id', $kelompok->tahun_pelajaran_id)
            ->whereHas('kelas', fn ($q) => $q->where('tahun_pelajaran_id', $kelompok->tahun_pelajaran_id))->with('kelas')]);
        $target = TargetRaporTahfidz::where('tahun_pelajaran_id', $kelompok->tahun_pelajaran_id)->get()->keyBy('tingkat');

        return $anggota->map(function (AnggotaTahfidz $item) use ($target) {
            $kelas = $item->siswa->anggotaKelas->first()?->kelas;
            $pengaturan = $kelas ? $target->get($kelas->tingkat) : null;

            return ['anggota' => $item, 'kelas' => $kelas, 'target' => $pengaturan, 'pesan' => $pengaturan ? null
                : ($kelas ? 'Koordinator belum mengatur target rapor untuk tingkat '.$kelas->tingkat.'.'
                    : 'Kelas siswa belum tercatat pada tahun pelajaran ini.')];
        });
    }

    private function dokumen(KelompokTahfidz $kelompok, AnggotaTahfidz $anggota, Kelas $kelas, TargetRaporTahfidz $target, array $capaian): array
    {
        return ['nama' => $anggota->siswa->nama_lengkap, 'kelas' => $kelas->nama,
            'kelompok' => $kelompok->nama, 'nis' => $anggota->siswa->nis, 'nisn' => $anggota->siswa->nisn,
            'tahun' => $kelompok->tahunPelajaran->nama, 'pembimbing' => $kelompok->pembimbing->nama_lengkap,
            'tanggal' => $target->tanggal_rapor->toDateString(), 'target' => $target->target, 'mode' => $target->mode,
            'baris' => $capaian['baris']->map(fn ($item) => ['surah' => $item['surah']?->nama,
                'nilai' => $item['setoran']?->labelNilai()])->values()->all(),
            'sudah' => $capaian['sudah'], 'jumlah' => number_format($capaian['jumlah'], 2, ',', ''),
            'rata' => $capaian['rata'] === null ? null : number_format($capaian['rata'], 2, ',', ''), 'predikat' => $capaian['predikat'],
        ];
    }
}
