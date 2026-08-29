<?php

namespace App\Services\Absensi;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class RangkumanWhatsappPresensiSiswaService
{
    public function buat(string $tanggal, string $labelCakupan, array $ringkasan, Collection $rekapAbsensi): string
    {
        $tanggalLabel = Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y');
        $hadirTepatWaktu = $rekapAbsensi->filter(fn (array $item) => $item['status_kehadiran'] === 'hadir' && (int) $item['terlambat'] === 0);
        $terlambat = $rekapAbsensi->filter(fn (array $item) => $item['status_kehadiran'] === 'hadir' && (int) $item['terlambat'] > 0);
        $izin = $rekapAbsensi->where('status_kehadiran', 'izin');
        $sakit = $rekapAbsensi->where('status_kehadiran', 'sakit');
        $alfa = $rekapAbsensi->filter(fn (array $item) => $item['status_kehadiran'] === 'alfa' && $item['status_sumber'] !== 'inferensi');
        $belumScan = $rekapAbsensi->filter(fn (array $item) => $item['status_kehadiran'] === 'alfa' && $item['status_sumber'] === 'inferensi');

        $baris = [
            '*REKAP KEHADIRAN SISWA*',
            'SMP Negeri 2 Padang Panjang',
            'Tanggal: '.$tanggalLabel,
            'Cakupan: '.$labelCakupan,
            '',
            'Total siswa: '.$ringkasan['total'],
            'Hadir tepat waktu: '.$hadirTepatWaktu->count(),
            'Terlambat: '.$terlambat->count(),
            'Sakit: '.$sakit->count(),
            'Izin: '.$izin->count(),
            'Alfa: '.$alfa->count(),
            'Belum scan: '.$belumScan->count(),
        ];

        $this->tambahkanBagian($baris, 'Terlambat', $terlambat, fn (array $item) => $this->barisSiswa($item, $this->formatJam($item['absensi']?->jam_masuk).' - terlambat '.$item['terlambat'].' menit'));
        $this->tambahkanBagian($baris, 'Sakit', $sakit, fn (array $item) => $this->barisSiswa($item, $item['absensi']?->catatan ?: 'Sakit'));
        $this->tambahkanBagian($baris, 'Izin', $izin, fn (array $item) => $this->barisSiswa($item, $item['absensi']?->catatan ?: 'Izin'));
        $this->tambahkanBagian($baris, 'Alfa', $alfa, fn (array $item) => $this->barisSiswa($item, $item['absensi']?->catatan ?: 'Alfa'));
        $this->tambahkanBagian($baris, 'Belum Scan', $belumScan, fn (array $item) => $this->barisSiswa($item, 'Belum ada catatan scan/manual'));

        $baris[] = '';
        $baris[] = 'Catatan: Siswa yang hadir tepat waktu tidak ditampilkan agar pesan lebih ringkas. Jika ada keterangan sakit/izin yang belum tercatat, silakan menghubungi wali kelas.';
        $baris[] = 'NUSA - SMP Negeri 2 Padang Panjang';

        return implode("\n", $baris);
    }

    private function tambahkanBagian(array &$baris, string $judul, Collection $items, callable $pembuatBaris): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $baris[] = '';
        $baris[] = '*'.$judul.'*';
        foreach ($items->values() as $index => $item) {
            $baris[] = ($index + 1).'. '.$pembuatBaris($item);
        }
    }

    private function barisSiswa(array $item, string $keterangan): string
    {
        $anggota = $item['anggota_kelas'];
        $nama = $anggota->siswa?->nama_lengkap ?: '-';
        $kelas = $anggota->kelas?->nama ? ' ('.$anggota->kelas->nama.')' : '';

        return $nama.$kelas.' - '.$keterangan;
    }

    private function formatJam(?string $jam): string
    {
        return $jam ? substr($jam, 0, 5) : '-';
    }
}
