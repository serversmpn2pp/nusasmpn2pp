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

    public function dokumen(int $siswaId, string $judul, string $pesan, string $kunci): void
    {
        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaOrangTuaUntukSiswa($siswaId)->loadMissing('orangTuaWali')->filter(fn ($akun) => $akun->akunOrangTua()),
            'informasi', $judul, $pesan, route('tahfidz-anak.index', ['siswa' => $siswaId]).'#tf-dokumen-anak', $kunci,
        );
    }
}
