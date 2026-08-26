<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjianCbt;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\RuangKegiatanUjianCbt;
use App\Services\Cbt\BagiPesertaUjianTerpusat;
use App\Services\Cbt\KodeMejaUjianTerpusat;
use App\Services\Cbt\SinkronkanPelaksanaanUjianTerpusat;
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

    public function show(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        KelompokPesertaKegiatanUjianCbt $kelompokPeserta,
        KodeMejaUjianTerpusat $kodeMeja,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
    ) {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $this->pastikanMilikKegiatan($kegiatanUjianCbt, $kelompokPeserta);
        $this->pastikanKodeMeja($kelompokPeserta, $kegiatanUjianCbt, $request, $kodeMeja, $sinkronisasi);
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

    public function cetakLabelMeja(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        KelompokPesertaKegiatanUjianCbt $kelompokPeserta,
        RuangKegiatanUjianCbt $ruangKegiatanUjianCbt,
        KodeMejaUjianTerpusat $kodeMeja,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
    ) {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $this->pastikanMilikKegiatan($kegiatanUjianCbt, $kelompokPeserta);
        abort_unless(
            (int) $ruangKegiatanUjianCbt->kegiatan_ujian_cbt_id === (int) $kegiatanUjianCbt->id
            && $kelompokPeserta->ruangKegiatanUjianCbt()->whereKey($ruangKegiatanUjianCbt->id)->exists(),
            404,
        );

        $this->pastikanKodeMeja($kelompokPeserta, $kegiatanUjianCbt, $request, $kodeMeja, $sinkronisasi);
        $kegiatanUjianCbt->load(['jenisUjianCbt', 'tahunPelajaran']);
        $kelompokPeserta->load('sesiKegiatanUjianCbt');
        $ruangKegiatanUjianCbt->load([
            'penempatanPesertaUjianCbt' => fn ($query) => $query
                ->where('kelompok_peserta_kegiatan_ujian_cbt_id', $kelompokPeserta->id)
                ->with(['anggotaKelas.kelas', 'anggotaKelas.siswa'])
                ->orderBy('nomor_meja'),
        ]);

        return view('ujian-terpusat.pelaksanaan.label-meja', [
            'kegiatan' => $kegiatanUjianCbt,
            'kelompok' => $kelompokPeserta,
            'ruang' => $ruangKegiatanUjianCbt,
            'daftar' => $ruangKegiatanUjianCbt->penempatanPesertaUjianCbt,
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

    private function pastikanKodeMeja(
        KelompokPesertaKegiatanUjianCbt $kelompok,
        KegiatanUjianCbt $kegiatan,
        Request $request,
        KodeMejaUjianTerpusat $kodeMeja,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
    ): void {
        if ($kodeMeja->sinkronkanKelompok($kelompok)) {
            $sinkronisasi->sinkronkanKegiatan($kegiatan, $request->user());
            $kelompok->unsetRelations();
        }
    }
}
