<?php

namespace App\Services\Cbt;

use App\Models\PesertaUjianCbt;
use App\Models\SesiUjianCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Support\Collection;

class RekapHasilUjianCbtService
{
    public function bangun(
        UjianCbt $ujianCbt,
        Collection $kelasYangDiizinkan,
        ?int $kelasId = null,
        ?int $sesiUjianCbtId = null,
        string $statusHasil = 'semua',
    ): array {
        $ujianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'mataPelajaran',
            'kelasUjianCbt.kelas',
            'kelasUjianCbt.komponenNilai',
            'sesiUjianCbt',
            'jadwalUjianCbt.kegiatanUjianCbt',
        ]);

        $kelasIds = $kelasYangDiizinkan
            ->pluck('kelas_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $soalUjian = $this->ambilSoalUjian($ujianCbt);
        $jumlahSoalTampil = $soalUjian->count();
        $bobotTotal = round($soalUjian->sum(fn ($item) => (float) $item->bobot), 2);
        $soalOtomatisIds = $soalUjian
            ->filter(fn ($item) => in_array($item->soalCbt?->jenis_soal, KoreksiOtomatisCbtService::JENIS_OTOMATIS, true))
            ->pluck('id')
            ->all();
        $soalManualIds = $soalUjian
            ->reject(fn ($item) => in_array($item->soalCbt?->jenis_soal, KoreksiOtomatisCbtService::JENIS_OTOMATIS, true))
            ->pluck('id')
            ->all();

        $kelasPeserta = $ujianCbt->kelasUjianCbt
            ->filter(fn ($item) => $kelasIds->contains((int) $item->kelas_id))
            ->sortBy(fn ($item) => $item->kelas?->nama)
            ->values();
        $sesiUjianCbt = $ujianCbt->sesiUjianCbt
            ->sortBy(fn (SesiUjianCbt $sesi) => sprintf(
                '%s|%s',
                $sesi->waktu_mulai?->format('YmdHis') ?? '99999999999999',
                $sesi->kode,
            ))
            ->values();

        $peserta = $ujianCbt->pesertaUjianCbt()
            ->with([
                'sesiUjianCbt',
                'kelasUjianCbt.kelas',
                'anggotaKelas.siswa',
                'jawabanPesertaUjianCbt',
            ])
            ->whereHas('kelasUjianCbt', fn ($query) => $query->whereIn('kelas_id', $kelasIds))
            ->when($kelasId, fn ($query) => $query->whereHas(
                'kelasUjianCbt',
                fn ($query) => $query->where('kelas_id', $kelasId),
            ))
            ->when($sesiUjianCbtId, fn ($query) => $query->where('sesi_ujian_cbt_id', $sesiUjianCbtId))
            ->get()
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%s|%s|%05d|%s',
                $item->sesiUjianCbt?->kode ?? '',
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();

        $rekapSemua = $peserta
            ->map(fn (PesertaUjianCbt $item) => $this->susunRekapPeserta(
                $item,
                $soalUjian,
                $soalOtomatisIds,
                $soalManualIds,
                $bobotTotal,
                $ujianCbt->kkm,
            ))
            ->values();
        $rekapHasil = $rekapSemua
            ->filter(fn ($item) => $statusHasil === 'semua' || $item['kode_status_hasil'] === $statusHasil)
            ->values();

        return [
            'ujianCbt' => $ujianCbt,
            'kelasPeserta' => $kelasPeserta,
            'sesiUjianCbt' => $sesiUjianCbt,
            'rekapSemua' => $rekapSemua,
            'rekapHasil' => $rekapHasil,
            'ringkasan' => $this->ringkasan($rekapSemua),
            'kelasId' => $kelasId,
            'sesiUjianCbtId' => $sesiUjianCbtId,
            'statusHasil' => $statusHasil,
            'jumlahSoalTampil' => $jumlahSoalTampil,
            'jumlahSoalOtomatis' => count($soalOtomatisIds),
            'jumlahSoalManual' => count($soalManualIds),
            'bobotTotal' => $bobotTotal,
        ];
    }

    private function susunRekapPeserta(
        PesertaUjianCbt $peserta,
        Collection $soalUjian,
        array $soalOtomatisIds,
        array $soalManualIds,
        float $bobotTotal,
        ?int $kkm,
    ): array {
        $jawaban = $peserta->jawabanPesertaUjianCbt->keyBy('soal_ujian_cbt_id');
        $jawabanTersimpan = $jawaban->filter(fn ($item) => ! is_null($item->jawaban))->count();
        $jawabanDikoreksi = $jawaban->filter(fn ($item) => ! is_null($item->skor))->count();
        $benar = $jawaban->filter(fn ($item) => $item->benar === true)->count();
        $skorTotal = round($jawaban->sum(fn ($item) => (float) ($item->skor ?? 0)), 2);
        $nilaiTersedia = $peserta->status === 'selesai';
        $nilai = $nilaiTersedia && $bobotTotal > 0
            ? round(($skorTotal / $bobotTotal) * 100, 2)
            : null;

        $belumDikoreksiOtomatis = collect($soalOtomatisIds)
            ->filter(fn ($id) => ! $jawaban->has($id) || is_null($jawaban[$id]->skor))
            ->count();
        $perluKoreksiManual = collect($soalManualIds)
            ->filter(fn ($id) => $jawaban->has($id) && ! is_null($jawaban[$id]->jawaban) && is_null($jawaban[$id]->skor))
            ->count();
        $belumJawab = max(0, $soalUjian->count() - $jawabanTersimpan);
        $status = $this->statusHasil(
            $peserta,
            $nilai,
            $kkm,
            $belumDikoreksiOtomatis,
            $perluKoreksiManual,
        );

        return [
            'peserta' => $peserta,
            'jawaban_tersimpan' => $jawabanTersimpan,
            'jawaban_dikoreksi' => $jawabanDikoreksi,
            'benar' => $benar,
            'salah' => max(0, $jawabanDikoreksi - $benar),
            'belum_jawab' => $belumJawab,
            'belum_dikoreksi_otomatis' => $belumDikoreksiOtomatis,
            'perlu_koreksi_manual' => $perluKoreksiManual,
            'skor_total' => $skorTotal,
            'nilai' => $nilai,
            'nilai_tersedia' => $nilaiTersedia,
            ...$status,
        ];
    }

    private function statusHasil(
        PesertaUjianCbt $peserta,
        ?float $nilai,
        ?int $kkm,
        int $belumDikoreksiOtomatis,
        int $perluKoreksiManual,
    ): array {
        if ($peserta->status !== 'selesai') {
            $statusKehadiran = $peserta->status_kehadiran_ujian ?: 'belum_absen';

            if (in_array($statusKehadiran, ['sakit', 'izin', 'alfa'], true)) {
                $labelSusulan = match ($peserta->status_susulan) {
                    'menunggu_jadwal' => 'Menunggu jadwal susulan',
                    'dijadwalkan' => 'Susulan dijadwalkan',
                    'dibatalkan' => 'Susulan dibatalkan',
                    default => 'Belum mengikuti',
                };

                return [
                    'kode_status_hasil' => 'belum_mengikuti',
                    'label_status_hasil' => $labelSusulan.' - '.$peserta->labelStatusKehadiranUjian(),
                    'badge_status_hasil' => $statusKehadiran === 'alfa' ? 'badge-inactive' : 'badge-warning',
                ];
            }

            if (is_null($peserta->waktu_mulai) && $statusKehadiran === 'belum_absen') {
                return [
                    'kode_status_hasil' => 'belum_mengikuti',
                    'label_status_hasil' => 'Belum mengikuti ujian',
                    'badge_status_hasil' => 'badge-muted',
                ];
            }

            return [
                'kode_status_hasil' => 'belum_selesai',
                'label_status_hasil' => match ($peserta->status) {
                    'sedang_mengerjakan' => 'Sedang mengerjakan',
                    'terblokir' => 'Akses terblokir',
                    'nonaktif' => 'Peserta nonaktif',
                    default => in_array($statusKehadiran, ['hadir', 'terlambat'], true)
                        ? 'Hadir, belum mulai'
                        : 'Belum selesai',
                },
                'badge_status_hasil' => 'badge-muted',
            ];
        }

        if ($belumDikoreksiOtomatis > 0) {
            return [
                'kode_status_hasil' => 'perlu_koreksi_otomatis',
                'label_status_hasil' => 'Perlu koreksi otomatis',
                'badge_status_hasil' => 'badge-warning',
            ];
        }

        if ($perluKoreksiManual > 0) {
            return [
                'kode_status_hasil' => 'perlu_koreksi_manual',
                'label_status_hasil' => 'Perlu koreksi manual',
                'badge_status_hasil' => 'badge-warning',
            ];
        }

        if (! is_null($kkm) && $nilai >= $kkm) {
            return [
                'kode_status_hasil' => 'tuntas',
                'label_status_hasil' => 'Tuntas',
                'badge_status_hasil' => 'badge-active',
            ];
        }

        return [
            'kode_status_hasil' => 'belum_tuntas',
            'label_status_hasil' => is_null($kkm) ? 'Selesai' : 'Belum tuntas',
            'badge_status_hasil' => is_null($kkm) ? 'badge-active' : 'badge-inactive',
        ];
    }

    private function ringkasan(Collection $rekapSemua): array
    {
        $hasilFinal = $rekapSemua->filter(fn ($item) => in_array(
            $item['kode_status_hasil'],
            ['tuntas', 'belum_tuntas'],
            true,
        ));
        $nilaiFinal = $hasilFinal->pluck('nilai');

        return [
            'total_peserta' => $rekapSemua->count(),
            'rata_rata' => $hasilFinal->isNotEmpty() ? round($nilaiFinal->avg(), 2) : null,
            'nilai_tertinggi' => $hasilFinal->isNotEmpty() ? round($nilaiFinal->max(), 2) : null,
            'nilai_terendah' => $hasilFinal->isNotEmpty() ? round($nilaiFinal->min(), 2) : null,
            'hasil_final' => $hasilFinal->count(),
            'rata_rata_final' => $hasilFinal->isNotEmpty() ? round($nilaiFinal->avg(), 2) : null,
            'nilai_tertinggi_final' => $hasilFinal->isNotEmpty() ? round($nilaiFinal->max(), 2) : null,
            'tuntas' => $rekapSemua->where('kode_status_hasil', 'tuntas')->count(),
            'belum_tuntas' => $rekapSemua->where('kode_status_hasil', 'belum_tuntas')->count(),
            'perlu_koreksi' => $rekapSemua
                ->filter(fn ($item) => in_array($item['kode_status_hasil'], ['perlu_koreksi_otomatis', 'perlu_koreksi_manual'], true))
                ->count(),
            'belum_selesai' => $rekapSemua->where('kode_status_hasil', 'belum_selesai')->count(),
            'belum_mengikuti' => $rekapSemua->where('kode_status_hasil', 'belum_mengikuti')->count(),
        ];
    }

    private function ambilSoalUjian(UjianCbt $ujianCbt): Collection
    {
        return $ujianCbt->soalUjianCbt()
            ->with('soalCbt')
            ->get()
            ->sortBy(fn (SoalUjianCbt $item) => sprintf('%05d|%08d', $item->nomor_urut ?? 9999, $item->id))
            ->values()
            ->take($ujianCbt->jumlah_soal);
    }
}
