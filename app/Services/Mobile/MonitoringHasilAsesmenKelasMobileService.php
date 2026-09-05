<?php

namespace App\Services\Mobile;

use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MonitoringHasilAsesmenKelasMobileService
{
    public function monitoring(Pengguna $pengguna, UjianCbt $asesmen, array $filter): array
    {
        $this->pastikanDapatDiakses($pengguna, $asesmen);
        $this->muatAsesmen($asesmen);

        $kelasId = filled($filter['kelas_id'] ?? null) ? (int) $filter['kelas_id'] : null;
        $status = $filter['status'] ?? 'semua';
        $jumlahSoalPaket = $asesmen->soalUjianCbt()->count();
        $jumlahSoalTampil = min((int) $asesmen->jumlah_soal, $jumlahSoalPaket);
        $waktuSekarang = now();
        $ringkasan = $this->ringkasanMonitoring($asesmen);

        $query = $asesmen->pesertaUjianCbt()
            ->with(['kelasUjianCbt.kelas', 'anggotaKelas.siswa'])
            ->withCount([
                'jawabanPesertaUjianCbt as jumlah_jawaban_tersimpan' => fn (Builder $query) => $query->whereNotNull('jawaban'),
                'jawabanPesertaUjianCbt as jumlah_jawaban_ragu' => fn (Builder $query) => $query->where('ragu', true),
            ])
            ->when($kelasId, fn (Builder $query) => $query->whereHas(
                'kelasUjianCbt',
                fn (Builder $query) => $query->where('kelas_id', $kelasId),
            ))
            ->when($status !== 'semua', fn (Builder $query) => $this->terapkanStatusMonitoring($query, $status));

        $items = $query->get()
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%s|%05d|%s',
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->map(fn (PesertaUjianCbt $item) => $this->pesertaMonitoring(
                $item,
                $asesmen,
                $jumlahSoalTampil,
                $waktuSekarang,
            ))
            ->values();

        return [
            'dihasilkan_pada' => $waktuSekarang->toISOString(),
            'pembaruan_berikutnya_detik' => 15,
            'asesmen' => $this->asesmen($asesmen, $jumlahSoalPaket, $jumlahSoalTampil),
            'kesiapan' => [
                'paket_dibuka' => in_array($asesmen->status, ['terjadwal', 'berlangsung'], true),
                'soal_siap' => $jumlahSoalTampil > 0,
                'peserta_siap' => $ringkasan['total'] > 0,
            ],
            'ringkasan' => $ringkasan,
            'referensi' => [
                'kelas' => $this->pilihanKelas($asesmen),
                'status' => $this->pilihanStatusMonitoring(),
            ],
            'filter' => ['kelas_id' => $kelasId, 'status' => $status],
            'items' => $items,
        ];
    }

    public function hasil(Pengguna $pengguna, UjianCbt $asesmen, array $filter): array
    {
        $this->pastikanDapatDiakses($pengguna, $asesmen);

        return $this->susunHasil($asesmen, $filter);
    }

    /**
     * Menyusun hasil memakai kalkulasi yang sama untuk paket ujian terpusat.
     * Pemeriksaan cakupan akses dilakukan oleh layanan ujian terpusat sebelum
     * metode ini dipanggil.
     */
    public function hasilUjianTerpusat(UjianCbt $asesmen, array $filter): array
    {
        abort_unless($asesmen->ujianTerpusat(), 404);

        return $this->susunHasil($asesmen, $filter);
    }

    private function susunHasil(UjianCbt $asesmen, array $filter): array
    {
        $this->muatAsesmen($asesmen);

        $kelasId = filled($filter['kelas_id'] ?? null) ? (int) $filter['kelas_id'] : null;
        $status = $filter['status'] ?? 'semua';
        $soal = $this->soalTampil($asesmen);
        $jumlahSoal = $soal->count();
        $bobotTotal = round($soal->sum(fn (SoalUjianCbt $item) => (float) $item->bobot), 2);
        $soalOtomatisIds = $soal
            ->filter(fn (SoalUjianCbt $item) => in_array(
                $item->soalCbt?->jenis_soal,
                KoreksiOtomatisCbtService::JENIS_OTOMATIS,
                true,
            ))
            ->pluck('id')
            ->all();
        $soalManualIds = $soal
            ->reject(fn (SoalUjianCbt $item) => in_array(
                $item->soalCbt?->jenis_soal,
                KoreksiOtomatisCbtService::JENIS_OTOMATIS,
                true,
            ))
            ->pluck('id')
            ->all();

        $semua = $asesmen->pesertaUjianCbt()
            ->with(['kelasUjianCbt.kelas', 'anggotaKelas.siswa', 'jawabanPesertaUjianCbt'])
            ->when($kelasId, fn (Builder $query) => $query->whereHas(
                'kelasUjianCbt',
                fn (Builder $query) => $query->where('kelas_id', $kelasId),
            ))
            ->get()
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%s|%05d|%s',
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->map(fn (PesertaUjianCbt $item) => $this->hasilPeserta(
                $item,
                $soal,
                $soalOtomatisIds,
                $soalManualIds,
                $bobotTotal,
                $asesmen->kkm,
            ))
            ->values();

        return [
            'dihasilkan_pada' => now()->toISOString(),
            'asesmen' => $this->asesmen($asesmen, $asesmen->soalUjianCbt()->count(), $jumlahSoal),
            'jumlah_soal' => $jumlahSoal,
            'bobot_total' => $bobotTotal,
            'ringkasan' => $this->ringkasanHasil($semua),
            'referensi' => [
                'kelas' => $this->pilihanKelas($asesmen),
                'status' => $this->pilihanStatusHasil(),
            ],
            'filter' => ['kelas_id' => $kelasId, 'status' => $status],
            'items' => $semua
                ->when($status !== 'semua', fn (Collection $items) => $items->where('status', $status))
                ->values(),
        ];
    }

    private function muatAsesmen(UjianCbt $asesmen): void
    {
        $asesmen->loadMissing([
            'tahunPelajaran:id,nama',
            'mataPelajaran:id,nama',
            'kelasUjianCbt.kelas:id,nama',
            'kelasUjianCbt.komponenNilai:id,nama',
        ]);
    }

    private function pastikanDapatDiakses(Pengguna $pengguna, UjianCbt $asesmen): void
    {
        abort_unless($asesmen->asesmenKelas() && $asesmen->dapatDikelolaOleh($pengguna), 403);
    }

    private function asesmen(UjianCbt $asesmen, int $jumlahSoalPaket, int $jumlahSoalTampil): array
    {
        return [
            'id' => (int) $asesmen->id,
            'nama' => $asesmen->nama,
            'kode' => $asesmen->kode,
            'mata_pelajaran' => $asesmen->mataPelajaran?->nama ?? '-',
            'tahun_pelajaran' => $asesmen->tahunPelajaran?->nama,
            'semester' => $asesmen->semester,
            'tingkat' => (int) $asesmen->tingkat,
            'status' => $asesmen->status,
            'label_status' => $asesmen->labelStatus(),
            'tanggal_mulai' => $asesmen->tanggal_mulai?->toISOString(),
            'tanggal_selesai' => $asesmen->tanggal_selesai?->toISOString(),
            'durasi_menit' => (int) $asesmen->durasi_menit,
            'kkm' => $asesmen->kkm === null ? null : (int) $asesmen->kkm,
            'jumlah_soal_paket' => $jumlahSoalPaket,
            'jumlah_soal_tampil' => $jumlahSoalTampil,
            'kelas' => $asesmen->kelasUjianCbt->map(fn ($item) => [
                'id' => (int) $item->kelas_id,
                'nama' => $item->kelas?->nama ?? '-',
                'komponen_nilai' => $item->komponenNilai?->nama,
            ])->sortBy('nama')->values(),
        ];
    }

    private function ringkasanMonitoring(UjianCbt $asesmen): array
    {
        $baris = $asesmen->pesertaUjianCbt()
            ->selectRaw("
                count(*) as total,
                sum(case when status = 'aktif' and (status_kehadiran_ujian is null or status_kehadiran_ujian = 'belum_absen') then 1 else 0 end) as belum_hadir,
                sum(case when status = 'aktif' and status_kehadiran_ujian in ('hadir', 'terlambat') then 1 else 0 end) as hadir_belum_mulai,
                sum(case when status = 'aktif' and status_kehadiran_ujian in ('sakit', 'izin', 'alfa') then 1 else 0 end) as tidak_hadir,
                sum(case when status = 'sedang_mengerjakan' then 1 else 0 end) as sedang_mengerjakan,
                sum(case when status = 'selesai' then 1 else 0 end) as selesai,
                sum(case when status = 'nonaktif' then 1 else 0 end) as nonaktif,
                sum(case when status = 'terblokir' then 1 else 0 end) as terblokir
            ")
            ->first();

        return [
            'total' => (int) ($baris->total ?? 0),
            'belum_hadir' => (int) ($baris->belum_hadir ?? 0),
            'hadir_belum_mulai' => (int) ($baris->hadir_belum_mulai ?? 0),
            'tidak_hadir' => (int) ($baris->tidak_hadir ?? 0),
            'sedang_mengerjakan' => (int) ($baris->sedang_mengerjakan ?? 0),
            'selesai' => (int) ($baris->selesai ?? 0),
            'nonaktif' => (int) ($baris->nonaktif ?? 0),
            'terblokir' => (int) ($baris->terblokir ?? 0),
        ];
    }

    private function pesertaMonitoring(
        PesertaUjianCbt $peserta,
        UjianCbt $asesmen,
        int $jumlahSoal,
        CarbonInterface $waktuSekarang,
    ): array {
        $status = $peserta->statusPelaksanaan();
        $kehadiran = $peserta->status_kehadiran_ujian ?: 'belum_absen';
        $jawaban = (int) $peserta->jumlah_jawaban_tersimpan;
        $sisaMenit = null;

        if (in_array($peserta->status, ['sedang_mengerjakan', 'terblokir'], true) && $peserta->waktu_mulai) {
            $batas = $peserta->waktu_mulai->copy()->addMinutes($asesmen->durasi_menit);
            if ($asesmen->tanggal_selesai && $asesmen->tanggal_selesai->lt($batas)) {
                $batas = $asesmen->tanggal_selesai;
            }
            $sisaMenit = max(0, (int) ceil($waktuSekarang->diffInSeconds($batas, false) / 60));
        }

        return [
            'id' => (int) $peserta->id,
            'siswa' => $this->siswa($peserta),
            'kelas' => $peserta->kelasUjianCbt?->kelas?->nama ?? '-',
            'status' => $status,
            'label_status' => $peserta->labelStatusPelaksanaan(),
            'nada_status' => $this->nadaPelaksanaan($status),
            'kehadiran' => $kehadiran,
            'label_kehadiran' => PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN[$kehadiran]
                ?? str($kehadiran)->headline()->toString(),
            'nada_kehadiran' => $this->nadaKehadiran($kehadiran),
            'jawaban_tersimpan' => $jawaban,
            'jawaban_ragu' => (int) $peserta->jumlah_jawaban_ragu,
            'persen_jawaban' => $jumlahSoal > 0 ? min(100, (int) round(($jawaban / $jumlahSoal) * 100)) : 0,
            'waktu_mulai' => $peserta->waktu_mulai?->toISOString(),
            'waktu_selesai' => $peserta->waktu_selesai?->toISOString(),
            'sisa_menit' => $sisaMenit,
            'jumlah_pindah_aplikasi' => (int) $peserta->jumlah_pindah_aplikasi,
            'durasi_di_luar_aplikasi_detik' => (int) $peserta->durasi_di_luar_aplikasi_detik,
            'heartbeat_terakhir_pada' => $peserta->heartbeat_terakhir_pada?->toISOString(),
            'ditahan_mode_aman_pada' => $peserta->ditahan_mode_aman_pada?->toISOString(),
        ];
    }

    private function terapkanStatusMonitoring(Builder $query, string $status): Builder
    {
        return match ($status) {
            'belum_hadir' => $query->where('status', 'aktif')->where(
                fn (Builder $query) => $query->whereNull('status_kehadiran_ujian')
                    ->orWhere('status_kehadiran_ujian', 'belum_absen'),
            ),
            'hadir_belum_mulai' => $query->where('status', 'aktif')
                ->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat']),
            'tidak_hadir' => $query->where('status', 'aktif')
                ->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa']),
            'sedang_mengerjakan', 'selesai', 'nonaktif', 'terblokir' => $query->where('status', $status),
            default => $query,
        };
    }

    private function soalTampil(UjianCbt $asesmen): Collection
    {
        return $asesmen->soalUjianCbt()
            ->with('soalCbt')
            ->get()
            ->sortBy(fn (SoalUjianCbt $item) => sprintf('%05d|%08d', $item->nomor_urut ?? 9999, $item->id))
            ->values()
            ->take($asesmen->jumlah_soal);
    }

    private function hasilPeserta(
        PesertaUjianCbt $peserta,
        Collection $soal,
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
        $nilai = $bobotTotal > 0 ? round(($skorTotal / $bobotTotal) * 100, 2) : 0.0;
        $belumOtomatis = collect($soalOtomatisIds)
            ->filter(fn ($id) => ! $jawaban->has($id) || is_null($jawaban[$id]->skor))
            ->count();
        $perluManual = collect($soalManualIds)
            ->filter(fn ($id) => $jawaban->has($id) && ! is_null($jawaban[$id]->jawaban) && is_null($jawaban[$id]->skor))
            ->count();
        [$status, $label, $nada] = $this->statusHasil(
            $peserta,
            $nilai,
            $kkm,
            $belumOtomatis,
            $perluManual,
        );
        $nilaiTersedia = $status !== 'belum_selesai';
        $nilaiSementara = in_array($status, ['perlu_koreksi_otomatis', 'perlu_koreksi_manual'], true);

        return [
            'id' => (int) $peserta->id,
            'siswa' => $this->siswa($peserta),
            'kelas' => $peserta->kelasUjianCbt?->kelas?->nama ?? '-',
            'status_pengerjaan' => $peserta->status,
            'label_status_pengerjaan' => $peserta->labelStatus(),
            'waktu_selesai' => $peserta->waktu_selesai?->toISOString(),
            'jawaban_tersimpan' => $jawabanTersimpan,
            'jawaban_dikoreksi' => $jawabanDikoreksi,
            'benar' => $benar,
            'salah' => max(0, $jawabanDikoreksi - $benar),
            'belum_jawab' => max(0, $soal->count() - $jawabanTersimpan),
            'perlu_koreksi_otomatis' => $belumOtomatis,
            'perlu_koreksi_manual' => $perluManual,
            'skor_total' => $skorTotal,
            'nilai' => $nilaiTersedia ? $nilai : null,
            'status_nilai' => ! $nilaiTersedia ? 'belum_tersedia' : ($nilaiSementara ? 'sementara' : 'akhir'),
            'status' => $status,
            'label_status' => $label,
            'nada_status' => $nada,
            'nilai_sudah_diterapkan' => ! is_null($peserta->nilai_diterapkan_pada),
            'nilai_diterapkan_pada' => $peserta->nilai_diterapkan_pada?->toISOString(),
        ];
    }

    private function statusHasil(
        PesertaUjianCbt $peserta,
        float $nilai,
        ?int $kkm,
        int $belumOtomatis,
        int $perluManual,
    ): array {
        if ($peserta->status !== 'selesai') {
            return ['belum_selesai', 'Belum selesai', 'netral'];
        }
        if ($belumOtomatis > 0) {
            return ['perlu_koreksi_otomatis', 'Perlu koreksi otomatis', 'peringatan'];
        }
        if ($perluManual > 0) {
            return ['perlu_koreksi_manual', 'Perlu koreksi manual', 'peringatan'];
        }
        if (! is_null($kkm) && $nilai >= $kkm) {
            return ['tuntas', 'Tuntas', 'aktif'];
        }

        return [
            'belum_tuntas',
            is_null($kkm) ? 'Selesai' : 'Belum tuntas',
            is_null($kkm) ? 'aktif' : 'bahaya',
        ];
    }

    private function ringkasanHasil(Collection $items): array
    {
        $hasilFinal = $items->whereIn('status', ['tuntas', 'belum_tuntas']);
        $nilaiFinal = $hasilFinal->pluck('nilai')->filter(fn ($nilai) => ! is_null($nilai));

        return [
            'total_peserta' => $items->count(),
            'selesai' => $items->where('status_pengerjaan', 'selesai')->count(),
            'hasil_final' => $hasilFinal->count(),
            'rata_rata_final' => $nilaiFinal->isNotEmpty() ? round((float) $nilaiFinal->avg(), 2) : null,
            'nilai_tertinggi_final' => $nilaiFinal->isNotEmpty() ? round((float) $nilaiFinal->max(), 2) : null,
            'nilai_terendah_final' => $nilaiFinal->isNotEmpty() ? round((float) $nilaiFinal->min(), 2) : null,
            'tuntas' => $items->where('status', 'tuntas')->count(),
            'belum_tuntas' => $items->where('status', 'belum_tuntas')->count(),
            'perlu_koreksi' => $items->whereIn('status', ['perlu_koreksi_otomatis', 'perlu_koreksi_manual'])->count(),
            'belum_selesai' => $items->where('status', 'belum_selesai')->count(),
            'sudah_masuk_nilai' => $items->where('nilai_sudah_diterapkan', true)->count(),
        ];
    }

    private function siswa(PesertaUjianCbt $peserta): array
    {
        $siswa = $peserta->anggotaKelas?->siswa;

        return [
            'id' => (int) ($siswa?->id ?? 0),
            'nama' => $siswa?->nama_lengkap ?? '-',
            'nis' => $siswa?->nis,
            'nisn' => $siswa?->nisn,
            'nomor_absen' => $peserta->anggotaKelas?->nomor_absen,
        ];
    }

    private function pilihanKelas(UjianCbt $asesmen): Collection
    {
        return $asesmen->kelasUjianCbt
            ->map(fn ($item) => ['id' => (int) $item->kelas_id, 'label' => $item->kelas?->nama ?? '-'])
            ->sortBy('label')
            ->values();
    }

    private function pilihanStatusMonitoring(): Collection
    {
        return collect(['semua' => 'Semua status'] + PesertaUjianCbt::DAFTAR_STATUS_PELAKSANAAN)
            ->map(fn ($label, $kode) => ['kode' => $kode, 'label' => $label])
            ->values();
    }

    private function pilihanStatusHasil(): Collection
    {
        return collect([
            'semua' => 'Semua hasil',
            'tuntas' => 'Tuntas',
            'belum_tuntas' => 'Belum tuntas',
            'perlu_koreksi_otomatis' => 'Perlu koreksi otomatis',
            'perlu_koreksi_manual' => 'Perlu koreksi manual',
            'belum_selesai' => 'Belum selesai',
        ])->map(fn ($label, $kode) => ['kode' => $kode, 'label' => $label])->values();
    }

    private function nadaPelaksanaan(string $status): string
    {
        return match ($status) {
            'sedang_mengerjakan', 'selesai' => 'aktif',
            'hadir_belum_mulai' => 'peringatan',
            'tidak_hadir', 'nonaktif', 'terblokir' => 'bahaya',
            default => 'netral',
        };
    }

    private function nadaKehadiran(string $status): string
    {
        return match ($status) {
            'hadir' => 'aktif',
            'terlambat', 'sakit', 'izin' => 'peringatan',
            'alfa' => 'bahaya',
            default => 'netral',
        };
    }
}
