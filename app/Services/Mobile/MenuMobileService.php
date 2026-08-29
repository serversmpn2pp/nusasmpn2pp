<?php

namespace App\Services\Mobile;

use App\Models\Pengguna;
use App\Services\Ibadah\AksesBerhalanganIbadah;
use App\Services\Ibadah\AksesScanKegiatanIbadah;

class MenuMobileService
{
    public function __construct(
        private AksesBerhalanganIbadah $aksesBerhalangan,
        private AksesScanKegiatanIbadah $aksesIbadah,
    ) {}

    public function siapkan(Pengguna $pengguna): array
    {
        $pengguna->loadMissing('daftarPeran.izin');

        $kelompok = collect(config('menu_mobile', []))
            ->map(fn (array $kelompok) => $this->siapkanKelompok($kelompok, $pengguna))
            ->filter(fn (array $kelompok) => count($kelompok['items']) > 0)
            ->values();

        return [
            'dihasilkan_pada' => now()->toISOString(),
            'jumlah_menu' => $kelompok->sum(fn (array $item) => count($item['items'])),
            'kelompok' => $kelompok->all(),
        ];
    }

    private function siapkanKelompok(array $kelompok, Pengguna $pengguna): array
    {
        $items = collect($kelompok['items'] ?? [])
            ->filter(fn (array $item) => $this->bolehDilihat($item, $pengguna))
            ->map(fn (array $item) => $this->siapkanItem($item))
            ->values()
            ->all();

        return [
            'kode' => $kelompok['kode'],
            'label' => $kelompok['label'],
            'deskripsi' => $kelompok['deskripsi'],
            'ikon' => $kelompok['ikon'],
            'items' => $items,
        ];
    }

    private function bolehDilihat(array $item, Pengguna $pengguna): bool
    {
        if (($item['pegawai_only'] ?? false) && ! $pengguna->pegawai_id) {
            return false;
        }

        if (($item['administrator_only'] ?? false) && ! $pengguna->administrator()) {
            return false;
        }

        if (($item['siswa_only'] ?? false)
            && ! ($pengguna->akunSiswa() || $pengguna->memilikiPeran('siswa'))) {
            return false;
        }

        if (($item['scan_berhalangan_only'] ?? false)
            && ! $this->aksesBerhalangan->dapatMemindai($pengguna)) {
            return false;
        }

        if (($item['konfirmasi_berhalangan_only'] ?? false)
            && ! $this->aksesBerhalangan->dapatMengonfirmasi($pengguna)) {
            return false;
        }

        if (($item['rekap_ibadah_only'] ?? false)
            && ! $this->aksesIbadah->dapatMelihatRekap($pengguna)) {
            return false;
        }

        if (($item['ringkasan_ibadah_only'] ?? false)
            && ! $this->aksesIbadah->dapatMelihatRingkasanBulanan($pengguna)) {
            return false;
        }

        $izin = $item['izin'] ?? null;

        return blank($izin) || $pengguna->memilikiIzin($izin);
    }

    private function siapkanItem(array $item): array
    {
        return [
            'kode' => $item['kode'],
            'label' => $item['label'],
            'deskripsi' => $item['deskripsi'] ?? 'Modul '.$item['label'].' NUSA.',
            'inisial' => $item['inisial'],
            'subkelompok' => $item['subkelompok'] ?? null,
            'ikon' => $item['ikon'] ?? null,
            'status' => $item['status'] ?? 'segera_hadir',
            'rute' => $item['rute'] ?? null,
        ];
    }
}
