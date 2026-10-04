<?php

namespace App\Services\Humas;

use App\Models\DokumenHumas;
use App\Services\Notifikasi\NotifikasiPenggunaService;

class IngatkanDokumenHumasService
{
    public function __construct(private readonly NotifikasiPenggunaService $notifikasi) {}

    public function kirimPengingat(): int
    {
        $penerima = $this->notifikasi->penggunaDenganIzin('dokumen_humas.kelola');

        if ($penerima->isEmpty()) {
            return 0;
        }

        $jumlahNotifikasi = 0;
        DokumenHumas::query()
            ->where('status', 'aktif')
            ->whereNotNull('berlaku_sampai')
            ->whereDate('berlaku_sampai', '>=', today())
            ->get()
            ->each(function (DokumenHumas $dokumen) use ($penerima, &$jumlahNotifikasi) {
                $sisaHari = (int) today()->diffInDays($dokumen->berlaku_sampai, false);
                $hariPengingat = (int) $dokumen->ingatkan_hari_sebelum;

                if ($sisaHari > $hariPengingat) {
                    return;
                }

                $pesan = $sisaHari === 0
                    ? "Dokumen {$dokumen->judul} berakhir hari ini."
                    : "Dokumen {$dokumen->judul} akan berakhir dalam {$sisaHari} hari.";

                $jumlahNotifikasi += $this->notifikasi->kirimKeBanyak(
                    $penerima,
                    'peringatan',
                    $sisaHari === 0 ? 'Masa berlaku dokumen berakhir hari ini' : 'Masa berlaku dokumen segera berakhir',
                    $pesan,
                    '/dokumen-humas/'.$dokumen->id,
                    'dokumen-humas-'.$dokumen->id.'-berakhir-'.$dokumen->berlaku_sampai->toDateString().'-ingatkan-'.$hariPengingat,
                    [
                        'dokumen_humas_id' => $dokumen->id,
                        'berlaku_sampai' => $dokumen->berlaku_sampai->toDateString(),
                    ],
                )->count();
            });

        return $jumlahNotifikasi;
    }
}
