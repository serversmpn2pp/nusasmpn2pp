<?php

namespace App\Services\Tahfidz;

use App\Models\PertemuanTahfidz;
use App\Services\Notifikasi\NotifikasiPenggunaService;

class NotifikasiTahfidzService
{
    public function __construct(private readonly NotifikasiPenggunaService $notifikasi) {}

    public function pertemuan(PertemuanTahfidz $pertemuan): void
    {
        $ringkasan = $pertemuan->setoran()
            ->join('anggota_tahfidz as anggota', 'anggota.id', '=', 'setoran_tahfidz.anggota_tahfidz_id')
            ->where('anggota.kelompok_tahfidz_id', $pertemuan->kelompok_tahfidz_id)
            ->groupBy('anggota.siswa_id')->selectRaw('anggota.siswa_id, COUNT(DISTINCT nomor_surah) AS jumlah')->toBase()->get()->keyBy('siswa_id');
        foreach ($this->notifikasi->penggunaUntukDaftarSiswa($ringkasan->keys()) as $akun) {
            if (! $akun->akunSiswa()) {
                continue;
            }
            $jumlah = $ringkasan->get($akun->siswa_id)->jumlah;
            $this->notifikasi->kirim($akun, 'informasi', 'Perkembangan Tahfidz',
                sprintf('Setoran Anda sebanyak %d surah pada pertemuan %s telah dikonfirmasi pembimbing. Buka Tahfidz Saya untuk melihat perkembangan.', $jumlah, $pertemuan->tanggal->format('d/m/Y')),
                route('notifikasi.index'), 'tahfidz-pertemuan:'.$pertemuan->id.':'.$akun->siswa_id);
        }
        $penerima = $this->notifikasi->penggunaOrangTuaUntukDaftarSiswa($ringkasan->keys())
            ->load(['orangTuaWali.siswa' => fn ($q) => $q->whereIn('siswa.id', $ringkasan->keys())->select('siswa.id', 'siswa.nama_lengkap')]);

        foreach ($penerima as $akun) {
            if (! $akun->akunOrangTua()) {
                continue;
            }
            foreach ($akun->orangTuaWali->siswa as $siswa) {
                $jumlah = $ringkasan->get($siswa->id)->jumlah;
                $this->notifikasi->kirim($akun, 'informasi', 'Perkembangan Tahfidz',
                    sprintf('%s menyetorkan %d surah pada pertemuan %s.', $siswa->nama_lengkap, $jumlah, $pertemuan->tanggal->format('d/m/Y')),
                    route('tahfidz-anak.index', ['siswa' => $siswa->id]), 'tahfidz-pertemuan:'.$pertemuan->id.':'.$siswa->id);
            }
        }
    }

    public function dokumen(int $siswaId, string $judul, string $pesan, string $kunci, string $judulSiswa, string $pesanSiswa): void
    {
        // Dokumen tetap khusus orang tua; siswa mendapat kabar pembagiannya.
        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaUntukSiswa($siswaId)->filter(fn ($akun) => $akun->akunSiswa()),
            'informasi', $judulSiswa, $pesanSiswa, route('notifikasi.index'), $kunci,
        );
        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaOrangTuaUntukSiswa($siswaId)->loadMissing('orangTuaWali')->filter(fn ($akun) => $akun->akunOrangTua()),
            'informasi', $judul, $pesan, route('tahfidz-anak.index', ['siswa' => $siswaId]).'#tf-dokumen-anak', $kunci,
        );
    }
}
