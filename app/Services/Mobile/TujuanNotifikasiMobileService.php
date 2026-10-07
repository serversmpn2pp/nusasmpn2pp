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

        if ($path === '/presensi-anak') {
            $path = '/kehadiran-anak-saya';
        } elseif ($path === '/notifikasi' && str_starts_with((string) $notifikasi->kunci_unik, 'presensi-masuk-siswa:')) {
            $path = '/kehadiran-saya';
        }

        if (in_array($path, ['/kehadiran-saya', '/kehadiran-anak-saya'], true)) {
            $parameter = [];
            $siswa = $this->query($notifikasi->tautan, 'siswa_id');
            $bulan = $this->query($notifikasi->tautan, 'bulan');
            if ($siswa && preg_match('/^[1-9][0-9]*$/', $siswa)) {
                $parameter['siswa_id'] = $siswa;
            }
            if ($bulan && preg_match('/^[0-9]{4}-(?:0[1-9]|1[0-2])$/', $bulan)) {
                $parameter['bulan'] = $bulan;
            }

            return $path.($parameter ? '?'.http_build_query($parameter) : '');
        }

        foreach ([
            '/dashboard-humas' => '/humas',
            '/agenda-humas' => '/humas/agenda',
            '/dokumen-humas' => '/humas/dokumen',
            '/pengaduan-humas' => '/humas/pengaduan',
            '/umpan-balik-humas' => '/humas/umpan-balik',
            '/pertemuan-saya' => '/humas/pertemuan-saya',
            '/pengaduan-saya' => '/humas/pengaduan-saya',
            '/umpan-balik-saya' => '/humas/umpan-balik-saya',
        ] as $asal => $tujuan) {
            if ($path === $asal) {
                return $tujuan;
            }
            if ($detail = $this->denganId($path, $asal, $tujuan)) {
                return $detail;
            }
        }

        if ($path === '/tugas-pengawas-ujian') {
            return '/tugas-pengawas-ujian';
        }

        if ($path === '/ujian-saya') {
            return '/ujian-saya';
        }

        if ($path === '/ujian-anak-saya') {
            return '/ujian-anak-saya';
        }

        if ($path === '/nilai-saya') {
            return '/nilai-saya';
        }

        if ($path === '/akademik-anak') {
            $tujuan = $this->query($notifikasi->tautan, 'tab') === 'nilai'
                ? '/nilai-anak-saya'
                : '/jadwal-pelajaran-anak';
            $parameter = array_filter([
                'semester' => $this->query($notifikasi->tautan, 'semester'),
            ]);

            return $tujuan.($parameter ? '?'.http_build_query($parameter) : '');
        }

        if ($path === '/hasil-survei-saya') {
            return '/hasil-survei-saya';
        }

        if ($path === '/pembinaan-poin-anak') {
            return $this->query($notifikasi->tautan, 'tab') === 'poin'
                ? '/pembinaan-poin-anak?tab=poin'
                : '/pembinaan-poin-anak';
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

        if ($tujuan = $this->denganId($path, '/progress-kasus-saya', '/progress-kasus-saya')) {
            return $tujuan;
        }

        if ($tujuan = $this->denganId($path, '/pembinaan-poin-anak', '/pembinaan-poin-anak')) {
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

    private function query(?string $tautan, string $nama): ?string
    {
        $query = parse_url((string) $tautan, PHP_URL_QUERY);
        if (! is_string($query)) {
            return null;
        }

        parse_str($query, $parameter);

        return is_string($parameter[$nama] ?? null) ? $parameter[$nama] : null;
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
            return '/laporan-siswa-kelas/'.$laporanId;
        }

        if (str_starts_with($kunci, 'keputusan-bk:')
            || str_starts_with($kunci, 'keputusan-wakil:')) {
            return '/laporan-saya/'.$laporanId;
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
