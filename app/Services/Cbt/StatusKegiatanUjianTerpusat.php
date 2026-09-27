<?php

namespace App\Services\Cbt;

use App\Models\KegiatanUjianCbt;
use App\Models\PesertaUjianCbt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StatusKegiatanUjianTerpusat
{
    public function batasiAktif(Builder $query, Carbon $sekarang): Builder
    {
        $tanggal = $sekarang->toDateString();
        $jam = $sekarang->format('H:i:s');

        return $query
            ->whereNotIn('status', ['selesai', 'nonaktif'])
            ->where(function (Builder $query) use ($sekarang, $tanggal, $jam) {
                $query
                    ->whereDate('tanggal_selesai', '>', $tanggal)
                    ->orWhere(function (Builder $query) use ($tanggal) {
                        $query->whereDoesntHave('jadwalUjianCbt')
                            ->whereDate('tanggal_selesai', '>=', $tanggal);
                    })
                    ->orWhereHas('jadwalUjianCbt', function (Builder $query) use ($tanggal, $jam) {
                        $query->where('status', '!=', 'dibatalkan')
                            ->where(function (Builder $query) use ($tanggal, $jam) {
                                $query->whereDate('tanggal', '>', $tanggal)
                                    ->orWhere(function (Builder $query) use ($tanggal, $jam) {
                                        $query->whereDate('tanggal', $tanggal)
                                            ->whereTime('waktu_selesai', '>=', $jam);
                                    });
                            });
                    })
                    ->orWhereHas('jadwalUjianCbt.ujianCbt.pesertaUjianCbt', function (Builder $query) use ($sekarang) {
                        $query->where(function (Builder $query) use ($sekarang) {
                            $query->where('status_susulan', 'menunggu_jadwal')
                                ->orWhere(function (Builder $query) use ($sekarang) {
                                    $query->where('status_susulan', 'dijadwalkan')
                                        ->where(function (Builder $query) use ($sekarang) {
                                            $query->whereNull('susulan_selesai')
                                                ->orWhere('susulan_selesai', '>=', $sekarang);
                                        });
                                });
                        });
                    });
            });
    }

    /**
     * @param  Collection<int, KegiatanUjianCbt>  $kegiatan
     * @return array<int, array{label: string, badge: string, riwayat: bool}>
     */
    public function statusUntuk(Collection $kegiatan, Carbon $sekarang): array
    {
        $ringkasanSusulan = $this->ringkasanSusulan($kegiatan);

        return $kegiatan->mapWithKeys(function (KegiatanUjianCbt $item) use ($ringkasanSusulan, $sekarang) {
            $ringkasan = $ringkasanSusulan->get($item->id);

            return [$item->id => $this->tentukanStatus($item, $sekarang, $ringkasan)];
        })->all();
    }

    /**
     * @return array{label: string, badge: string, riwayat: bool}
     */
    private function tentukanStatus(KegiatanUjianCbt $kegiatan, Carbon $sekarang, mixed $ringkasanSusulan): array
    {
        if ($kegiatan->status === 'nonaktif') {
            return ['label' => 'Nonaktif', 'badge' => 'badge-inactive', 'riwayat' => true];
        }

        if ($kegiatan->status === 'selesai') {
            return ['label' => 'Selesai', 'badge' => 'badge-active', 'riwayat' => true];
        }

        [$mulaiUtama, $selesaiUtama] = $this->rentangUtama($kegiatan, $sekarang);

        if ($mulaiUtama && $sekarang->lt($mulaiUtama)) {
            return ['label' => 'Akan datang', 'badge' => 'badge-warning', 'riwayat' => false];
        }

        if ($selesaiUtama && $sekarang->lte($selesaiUtama)) {
            return ['label' => 'Sedang berlangsung', 'badge' => 'badge-active', 'riwayat' => false];
        }

        if ((int) ($ringkasanSusulan?->jumlah_menunggu ?? 0) > 0) {
            return ['label' => 'Menunggu susulan', 'badge' => 'badge-warning', 'riwayat' => false];
        }

        $susulanTerakhir = filled($ringkasanSusulan?->susulan_selesai_terakhir)
            ? Carbon::parse($ringkasanSusulan->susulan_selesai_terakhir)
            : null;

        if ($susulanTerakhir && $sekarang->lte($susulanTerakhir)) {
            return ['label' => 'Susulan dijadwalkan', 'badge' => 'badge-active', 'riwayat' => false];
        }

        return ['label' => 'Jadwal utama selesai', 'badge' => 'badge-muted', 'riwayat' => true];
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function rentangUtama(KegiatanUjianCbt $kegiatan, Carbon $sekarang): array
    {
        $jadwal = $kegiatan->jadwalUjianCbt
            ->where('status', '!=', 'dibatalkan');
        $mulai = $jadwal
            ->filter(fn ($item) => $item->tanggal && filled($item->waktu_mulai))
            ->map(fn ($item) => Carbon::parse($item->tanggal->toDateString().' '.$item->waktu_mulai))
            ->min();
        $selesai = $jadwal
            ->filter(fn ($item) => $item->tanggal && filled($item->waktu_selesai))
            ->map(fn ($item) => Carbon::parse($item->tanggal->toDateString().' '.$item->waktu_selesai))
            ->max();

        if ($jadwal->isEmpty()) {
            return [
                $kegiatan->tanggal_mulai?->copy()->startOfDay(),
                $kegiatan->tanggal_selesai?->copy()->endOfDay(),
            ];
        }

        if ($kegiatan->tanggal_selesai?->gt($sekarang->copy()->startOfDay())) {
            $selesaiPeriode = $kegiatan->tanggal_selesai->copy()->endOfDay();
            $selesai = ! $selesai || $selesaiPeriode->gt($selesai) ? $selesaiPeriode : $selesai;
        }

        return [$mulai, $selesai];
    }

    private function ringkasanSusulan(Collection $kegiatan): Collection
    {
        $ids = $kegiatan->pluck('id')->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return PesertaUjianCbt::query()
            ->join('ujian_cbt', 'ujian_cbt.id', '=', 'peserta_ujian_cbt.ujian_cbt_id')
            ->join('jadwal_ujian_cbt', 'jadwal_ujian_cbt.ujian_cbt_id', '=', 'ujian_cbt.id')
            ->whereIn('jadwal_ujian_cbt.kegiatan_ujian_cbt_id', $ids)
            ->whereIn('peserta_ujian_cbt.status_susulan', ['menunggu_jadwal', 'dijadwalkan'])
            ->selectRaw('jadwal_ujian_cbt.kegiatan_ujian_cbt_id as kegiatan_id')
            ->selectRaw("SUM(CASE WHEN peserta_ujian_cbt.status_susulan = 'menunggu_jadwal' THEN 1 ELSE 0 END) as jumlah_menunggu")
            ->selectRaw("MAX(CASE WHEN peserta_ujian_cbt.status_susulan = 'dijadwalkan' THEN peserta_ujian_cbt.susulan_selesai END) as susulan_selesai_terakhir")
            ->groupBy('jadwal_ujian_cbt.kegiatan_ujian_cbt_id')
            ->get()
            ->keyBy('kegiatan_id');
    }
}
