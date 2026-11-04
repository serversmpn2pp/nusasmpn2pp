<?php

namespace App\Services\Tahfidz;

use App\Models\AnggotaTahfidz;
use App\Models\KelompokTahfidz;
use App\Models\SetoranTahfidz;
use App\Models\SurahTahfidz;
use Illuminate\Database\Eloquent\Builder;

class RekapTahfidzService
{
    public function kelompok(KelompokTahfidz $kelompok): array
    {
        $anggota = $kelompok->anggotaAktif()->with(['siswa.anggotaKelas' => fn ($q) => $q
            ->where('tahun_pelajaran_id', $kelompok->tahun_pelajaran_id)->with('kelas:id,nama')])->get()
            ->sortBy(fn (AnggotaTahfidz $item) => $item->siswa->nama_lengkap, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $target = SurahTahfidz::count();
        $setoran = $this->setoran($kelompok)->whereIn('setoran_tahfidz.anggota_tahfidz_id', $anggota->modelKeys())
            ->selectRaw('setoran_tahfidz.anggota_tahfidz_id, COUNT(DISTINCT nomor_surah) AS jumlah_surah, COUNT(*) AS jumlah_setoran, MAX(pertemuan.tanggal) AS terakhir')
            ->groupBy('setoran_tahfidz.anggota_tahfidz_id')->toBase()->get()->keyBy('anggota_tahfidz_id');
        $baris = $anggota->map(function (AnggotaTahfidz $item) use ($setoran, $target) {
            $rekap = $setoran->get($item->id);
            $sudah = (int) ($rekap->jumlah_surah ?? 0);

            return ['anggota' => $item, 'sudah' => $sudah, 'setoran' => (int) ($rekap->jumlah_setoran ?? 0),
                'terakhir' => $rekap->terakhir ?? null,
                'status' => $sudah === 0 ? 'belum' : ($sudah === $target ? 'lengkap' : 'berjalan')];
        });

        return ['target' => $target, 'baris' => $baris, 'sudahMulai' => $baris->where('sudah', '>', 0)->count(),
            'lengkap' => $baris->where('status', 'lengkap')->count()];
    }

    public function siswa(KelompokTahfidz $kelompok, AnggotaTahfidz $anggota): array
    {
        // Tanggal pertemuan menentukan urutan, meskipun setoran lama baru dicatat belakangan.
        $peringkat = $this->setoran($kelompok)->where('setoran_tahfidz.anggota_tahfidz_id', $anggota->id)
            ->select('setoran_tahfidz.*', 'pertemuan.tanggal as tanggal_setoran')
            ->selectRaw('COUNT(*) OVER (PARTITION BY nomor_surah) AS jumlah_setoran')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY nomor_surah ORDER BY pertemuan.tanggal DESC, setoran_tahfidz.id DESC) AS urutan');
        $terbaru = SetoranTahfidz::query()->fromSub($peringkat, 'setoran_terurut')->where('urutan', 1)->get()->keyBy('nomor_surah');
        $baris = SurahTahfidz::orderBy('nomor')->get()->map(fn (SurahTahfidz $surah) => [
            'surah' => $surah, 'terbaru' => $terbaru->get($surah->nomor),
        ]);

        return ['target' => $baris->count(), 'baris' => $baris, 'sudah' => $terbaru->count(),
            'jumlahSetoran' => (int) $terbaru->sum('jumlah_setoran')];
    }

    private function setoran(KelompokTahfidz $kelompok): Builder
    {
        return SetoranTahfidz::query()->join('pertemuan_tahfidz as pertemuan', 'pertemuan.id', '=', 'setoran_tahfidz.pertemuan_tahfidz_id')
            ->where('pertemuan.kelompok_tahfidz_id', $kelompok->id);
    }
}
