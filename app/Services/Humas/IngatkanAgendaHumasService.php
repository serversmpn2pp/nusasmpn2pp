<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\TindakLanjutAgendaHumas;
use App\Services\Notifikasi\NotifikasiPenggunaService;

class IngatkanAgendaHumasService
{
    public function __construct(private readonly NotifikasiPenggunaService $notifikasi) {}

    public function kirimPengingat(): int
    {
        $penerima = $this->notifikasi->penggunaDenganIzin('agenda_humas.kelola');
        if ($penerima->isEmpty()) {
            return 0;
        }

        $jumlah = 0;
        AgendaHumas::where('status', 'terjadwal')->whereBetween('waktu_mulai', [now(), now()->addDay()])
            ->chunkById(100, function ($agenda) use ($penerima, &$jumlah) {
                foreach ($agenda as $item) {
                    $hasil = $this->notifikasi->kirimKeBanyak(
                        $penerima, 'informasi', 'Pengingat agenda Humas',
                        $item->judul.' pada '.$item->waktu_mulai->format('d-m-Y H:i').' di '.$item->tempat.'.',
                        '/agenda-humas/'.$item->id,
                        'agenda-humas-'.$item->id.'-mulai-'.$item->waktu_mulai->format('YmdHi'),
                        ['agenda_humas_id' => $item->id],
                    );
                    $jumlah += $hasil->where('wasRecentlyCreated', true)->count();
                }
            });

        TindakLanjutAgendaHumas::with('agenda')->where('status', '<>', 'selesai')
            ->whereDate('batas_tanggal', '<=', today())
            ->whereHas('agenda', fn ($q) => $q->where('status', '<>', 'dibatalkan'))
            ->chunkById(100, function ($tugas) use ($penerima, &$jumlah) {
                foreach ($tugas as $item) {
                    $hasil = $this->notifikasi->kirimKeBanyak(
                        $penerima, 'peringatan', $item->terlambat() ? 'Tindak lanjut Humas melewati batas waktu' : 'Tindak lanjut Humas jatuh tempo hari ini',
                        $item->uraian.' Penanggung jawab: '.$item->penanggung_jawab.'. Batas: '.$item->batas_tanggal->format('d-m-Y').'.',
                        '/agenda-humas/'.$item->agenda_humas_id.'?tab=tindak-lanjut',
                        'tindak-lanjut-humas-'.$item->id.'-batas-'.$item->batas_tanggal->toDateString(),
                        ['agenda_humas_id' => $item->agenda_humas_id, 'tindak_lanjut_id' => $item->id],
                    );
                    $jumlah += $hasil->where('wasRecentlyCreated', true)->count();
                }
            });

        return $jumlah;
    }
}
