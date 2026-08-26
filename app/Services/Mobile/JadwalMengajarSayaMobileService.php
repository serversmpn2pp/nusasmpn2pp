<?php

namespace App\Services\Mobile;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;

class JadwalMengajarSayaMobileService
{
    public function daftar(Pengguna $pengguna, ?int $tahunPelajaranId = null): array
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $tahunTerpilih = $tahunPelajaranId
            ? $tahunPelajaran->firstWhere('id', $tahunPelajaranId)
            : ($tahunPelajaran->firstWhere('aktif', true) ?? $tahunPelajaran->first());
        $pegawai = $pengguna->pegawai;
        $sekarang = now();
        $hariHariIni = $this->kodeHari((int) $sekarang->dayOfWeekIso);

        $jadwal = JadwalPelajaran::query()
            ->with([
                'kelas:id,nama,tingkat',
                'jamPelajaran:id,hari,nomor_jam,label,jam_mulai,jam_selesai,jenis',
                'guruMataPelajaran.mataPelajaran:id,kode,nama',
            ])
            ->when(
                $tahunTerpilih,
                fn ($query) => $query->where('tahun_pelajaran_id', $tahunTerpilih->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->when(
                $pegawai,
                fn ($query) => $query->whereHas(
                    'guruMataPelajaran',
                    fn ($guruMapel) => $guruMapel->where('pegawai_id', $pegawai->id),
                ),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('aktif', true)
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy(
                JamPelajaran::select('nomor_jam')
                    ->whereColumn('jam_pelajaran.id', 'jadwal_pelajaran.jam_pelajaran_id')
                    ->limit(1),
            )
            ->get();

        $daftarHari = collect(JamPelajaran::DAFTAR_HARI)->except('minggu');
        $hari = $daftarHari
            ->map(function (string $label, string $kode) use ($jadwal, $hariHariIni, $sekarang) {
                $items = $jadwal
                    ->where('hari', $kode)
                    ->map(fn (JadwalPelajaran $item) => $this->siapkanJadwal(
                        $item,
                        $kode === $hariHariIni,
                        $sekarang->format('H:i:s'),
                    ))
                    ->values();

                return [
                    'kode' => $kode,
                    'label' => $label,
                    'hari_ini' => $kode === $hariHariIni,
                    'jumlah' => $items->count(),
                    'jadwal' => $items,
                ];
            })
            ->values();

        $peringatan = collect([
            ! $pegawai
                ? 'Akun ini belum terhubung dengan data pegawai. Hubungi administrator.'
                : null,
            $pegawai && ! $tahunTerpilih
                ? 'Tahun pelajaran belum tersedia.'
                : null,
        ])->filter()->values()->all();

        return [
            'tahun_pelajaran' => $tahunPelajaran
                ->map(fn (TahunPelajaran $tahun) => [
                    'id' => (int) $tahun->id,
                    'nama' => $tahun->nama,
                    'aktif' => (bool) $tahun->aktif,
                ])
                ->values(),
            'tahun_terpilih_id' => $tahunTerpilih?->id ? (int) $tahunTerpilih->id : null,
            'pegawai' => $pegawai ? [
                'id' => (int) $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nip' => $pegawai->nip,
                'jabatan' => $pegawai->jabatan_utama,
            ] : null,
            'terhubung_pegawai' => $pegawai !== null,
            'hari_ini' => $hariHariIni,
            'waktu_server' => $sekarang->toISOString(),
            'ringkasan' => [
                'jam_mengajar' => $jadwal->count(),
                'kelas' => $jadwal->pluck('kelas_id')->unique()->count(),
                'mata_pelajaran' => $jadwal
                    ->pluck('guruMataPelajaran.mata_pelajaran_id')
                    ->filter()
                    ->unique()
                    ->count(),
                'hari_mengajar' => $jadwal->pluck('hari')->unique()->count(),
                'jadwal_hari_ini' => $jadwal->where('hari', $hariHariIni)->count(),
            ],
            'hari' => $hari,
            'peringatan' => $peringatan,
        ];
    }

    private function siapkanJadwal(
        JadwalPelajaran $jadwal,
        bool $hariIni,
        string $jamSekarang,
    ): array {
        $jam = $jadwal->jamPelajaran;
        $mataPelajaran = $jadwal->guruMataPelajaran?->mataPelajaran;

        return [
            'id' => (int) $jadwal->id,
            'jam' => $jam ? [
                'id' => (int) $jam->id,
                'nomor' => (int) $jam->nomor_jam,
                'label' => $jam->label ?: 'Jam '.$jam->nomor_jam,
                'mulai' => $this->formatJam($jam->jam_mulai),
                'selesai' => $this->formatJam($jam->jam_selesai),
            ] : null,
            'mata_pelajaran' => $mataPelajaran ? [
                'id' => (int) $mataPelajaran->id,
                'kode' => $mataPelajaran->kode,
                'nama' => $mataPelajaran->nama,
            ] : null,
            'kelas' => $jadwal->kelas ? [
                'id' => (int) $jadwal->kelas->id,
                'nama' => $jadwal->kelas->nama,
                'tingkat' => (int) $jadwal->kelas->tingkat,
            ] : null,
            'sedang_berlangsung' => $hariIni
                && $jam
                && $jam->jam_mulai <= $jamSekarang
                && $jam->jam_selesai >= $jamSekarang,
            'keterangan' => $jadwal->keterangan,
        ];
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }

    private function kodeHari(int $hariIso): string
    {
        return [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$hariIso];
    }
}
