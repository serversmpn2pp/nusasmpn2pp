<?php

namespace App\Services\Survei;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\PertanyaanSurveiPembelajaran;
use App\Models\SurveiPembelajaran;
use Illuminate\Support\Collection;

class RekapSurveiPembelajaranService
{
    public const MINIMAL_RESPONDEN = 5;

    public function untukPenugasan(GuruMataPelajaran $penugasan, string $semester): array
    {
        $jumlahSiswa = AnggotaKelas::query()
            ->where('tahun_pelajaran_id', $penugasan->tahun_pelajaran_id)
            ->where('kelas_id', $penugasan->kelas_id)
            ->where('status_keanggotaan', 'aktif')
            ->distinct()
            ->count('siswa_id');
        $survei = SurveiPembelajaran::query()
            ->where('guru_mata_pelajaran_id', $penugasan->id)
            ->where('semester', $semester)
            ->orderByDesc('diisi_pada')
            ->get(['id', 'jawaban', 'snapshot_pertanyaan', 'saran', 'diisi_pada']);

        return $this->susun($survei, $jumlahSiswa);
    }

    public function susun(Collection $survei, int $jumlahSiswa): array
    {
        $ringkasan = $this->ringkas($survei, $jumlahSiswa);
        $hasilTerbuka = $ringkasan['hasilTerbuka'];
        $rincianPertanyaan = collect();
        $daftarSaran = collect();

        if ($hasilTerbuka) {
            $rincianPertanyaan = $this->susunRincianPertanyaan($survei);
            $daftarSaran = $survei
                ->filter(fn (SurveiPembelajaran $item) => filled($item->saran))
                ->map(fn (SurveiPembelajaran $item) => [
                    'saran' => trim((string) $item->saran),
                    'diisi_pada' => $item->diisi_pada,
                ])
                ->values();
        }

        return $ringkasan + compact(
            'rincianPertanyaan',
            'daftarSaran',
        );
    }

    public function ringkas(Collection $survei, int $jumlahSiswa): array
    {
        $jumlahPengisi = $survei->count();
        $persentasePengisian = $jumlahSiswa > 0
            ? min(100, round(($jumlahPengisi / $jumlahSiswa) * 100, 1))
            : ($jumlahPengisi > 0 ? 100 : 0);
        $hasilTerbuka = $jumlahPengisi >= self::MINIMAL_RESPONDEN;
        $semuaNilai = $hasilTerbuka
            ? $survei->flatMap(fn (SurveiPembelajaran $item) => collect($item->jawaban ?? [])
                ->map(fn ($nilai) => (int) $nilai)
                ->filter(fn (int $nilai) => $nilai >= 1 && $nilai <= 5)
                ->values())
            : collect();
        $rataRataKeseluruhan = $semuaNilai->isNotEmpty()
            ? round((float) $semuaNilai->avg(), 2)
            : null;

        return compact(
            'jumlahSiswa',
            'jumlahPengisi',
            'persentasePengisian',
            'hasilTerbuka',
            'rataRataKeseluruhan',
        );
    }

    private function susunRincianPertanyaan(Collection $survei): Collection
    {
        $teksSaatIni = PertanyaanSurveiPembelajaran::pluck('pernyataan', 'kode');
        $kelompok = [];

        foreach ($survei as $item) {
            foreach ($item->jawaban ?? [] as $kode => $nilai) {
                $nilai = (int) $nilai;

                if ($nilai < 1 || $nilai > 5) {
                    continue;
                }

                $snapshot = $item->snapshot_pertanyaan[$kode] ?? [];
                $pernyataan = trim((string) ($snapshot['pernyataan'] ?? $teksSaatIni->get($kode, $kode)));
                $urutan = (int) ($snapshot['urutan'] ?? 999);
                $kunci = $kode.'|'.sha1($pernyataan);

                if (! isset($kelompok[$kunci])) {
                    $kelompok[$kunci] = [
                        'kode' => $kode,
                        'pernyataan' => $pernyataan,
                        'urutan' => $urutan,
                        'nilai' => [],
                    ];
                }

                $kelompok[$kunci]['nilai'][] = $nilai;
            }
        }

        return collect($kelompok)
            ->map(function (array $item): array {
                $nilai = collect($item['nilai']);
                $distribusi = collect(range(1, 5))->mapWithKeys(function (int $pilihan) use ($nilai) {
                    $jumlah = $nilai->filter(fn (int $item) => $item === $pilihan)->count();

                    return [$pilihan => [
                        'jumlah' => $jumlah,
                        'persentase' => $nilai->isNotEmpty()
                            ? round(($jumlah / $nilai->count()) * 100, 1)
                            : 0,
                    ]];
                })->all();

                return $item + [
                    'jumlah_jawaban' => $nilai->count(),
                    'rata_rata' => $nilai->isNotEmpty() ? round((float) $nilai->avg(), 2) : null,
                    'distribusi' => $distribusi,
                ];
            })
            ->sortBy(fn (array $item) => sprintf('%04d|%s', $item['urutan'], $item['pernyataan']))
            ->values();
    }
}
