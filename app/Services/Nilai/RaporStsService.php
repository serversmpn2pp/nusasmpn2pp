<?php

namespace App\Services\Nilai;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\RaporStsKelas;
use App\Models\UjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use App\Services\Cbt\PengacakPenyajianCbt;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class RaporStsService
{
    public static function dapatMengakses(?Pengguna $pengguna): bool
    {
        return $pengguna && $pengguna->memilikiIzin('nilai.rekap')
            && ($pengguna->administrator() || $pengguna->memilikiPeran('wakil_pimpinan_kurikulum')
                || ($pengguna->memilikiPeran('wali_kelas') && filled($pengguna->pegawai_id)));
    }

    public function kelasDalamCakupan(Pengguna $pengguna): Builder
    {
        abort_unless(self::dapatMengakses($pengguna), 403);

        return Kelas::query()->when(! $pengguna->administrator() && ! $pengguna->memilikiPeran('wakil_pimpinan_kurikulum'),
            fn ($q) => $q->where('wali_kelas_id', $pengguna->pegawai_id));
    }

    public function pastikanCakupan(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, Kelas $kelas): void
    {
        abort_unless($this->kelasDalamCakupan($pengguna)->whereKey($kelas->id)->exists(), 403);
        abort_unless($kegiatan->jenisUjianCbt?->kode === 'STS'
            && (int) $kelas->tahun_pelajaran_id === (int) $kegiatan->tahun_pelajaran_id, 404);
    }

    public function pengaturan(KegiatanUjianCbt $kegiatan, Kelas $kelas): RaporStsKelas
    {
        $tersimpan = RaporStsKelas::where('kegiatan_ujian_cbt_id', $kegiatan->id)->where('kelas_id', $kelas->id)->first();
        if ($tersimpan) {
            return $tersimpan;
        }
        $tahun = $kegiatan->tahunPelajaran;
        $awal = $tahun->tanggal_mulai->copy();
        if ($kegiatan->semester === 'genap') {
            $awal = Carbon::create($awal->year + 1, 1, 1)->max($awal);
        }
        $akhir = $kegiatan->tanggal_selesai->copy()->max($awal);

        return new RaporStsKelas([
            'kegiatan_ujian_cbt_id' => $kegiatan->id, 'kelas_id' => $kelas->id,
            'tanggal_awal_presensi' => $awal, 'tanggal_akhir_presensi' => $akhir,
            'tanggal_rapor' => $akhir, 'versi' => 0,
        ]);
    }

    public function bangun(KegiatanUjianCbt $kegiatan, Kelas $kelas, ?RaporStsKelas $pengaturan = null): array
    {
        $pengaturan ??= $this->pengaturan($kegiatan, $kelas);
        $kelas->loadMissing('waliKelas');
        $anggota = $kelas->anggotaKelas()->with('siswa')->where('status_keanggotaan', 'aktif')
            ->orderByRaw('nomor_absen IS NULL')->orderBy('nomor_absen')->orderBy('id')->get();
        $jadwal = $kegiatan->jadwalUjianCbt()->where('status', '!=', 'dibatalkan')
            ->whereHas('kelas', fn ($q) => $q->where('kelas.id', $kelas->id))
            ->with(['mataPelajaran', 'ujianCbt.soalUjianCbt.soalCbt'])->get();
        // Missing STS sources remain incomplete unless the subject is explicitly excluded for this grade.
        $mapelIds = $kelas->guruMataPelajaran()->where('aktif', true)->pluck('mata_pelajaran_id')
            ->merge($jadwal->pluck('mata_pelajaran_id'))->unique();
        $mapelDikecualikan = app(MapelRaporStsService::class)->dikecualikan($kegiatan, (int) $kelas->tingkat);
        $mapelIds = $mapelIds->diff($mapelDikecualikan);
        $mapel = MataPelajaran::whereIn('id', $mapelIds)->orderBy('urutan')->orderBy('nama')->get()
            ->reject(fn ($item) => $item->menggunakanPredikat())->values();
        $peserta = PesertaUjianCbt::whereIn('ujian_cbt_id', $jadwal->pluck('ujian_cbt_id')->filter())
            ->whereIn('anggota_kelas_id', $anggota->pluck('id'))
            ->with(['jawabanPesertaUjianCbt', 'kelasUjianCbt.komponenNilai.guruMataPelajaran',
                'kelasUjianCbt.komponenNilai.nilaiSiswa' => fn ($q) => $q->whereIn('siswa_id', $anggota->pluck('siswa_id'))])
            ->get()->groupBy('anggota_kelas_id');
        $presensi = AbsensiSiswa::where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
            ->whereIn('siswa_id', $anggota->pluck('siswa_id'))
            ->whereDate('tanggal', '>=', $pengaturan->tanggal_awal_presensi)
            ->whereDate('tanggal', '<=', $pengaturan->tanggal_akhir_presensi)
            ->get(['siswa_id', 'tanggal', 'status_kehadiran'])->groupBy('siswa_id');
        $koreksi = $pengaturan->exists ? $pengaturan->kehadiran()->with('pemeriksa')->get()->keyBy('anggota_kelas_id') : collect();
        $pengecualian = $pengaturan->exists ? $pengaturan->pengecualian()->with('penetap')->get()->groupBy('anggota_kelas_id') : collect();
        $jadwalMapel = $jadwal->groupBy('mata_pelajaran_id');
        $komponenManual = KomponenNilai::where('aktif', true)->where('jenis_komponen', 'sts')->where('semester', $kegiatan->semester)
            ->whereHas('guruMataPelajaran', fn ($q) => $q->whereNotIn('mata_pelajaran_id', $jadwalMapel->keys()))
            ->whereHas('guruMataPelajaran', fn ($q) => $q->where('aktif', true)->where('kelas_id', $kelas->id)->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id))
            ->with(['guruMataPelajaran.kelas', 'guruMataPelajaran.mataPelajaran',
                'nilaiSiswa' => fn ($q) => $q->whereIn('siswa_id', $anggota->pluck('siswa_id'))])->get()
            ->groupBy(fn ($c) => $c->guruMataPelajaran->mata_pelajaran_id);
        $manual = $komponenManual->map(function ($komponen) use ($anggota) {
            if ($komponen->count() !== 1) {
                return ['komponen' => null, 'konteks' => null];
            }
            $c = $komponen->first();

            return ['komponen' => $c, 'konteks' => app(StsManualService::class)->konteks($c, $anggota, $c->nilaiSiswa)];
        });
        $baris = $anggota->map(function ($siswa) use ($pengaturan, $mapel, $jadwalMapel, $peserta, $presensi, $koreksi, $pengecualian, $kelas, $manual) {
            $nilai = $mapel->map(function ($pelajaran) use ($jadwalMapel, $peserta, $pengecualian, $siswa, $manual) {
                $jadwal = $jadwalMapel->get($pelajaran->id, collect());
                $hasil = ['nilai' => null, 'status' => 'Belum ada paket STS'];
                $sumberNilai = null;
                $komponenId = null;
                $pesertaSiswa = null;
                $dapatDikecualikan = false;
                $sidikKondisi = null;
                if ($jadwal->count() > 1) {
                    $hasil['status'] = 'Ada beberapa jadwal STS; periksa sumber nilai';
                } elseif ($jadwal->count() === 1 && $ujian = $jadwal->first()->ujianCbt) {
                    $sumberNilai = 'cbt';
                    $pesertaSiswa = $peserta->get($siswa->id, collect())->firstWhere('ujian_cbt_id', $ujian->id);
                    $hasil = $this->nilaiPeserta($ujian, $pesertaSiswa, $siswa);
                    $dapatDikecualikan = $this->dapatDikecualikan($jadwal->first(), $pesertaSiswa);
                    $sidikKondisi = $this->sidikKondisi($jadwal->first(), $pesertaSiswa);
                } elseif ($jadwal->isEmpty() && $m = $manual->get($pelajaran->id)) {
                    $sumberNilai = 'manual';
                    $komponenId = $m['komponen']?->id;
                    $hasil = $this->nilaiManual($m['konteks'], $siswa->siswa_id);
                }
                $catatan = $pengecualian->get($siswa->id, collect())->firstWhere('mata_pelajaran_id', $pelajaran->id);
                $dikecualikan = $dapatDikecualikan && $catatan?->aktif
                    && (int) $catatan->peserta_ujian_cbt_id === (int) $pesertaSiswa->id
                    && hash_equals($catatan->sidik_kondisi, $sidikKondisi);
                if ($dikecualikan) {
                    $hasil['status'] = 'Tidak mengikuti STS';
                }

                return ['mapel' => $pelajaran, ...$hasil,
                    'sumber_nilai' => $sumberNilai, 'komponen_nilai_id' => $komponenId,
                    'keterangan' => $dikecualikan ? 'Tidak mengikuti STS' : $this->keterangan($hasil['nilai']),
                    'dapat_dikecualikan' => $dapatDikecualikan, 'dikecualikan' => (bool) $dikecualikan,
                    'pengecualian' => $catatan, 'sidik_kondisi' => $sidikKondisi,
                    'peserta_id' => $pesertaSiswa?->id,
                ];
            });
            $rekaman = $presensi->get($siswa->siswa_id, collect())->unique(fn ($p) => $p->tanggal->toDateString());
            $sumber = [
                'awal' => $pengaturan->tanggal_awal_presensi->toDateString(),
                'akhir' => $pengaturan->tanggal_akhir_presensi->toDateString(),
                'sakit' => $rekaman->where('status_kehadiran', 'sakit')->count(),
                'izin' => $rekaman->where('status_kehadiran', 'izin')->count(),
                'alfa' => $rekaman->where('status_kehadiran', 'alfa')->count(),
                'hari_tercatat' => $rekaman->count(),
            ];
            $koreksiSiswa = $koreksi->get($siswa->id);
            $diperiksa = $koreksiSiswa?->diperiksa_pada && $koreksiSiswa->rekap_sumber === $sumber;
            $kehadiran = collect(['sakit', 'izin', 'alfa'])->mapWithKeys(fn ($jenis) => [$jenis => $koreksiSiswa ? $koreksiSiswa->{$jenis} : $sumber[$jenis]])->all();
            $lengkap = $nilai->isNotEmpty() && $nilai->every(fn ($n) => $n['nilai'] !== null);
            $tuntas = $nilai->isNotEmpty() && $nilai->every(fn ($n) => $n['nilai'] !== null || $n['dikecualikan']);
            $bernilai = $nilai->whereNotNull('nilai');

            return [
                'anggota' => $siswa, 'nilai' => $nilai, 'nilai_lengkap' => $lengkap, 'nilai_tuntas' => $tuntas,
                'jumlah_bernilai' => $bernilai->count(), 'jumlah_pengecualian' => $nilai->where('dikecualikan', true)->count(),
                'jumlah' => $tuntas && $bernilai->isNotEmpty() ? round($bernilai->sum('nilai'), 2) : null,
                'rata' => $tuntas && $bernilai->isNotEmpty() ? round($bernilai->avg('nilai'), 2) : null,
                'sumber' => $sumber, 'sidik_sumber' => $this->sidikSumber($sumber), 'koreksi' => $koreksiSiswa,
                'kehadiran' => $kehadiran, 'diperiksa' => (bool) $diperiksa,
                'sumber_berubah' => $koreksiSiswa && $koreksiSiswa->rekap_sumber !== $sumber,
                'siap' => $pengaturan->exists && $diperiksa && $kelas->waliKelas !== null
                    && ! $pengaturan->tanggal_akhir_presensi->isFuture(),
            ];
        });

        return compact('kegiatan', 'kelas', 'pengaturan', 'mapel', 'baris');
    }

    public function sidikSumber(array $sumber): string
    {
        return hash('sha256', json_encode($sumber));
    }

    private function nilaiManual(?array $konteks, int $siswaId): array
    {
        if ($konteks === null) {
            return ['nilai' => null, 'status' => 'Komponen STS manual perlu diperiksa'];
        }
        if (! $konteks['difinalisasi']) {
            return ['nilai' => null, 'status' => $konteks['berubah']
                ? 'Nilai STS manual berubah; finalisasi ulang'
                : 'Nilai STS manual belum difinalisasi'];
        }
        $nilai = $konteks['nilai']->get($siswaId)?->nilai;

        return $nilai !== null
            ? ['nilai' => round((float) $nilai, 2), 'status' => 'Final manual']
            : ['nilai' => null, 'status' => 'Belum ada nilai STS manual'];
    }

    private function dapatDikecualikan(JadwalUjianCbt $jadwal, ?PesertaUjianCbt $peserta): bool
    {
        return $jadwal->ujianCbt?->hasil_difinalisasi_pada !== null
            && $jadwal->tanggal->copy()->setTimeFromTimeString($jadwal->waktu_selesai)->lte(now())
            && $peserta !== null
            && in_array($peserta->status_kehadiran_ujian, ['sakit', 'izin', 'alfa'], true)
            && ! in_array($peserta->status_susulan, ['dijadwalkan', 'selesai'], true)
            && in_array($peserta->status, ['aktif', 'nonaktif'], true)
            && $peserta->waktu_mulai === null && $peserta->waktu_selesai === null
            // Ignore legacy empty rows that may have been created before absent students were excluded.
            && ! $peserta->jawabanPesertaUjianCbt->contains(fn ($jawaban) => filled($jawaban->jawaban)
                || $jawaban->waktu_dijawab !== null || (float) ($jawaban->skor ?? 0) !== 0.0);
    }

    private function sidikKondisi(JadwalUjianCbt $jadwal, ?PesertaUjianCbt $peserta): string
    {
        // Recheck an exception after a different exam, finalization, or attendance decision.
        return hash('sha256', json_encode([
            $jadwal->id, $jadwal->ujian_cbt_id, $jadwal->tanggal->toDateString(), $jadwal->waktu_selesai,
            $jadwal->ujianCbt?->hasil_difinalisasi_pada?->toISOString(),
            $peserta?->id, $peserta?->status, $peserta?->status_kehadiran_ujian, $peserta?->status_susulan,
        ]));
    }

    private function nilaiPeserta(UjianCbt $ujian, ?PesertaUjianCbt $peserta, AnggotaKelas $anggota): array
    {
        if (! $ujian->hasil_difinalisasi_pada) {
            return ['nilai' => null, 'status' => 'Belum difinalisasi guru mapel'];
        }
        if (! $peserta || $peserta->status !== 'selesai') {
            return ['nilai' => null, 'status' => 'Belum mengikuti / menyelesaikan STS'];
        }
        // Applied component grades are authoritative, including subsequent teacher corrections or blanks.
        if ($peserta->nilai_diterapkan_pada !== null || $peserta->nilai_siswa_id !== null) {
            return $this->nilaiDiterapkan($ujian, $peserta, $anggota);
        }
        $soal = app(PengacakPenyajianCbt::class)->urutkanSoal($ujian, $peserta, $ujian->soalUjianCbt)
            ->take($ujian->jumlah_soal);
        $jawaban = $peserta->jawabanPesertaUjianCbt->keyBy('soal_ujian_cbt_id');
        $skor = 0;
        $maksimal = 0;
        foreach ($soal as $item) {
            $hasil = $jawaban->get($item->id);
            $otomatis = in_array($item->soalCbt?->jenis_soal, KoreksiOtomatisCbtService::JENIS_OTOMATIS, true);
            if (($otomatis || $hasil?->jawaban !== null) && $hasil?->skor === null) {
                return ['nilai' => null, 'status' => 'Koreksi nilai belum lengkap'];
            }
            $nilaiSoal = (float) ($hasil?->skor ?? 0);
            if ($nilaiSoal < 0 || $nilaiSoal > (float) $item->bobot || (float) $item->bobot <= 0) {
                return ['nilai' => null, 'status' => 'Skor perlu diperiksa guru mapel'];
            }
            $skor += $nilaiSoal;
            $maksimal += (float) $item->bobot;
        }

        return $maksimal > 0
            ? ['nilai' => round($skor / $maksimal * 100, 2), 'status' => 'Final']
            : ['nilai' => null, 'status' => 'Belum ada soal bernilai'];
    }

    private function nilaiDiterapkan(UjianCbt $ujian, PesertaUjianCbt $peserta, AnggotaKelas $anggota): array
    {
        $kelasUjian = $peserta->kelasUjianCbt;
        $komponen = $kelasUjian?->komponenNilai;
        $guru = $komponen?->guruMataPelajaran;
        if (! $komponen?->aktif || ! $guru?->aktif || $komponen->jenis_komponen !== 'sts'
            || $komponen->semester !== $ujian->semester
            || (int) $kelasUjian->ujian_cbt_id !== (int) $ujian->id
            || (int) $kelasUjian->kelas_id !== (int) $anggota->kelas_id
            || (int) $guru->kelas_id !== (int) $anggota->kelas_id
            || (int) $guru->tahun_pelajaran_id !== (int) $ujian->tahun_pelajaran_id
            || (int) $guru->mata_pelajaran_id !== (int) $ujian->mata_pelajaran_id) {
            return ['nilai' => null, 'status' => 'Komponen nilai STS tujuan perlu diperiksa'];
        }
        $nilai = $komponen->nilaiSiswa->firstWhere('siswa_id', $anggota->siswa_id)?->nilai;
        if ($nilai === null) {
            return ['nilai' => null, 'status' => 'Nilai STS pada Input Nilai belum tersedia'];
        }
        if (! is_numeric($nilai) || ! is_finite((float) $nilai) || (float) $nilai < 0 || (float) $nilai > 100) {
            return ['nilai' => null, 'status' => 'Nilai STS pada Input Nilai perlu diperiksa'];
        }

        return ['nilai' => round((float) $nilai, 2), 'status' => 'Final'];
    }

    private function keterangan(?float $nilai): string
    {
        return match (true) {
            $nilai === null => 'Belum tersedia',
            $nilai < 70 => 'Perlu Bimbingan',
            $nilai < 80 => 'Cukup',
            $nilai < 90 => 'Baik',
            default => 'Sangat Baik',
        };
    }
}
