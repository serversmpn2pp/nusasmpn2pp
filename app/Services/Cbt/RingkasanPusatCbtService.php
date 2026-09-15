<?php

namespace App\Services\Cbt;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Pengguna;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RingkasanPusatCbtService
{
    public function untuk(Pengguna $pengguna): array
    {
        $cakupanGuru = $this->cakupanGuru($pengguna);

        $soal = SoalCbt::query()
            ->where('aktif', true)
            ->where('status', 'siap');
        if (! $pengguna->memilikiIzin(['cbt.lihat', 'cbt.soal_kelola', 'cbt.kelola'])) {
            $soal->whereRaw('1 = 0');
        } elseif (! $this->dapatMelihatSemuaSoal($pengguna)) {
            $this->batasiSoal($soal, $cakupanGuru);
        }

        $asesmen = UjianCbt::query()
            ->where('alur', 'kelas')
            ->where('status', '!=', 'nonaktif');
        if (! $pengguna->memilikiIzin(['cbt.asesmen_kelola', 'cbt.kelola'])) {
            $asesmen->whereRaw('1 = 0');
        } elseif (! $pengguna->memilikiIzin('cbt.kelola')) {
            $asesmen->where('dibuat_oleh_pengguna_id', $pengguna->id);
            $this->batasiUjian($asesmen, $cakupanGuru);
        }

        $paket = JadwalUjianCbt::query()
            ->whereHas('ujianCbt', fn (Builder $query) => $query
                ->whereIn('status', ['terjadwal', 'berlangsung', 'selesai']));
        $kegiatan = KegiatanUjianCbt::query()
            ->where('status', '!=', 'nonaktif');

        if (! $pengguna->memilikiIzin(['cbt.kelola', 'cbt.terpusat_lihat'])) {
            $this->batasiJadwalTerpusat($paket, $pengguna, $cakupanGuru);
            $this->batasiKegiatanTerpusat($kegiatan, $pengguna, $cakupanGuru);
        }

        return [
            'soal_siap' => $soal->count(),
            'asesmen_kelas' => $asesmen->count(),
            'paket_terjadwal' => $paket->count(),
            'kegiatan_terpusat' => $kegiatan->count(),
        ];
    }

    private function cakupanGuru(Pengguna $pengguna): Collection
    {
        if (! $pengguna->pegawai_id) {
            return collect();
        }

        $tahunAktifId = TahunPelajaran::query()->where('aktif', true)->value('id');

        return GuruMataPelajaran::query()
            ->with('kelas:id,tingkat')
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('aktif', true)
            ->when($tahunAktifId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunAktifId))
            ->get()
            ->filter(fn (GuruMataPelajaran $item) => filled($item->kelas?->tingkat))
            ->map(fn (GuruMataPelajaran $item) => [
                'tahun_pelajaran_id' => (int) $item->tahun_pelajaran_id,
                'mata_pelajaran_id' => (int) $item->mata_pelajaran_id,
                'tingkat' => (int) $item->kelas->tingkat,
                'kelas_id' => (int) $item->kelas_id,
            ])
            ->groupBy(fn (array $item) => implode(':', collect($item)->only([
                'tahun_pelajaran_id',
                'mata_pelajaran_id',
                'tingkat',
            ])->all()))
            ->map(fn (Collection $items) => [
                ...$items->first(),
                'kelas_ids' => $items->pluck('kelas_id')->unique()->values()->all(),
            ])
            ->values();
    }

    private function dapatMelihatSemuaSoal(Pengguna $pengguna): bool
    {
        return $pengguna->memilikiIzin('cbt.kelola')
            || $pengguna->memilikiPeran(['pimpinan', 'wakil_pimpinan_kurikulum']);
    }

    private function batasiSoal(Builder $query, Collection $cakupanGuru): void
    {
        $query->where(function (Builder $query) use ($cakupanGuru) {
            foreach ($cakupanGuru as $cakupan) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('mata_pelajaran_id', $cakupan['mata_pelajaran_id'])
                    ->where('tingkat', $cakupan['tingkat']));
            }

            if ($cakupanGuru->isEmpty()) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    private function batasiUjian(Builder $query, Collection $cakupanGuru): void
    {
        $query->where(function (Builder $query) use ($cakupanGuru) {
            foreach ($cakupanGuru as $cakupan) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('tahun_pelajaran_id', $cakupan['tahun_pelajaran_id'])
                    ->where('mata_pelajaran_id', $cakupan['mata_pelajaran_id'])
                    ->where('tingkat', $cakupan['tingkat']));
            }

            if ($cakupanGuru->isEmpty()) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    private function batasiJadwalTerpusat(Builder $query, Pengguna $pengguna, Collection $cakupanGuru): void
    {
        $sebagaiPanitia = filled($pengguna->pegawai_id) && $pengguna->memilikiIzin('cbt.panitia');
        $sebagaiGuru = $pengguna->memilikiIzin('cbt.soal_kelola') && $cakupanGuru->isNotEmpty();

        if (! $sebagaiPanitia && ! $sebagaiGuru) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($pengguna, $cakupanGuru, $sebagaiPanitia, $sebagaiGuru) {
            if ($sebagaiPanitia) {
                $query->orWhereHas('kegiatanUjianCbt.panitiaUjianCbt', fn (Builder $query) => $query
                    ->where('pegawai_id', $pengguna->pegawai_id)
                    ->where('aktif', true));
            }

            if ($sebagaiGuru) {
                foreach ($cakupanGuru as $cakupan) {
                    $query->orWhere(fn (Builder $query) => $this->jadwalSesuaiCakupan($query, $cakupan));
                }
            }
        });
    }

    private function batasiKegiatanTerpusat(Builder $query, Pengguna $pengguna, Collection $cakupanGuru): void
    {
        $sebagaiPanitia = filled($pengguna->pegawai_id) && $pengguna->memilikiIzin('cbt.panitia');
        $sebagaiGuru = $pengguna->memilikiIzin('cbt.soal_kelola') && $cakupanGuru->isNotEmpty();

        if (! $sebagaiPanitia && ! $sebagaiGuru) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($pengguna, $cakupanGuru, $sebagaiPanitia, $sebagaiGuru) {
            if ($sebagaiPanitia) {
                $query->orWhereHas('panitiaUjianCbt', fn (Builder $query) => $query
                    ->where('pegawai_id', $pengguna->pegawai_id)
                    ->where('aktif', true));
            }

            if ($sebagaiGuru) {
                $query->orWhereHas('jadwalUjianCbt', function (Builder $query) use ($cakupanGuru) {
                    $query->where(function (Builder $query) use ($cakupanGuru) {
                        foreach ($cakupanGuru as $cakupan) {
                            $query->orWhere(fn (Builder $query) => $this->jadwalSesuaiCakupan($query, $cakupan));
                        }
                    });
                });
            }
        });
    }

    private function jadwalSesuaiCakupan(Builder $query, array $cakupan): Builder
    {
        return $query
            ->where('mata_pelajaran_id', $cakupan['mata_pelajaran_id'])
            ->where('tingkat', $cakupan['tingkat'])
            ->whereHas('kelas', fn (Builder $query) => $query->whereIn('kelas.id', $cakupan['kelas_ids']))
            ->whereHas('kegiatanUjianCbt', fn (Builder $query) => $query
                ->where('tahun_pelajaran_id', $cakupan['tahun_pelajaran_id']));
    }
}
