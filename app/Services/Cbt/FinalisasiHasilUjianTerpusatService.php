<?php

namespace App\Services\Cbt;

use App\Models\JadwalUjianCbt;
use App\Models\JawabanPesertaUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\UjianCbt;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Validation\ValidationException;

class FinalisasiHasilUjianTerpusatService
{
    public function __construct(
        private readonly KoreksiOtomatisCbtService $koreksiOtomatis,
        private readonly NotifikasiPenggunaService $notifikasi,
    ) {}

    public function ringkasan(Pengguna $pengguna, UjianCbt $ujian): array
    {
        $ujian->loadMissing(['hasilDifinalisasiOleh:id,nama', 'hasilDipublikasikanOleh:id,nama']);
        $dapatMengelola = $ujian->dapatDikelolaOleh($pengguna);
        $kesiapan = $this->kesiapan($ujian);
        $final = ! is_null($ujian->hasil_difinalisasi_pada);
        $dipublikasikan = $final && (bool) $ujian->tampilkan_hasil;

        return [
            'status' => $dipublikasikan ? 'dipublikasikan' : ($final ? 'final' : 'draf'),
            'label_status' => $dipublikasikan ? 'Dipublikasikan' : ($final ? 'Final' : 'Draf hasil'),
            'dapat_mengelola' => $dapatMengelola,
            'siap_difinalisasi' => $kesiapan['siap'],
            'dapat_finalisasi' => $dapatMengelola && ! $final && $kesiapan['siap'],
            'dapat_batalkan_finalisasi' => $dapatMengelola && $final && ! $dipublikasikan,
            'dapat_publikasi' => $dapatMengelola && $final && ! $dipublikasikan,
            'dapat_batalkan_publikasi' => $dapatMengelola && $dipublikasikan,
            'difinalisasi_pada' => $ujian->hasil_difinalisasi_pada?->toISOString(),
            'difinalisasi_oleh' => $ujian->hasilDifinalisasiOleh?->nama,
            'dipublikasikan_pada' => $dipublikasikan
                ? ($ujian->hasil_dipublikasikan_pada?->toISOString() ?? $ujian->updated_at?->toISOString())
                : null,
            'dipublikasikan_oleh' => $dipublikasikan ? $ujian->hasilDipublikasikanOleh?->nama : null,
            'kesiapan' => $kesiapan,
        ];
    }

    public function finalisasi(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
    ): array {
        $ujian = $this->paket($pengguna, $kegiatan, $jadwal);

        if ($ujian->hasil_difinalisasi_pada) {
            return [
                'pesan' => 'Hasil ujian sudah difinalisasi.',
                'data' => $this->ringkasan($pengguna, $ujian),
            ];
        }

        $this->koreksiOtomatis->koreksiUjian($ujian);
        $kesiapan = $this->kesiapan($ujian->fresh());

        if (! $kesiapan['siap']) {
            throw ValidationException::withMessages([
                'finalisasi' => $this->pesanBelumSiap($kesiapan),
            ]);
        }

        $ujian->update([
            'status' => 'selesai',
            'tampilkan_hasil' => false,
            'hasil_difinalisasi_pada' => now(),
            'hasil_difinalisasi_oleh_pengguna_id' => $pengguna->id,
            'hasil_dipublikasikan_pada' => null,
            'hasil_dipublikasikan_oleh_pengguna_id' => null,
        ]);

        return [
            'pesan' => 'Hasil ujian berhasil difinalisasi dan skor dikunci.',
            'data' => $this->ringkasan($pengguna, $ujian->fresh()),
        ];
    }

    public function batalkanFinalisasi(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
    ): array {
        $ujian = $this->paket($pengguna, $kegiatan, $jadwal);
        abort_if($ujian->tampilkan_hasil, 422, 'Batalkan publikasi sebelum membuka kembali hasil final.');

        $ujian->update([
            'hasil_difinalisasi_pada' => null,
            'hasil_difinalisasi_oleh_pengguna_id' => null,
        ]);

        return [
            'pesan' => 'Finalisasi dibatalkan. Skor dapat dikoreksi kembali.',
            'data' => $this->ringkasan($pengguna, $ujian->fresh()),
        ];
    }

    public function publikasikan(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
    ): array {
        $ujian = $this->paket($pengguna, $kegiatan, $jadwal);
        abort_unless($ujian->hasil_difinalisasi_pada, 422, 'Finalisasi hasil terlebih dahulu sebelum publikasi.');
        $baruDipublikasikan = ! $ujian->tampilkan_hasil;

        $ujian->update([
            'tampilkan_hasil' => true,
            'hasil_dipublikasikan_pada' => $baruDipublikasikan
                ? now()
                : $ujian->hasil_dipublikasikan_pada,
            'hasil_dipublikasikan_oleh_pengguna_id' => $pengguna->id,
        ]);

        if ($baruDipublikasikan) {
            $this->kirimNotifikasi($ujian->fresh(), $kegiatan, $jadwal);
        }

        return [
            'pesan' => 'Hasil ujian dipublikasikan. Siswa dapat melihat nilainya di Ujian Saya.',
            'data' => $this->ringkasan($pengguna, $ujian->fresh()),
        ];
    }

    public function batalkanPublikasi(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
    ): array {
        $ujian = $this->paket($pengguna, $kegiatan, $jadwal);
        $ujian->update([
            'tampilkan_hasil' => false,
            'hasil_dipublikasikan_pada' => null,
            'hasil_dipublikasikan_oleh_pengguna_id' => null,
        ]);

        return [
            'pesan' => 'Publikasi dibatalkan. Hasil tidak lagi terlihat oleh siswa.',
            'data' => $this->ringkasan($pengguna, $ujian->fresh()),
        ];
    }

    private function paket(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
    ): UjianCbt {
        abort_unless((int) $jadwal->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
        $jadwal->loadMissing('ujianCbt');
        $ujian = $jadwal->ujianCbt;
        abort_unless($ujian?->ujianTerpusat(), 404);
        abort_unless($ujian->dapatDikelolaOleh($pengguna), 403);

        return $ujian;
    }

    private function kesiapan(UjianCbt $ujian): array
    {
        $peserta = $ujian->pesertaUjianCbt()->get([
            'id', 'status', 'status_kehadiran_ujian',
        ]);
        $tidakHadir = $peserta->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa']);
        $dikecualikan = $tidakHadir->pluck('id')
            ->merge($peserta->where('status', 'nonaktif')->pluck('id'))
            ->unique();
        $wajibSelesai = $peserta->whereNotIn('id', $dikecualikan);
        $belumSelesai = $wajibSelesai->where('status', '!=', 'selesai')->count();
        $soalManualIds = $ujian->soalUjianCbt()
            ->whereHas('soalCbt', fn ($query) => $query->whereNotIn(
                'jenis_soal',
                KoreksiOtomatisCbtService::JENIS_OTOMATIS,
            ))
            ->orderBy('nomor_urut')
            ->limit($ujian->jumlah_soal)
            ->pluck('id');
        $perluManual = $soalManualIds->isEmpty()
            ? 0
            : JawabanPesertaUjianCbt::query()
                ->whereIn('peserta_ujian_cbt_id', $wajibSelesai->pluck('id'))
                ->whereIn('soal_ujian_cbt_id', $soalManualIds)
                ->whereNotNull('jawaban')
                ->whereNull('skor')
                ->count();
        $jumlahSoal = min($ujian->jumlah_soal, $ujian->soalUjianCbt()->count());

        return [
            'siap' => $peserta->isNotEmpty()
                && $jumlahSoal > 0
                && $belumSelesai === 0
                && $perluManual === 0,
            'total_peserta' => $peserta->count(),
            'peserta_wajib_selesai' => $wajibSelesai->count(),
            'peserta_selesai' => $wajibSelesai->where('status', 'selesai')->count(),
            'peserta_belum_selesai' => $belumSelesai,
            'peserta_tidak_hadir' => $tidakHadir->count(),
            'perlu_koreksi_manual' => $perluManual,
            'jumlah_soal' => $jumlahSoal,
        ];
    }

    private function pesanBelumSiap(array $kesiapan): string
    {
        $alasan = collect([
            $kesiapan['total_peserta'] < 1 ? 'belum ada peserta' : null,
            $kesiapan['jumlah_soal'] < 1 ? 'belum ada soal' : null,
            $kesiapan['peserta_belum_selesai'] > 0
                ? "{$kesiapan['peserta_belum_selesai']} peserta wajib belum selesai"
                : null,
            $kesiapan['perlu_koreksi_manual'] > 0
                ? "{$kesiapan['perlu_koreksi_manual']} jawaban uraian belum dikoreksi"
                : null,
        ])->filter()->implode(', ');

        return 'Hasil belum siap difinalisasi: '.$alasan.'.';
    }

    private function kirimNotifikasi(
        UjianCbt $ujian,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
    ): void {
        $siswaIds = PesertaUjianCbt::query()
            ->where('ujian_cbt_id', $ujian->id)
            ->where('status', 'selesai')
            ->whereHas('anggotaKelas.siswa')
            ->with('anggotaKelas:id,siswa_id')
            ->get()
            ->pluck('anggotaKelas.siswa_id')
            ->filter()
            ->unique();
        $waktu = $ujian->hasil_dipublikasikan_pada?->format('YmdHisv') ?? now()->format('YmdHisv');

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaUntukDaftarSiswa($siswaIds),
            'berhasil',
            'Hasil ujian telah tersedia',
            sprintf(
                'Hasil %s untuk %s telah dipublikasikan. Buka Ujian Saya untuk melihat nilai.',
                $kegiatan->nama,
                $jadwal->mataPelajaran?->nama ?? $ujian->mataPelajaran?->nama ?? 'mata pelajaran',
            ),
            '/ujian-saya',
            "hasil-ujian-dipublikasikan:{$ujian->id}:{$waktu}",
            [
                'kegiatan_ujian_cbt_id' => $kegiatan->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ujian_cbt_id' => $ujian->id,
            ],
        );
    }
}
