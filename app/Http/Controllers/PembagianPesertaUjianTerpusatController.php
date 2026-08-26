<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjianCbt;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Services\Cbt\BagiPesertaUjianTerpusat;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PembagianPesertaUjianTerpusatController extends Controller
{
    public function atur(Request $request, KegiatanUjianCbt $kegiatanUjianCbt, BagiPesertaUjianTerpusat $pembagi)
    {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $data = $this->validasiPengaturan($request);

        $kelompok = $pembagi->atur(
            $kegiatanUjianCbt,
            (int) $data['tingkat'],
            (int) $data['sesi_kegiatan_ujian_cbt_id'],
            $data['kelas'],
            $data['ruang'],
        );

        return redirect()
            ->route('ujian-terpusat.pelaksanaan.index', [$kegiatanUjianCbt, 'tahap' => 5])
            ->with('berhasil', "Kelas, sesi, dan ruang tingkat {$kelompok->tingkat} berhasil ditetapkan. Lanjutkan ke tahap pembagian peserta.");
    }

    public function bangkitkan(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        KelompokPesertaKegiatanUjianCbt $kelompokPeserta,
        BagiPesertaUjianTerpusat $pembagi,
    ) {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $this->pastikanMilikKegiatan($kegiatanUjianCbt, $kelompokPeserta);

        $kelompok = $pembagi->bangkitkan($kegiatanUjianCbt, $kelompokPeserta, $request->user());

        return redirect()
            ->route('ujian-terpusat.pelaksanaan.index', [$kegiatanUjianCbt, 'tahap' => 6])
            ->with('berhasil', "{$kelompok->jumlah_peserta} siswa tingkat {$kelompok->tingkat} berhasil dibagi otomatis ke ruang ujian.");
    }

    public function show(Request $request, KegiatanUjianCbt $kegiatanUjianCbt, KelompokPesertaKegiatanUjianCbt $kelompokPeserta)
    {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $this->pastikanMilikKegiatan($kegiatanUjianCbt, $kelompokPeserta);
        $kegiatanUjianCbt->load(['jenisUjianCbt', 'tahunPelajaran']);
        $kelompokPeserta->load([
            'sesiKegiatanUjianCbt',
            'kelas',
            'ruangKegiatanUjianCbt',
            'penempatanPesertaUjianCbt' => fn ($query) => $query
                ->with(['ruangKegiatanUjianCbt', 'anggotaKelas.kelas', 'anggotaKelas.siswa'])
                ->orderBy('ruang_kegiatan_ujian_cbt_id')
                ->orderBy('nomor_meja'),
        ]);

        return view('ujian-terpusat.pelaksanaan.peserta', [
            'kegiatan' => $kegiatanUjianCbt,
            'kelompok' => $kelompokPeserta,
            'penempatanPerRuang' => $kelompokPeserta->penempatanPesertaUjianCbt
                ->groupBy('ruang_kegiatan_ujian_cbt_id'),
        ]);
    }

    public function destroy(Request $request, KegiatanUjianCbt $kegiatanUjianCbt, KelompokPesertaKegiatanUjianCbt $kelompokPeserta)
    {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $this->pastikanMilikKegiatan($kegiatanUjianCbt, $kelompokPeserta);

        if ($kegiatanUjianCbt->jadwalUjianCbt()->where('tingkat', $kelompokPeserta->tingkat)->exists()) {
            throw ValidationException::withMessages(['peserta' => 'Hapus jadwal tingkat ini sebelum mengosongkan pembagian peserta.']);
        }

        $tingkat = $kelompokPeserta->tingkat;
        $kelompokPeserta->delete();

        return redirect()
            ->route('ujian-terpusat.pelaksanaan.index', [$kegiatanUjianCbt, 'tahap' => 5])
            ->with('berhasil', "Penetapan ruang dan pembagian peserta tingkat {$tingkat} berhasil dikosongkan.");
    }

    private function validasiPengaturan(Request $request): array
    {
        return $request->validate([
            'tingkat' => ['required', 'integer', Rule::in([7, 8, 9])],
            'sesi_kegiatan_ujian_cbt_id' => ['required', 'integer'],
            'kelas' => ['required', 'array', 'min:1'],
            'kelas.*' => ['integer'],
            'ruang' => ['required', 'array', 'min:1'],
            'ruang.*' => ['integer'],
        ]);
    }

    private function pastikanAkses(Request $request, KegiatanUjianCbt $kegiatan): void
    {
        abort_unless($kegiatan->dapatDiaksesOleh($request->user()), 403);
    }

    private function pastikanMilikKegiatan(KegiatanUjianCbt $kegiatan, KelompokPesertaKegiatanUjianCbt $kelompok): void
    {
        abort_unless((int) $kelompok->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
    }
}
