<?php

namespace App\Services\Mobile;

use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class JadwalHariIniMobileService
{
    public function siapkan(
        Pengguna $pengguna,
        ?Pegawai $pegawai,
        ?TahunPelajaran $tahunPelajaran,
        Carbon $hariIni,
        ?array $presensiPegawai,
        ?array $piketHariIni,
        ?array $perwalian,
    ): array {
        if ($pengguna->akunSiswa() || $pengguna->memilikiPeran('siswa')) {
            return $this->jadwalSiswa($pengguna->siswa, $tahunPelajaran, $hariIni, false);
        }

        if ($pengguna->akunOrangTua() || $pengguna->memilikiPeran('orang_tua')) {
            return $this->jadwalOrangTua($pengguna, $tahunPelajaran, $hariIni);
        }

        if ($pengguna->administrator()) {
            return $this->pantauanAdministrator($tahunPelajaran, $hariIni);
        }

        if ($pegawai && $this->pegawaiAdalahGuru($pegawai, $tahunPelajaran)) {
            return $this->jadwalGuru($pegawai, $tahunPelajaran, $hariIni);
        }

        return $this->ringkasanPegawai($presensiPegawai, $piketHariIni, $perwalian);
    }

    private function jadwalGuru(
        Pegawai $pegawai,
        ?TahunPelajaran $tahunPelajaran,
        Carbon $hariIni,
    ): array {
        $kodeHari = $this->kodeHari($hariIni);
        $jadwal = JadwalPelajaran::query()
            ->with([
                'kelas:id,nama,tingkat',
                'jamPelajaran:id,hari,nomor_jam,label,jam_mulai,jam_selesai,jenis',
                'mataPelajaran:id,kode,nama,kelompok',
                'guruMataPelajaran.mataPelajaran:id,kode,nama,kelompok',
            ])
            ->when(
                $tahunPelajaran,
                fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaran->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('hari', $kodeHari)
            ->where('aktif', true)
            ->whereHas('jamPelajaran', fn ($query) => $query->where('aktif', true))
            ->whereHas('guruMataPelajaran', fn ($query) => $query
                ->where('pegawai_id', $pegawai->id)
                ->where('aktif', true))
            ->orderBy(
                JamPelajaran::select('nomor_jam')
                    ->whereColumn('jam_pelajaran.id', 'jadwal_pelajaran.jam_pelajaran_id')
                    ->limit(1),
            )
            ->get();
        $jadwalRingkas = $this->pilihJadwalTerdekat($jadwal, $hariIni)
            ->map(fn (JadwalPelajaran $item) => $this->itemJadwal(
                $item,
                $hariIni,
                $item->kelas?->nama ?? 'Kelas belum ditentukan',
            ))
            ->values()
            ->all();
        $semuaJadwal = $jadwal
            ->map(fn (JadwalPelajaran $item) => $this->itemJadwal(
                $item,
                $hariIni,
                $item->kelas?->nama ?? 'Kelas belum ditentukan',
            ))
            ->values()
            ->all();

        return $this->bagian(
            mode: 'guru',
            judul: 'Jadwal Mengajar Hari Ini',
            items: $jadwalRingkas,
            pesanKosong: 'Tidak ada jadwal mengajar untuk '.$this->labelHari($kodeHari).'.',
            labelAksi: 'Lihat Semua',
            ruteAksi: '/jadwal-mengajar-saya',
            semuaItems: $semuaJadwal,
        );
    }

    private function jadwalOrangTua(
        Pengguna $pengguna,
        ?TahunPelajaran $tahunPelajaran,
        Carbon $hariIni,
    ): array {
        $orangTua = $pengguna->orangTuaWali()
            ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
            ->first();
        $siswa = $orangTua?->siswa
            ->firstWhere('id', $orangTua->siswa_acuan_username_id)
            ?: $orangTua?->siswa->first();

        return $this->jadwalSiswa($siswa, $tahunPelajaran, $hariIni, true);
    }

    private function jadwalSiswa(
        ?Siswa $siswa,
        ?TahunPelajaran $tahunPelajaran,
        Carbon $hariIni,
        bool $untukOrangTua,
    ): array {
        $anggotaKelas = $siswa
            ? $this->anggotaKelasAktif($siswa, $tahunPelajaran)
            : null;
        $tahunJadwal = $anggotaKelas?->tahunPelajaran ?: $tahunPelajaran;
        $kodeHari = $this->kodeHari($hariIni);
        $kelas = $anggotaKelas?->kelas;
        $jadwal = JadwalPelajaran::query()
            ->with([
                'kelas:id,nama,tingkat',
                'jamPelajaran:id,hari,nomor_jam,label,jam_mulai,jam_selesai,jenis',
                'mataPelajaran:id,kode,nama,kelompok',
                'guruMataPelajaran.mataPelajaran:id,kode,nama,kelompok',
                'guruMataPelajaran.pegawai:id,nama_lengkap',
            ])
            ->where('kelas_id', $kelas?->id ?: 0)
            ->where('tahun_pelajaran_id', $tahunJadwal?->id ?: 0)
            ->where('hari', $kodeHari)
            ->where('aktif', true)
            ->whereHas('jamPelajaran', fn ($query) => $query->where('aktif', true))
            ->orderBy(
                JamPelajaran::select('nomor_jam')
                    ->whereColumn('jam_pelajaran.id', 'jadwal_pelajaran.jam_pelajaran_id')
                    ->limit(1),
            )
            ->get();
        $buatItem = function (JadwalPelajaran $item) use ($hariIni, $kelas, $siswa, $untukOrangTua) {
            $guru = $item->guruMataPelajaran?->pegawai?->nama_lengkap;
            $subjudul = $untukOrangTua
                ? ($siswa?->nama_lengkap ?? 'Anak').' · '.($kelas?->nama ?? '-')
                : ($kelas?->nama ?? 'Kelas belum ditentukan').($guru ? ' · '.$guru : '');

            return $this->itemJadwal($item, $hariIni, $subjudul);
        };
        $jadwalRingkas = $this->pilihJadwalTerdekat($jadwal, $hariIni)
            ->map($buatItem)
            ->values()
            ->all();
        $semuaJadwal = $jadwal
            ->map($buatItem)
            ->values()
            ->all();
        $pesanKosong = ! $siswa
            ? ($untukOrangTua
                ? 'Belum ada data anak yang terhubung dengan akun ini.'
                : 'Akun ini belum terhubung dengan data siswa.')
            : (! $anggotaKelas
                ? 'Siswa belum tercatat pada kelas aktif.'
                : 'Tidak ada jadwal pelajaran untuk '.$this->labelHari($kodeHari).'.');

        return $this->bagian(
            mode: $untukOrangTua ? 'orang_tua' : 'siswa',
            judul: 'Jadwal Hari Ini',
            items: $jadwalRingkas,
            pesanKosong: $pesanKosong,
            labelAksi: 'Lihat Semua',
            semuaItems: $semuaJadwal,
        );
    }

    private function anggotaKelasAktif(
        Siswa $siswa,
        ?TahunPelajaran $tahunPelajaran,
    ): ?AnggotaKelas {
        $query = AnggotaKelas::query()
            ->with(['kelas:id,nama,tingkat,tahun_pelajaran_id', 'tahunPelajaran:id,nama'])
            ->where('siswa_id', $siswa->id)
            ->where('status_keanggotaan', 'aktif');

        if ($tahunPelajaran) {
            $aktif = (clone $query)
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->latest('id')
                ->first();

            if ($aktif) {
                return $aktif;
            }
        }

        return $query->latest('tahun_pelajaran_id')->latest('id')->first();
    }

    private function pegawaiAdalahGuru(
        Pegawai $pegawai,
        ?TahunPelajaran $tahunPelajaran,
    ): bool {
        if (str_contains(mb_strtolower((string) $pegawai->jenis_pegawai), 'guru')) {
            return true;
        }

        return GuruMataPelajaran::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('aktif', true)
            ->when($tahunPelajaran, fn ($query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaran->id))
            ->exists();
    }

    private function pantauanAdministrator(
        ?TahunPelajaran $tahunPelajaran,
        Carbon $hariIni,
    ): array {
        $jumlahSiswa = AnggotaKelas::query()
            ->where('status_keanggotaan', 'aktif')
            ->when($tahunPelajaran, fn ($query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaran->id))
            ->distinct('siswa_id')
            ->count('siswa_id');
        $presensiSiswa = AbsensiSiswa::query()
            ->whereDate('tanggal', $hariIni->toDateString())
            ->when($tahunPelajaran, fn ($query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaran->id))
            ->distinct('siswa_id')
            ->count('siswa_id');
        $jumlahPegawai = Pegawai::query()->where('aktif', true)->count();
        $presensiPegawai = AbsensiPegawai::query()
            ->whereDate('tanggal', $hariIni->toDateString())
            ->distinct('pegawai_id')
            ->count('pegawai_id');

        return $this->bagian(
            mode: 'administrator',
            judul: 'Pantauan Hari Ini',
            items: [
                $this->itemRingkasan(
                    id: 'pantauan-presensi-siswa',
                    waktu: 'Siswa',
                    judul: 'Presensi Siswa',
                    subjudul: $presensiSiswa.' dari '.$jumlahSiswa.' siswa sudah tercatat',
                    jenis: 'presensi_siswa',
                    tautan: '/status-scan-presensi-siswa',
                ),
                $this->itemRingkasan(
                    id: 'pantauan-presensi-pegawai',
                    waktu: 'Pegawai',
                    judul: 'Presensi Pegawai',
                    subjudul: $presensiPegawai.' dari '.$jumlahPegawai.' pegawai sudah tercatat',
                    jenis: 'presensi_pegawai',
                    tautan: '/status-scan-presensi-pegawai',
                ),
            ],
            pesanKosong: 'Belum ada data yang dapat dipantau hari ini.',
        );
    }

    private function ringkasanPegawai(
        ?array $presensi,
        ?array $piket,
        ?array $perwalian,
    ): array {
        $items = [];
        if ($presensi) {
            $hariIni = $presensi['hari_ini'];
            $jamMasuk = $hariIni['jam_masuk'] ?? null;
            $items[] = $this->itemRingkasan(
                id: 'ringkasan-presensi-pegawai',
                waktu: $jamMasuk ? 'Masuk '.$jamMasuk : 'Hari ini',
                judul: 'Presensi Pegawai',
                subjudul: $hariIni['label_status'] ?? 'Belum tercatat',
                jenis: 'presensi_pegawai',
            );
        }

        if ($piket) {
            $items[] = $this->itemRingkasan(
                id: 'ringkasan-piket-pegawai',
                waktu: 'Hari ini',
                judul: 'Tugas Guru Piket',
                subjudul: $piket['keterangan'] ?: 'Anda terjadwal sebagai guru piket.',
                jenis: 'piket',
                tautan: '/piket-saya',
            );
        }

        if (($perwalian['jumlah_siswa_guru_wali'] ?? 0) > 0) {
            $items[] = $this->itemRingkasan(
                id: 'ringkasan-perwalian-pegawai',
                waktu: 'Perwalian',
                judul: 'Siswa Bimbingan',
                subjudul: $perwalian['jumlah_siswa_guru_wali'].' siswa dalam pemantauan Anda',
                jenis: 'perwalian',
                tautan: '/siswa-wali-saya',
            );
        }

        return $this->bagian(
            mode: 'pegawai',
            judul: 'Ringkasan Anda Hari Ini',
            items: $items,
            pesanKosong: 'Tidak ada aktivitas khusus untuk hari ini.',
        );
    }

    private function itemJadwal(
        JadwalPelajaran $jadwal,
        Carbon $hariIni,
        string $subjudul,
    ): array {
        $jam = $jadwal->jamPelajaran;
        $mataPelajaran = $jadwal->mataPelajaranTerjadwal();
        $jamSekarang = $hariIni->format('H:i:s');

        return [
            'id' => 'jadwal-'.$jadwal->id,
            'waktu' => $jam
                ? $this->formatJam($jam->jam_mulai).' - '.$this->formatJam($jam->jam_selesai)
                : '-',
            'judul' => $mataPelajaran?->nama ?? $jadwal->keterangan ?? 'Kegiatan kelas',
            'subjudul' => $subjudul,
            'jenis' => 'pelajaran',
            'sedang_berlangsung' => $jam
                && $jam->jam_mulai <= $jamSekarang
                && $jam->jam_selesai >= $jamSekarang,
            'tautan_mobile' => null,
        ];
    }

    private function itemRingkasan(
        string $id,
        string $waktu,
        string $judul,
        string $subjudul,
        string $jenis,
        ?string $tautan = null,
    ): array {
        return [
            'id' => $id,
            'waktu' => $waktu,
            'judul' => $judul,
            'subjudul' => $subjudul,
            'jenis' => $jenis,
            'sedang_berlangsung' => false,
            'tautan_mobile' => $tautan,
        ];
    }

    private function bagian(
        string $mode,
        string $judul,
        array $items,
        string $pesanKosong,
        ?string $labelAksi = null,
        ?string $ruteAksi = null,
        ?array $semuaItems = null,
    ): array {
        return [
            'mode' => $mode,
            'judul' => $judul,
            'label_aksi' => $labelAksi,
            'rute_aksi' => $ruteAksi,
            'pesan_kosong' => $pesanKosong,
            'items' => $items,
            'semua_items' => $semuaItems ?? $items,
        ];
    }

    /**
     * Mengutamakan jadwal yang sedang berlangsung, lalu jadwal berikutnya.
     * Jika seluruh jadwal telah selesai, dua jadwal terakhir yang ditampilkan.
     *
     * @param  Collection<int, JadwalPelajaran>  $jadwal
     * @return Collection<int, JadwalPelajaran>
     */
    private function pilihJadwalTerdekat(Collection $jadwal, Carbon $hariIni): Collection
    {
        $jamSekarang = $hariIni->format('H:i:s');
        $terpilih = $jadwal
            ->filter(function (JadwalPelajaran $item) use ($jamSekarang) {
                $jam = $item->jamPelajaran;

                return $jam
                    && $jam->jam_mulai <= $jamSekarang
                    && $jam->jam_selesai >= $jamSekarang;
            })
            ->concat($jadwal->filter(function (JadwalPelajaran $item) use ($jamSekarang) {
                return $item->jamPelajaran?->jam_mulai > $jamSekarang;
            }))
            ->unique('id')
            ->take(2);

        if ($terpilih->count() < 2) {
            $jadwalSebelumnya = $jadwal
                ->reject(fn (JadwalPelajaran $item) => $terpilih->contains('id', $item->id))
                ->filter(function (JadwalPelajaran $item) use ($jamSekarang) {
                    return $item->jamPelajaran?->jam_selesai < $jamSekarang;
                })
                ->sortByDesc(fn (JadwalPelajaran $item) => $item->jamPelajaran?->jam_selesai)
                ->take(2 - $terpilih->count());
            $terpilih = $terpilih->concat($jadwalSebelumnya);
        }

        return $terpilih
            ->sortBy(fn (JadwalPelajaran $item) => $item->jamPelajaran?->nomor_jam ?? PHP_INT_MAX)
            ->values();
    }

    private function kodeHari(Carbon $hari): string
    {
        return array_keys(JamPelajaran::DAFTAR_HARI)[$hari->dayOfWeekIso - 1] ?? 'minggu';
    }

    private function labelHari(string $kode): string
    {
        return JamPelajaran::DAFTAR_HARI[$kode] ?? str($kode)->headline()->toString();
    }

    private function formatJam(?string $jam): string
    {
        return $jam ? substr($jam, 0, 5) : '-';
    }
}
