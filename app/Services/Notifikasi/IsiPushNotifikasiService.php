<?php

namespace App\Services\Notifikasi;

use App\Models\NotifikasiPengguna;
use Illuminate\Support\Str;

/** Teks layar kunci tidak sama dengan detail yang tersedia dalam sesi NUSA. */
class IsiPushNotifikasiService
{
    public function untuk(NotifikasiPengguna $notifikasi): array
    {
        $path = '/'.trim((string) parse_url((string) $notifikasi->tautan, PHP_URL_PATH), '/');
        $kunci = (string) $notifikasi->kunci_unik;

        // Periksa kategori sensitif lebih dahulu, termasuk notifikasi/job lama.
        if (str_contains($path, 'berhalangan') || str_contains($kunci, 'berhalangan')) {
            return $this->pesan('Pembaruan ibadah privat', 'Ada pembaruan catatan ibadah privat. Buka NUSA untuk melihat detail.');
        }
        foreach (['sanksi', 'pembinaan', 'pelanggaran', 'peringatan-dini', 'progress-kasus', 'pengurangan-poin', 'pendampingan', 'laporan-siswa', 'laporan-saya', 'pemeriksaan-pengesahan'] as $kategori) {
            if (str_contains($path, $kategori) || str_contains($kunci, $kategori)) {
                return $this->pesan('Pembaruan Kesiswaan & BK', 'Ada pembaruan pembinaan siswa. Buka NUSA untuk melihat detail.');
            }
        }

        if (preg_match('/^presensi-masuk-(siswa|orang-tua):[1-9][0-9]*$/', $kunci, $cocok)
            && in_array($path, $cocok[1] === 'orang-tua'
                ? ['/kehadiran-anak-saya', '/presensi-anak'] : ['/kehadiran-saya', '/notifikasi'], true)) {
            $data = $notifikasi->data_tambahan ?? [];
            $jam = $data['jam_masuk'] ?? null;
            $menit = $data['menit_terlambat'] ?? null;
            if (is_string($jam) && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $jam)
                && is_int($menit) && $menit >= 0) {
                $subjek = $cocok[1] === 'orang-tua' ? 'Anak Anda' : 'Anda';
                $status = $menit > 0 ? "terlambat {$menit} menit" : 'tepat waktu';

                return $this->pesan('Presensi masuk tercatat', "{$subjek} tercatat hadir {$status} pukul ".str_replace(':', '.', $jam).' WIB.');
            }
        }

        // Hanya jenis rutin yang diketahui aman yang boleh memakai pesan asli.
        if (($path === '/nilai-saya' && str_starts_with($kunci, 'nilai-dipublikasikan:'))
            || ($path === '/akademik-anak' && str_starts_with($kunci, 'nilai-anak-dipublikasikan:'))
            || ($path === '/ujian-saya' && str_starts_with($kunci, 'hasil-ujian-dipublikasikan:'))
            || ($path === '/ujian-anak-saya' && str_starts_with($kunci, 'hasil-ujian-anak-dipublikasikan:'))
            || ($path === '/hasil-survei-saya' && str_starts_with($kunci, 'hasil-survei-terbuka:'))) {
            return $this->pesan($notifikasi->judul, $notifikasi->pesan);
        }

        // Modul dengan catatan bebas mendapat ringkasan, bukan salinan catatannya.
        foreach ([
            '/tugas-pengawas-ujian' => ['Pembaruan tugas pengawas', 'Ada pembaruan tugas atau bukti pelaksanaan ujian Anda. Periksa di NUSA.'],
            '/perangkat-ajar-saya' => ['Pemeriksaan perangkat ajar', 'Perangkat ajar Anda telah diperiksa. Buka NUSA untuk melihat hasil pemeriksaan.'],
            '/pemeriksaan-perangkat-ajar' => ['Perangkat ajar menunggu pemeriksaan', 'Ada pembaruan perangkat ajar yang perlu Anda periksa di NUSA.'],
            '/pengajuan-barang-saya' => ['Pembaruan pengajuan barang Anda', 'Status pengajuan barang Anda telah diperbarui. Periksa di NUSA.'],
            '/pengajuan-barang' => ['Pembaruan pengajuan barang', 'Ada pengajuan barang yang perlu Anda periksa di NUSA.'],
            '/agenda-humas' => ['Agenda sekolah', 'Ada pengingat agenda atau tindak lanjut pertemuan sekolah. Periksa jadwalnya di NUSA.'],
            '/pertemuan-saya' => ['Pertemuan sekolah', 'Ada pembaruan pertemuan sekolah Anda. Periksa jadwalnya di NUSA.'],
            '/pengaduan-humas' => ['Pembaruan layanan Humas', 'Ada pembaruan tiket layanan Humas yang perlu Anda periksa di NUSA.'],
            '/pengaduan-saya' => ['Pembaruan laporan Anda', 'Ada balasan atau pembaruan status laporan Anda. Buka NUSA untuk melihatnya.'],
            '/umpan-balik-saya' => ['Evaluasi orang tua', 'Ada formulir evaluasi sekolah untuk Anda. Silakan isi melalui NUSA.'],
            '/dokumen-humas' => ['Pembaruan dokumen Humas', 'Ada dokumen Humas yang perlu Anda periksa di NUSA.'],
            '/kemitraan-humas' => ['Masa berlaku kerja sama sekolah', 'Ada kerja sama sekolah yang mendekati atau melewati batas berlaku. Periksa di NUSA.'],
            '/publikasi-humas' => ['Pembaruan publikasi sekolah', 'Ada pengajuan atau hasil pemeriksaan konten sekolah. Periksa di NUSA.'],
            '/umpan-balik-humas' => ['Pembaruan evaluasi orang tua', 'Ada pembaruan evaluasi orang tua yang dapat Anda periksa di NUSA.'],
        ] as $awal => [$judul, $pesan]) {
            if ($path === $awal || str_starts_with($path, $awal.'/')) {
                return $this->pesan($judul, $pesan);
            }
        }

        // Jenis baru/tidak dikenal tetap aman sampai kebijakan eksplisit ditambahkan.
        return $this->pesan('NUSA', 'Ada pembaruan di NUSA. Buka aplikasi untuk melihat detail.');
    }

    private function pesan(?string $judul, ?string $pesan): array
    {
        return [
            'title' => $this->teks($judul, 100) ?: 'NUSA',
            'body' => $this->teks($pesan, 300) ?: 'Buka NUSA untuk melihat detail.',
        ];
    }

    private function teks(?string $nilai, int $batas): string
    {
        $nilai = strip_tags(html_entity_decode((string) $nilai, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $nilai = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $nilai);

        return Str::limit(Str::squish($nilai), $batas);
    }
}
