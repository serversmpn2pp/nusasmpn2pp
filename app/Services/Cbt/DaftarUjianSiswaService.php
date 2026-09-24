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
                'pengawasSusulan:id,nama_lengkap',
                'ruangUjianCbt.jadwalUjianCbt.kegiatanUjianCbt',
            ])
            ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $siswa->id))
            ->where('status', '!=', 'nonaktif')
            ->whereHas('ujianCbt', fn ($query) => $query->whereIn('status', [
                'terjadwal',
                'berlangsung',
                'selesai',
            ]))
            ->get();

        $pesertaTerpublikasi = $peserta->filter(fn (PesertaUjianCbt $item) =>
            $item->status === 'selesai'
            && $item->ujianCbt?->ujianTerpusat()
            && $item->ujianCbt->tampilkan_hasil
            && $item->ujianCbt->hasil_difinalisasi_pada
        );
        $pesertaTerpublikasi->loadMissing([
            'jawabanPesertaUjianCbt',
            'ujianCbt.soalUjianCbt',
        ]);

        $peserta = $peserta
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
            'ujianSelesai' => $peserta->where('kelompok', 'selesai')
                ->sortByDesc(fn (array $item) => $item['waktu_selesai']?->timestamp
                    ?? $item['waktu_mulai']?->timestamp
                    ?? 0)
                ->values(),
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
        $susulanTerjadwal = $peserta->susulanDijadwalkan();
        $waktuMulai = $susulanTerjadwal
            ? $peserta->susulan_mulai
            : $this->waktuMulaiResmi($jadwal, $ujian?->tanggal_mulai);
        $waktuSelesai = $susulanTerjadwal
            ? $peserta->susulan_selesai
            : $this->waktuSelesaiResmi($jadwal, $ujian?->tanggal_selesai);
        $mulaiAkses = $susulanTerjadwal
            ? $peserta->susulan_mulai
            : ($peserta->sesiUjianCbt?->waktu_mulai ?: $ujian?->tanggal_mulai);
        $selesaiAkses = $susulanTerjadwal
            ? $peserta->susulan_selesai
            : ($peserta->sesiUjianCbt?->waktu_selesai ?: $ujian?->tanggal_selesai);
        $jadwalDibatalkan = $jadwal?->status === 'dibatalkan';
        $aksesDiblokir = $peserta->status === 'terblokir';
        $sesiNonaktif = ! $susulanTerjadwal && $peserta->sesiUjianCbt?->status === 'nonaktif';
        $waktuAksesDimulai = ! $mulaiAkses || $sekarang->gte($mulaiAkses);
        $waktuAksesBelumBerakhir = ! $selesaiAkses || $sekarang->lte($selesaiAkses);
        $statusPaketDiizinkan = $susulanTerjadwal
            ? ['terjadwal', 'berlangsung', 'selesai']
            : ['terjadwal', 'berlangsung'];
        $dalamWaktuPelaksanaan = in_array($ujian?->status, $statusPaketDiizinkan, true)
            && ($susulanTerjadwal || ! $jadwalDibatalkan)
            && ! $sesiNonaktif
            && $waktuAksesDimulai
            && $waktuAksesBelumBerakhir;
        $dapatAktif = $dalamWaktuPelaksanaan
            && in_array($peserta->status, ['aktif', 'sedang_mengerjakan'], true);

        $sudahBerakhir = $peserta->status === 'selesai'
            || $peserta->status_susulan === 'selesai'
            || (! $susulanTerjadwal && $ujian?->status === 'selesai')
            || (! $susulanTerjadwal && $jadwalDibatalkan)
            || ($selesaiAkses && $sekarang->gt($selesaiAkses));

        $kelompok = match (true) {
            $sudahBerakhir => 'selesai',
            ($dapatAktif || ($dalamWaktuPelaksanaan && $aksesDiblokir)) => 'aktif',
            default => 'akan_datang',
        };

        [$labelStatus, $nadaStatus] = match (true) {
            $peserta->status_susulan === 'dibatalkan' => ['Susulan dibatalkan', 'bahaya'],
            $jadwalDibatalkan && ! $susulanTerjadwal => ['Dibatalkan', 'bahaya'],
            $sesiNonaktif => ['Sesi tidak aktif', 'bahaya'],
            $peserta->status_susulan === 'selesai' => ['Susulan selesai', 'selesai'],
            $peserta->status === 'selesai' => ['Selesai dikerjakan', 'selesai'],
            $susulanTerjadwal && $selesaiAkses && $sekarang->gt($selesaiAkses) => ['Waktu susulan berakhir', 'selesai'],
            ! $susulanTerjadwal && $ujian?->status === 'selesai' => ['Ujian selesai', 'selesai'],
            $selesaiAkses && $sekarang->gt($selesaiAkses) => ['Waktu berakhir', 'selesai'],
            $aksesDiblokir => ['Ditahan Mode Aman', 'bahaya'],
            $peserta->status === 'sedang_mengerjakan' => [$susulanTerjadwal ? 'Susulan sedang dikerjakan' : 'Sedang dikerjakan', 'aktif'],
            $dapatAktif => [$susulanTerjadwal ? 'Susulan siap dimulai' : 'Siap dimulai', 'aktif'],
            $susulanTerjadwal => ['Susulan terjadwal', 'menunggu'],
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
            'susulan' => $susulanTerjadwal || filled($peserta->status_susulan),
            'hasil_cbt' => $peserta->status === 'selesai' && $ujian?->ujianTerpusat()
                ? $this->hasilCbt($peserta)
                : null,
        ];
    }

    private function hasilCbt(PesertaUjianCbt $peserta): array
    {
        $ujian = $peserta->ujianCbt;
        if (! $ujian->tampilkan_hasil || ! $ujian->hasil_difinalisasi_pada) {
            return ['status' => 'belum_dipublikasikan', 'nilai' => null];
        }

        $soal = $ujian->soalUjianCbt
            ->sortBy(fn ($item) => sprintf('%05d|%08d', $item->nomor_urut ?? 9999, $item->id))
            ->take($ujian->jumlah_soal);
        $bobotTotal = (float) $soal->sum(fn ($item) => (float) $item->bobot);
        $jawaban = $peserta->jawabanPesertaUjianCbt->keyBy('soal_ujian_cbt_id');
        $belumDikoreksi = $soal->contains(function ($item) use ($jawaban) {
            $jawabanSoal = $jawaban->get($item->id);

            return ! is_null($jawabanSoal?->jawaban) && is_null($jawabanSoal?->skor);
        });

        if ($bobotTotal <= 0 || $belumDikoreksi) {
            return ['status' => 'belum_tersedia', 'nilai' => null];
        }

        $skorTotal = $soal->sum(fn ($item) => (float) ($jawaban->get($item->id)?->skor ?? 0));

        return [
            'status' => 'dipublikasikan',
            'nilai' => max(0, min(100, round(($skorTotal / $bobotTotal) * 100, 2))),
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
