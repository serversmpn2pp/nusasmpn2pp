<?php

namespace App\Services\Cbt;

use App\Models\AnggotaKelas;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\Pengguna;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\SesiKegiatanUjianCbt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BagiPesertaUjianTerpusat
{
    public function __construct(
        private SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
        private KodeMejaUjianTerpusat $kodeMeja,
    ) {}

    public function atur(
        KegiatanUjianCbt $kegiatan,
        int $tingkat,
        int $sesiId,
        array $kelasIds,
        array $ruangIds,
    ): KelompokPesertaKegiatanUjianCbt {
        [$sesi, $kelas, $ruang, $kelompokLama, $jadwalTingkat, $anggota, $totalKapasitas] = $this->dataValid(
            $kegiatan,
            $tingkat,
            $sesiId,
            $kelasIds,
            $ruangIds,
        );

        $kelompokLama?->loadMissing(['kelas:id', 'ruangKegiatanUjianCbt:id']);
        $kelasBerubah = $kelompokLama
            ? collect($kelompokLama->kelas->modelKeys())->sort()->values()->all() !== collect($kelas->modelKeys())->sort()->values()->all()
            : true;
        $ruangBerubah = $kelompokLama
            ? $kelompokLama->ruangKegiatanUjianCbt->modelKeys() !== $ruang->modelKeys()
            : true;
        $pengaturanBerubah = ! $kelompokLama
            || (int) $kelompokLama->sesi_kegiatan_ujian_cbt_id !== (int) $sesi->id
            || $kelasBerubah
            || $ruangBerubah;

        if ($pengaturanBerubah && $jadwalTingkat->isNotEmpty()) {
            throw ValidationException::withMessages([
                'peserta' => 'Penetapan ruang tidak dapat diubah karena jadwal tingkat ini sudah dibuat. Hapus jadwal tingkat tersebut terlebih dahulu.',
            ]);
        }

        return DB::transaction(function () use ($kegiatan, $tingkat, $sesi, $kelas, $ruang, $totalKapasitas, $kelompokLama, $pengaturanBerubah) {
            $kelompok = KelompokPesertaKegiatanUjianCbt::query()->updateOrCreate(
                [
                    'kegiatan_ujian_cbt_id' => $kegiatan->id,
                    'tingkat' => $tingkat,
                ],
                [
                    'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                    'jumlah_peserta' => $pengaturanBerubah ? 0 : ($kelompokLama?->jumlah_peserta ?? 0),
                    'total_kapasitas' => $totalKapasitas,
                    'dibangkitkan_pada' => $pengaturanBerubah ? null : $kelompokLama?->dibangkitkan_pada,
                    'dibangkitkan_oleh_pengguna_id' => $pengaturanBerubah ? null : $kelompokLama?->dibangkitkan_oleh_pengguna_id,
                ]
            );

            $kelompok->kelas()->sync($kelas->modelKeys());
            $kelompok->ruangKegiatanUjianCbt()->sync(
                $ruang->values()->mapWithKeys(fn (RuangKegiatanUjianCbt $item, int $index) => [
                    $item->id => ['urutan' => $index + 1],
                ])->all()
            );

            if ($pengaturanBerubah) {
                $kelompok->penempatanPesertaUjianCbt()->delete();
            }

            return $kelompok->fresh([
                'sesiKegiatanUjianCbt',
                'kelas',
                'ruangKegiatanUjianCbt',
            ]);
        });
    }

    public function bangkitkan(
        KegiatanUjianCbt $kegiatan,
        KelompokPesertaKegiatanUjianCbt $kelompok,
        ?Pengguna $pengguna,
    ): KelompokPesertaKegiatanUjianCbt {
        if ((int) $kelompok->kegiatan_ujian_cbt_id !== (int) $kegiatan->id) {
            throw ValidationException::withMessages(['peserta' => 'Penetapan ruang tidak termasuk dalam kegiatan ini.']);
        }

        $kelompok->loadMissing(['kelas:id', 'ruangKegiatanUjianCbt:id']);

        return $this->bagi(
            $kegiatan,
            (int) $kelompok->tingkat,
            (int) $kelompok->sesi_kegiatan_ujian_cbt_id,
            $kelompok->kelas->modelKeys(),
            $kelompok->ruangKegiatanUjianCbt->modelKeys(),
            $pengguna,
        );
    }

    public function bagi(
        KegiatanUjianCbt $kegiatan,
        int $tingkat,
        int $sesiId,
        array $kelasIds,
        array $ruangIds,
        ?Pengguna $pengguna,
    ): KelompokPesertaKegiatanUjianCbt {
        [$sesi, $kelas, $ruang, $kelompokLama, $jadwalTingkat, $anggota, $totalKapasitas] = $this->dataValid(
            $kegiatan,
            $tingkat,
            $sesiId,
            $kelasIds,
            $ruangIds,
        );

        $hasil = DB::transaction(function () use ($kegiatan, $tingkat, $sesi, $kelas, $ruang, $anggota, $totalKapasitas, $pengguna) {
            $kelompok = KelompokPesertaKegiatanUjianCbt::query()->updateOrCreate(
                [
                    'kegiatan_ujian_cbt_id' => $kegiatan->id,
                    'tingkat' => $tingkat,
                ],
                [
                    'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                    'jumlah_peserta' => $anggota->count(),
                    'total_kapasitas' => $totalKapasitas,
                    'dibangkitkan_pada' => now(),
                    'dibangkitkan_oleh_pengguna_id' => $pengguna?->id,
                ]
            );

            $kelompok->kelas()->sync($kelas->modelKeys());
            $kelompok->ruangKegiatanUjianCbt()->sync(
                $ruang->values()->mapWithKeys(fn (RuangKegiatanUjianCbt $item, int $index) => [
                    $item->id => ['urutan' => $index + 1],
                ])->all()
            );
            $kelompok->penempatanPesertaUjianCbt()->delete();

            $waktu = now();
            $baris = [];
            $indeksRuang = 0;
            $nomorMeja = 1;

            foreach ($anggota->values() as $indeksPeserta => $item) {
                while ($nomorMeja > $ruang[$indeksRuang]->kapasitas) {
                    $indeksRuang++;
                    $nomorMeja = 1;
                }

                $baris[] = [
                    'kelompok_peserta_kegiatan_ujian_cbt_id' => $kelompok->id,
                    'anggota_kelas_id' => $item->id,
                    'ruang_kegiatan_ujian_cbt_id' => $ruang[$indeksRuang]->id,
                    'nomor_meja' => $nomorMeja,
                    'kode_meja' => $this->kodeMeja->buat($kegiatan, $sesi, $ruang[$indeksRuang], $nomorMeja),
                    'nomor_peserta' => sprintf('%s-T%d-%03d', $kegiatan->kode, $tingkat, $indeksPeserta + 1),
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ];
                $nomorMeja++;
            }

            DB::table('penempatan_peserta_ujian_cbt')->insert($baris);

            JadwalUjianCbt::query()
                ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
                ->where('tingkat', $tingkat)
                ->get()
                ->each(function (JadwalUjianCbt $jadwal) use ($sesi, $kelas) {
                    $jadwal->update([
                        'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                        'waktu_mulai' => $sesi->waktu_mulai,
                        'waktu_selesai' => $sesi->waktu_selesai,
                        'label_sesi' => $sesi->nama,
                    ]);
                    $jadwal->kelas()->sync($kelas->modelKeys());
                });

            return $kelompok->fresh([
                'sesiKegiatanUjianCbt',
                'kelas',
                'ruangKegiatanUjianCbt',
                'penempatanPesertaUjianCbt',
            ]);
        });

        $this->sinkronisasi->sinkronkanKegiatan($kegiatan, $pengguna);

        return $hasil;
    }

    private function dataValid(
        KegiatanUjianCbt $kegiatan,
        int $tingkat,
        int $sesiId,
        array $kelasIds,
        array $ruangIds,
    ): array {
        $kelasIds = collect($kelasIds)->map(fn ($id) => (int) $id)->unique()->values();
        $ruangIds = collect($ruangIds)->map(fn ($id) => (int) $id)->unique()->values();

        $sesi = SesiKegiatanUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->where('aktif', true)
            ->find($sesiId);

        if (! $sesi) {
            throw ValidationException::withMessages(['sesi_kegiatan_ujian_cbt_id' => 'Sesi tidak termasuk dalam kegiatan ini.']);
        }

        $kelas = Kelas::query()
            ->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
            ->where('tingkat', $tingkat)
            ->where('aktif', true)
            ->whereIn('id', $kelasIds)
            ->orderBy('nama')
            ->get();

        if ($kelas->count() !== $kelasIds->count()) {
            throw ValidationException::withMessages(['kelas' => 'Ada kelas yang tidak sesuai dengan tahun pelajaran atau tingkat yang dipilih.']);
        }

        $ruang = RuangKegiatanUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->where('aktif', true)
            ->whereIn('id', $ruangIds)
            ->orderBy('urutan')
            ->orderBy('kode')
            ->get();

        if ($ruang->count() !== $ruangIds->count()) {
            throw ValidationException::withMessages(['ruang' => 'Ada ruang yang tidak aktif atau bukan milik kegiatan ini.']);
        }

        $kelompokLama = KelompokPesertaKegiatanUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->where('tingkat', $tingkat)
            ->first();
        $jadwalTingkat = JadwalUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->where('tingkat', $tingkat)
            ->get();

        if ($jadwalTingkat->contains(fn (JadwalUjianCbt $jadwal) => $jadwal->ujianCbt()
            ->whereHas('pesertaUjianCbt', fn ($query) => $query->whereIn('status', ['sedang_mengerjakan', 'selesai']))
            ->exists())) {
            throw ValidationException::withMessages([
                'peserta' => 'Pembagian peserta tidak dapat diubah karena ujian tingkat ini sudah mulai dikerjakan.',
            ]);
        }

        if ($jadwalTingkat->isNotEmpty() && $jadwalTingkat->contains(
            fn (JadwalUjianCbt $jadwal) => (int) $jadwal->sesi_kegiatan_ujian_cbt_id !== (int) $sesi->id
        )) {
            throw ValidationException::withMessages([
                'sesi_kegiatan_ujian_cbt_id' => 'Sesi tingkat ini tidak dapat diganti karena jadwal sudah dibuat. Hapus jadwal tingkat tersebut terlebih dahulu.',
            ]);
        }

        $ruangBentrok = KelompokPesertaKegiatanUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->where('sesi_kegiatan_ujian_cbt_id', $sesi->id)
            ->when($kelompokLama, fn ($query) => $query->where('id', '!=', $kelompokLama->id))
            ->whereHas('ruangKegiatanUjianCbt', fn ($query) => $query->whereIn('ruang_kegiatan_ujian_cbt.id', $ruangIds))
            ->with('ruangKegiatanUjianCbt')
            ->first();

        if ($ruangBentrok) {
            $namaRuang = $ruangBentrok->ruangKegiatanUjianCbt
                ->whereIn('id', $ruangIds)
                ->pluck('nama')
                ->join(', ');
            throw ValidationException::withMessages([
                'ruang' => "{$namaRuang} sudah dipakai tingkat {$ruangBentrok->tingkat} pada sesi yang sama.",
            ]);
        }

        $anggota = AnggotaKelas::query()
            ->select('anggota_kelas.*')
            ->join('kelas', 'kelas.id', '=', 'anggota_kelas.kelas_id')
            ->join('siswa', 'siswa.id', '=', 'anggota_kelas.siswa_id')
            ->where('anggota_kelas.tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
            ->where('anggota_kelas.status_keanggotaan', 'aktif')
            ->where('siswa.aktif', true)
            ->whereIn('anggota_kelas.kelas_id', $kelasIds)
            ->orderBy('kelas.nama')
            ->orderBy('siswa.nama_lengkap')
            ->orderBy('siswa.nisn')
            ->get();

        if ($anggota->isEmpty()) {
            throw ValidationException::withMessages(['kelas' => 'Kelas yang dipilih belum memiliki siswa aktif.']);
        }

        $totalKapasitas = (int) $ruang->sum('kapasitas');
        if ($totalKapasitas < $anggota->count()) {
            $kekurangan = $anggota->count() - $totalKapasitas;
            throw ValidationException::withMessages([
                'ruang' => "Kapasitas ruang kurang {$kekurangan} kursi. Tersedia {$totalKapasitas} kursi untuk {$anggota->count()} siswa.",
            ]);
        }

        return [$sesi, $kelas, $ruang, $kelompokLama, $jadwalTingkat, $anggota, $totalKapasitas];
    }
}
