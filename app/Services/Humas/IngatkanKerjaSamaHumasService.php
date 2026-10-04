<?php

namespace App\Services\Humas;

use App\Models\KerjaSamaHumas;
use App\Services\Notifikasi\NotifikasiPenggunaService;

class IngatkanKerjaSamaHumasService
{
    public function __construct(private readonly NotifikasiPenggunaService $notifikasi) {}

    public function kirimPengingat(): int
    {
        $penerima = $this->notifikasi->penggunaDenganIzin('kemitraan_humas.kelola');
        if ($penerima->isEmpty()) {
            return 0;
        }
        $jumlah = 0;
        KerjaSamaHumas::with('mitra')->where('status', 'aktif')->whereNotNull('tanggal_selesai')->whereDate('tanggal_mulai', '<=', today())
            ->whereDate('tanggal_selesai', '<=', today()->addDays(max(KerjaSamaHumas::PENGINGAT)))
            ->whereHas('mitra', fn ($q) => $q->where('status', 'aktif'))
            ->chunkById(100, function ($items) use ($penerima, &$jumlah) {
                foreach ($items as $mou) {
                    $sisa = (int) today()->diffInDays($mou->tanggal_selesai, false);
                    if ($sisa > $mou->ingatkan_hari_sebelum) {
                        continue;
                    }
                    $tahap = $sisa < 0 ? 'lewat' : ($sisa === 0 ? 'hari-ini' : 'mendekati');
                    $judul = $sisa < 0 ? 'MoU sekolah telah kedaluwarsa' : ($sisa === 0 ? 'MoU sekolah berakhir hari ini' : 'MoU sekolah segera berakhir');
                    $hasil = $this->notifikasi->kirimKeBanyak($penerima, 'peringatan', $judul,
                        $mou->judul.' dengan '.$mou->mitra->nama.'. Batas berlaku: '.$mou->tanggal_selesai->format('d-m-Y').'.',
                        '/kemitraan-humas/'.$mou->mitra_humas_id.'/mou/'.$mou->id,
                        'mou-humas-'.$mou->id.'-'.$mou->tanggal_selesai->toDateString().'-'.$tahap,
                        ['kerja_sama_humas_id' => $mou->id, 'mitra_humas_id' => $mou->mitra_humas_id]);
                    $jumlah += $hasil->where('wasRecentlyCreated', true)->count();
                }
            });

        return $jumlah;
    }
}
