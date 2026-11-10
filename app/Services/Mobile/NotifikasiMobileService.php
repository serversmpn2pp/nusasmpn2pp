<?php

namespace App\Services\Mobile;

use App\Models\NotifikasiPengguna;
use App\Models\Pengguna;
use Illuminate\Support\Collection;

class NotifikasiMobileService
{
    public function __construct(private readonly TujuanNotifikasiMobileService $tujuan) {}

    public function ringkasan(Pengguna $pengguna): array
    {
        return [
            'jumlah_belum_dibaca' => $pengguna->notifikasiPengguna()->belumDibaca()->count(),
            'terbaru' => $this->items($pengguna, $pengguna->notifikasiPengguna()
                ->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get()),
        ];
    }

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $status = $filter['status'] ?? 'semua';
        $query = $pengguna->notifikasiPengguna()
            ->when($status === 'belum_dibaca', fn ($query) => $query->belumDibaca());
        if ($sebelumId = $filter['sebelum_id'] ?? null) {
            // Anchor remains usable after it is read, but must belong to this account.
            $batas = $pengguna->notifikasiPengguna()->findOrFail($sebelumId);
            $query->where(fn ($query) => $query->where('created_at', '<', $batas->created_at)
                ->orWhere(fn ($query) => $query->where('created_at', $batas->created_at)->where('id', '<', $batas->id)));
        }
        // Keyset pagination keeps new notifications from shifting older pages.
        $baris = $query->orderByDesc('created_at')->orderByDesc('id')->limit(21)->get();
        $adaLagi = $baris->count() > 20;
        $items = $baris->take(20)->values();

        return [
            'items' => $this->items($pengguna, $items),
            'jumlah_belum_dibaca' => $pengguna->notifikasiPengguna()->belumDibaca()->count(),
            'filter' => ['status' => $status],
            'paginasi' => ['per_halaman' => 20, 'ada_lagi' => $adaLagi,
                'sebelum_id_berikutnya' => $adaLagi ? (int) $items->last()->id : null],
        ];
    }

    private function items(Pengguna $pengguna, Collection $items): Collection
    {
        $pengguna->loadMissing('daftarPeran.izin');

        return $items->map(function (NotifikasiPengguna $item) use ($pengguna): array {
            $item->setRelation('pengguna', $pengguna);

            return [
                'id' => (int) $item->id, 'jenis' => $item->jenis, 'label_jenis' => $item->labelJenis(),
                'judul' => $item->judul, 'pesan' => $item->pesan, 'belum_dibaca' => $item->masihBelumDibaca(),
                'dibuat_pada' => $item->created_at->toISOString(),
                'waktu_relatif' => $item->created_at->locale('id')->diffForHumans(),
                'tautan_mobile' => $this->tujuan->untuk($item),
            ];
        })->values();
    }
}
