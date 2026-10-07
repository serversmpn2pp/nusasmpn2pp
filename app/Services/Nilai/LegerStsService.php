<?php

namespace App\Services\Nilai;

use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use Illuminate\Support\Collection;

class LegerStsService
{
    public function __construct(private readonly RaporStsService $raporSts) {}

    public function bangun(KegiatanUjianCbt $kegiatan, Kelas $kelas): array
    {
        $laporan = $this->raporSts->bangun($kegiatan, $kelas, sertakanDraf: true);
        $jumlahMapel = $laporan['mapel']->count();

        $baris = $laporan['baris']->map(fn (array $item) => $this->nilaiRanking($item, $jumlahMapel));

        $baris = $this->terapkanRanking($baris);

        $statistikMapel = $this->statistikMapel($laporan['mapel'], $baris);
        $rataMapelTertinggi = $statistikMapel->whereNotNull('rata')->max('rata');
        $statistikMapel = $statistikMapel->map(function (array $statistik) use ($rataMapelTertinggi) {
            $statistik['tertinggi'] = $statistik['rata'] !== null && $statistik['rata'] === $rataMapelTertinggi;

            return $statistik;
        });
        $mapelTertinggi = $statistikMapel->where('tertinggi', true)
            ->sortBy(fn (array $item) => $item['mapel']->nama)->first();

        $rataSiswa = $baris->where('layak_ranking', true)->pluck('rata_leger');
        $ringkasan = [
            'jumlah_siswa' => $baris->count(),
            'masuk_ranking' => $rataSiswa->count(),
            'belum_masuk_ranking' => $baris->count() - $rataSiswa->count(),
            'lengkap_final' => $baris->where('nilai_final_lengkap', true)->count(),
            'rata_kelas' => $rataSiswa->isNotEmpty() ? round($rataSiswa->avg(), 2) : null,
            'rata_tertinggi' => $rataSiswa->isNotEmpty() ? round($rataSiswa->max(), 2) : null,
            'rata_terendah' => $rataSiswa->isNotEmpty() ? round($rataSiswa->min(), 2) : null,
        ];

        return [
            ...$laporan,
            'baris' => $baris,
            'ranking_sementara' => $jumlahMapel > 0 && $baris->contains(fn (array $item) => ! $item['nilai_final_lengkap']),
            'ringkasan' => $ringkasan,
            'distribusi' => $this->distribusi($rataSiswa),
            'statistik_mapel' => $statistikMapel,
            'mapel_tertinggi' => $mapelTertinggi,
        ];
    }

    public function bangunTingkat(KegiatanUjianCbt $kegiatan, Collection $daftarKelas, int $tingkat): array
    {
        $kelas = $daftarKelas->filter(fn (Kelas $item) => $item->tingkat === $tingkat)
            ->sortBy('nama', SORT_NATURAL)->values();
        $laporanKelas = $kelas->map(fn (Kelas $item) => $this->raporSts->bangun($kegiatan, $item, sertakanDraf: true));
        $mapel = $laporanKelas->flatMap(fn (array $laporan) => $laporan['mapel'])
            ->unique('id')
            ->sort(function ($a, $b) {
                return (($a->urutan ?? PHP_INT_MAX) <=> ($b->urutan ?? PHP_INT_MAX))
                    ?: strcasecmp($a->nama, $b->nama);
            })->values();

        $baris = $laporanKelas->flatMap(function (array $laporan) use ($mapel) {
            return $laporan['baris']->map(function (array $item) use ($laporan, $mapel) {
                $nilaiAsli = $item['nilai']->keyBy(fn (array $nilai) => $nilai['mapel']->id);
                $nilai = $mapel->map(function ($pelajaran) use ($nilaiAsli) {
                    return $nilaiAsli->get($pelajaran->id) ?? [
                        'mapel' => $pelajaran,
                        'nilai' => null,
                        'draf' => false,
                        'status' => 'Mata pelajaran belum tersedia di kelas',
                        'keterangan' => 'Belum tersedia',
                        'dapat_dikecualikan' => false,
                        'dikecualikan' => false,
                        'pengecualian' => null,
                        'sidik_kondisi' => null,
                        'peserta_id' => null,
                    ];
                });
                $lengkap = $mapel->isNotEmpty() && $nilai->every(fn (array $hasil) => $hasil['nilai'] !== null);
                $jumlahPengecualian = $nilai->where('dikecualikan', true)->count();
                $barisTingkat = [
                    ...$item,
                    'kelas' => $laporan['kelas'],
                    'nilai' => $nilai,
                    'nilai_lengkap' => $lengkap,
                    'jumlah_pengecualian' => $jumlahPengecualian,
                ];

                return $this->nilaiRanking($barisTingkat, $mapel->count());
            });
        })->values();
        $baris = $this->terapkanRanking($baris);

        $statistikKelas = $this->statistikKelas($kelas, $baris);
        $statistikMapel = $this->statistikMapel($mapel, $baris)->map(function (array $statistik) use ($baris, $kelas) {
            $statistik['per_kelas'] = $kelas->map(function (Kelas $kelas) use ($baris, $statistik) {
                $nilai = $baris->where('kelas.id', $kelas->id)->map(function (array $item) use ($statistik) {
                    return $item['nilai']->firstWhere('mapel.id', $statistik['mapel']->id)['nilai'] ?? null;
                })->reject(fn ($angka) => $angka === null)->values();

                return [
                    'kelas' => $kelas,
                    'jumlah_nilai' => $nilai->count(),
                    'jumlah_siswa' => $baris->where('kelas.id', $kelas->id)->count(),
                    'rata' => $nilai->isNotEmpty() ? round($nilai->avg(), 2) : null,
                ];
            });

            return $statistik;
        });
        $rataMapelTertinggi = $statistikMapel->whereNotNull('rata')->max('rata');
        $statistikMapel = $statistikMapel->map(function (array $statistik) use ($rataMapelTertinggi) {
            $statistik['tertinggi'] = $statistik['rata'] !== null && $statistik['rata'] === $rataMapelTertinggi;

            return $statistik;
        });
        $mapelTertinggi = $statistikMapel->where('tertinggi', true)
            ->sortBy(fn (array $item) => $item['mapel']->nama)->first();
        $rataSiswa = $baris->where('layak_ranking', true)->pluck('rata_leger');

        return [
            'kegiatan' => $kegiatan,
            'tingkat' => $tingkat,
            'kelas' => $kelas,
            'mapel' => $mapel,
            'baris' => $baris,
            'ranking_sementara' => $mapel->isNotEmpty() && $baris->contains(fn (array $item) => ! $item['nilai_final_lengkap']),
            'statistik_kelas' => $statistikKelas,
            'statistik_mapel' => $statistikMapel,
            'mapel_tertinggi' => $mapelTertinggi,
            'distribusi' => $this->distribusi($rataSiswa),
            'ringkasan' => [
                'jumlah_kelas' => $kelas->count(),
                'jumlah_siswa' => $baris->count(),
                'masuk_ranking' => $rataSiswa->count(),
                'belum_masuk_ranking' => $baris->count() - $rataSiswa->count(),
                'lengkap_final' => $baris->where('nilai_final_lengkap', true)->count(),
                'rata_tingkat' => $rataSiswa->isNotEmpty() ? round($rataSiswa->avg(), 2) : null,
                'rata_tertinggi' => $rataSiswa->isNotEmpty() ? round($rataSiswa->max(), 2) : null,
                'rata_terendah' => $rataSiswa->isNotEmpty() ? round($rataSiswa->min(), 2) : null,
                'kelas_tertinggi' => $statistikKelas->firstWhere('ranking', 1),
            ],
        ];
    }

    public function kandidatPenghargaan(array $leger, string $kategori, ?int $mapelId, int $batas): array
    {
        $mapelTerpilih = $kategori === 'mapel'
            ? $leger['mapel']->firstWhere('id', $mapelId)
            : null;
        $kelasDefault = $leger['kelas'] instanceof Kelas ? $leger['kelas'] : null;
        $peringkatMapel = $mapelTerpilih
            ? $this->peringkatMapel($leger['baris'], $mapelTerpilih, $kelasDefault)
            : collect();
        // Award candidates use their own final-only ranking, never the provisional ranking.
        $peringkatFinal = $this->terapkanRanking($leger['baris']->where('nilai_final_lengkap', true));

        $kandidat = $kategori === 'mapel'
            ? $peringkatMapel->where('ranking', '<=', $batas)->values()
            : $peringkatFinal->filter(fn (array $item) => $item['ranking'] !== null && $item['ranking'] <= $batas)
                ->map(function (array $item) use ($kelasDefault) {
                    return [
                        'anggota' => $item['anggota'],
                        'kelas' => $item['kelas'] ?? $kelasDefault,
                        'ranking' => $item['ranking'],
                        'nilai' => $item['rata_leger'],
                        'jumlah_nilai_final' => $item['jumlah_nilai_final'],
                        'jumlah_mapel' => $item['nilai']->count(),
                        'label_penghargaan' => $this->labelPenghargaan($item['ranking'], false),
                    ];
                })->values();
        $jumlahTersedia = $kategori === 'mapel'
            ? $peringkatMapel->count()
            : $peringkatFinal->count();

        $ringkasanMapel = $leger['mapel']->map(function ($pelajaran) use ($leger, $kelasDefault) {
            $peringkat = $this->peringkatMapel($leger['baris'], $pelajaran, $kelasDefault);
            $juara = $peringkat->where('ranking', 1)->values();

            return [
                'mapel' => $pelajaran,
                'jumlah_nilai_final' => $peringkat->count(),
                'nilai_tertinggi' => $juara->first()['nilai'] ?? null,
                'juara' => $juara,
            ];
        });

        return [
            'kategori' => $kategori,
            'batas' => $batas,
            'mapel_terpilih' => $mapelTerpilih,
            'kandidat' => $kandidat,
            'ringkasan_mapel' => $ringkasanMapel,
            'ringkasan' => [
                'jumlah_kandidat' => $kandidat->count(),
                'jumlah_tersedia' => $jumlahTersedia,
                'jumlah_tanpa_data' => $leger['baris']->count() - $jumlahTersedia,
                'nilai_tertinggi' => $kandidat->isNotEmpty() ? $kandidat->max('nilai') : null,
                'nilai_batas' => $kandidat->isNotEmpty() ? $kandidat->min('nilai') : null,
            ],
        ];
    }

    private function peringkatMapel(Collection $baris, $pelajaran, ?Kelas $kelasDefault): Collection
    {
        $bernilai = $baris->map(function (array $item) use ($pelajaran, $kelasDefault) {
            $nilai = $item['nilai']->firstWhere('mapel.id', $pelajaran->id);
            if (($nilai['nilai'] ?? null) === null || ($nilai['draf'] ?? false)) {
                return null;
            }

            return [
                'anggota' => $item['anggota'],
                'kelas' => $item['kelas'] ?? $kelasDefault,
                'ranking' => null,
                'nilai' => (float) $nilai['nilai'],
                'jumlah_nilai_final' => 1,
                'jumlah_mapel' => 1,
                'label_penghargaan' => null,
            ];
        })->filter()->sort(function (array $a, array $b) {
            return ($b['nilai'] <=> $a['nilai'])
                ?: strcasecmp($a['anggota']->siswa->nama_lengkap, $b['anggota']->siswa->nama_lengkap)
                ?: strcasecmp($a['kelas']?->nama ?? '', $b['kelas']?->nama ?? '');
        })->values();

        $nilaiSebelumnya = null;
        $peringkat = null;

        return $bernilai->map(function (array $item, int $index) use (&$nilaiSebelumnya, &$peringkat) {
            if ($nilaiSebelumnya === null || $item['nilai'] !== $nilaiSebelumnya) {
                $peringkat = $index + 1;
                $nilaiSebelumnya = $item['nilai'];
            }
            $item['ranking'] = $peringkat;
            $item['label_penghargaan'] = $this->labelPenghargaan($peringkat, true);

            return $item;
        });
    }

    private function labelPenghargaan(int $peringkat, bool $mapel): string
    {
        return match (true) {
            $mapel && $peringkat === 1 => 'Terbaik Mata Pelajaran',
            $mapel && $peringkat <= 3 => 'Tiga Besar Mata Pelajaran',
            $mapel => 'Sepuluh Besar Mata Pelajaran',
            $peringkat === 1 => 'Juara Umum',
            $peringkat <= 3 => 'Tiga Besar Akademik',
            default => 'Sepuluh Besar Akademik',
        };
    }

    private function terapkanRanking(Collection $baris): Collection
    {
        $berperingkat = $baris->where('layak_ranking', true)
            ->sort(function (array $a, array $b) {
                $berdasarkanRata = $b['rata_leger'] <=> $a['rata_leger'];

                return $berdasarkanRata !== 0
                    ? $berdasarkanRata
                    : strcasecmp($a['anggota']->siswa->nama_lengkap, $b['anggota']->siswa->nama_lengkap);
            })->values();

        $peringkat = [];
        $rataSebelumnya = null;
        $posisi = null;
        foreach ($berperingkat as $index => $item) {
            if ($rataSebelumnya === null || $item['rata_leger'] !== $rataSebelumnya) {
                $posisi = $index + 1;
                $rataSebelumnya = $item['rata_leger'];
            }
            $peringkat[$item['anggota']->id] = $posisi;
        }

        return $baris->map(function (array $item) use ($peringkat) {
            $item['ranking'] = $peringkat[$item['anggota']->id] ?? null;

            return $item;
        })->sort(function (array $a, array $b) {
            if ($a['ranking'] !== null && $b['ranking'] !== null) {
                return ($a['ranking'] <=> $b['ranking'])
                    ?: strcasecmp($a['anggota']->siswa->nama_lengkap, $b['anggota']->siswa->nama_lengkap);
            }
            if ($a['ranking'] !== null) {
                return -1;
            }
            if ($b['ranking'] !== null) {
                return 1;
            }

            return (($a['anggota']->nomor_absen ?? PHP_INT_MAX) <=> ($b['anggota']->nomor_absen ?? PHP_INT_MAX))
                ?: strcasecmp($a['anggota']->siswa->nama_lengkap, $b['anggota']->siswa->nama_lengkap);
        })->values();
    }

    private function statistikKelas(Collection $kelas, Collection $baris): Collection
    {
        $statistik = $kelas->map(function (Kelas $item) use ($baris) {
            $siswa = $baris->where('kelas.id', $item->id);
            $rataSiswa = $siswa->where('layak_ranking', true)->pluck('rata_leger');

            return [
                'kelas' => $item,
                'jumlah_siswa' => $siswa->count(),
                'masuk_ranking' => $rataSiswa->count(),
                'belum_masuk_ranking' => $siswa->count() - $rataSiswa->count(),
                'kelengkapan' => $siswa->isNotEmpty() ? round($siswa->where('nilai_final_lengkap', true)->count() / $siswa->count() * 100, 2) : 0.0,
                'rata' => $rataSiswa->isNotEmpty() ? round($rataSiswa->avg(), 2) : null,
                'tertinggi' => $rataSiswa->isNotEmpty() ? round($rataSiswa->max(), 2) : null,
                'terendah' => $rataSiswa->isNotEmpty() ? round($rataSiswa->min(), 2) : null,
                'ranking' => null,
            ];
        });
        $terurut = $statistik->whereNotNull('rata')->sortByDesc('rata')->values();
        $peringkat = [];
        $rataSebelumnya = null;
        $posisi = null;
        foreach ($terurut as $index => $item) {
            if ($rataSebelumnya === null || $item['rata'] !== $rataSebelumnya) {
                $posisi = $index + 1;
                $rataSebelumnya = $item['rata'];
            }
            $peringkat[$item['kelas']->id] = $posisi;
        }

        return $statistik->map(function (array $item) use ($peringkat) {
            $item['ranking'] = $peringkat[$item['kelas']->id] ?? null;

            return $item;
        })->sort(function (array $a, array $b) {
            if ($a['ranking'] !== null && $b['ranking'] !== null) {
                return ($a['ranking'] <=> $b['ranking']) ?: strcasecmp($a['kelas']->nama, $b['kelas']->nama);
            }

            return $a['ranking'] !== null ? -1 : ($b['ranking'] !== null ? 1 : strcasecmp($a['kelas']->nama, $b['kelas']->nama));
        })->values();
    }

    private function nilaiRanking(array $item, int $jumlahMapel): array
    {
        $tersedia = $item['nilai']->whereNotNull('nilai');
        $jumlahDraf = $tersedia->where('draf', true)->count();
        $jumlahFinal = $tersedia->count() - $jumlahDraf;
        $layak = $jumlahMapel > 0 && $tersedia->isNotEmpty();
        $jumlah = $layak ? round((float) $tersedia->sum('nilai'), 2) : null;

        $item = [
            ...$item,
            'jumlah_mapel' => $jumlahMapel,
            'jumlah_nilai_tersedia' => $tersedia->count(),
            'jumlah_nilai_final' => $jumlahFinal,
            'jumlah_nilai_draf' => $jumlahDraf,
            'nilai_lengkap' => $jumlahMapel > 0 && $tersedia->count() === $jumlahMapel,
            'nilai_final_lengkap' => $jumlahMapel > 0 && $jumlahFinal === $jumlahMapel,
            'layak_ranking' => $layak,
            'jumlah_leger' => $jumlah,
            'rata_leger' => $layak ? round($jumlah / $jumlahMapel, 2) : null,
            'ranking' => null,
        ];

        return [...$item, 'status_ranking' => $this->statusRanking($item, $jumlahMapel)];
    }

    private function statusRanking(array $item, int $jumlahMapel): string
    {
        if ($jumlahMapel === 0) {
            return 'Belum ada mata pelajaran';
        }
        if ($item['nilai_final_lengkap']) {
            return 'Masuk ranking';
        }
        if ($item['layak_ranking']) {
            return 'Ranking sementara';
        }
        if ($item['jumlah_pengecualian'] > 0) {
            return 'Tidak mengikuti STS';
        }

        return 'Belum ada nilai';
    }

    private function statistikMapel(Collection $mapel, Collection $baris): Collection
    {
        return $mapel->map(function ($pelajaran) use ($baris) {
            $dataNilai = $baris->map(fn (array $item) => $item['nilai']->firstWhere('mapel.id', $pelajaran->id))
                ->filter(fn (?array $data) => ($data['nilai'] ?? null) !== null);
            $nilai = $dataNilai->pluck('nilai');

            return [
                'mapel' => $pelajaran,
                'jumlah_nilai' => $nilai->count(),
                'jumlah_draf' => $dataNilai->where('draf', true)->count(),
                'jumlah_final' => $dataNilai->where('draf', false)->count(),
                'jumlah_siswa' => $baris->count(),
                'cakupan' => $baris->isNotEmpty() ? round($nilai->count() / $baris->count() * 100, 2) : 0.0,
                'rata' => $nilai->isNotEmpty() ? round($nilai->avg(), 2) : null,
                'tertinggi_nilai' => $nilai->isNotEmpty() ? round($nilai->max(), 2) : null,
                'terendah_nilai' => $nilai->isNotEmpty() ? round($nilai->min(), 2) : null,
                'tertinggi' => false,
            ];
        });
    }

    private function distribusi(Collection $rataSiswa): Collection
    {
        $kategori = collect([
            ['kode' => 'perlu_bimbingan', 'label' => 'Perlu Bimbingan', 'jumlah' => $rataSiswa->filter(fn ($nilai) => $nilai < 70)->count()],
            ['kode' => 'cukup', 'label' => 'Cukup', 'jumlah' => $rataSiswa->filter(fn ($nilai) => $nilai >= 70 && $nilai < 80)->count()],
            ['kode' => 'baik', 'label' => 'Baik', 'jumlah' => $rataSiswa->filter(fn ($nilai) => $nilai >= 80 && $nilai < 90)->count()],
            ['kode' => 'sangat_baik', 'label' => 'Sangat Baik', 'jumlah' => $rataSiswa->filter(fn ($nilai) => $nilai >= 90)->count()],
        ]);

        return $kategori->map(function (array $item) use ($rataSiswa) {
            $item['persentase'] = $rataSiswa->isNotEmpty()
                ? round($item['jumlah'] / $rataSiswa->count() * 100, 2)
                : 0.0;

            return $item;
        });
    }
}
