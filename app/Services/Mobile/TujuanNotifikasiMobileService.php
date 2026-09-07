<?php

namespace App\Services\Mobile;

use App\Models\NotifikasiPengguna;

class TujuanNotifikasiMobileService
{
    public function untuk(NotifikasiPengguna $notifikasi): ?string
    {
        $path = $this->path($notifikasi->tautan);

        if (! $path) {
            return null;
        }

        if ($path === '/tugas-pengawas-ujian') {
            return '/tugas-pengawas-ujian';
        }

        if ($path === '/ujian-saya') {
            return '/ujian-saya';
        }

        if ($path === '/nilai-saya') {
            return '/nilai-saya';
        }

        if ($path === '/hasil-survei-saya') {
            return '/monitoring-survei';
        }

        if ($path === '/peringatan-dini-siswa') {
            return '/peringatan-dini-siswa';
        }

        if ($path === '/pusat-verifikasi-pelanggaran') {
            return '/pemeriksaan-pengesahan';
        }

        if ($path === '/pengurangan-poin-siswa') {
            return '/pengurangan-poin-siswa';
        }

        if ($path === '/laporan-pembinaan-siswa') {
            return '/pemeriksaan-pengesahan';
        }

        if ($tujuan = $this->denganId($path, '/pengajuan-barang', '/pengajuan-barang')) {
            return $tujuan;
        }

        if ($tujuan = $this->denganId($path, '/pengajuan-barang-saya', '/pengajuan-saya')) {
            return $tujuan;
        }

        if ($tujuan = $this->denganId($path, '/tugas-pengawas-ujian', '/tugas-pengawas-ujian')) {
            return $tujuan;
        }

        if ($tujuan = $this->denganId($path, '/konfirmasi-berhalangan-ibadah', '/konfirmasi-berhalangan-ibadah')) {
            return $tujuan;
        }

        if ($tujuan = $this->denganId($path, '/perangkat-ajar-saya', '/perangkat-ajar-saya')) {
            return $tujuan;
        }

        if ($tujuan = $this->denganId($path, '/sanksi-poin-siswa', '/pelaksanaan-sanksi-siswa')) {
            return $tujuan;
        }

        if ($tujuan = $this->denganId($path, '/pemeriksaan-perangkat-ajar/guru', '/pemeriksaan-perangkat-ajar/guru')) {
            return $tujuan;
        }

        if ($id = $this->idDariPath($path, '/laporan-pembinaan-siswa')) {
            return $this->tujuanLaporan($notifikasi, $id);
        }

        return null;
    }

    private function path(?string $tautan): ?string
    {
        if (blank($tautan)) {
            return null;
        }

        $path = parse_url($tautan, PHP_URL_PATH);

        if (! is_string($path) || blank($path)) {
            return null;
        }

        return '/'.trim($path, '/');
    }

    private function denganId(string $path, string $asal, string $tujuan): ?string
    {
        $id = $this->idDariPath($path, $asal);

        return $id ? $tujuan.'/'.$id : null;
    }

    private function idDariPath(string $path, string $awal): ?int
    {
        if (! preg_match('#^'.preg_quote($awal, '#').'/([1-9][0-9]*)$#', $path, $cocok)) {
            return null;
        }

        return (int) $cocok[1];
    }

    private function tujuanLaporan(NotifikasiPengguna $notifikasi, int $laporanId): string
    {
        $kunci = (string) $notifikasi->kunci_unik;

        if (str_starts_with($kunci, 'laporan-pembinaan-wali-kelas:')) {
            return '/laporan-siswa-wali/'.$laporanId;
        }

        if (str_starts_with($kunci, 'batas-proses:')
            || str_starts_with($kunci, 'pengesahan-wakil-menunggu:')
            || str_starts_with($kunci, 'laporan-pembinaan-baru:')
            || str_starts_with($kunci, 'laporan-keterlambatan:')) {
            return '/pemeriksaan-pengesahan/'.$laporanId;
        }

        return '/daftar-laporan-siswa/'.$laporanId;
    }
}
