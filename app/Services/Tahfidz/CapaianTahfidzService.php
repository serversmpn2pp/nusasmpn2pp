<?php

namespace App\Services\Tahfidz;

use App\Models\SetoranTahfidz;
use App\Models\SurahTahfidz;
use App\Models\TargetRaporTahfidz;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CapaianTahfidzService
{
    public function jumlahSurah(int $siswaId): int
    {
        return $this->setoran()->where('anggota.siswa_id', $siswaId)->whereBetween('setoran_tahfidz.nomor_surah', [78, 114])->distinct()->count('setoran_tahfidz.nomor_surah');
    }

    public function rapor(int $siswaId, TargetRaporTahfidz $target): array
    {
        return $this->raporBanyak([$siswaId], $target)[$siswaId];
    }

    public function raporBanyak(array $siswaIds, TargetRaporTahfidz $target): array
    {
        $terbaru = $this->terbaru($this->setoran()->whereIn('anggota.siswa_id', $siswaIds)
            ->where('kelompok.tahun_pelajaran_id', $target->tahun_pelajaran_id)
            ->whereDate('pertemuan.tanggal', '<=', $target->tanggal_rapor->toDateString()))->groupBy('siswa_id');
        $surah = SurahTahfidz::orderBy('nomor')->get()->keyBy('nomor');

        return collect($siswaIds)->mapWithKeys(fn (int $id) => [$id => $this->hitungRapor(
            $terbaru->get($id, collect())->keyBy('nomor_surah'), $surah, $target,
        )])->all();
    }

    private function hitungRapor(Collection $terbaru, Collection $surah, TargetRaporTahfidz $target): array
    {
        $nomor = $target->mode === 'tetap' ? collect($target->surah)->sort()->values()
            : $terbaru->sort(fn ($a, $b) => (float) $b->nilai <=> (float) $a->nilai ?: $a->nomor_surah <=> $b->nomor_surah)
                ->take($target->target)->keys()->sort()->values();
        $baris = $nomor->map(fn ($id) => ['surah' => $surah->get($id), 'setoran' => $terbaru->get($id)]);
        $sudah = $baris->filter(fn ($item) => $item['setoran'] !== null)->count();
        $jumlah = round((float) $baris->sum(fn ($item) => $item['setoran']?->nilai ?? 0), 2);
        $rata = $sudah ? round($jumlah / $target->target, 2) : null;
        while ($baris->count() < $target->target) {
            $baris->push(['surah' => null, 'setoran' => null]);
        }

        return ['baris' => $baris, 'sudah' => $sudah, 'jumlah' => $jumlah, 'rata' => $rata,
            'predikat' => $rata !== null ? $target->predikat($rata) : null];
    }

    public function perkembanganAnak(int $siswaId): array
    {
        $setoran = $this->setoran()->where('anggota.siswa_id', $siswaId)->whereNotNull('pertemuan.selesai_pada');
        $terbaru = $this->terbaru(clone $setoran)->keyBy('nomor_surah');
        $surah = SurahTahfidz::orderBy('nomor')->get();
        $baris = $surah->map(fn (SurahTahfidz $item) => ['surah' => $item, 'terbaru' => $terbaru->get($item->nomor)]);

        return ['target' => $surah->count(), 'sudah' => $terbaru->count(), 'baris' => $baris,
            'riwayat' => $setoran->select('setoran_tahfidz.id', 'setoran_tahfidz.nomor_surah', 'setoran_tahfidz.nilai', 'pertemuan.tanggal as tanggal_setoran')
                ->with('surah')->orderByDesc('pertemuan.tanggal')->orderByDesc('setoran_tahfidz.id')->paginate(15, ['*'], 'riwayat')->withQueryString(),
        ];
    }

    private function terbaru(Builder $setoran): Collection
    {
        $urut = $setoran->select('setoran_tahfidz.id', 'setoran_tahfidz.nomor_surah', 'setoran_tahfidz.nilai', 'anggota.siswa_id', 'pertemuan.tanggal as tanggal_setoran')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY anggota.siswa_id, nomor_surah ORDER BY pertemuan.tanggal DESC, setoran_tahfidz.id DESC) AS urutan');

        return SetoranTahfidz::query()->fromSub($urut, 'setoran_terurut')->where('urutan', 1)->get();
    }

    private function setoran(): Builder
    {
        return SetoranTahfidz::query()->join('anggota_tahfidz as anggota', 'anggota.id', '=', 'setoran_tahfidz.anggota_tahfidz_id')
            ->join('pertemuan_tahfidz as pertemuan', 'pertemuan.id', '=', 'setoran_tahfidz.pertemuan_tahfidz_id')
            ->join('kelompok_tahfidz as kelompok', 'kelompok.id', '=', 'pertemuan.kelompok_tahfidz_id')
            ->whereColumn('anggota.kelompok_tahfidz_id', 'kelompok.id');
    }
}
