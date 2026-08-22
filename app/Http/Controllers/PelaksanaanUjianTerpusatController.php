<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use Illuminate\Http\Request;

class PelaksanaanUjianTerpusatController extends Controller
{
    public function index(Request $request, KegiatanUjianCbt $kegiatanUjianCbt)
    {
        abort_unless($kegiatanUjianCbt->dapatDiaksesOleh($request->user()), 403);

        $kegiatanUjianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'sesiKegiatanUjianCbt' => fn ($query) => $query->where('aktif', true)->orderBy('urutan'),
            'ruangKegiatanUjianCbt' => fn ($query) => $query->where('aktif', true)->orderBy('urutan'),
            'kelompokPesertaKegiatanUjianCbt' => fn ($query) => $query
                ->with(['sesiKegiatanUjianCbt', 'kelas', 'ruangKegiatanUjianCbt'])
                ->withCount('penempatanPesertaUjianCbt')
                ->orderBy('tingkat'),
            'jadwalUjianCbt' => fn ($query) => $query
                ->with([
                    'sesiKegiatanUjianCbt',
                    'mataPelajaran',
                    'kelas',
                    'ujianCbt' => fn ($query) => $query->withCount('soalUjianCbt'),
                ])
                ->orderBy('tanggal')
                ->orderBy('waktu_mulai')
                ->orderBy('tingkat'),
        ]);

        $daftarKelas = Kelas::query()
            ->where('tahun_pelajaran_id', $kegiatanUjianCbt->tahun_pelajaran_id)
            ->whereIn('tingkat', [7, 8, 9])
            ->where('aktif', true)
            ->withCount(['anggotaKelas as jumlah_siswa_aktif' => fn ($query) => $query
                ->where('status_keanggotaan', 'aktif')
                ->whereHas('siswa', fn ($query) => $query->where('aktif', true))])
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get()
            ->groupBy('tingkat');

        $daftarMataPelajaran = MataPelajaran::query()
            ->where('aktif', true)
            ->with('pengaturanTingkat:id,mata_pelajaran_id,tahun_pelajaran_id,tingkat,aktif')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get()
            ->map(function (MataPelajaran $mataPelajaran) use ($kegiatanUjianCbt) {
                $memilikiPengaturan = $mataPelajaran->pengaturanTingkat->isNotEmpty();
                $mataPelajaran->setAttribute('tingkat_tersedia', collect([7, 8, 9])
                    ->filter(function (int $tingkat) use ($mataPelajaran, $kegiatanUjianCbt, $memilikiPengaturan) {
                        if ($memilikiPengaturan) {
                            return $mataPelajaran->pengaturanTingkat->contains(fn ($pengaturan) => (
                                (int) $pengaturan->tahun_pelajaran_id === (int) $kegiatanUjianCbt->tahun_pelajaran_id
                                && (int) $pengaturan->tingkat === $tingkat
                                && $pengaturan->aktif
                            ));
                        }

                        return ! $mataPelajaran->tingkat || (int) $mataPelajaran->tingkat === $tingkat;
                    })
                    ->values()
                    ->all());

                return $mataPelajaran;
            })
            ->filter(fn (MataPelajaran $mataPelajaran) => filled($mataPelajaran->tingkat_tersedia))
            ->values();

        return view('ujian-terpusat.pelaksanaan.index', [
            'kegiatan' => $kegiatanUjianCbt,
            'daftarKelas' => $daftarKelas,
            'daftarMataPelajaran' => $daftarMataPelajaran,
            'kelompokPerTingkat' => $kegiatanUjianCbt->kelompokPesertaKegiatanUjianCbt->keyBy('tingkat'),
            'bolehKelola' => $request->user()->memilikiIzin(['cbt.kelola', 'cbt.panitia']),
        ]);
    }
}
