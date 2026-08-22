<?php

namespace App\Services\Cbt;

use App\Models\JadwalUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\Siswa;
use Carbon\Carbon;

class DaftarUjianSiswaService
{
    public function siapkan(Siswa $siswa): array
    {
        $sekarang = now();
        $peserta = PesertaUjianCbt::query()
            ->with([
                'anggotaKelas',
                'kelasUjianCbt.kelas.tahunPelajaran',
                'ujianCbt.jenisUjianCbt',
                'ujianCbt.tahunPelajaran',
                'ujianCbt.mataPelajaran',
                'ujianCbt.jadwalUjianCbt' => fn ($query) => $query
                    ->with(['kegiatanUjianCbt', 'kelas:id,nama'])
                    ->orderBy('tanggal')
                    ->orderBy('waktu_mulai')
                    ->orderBy('urutan'),
                'sesiUjianCbt',
                'ruangUjianCbt.jadwalUjianCbt.kegiatanUjianCbt',
            ])
            ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $siswa->id))
            ->where('status', '!=', 'nonaktif')
            ->whereHas('ujianCbt', fn ($query) => $query->whereIn('status', [
                'terjadwal',
                'berlangsung',
                'selesai',
            ]))
            ->get()
            ->map(fn (PesertaUjianCbt $item) => $this->rapikanItem($item, $sekarang))
            ->sortBy(fn (array $item) => sprintf(
                '%d|%s|%08d',
                match ($item['kelompok']) {
                    'aktif' => 0,
                    'akan_datang' => 1,
                    default => 2,
                },
                $item['waktu_mulai']?->format('YmdHis') ?: '99999999999999',
                $item['peserta']->id,
            ))
            ->values();

        return [
            'daftarUjian' => $peserta,
            'ujianAktif' => $peserta->where('kelompok', 'aktif')->values(),
            'ujianAkanDatang' => $peserta->where('kelompok', 'akan_datang')->values(),
            'ujianSelesai' => $peserta->where('kelompok', 'selesai')->values(),
            'ringkasanUjian' => [
                'aktif' => $peserta->where('kelompok', 'aktif')->count(),
                'akan_datang' => $peserta->where('kelompok', 'akan_datang')->count(),
                'selesai' => $peserta->where('kelompok', 'selesai')->count(),
                'total' => $peserta->count(),
            ],
        ];
    }

    private function rapikanItem(PesertaUjianCbt $peserta, Carbon $sekarang): array
    {
        $ujian = $peserta->ujianCbt;
        $jadwal = $this->jadwalUntukPeserta($peserta);
        $waktuMulai = $this->waktuMulaiResmi($jadwal, $ujian?->tanggal_mulai);
        $waktuSelesai = $this->waktuSelesaiResmi($jadwal, $ujian?->tanggal_selesai);
        $mulaiAkses = $peserta->sesiUjianCbt?->waktu_mulai ?: $ujian?->tanggal_mulai;
        $selesaiAkses = $peserta->sesiUjianCbt?->waktu_selesai ?: $ujian?->tanggal_selesai;
        $jadwalDibatalkan = $jadwal?->status === 'dibatalkan';
        $aksesDiblokir = $peserta->status === 'terblokir';
        $sesiNonaktif = $peserta->sesiUjianCbt?->status === 'nonaktif';
        $waktuAksesDimulai = ! $mulaiAkses || $sekarang->gte($mulaiAkses);
        $waktuAksesBelumBerakhir = ! $selesaiAkses || $sekarang->lte($selesaiAkses);
        $dapatAktif = in_array($ujian?->status, ['terjadwal', 'berlangsung'], true)
            && in_array($peserta->status, ['aktif', 'sedang_mengerjakan'], true)
            && ! $jadwalDibatalkan
            && ! $sesiNonaktif
            && $waktuAksesDimulai
            && $waktuAksesBelumBerakhir;

        $sudahBerakhir = $peserta->status === 'selesai'
            || $ujian?->status === 'selesai'
            || $jadwalDibatalkan
            || ($selesaiAkses && $sekarang->gt($selesaiAkses));

        $kelompok = match (true) {
            $sudahBerakhir => 'selesai',
            $dapatAktif => 'aktif',
            default => 'akan_datang',
        };

        [$labelStatus, $nadaStatus] = match (true) {
            $jadwalDibatalkan => ['Dibatalkan', 'bahaya'],
            $aksesDiblokir => ['Akses diblokir', 'bahaya'],
            $sesiNonaktif => ['Sesi tidak aktif', 'bahaya'],
            $peserta->status === 'selesai' => ['Selesai dikerjakan', 'selesai'],
            $ujian?->status === 'selesai' => ['Ujian selesai', 'selesai'],
            $selesaiAkses && $sekarang->gt($selesaiAkses) => ['Waktu berakhir', 'selesai'],
            $peserta->status === 'sedang_mengerjakan' => ['Sedang dikerjakan', 'aktif'],
            $dapatAktif => ['Siap dimulai', 'aktif'],
            default => ['Belum dibuka', 'menunggu'],
        };

        return [
            'peserta' => $peserta,
            'ujian' => $ujian,
            'jadwal' => $jadwal,
            'waktu_mulai' => $waktuMulai,
            'waktu_selesai' => $waktuSelesai,
            'kelompok' => $kelompok,
            'label_status' => $labelStatus,
            'nada_status' => $nadaStatus,
        ];
    }

    private function jadwalUntukPeserta(PesertaUjianCbt $peserta): ?JadwalUjianCbt
    {
        if ($peserta->ruangUjianCbt?->jadwalUjianCbt) {
            return $peserta->ruangUjianCbt->jadwalUjianCbt;
        }

        $kelasId = $peserta->kelasUjianCbt?->kelas_id;
        $jadwal = $peserta->ujianCbt?->jadwalUjianCbt ?: collect();

        if ($kelasId) {
            $jadwalKelas = $jadwal->first(fn (JadwalUjianCbt $item) => $item->kelas->contains('id', $kelasId));

            if ($jadwalKelas) {
                return $jadwalKelas;
            }
        }

        return $jadwal->first();
    }

    private function waktuMulaiResmi(?JadwalUjianCbt $jadwal, mixed $waktuPaket): ?Carbon
    {
        if ($jadwal?->tanggal && filled($jadwal->waktu_mulai)) {
            return Carbon::parse($jadwal->tanggal->format('Y-m-d').' '.substr((string) $jadwal->waktu_mulai, 0, 8));
        }

        return $waktuPaket?->copy();
    }

    private function waktuSelesaiResmi(?JadwalUjianCbt $jadwal, mixed $waktuPaket): ?Carbon
    {
        if ($jadwal?->tanggal && filled($jadwal->waktu_selesai)) {
            return Carbon::parse($jadwal->tanggal->format('Y-m-d').' '.substr((string) $jadwal->waktu_selesai, 0, 8));
        }

        return $waktuPaket?->copy();
    }
}
