<?php

namespace App\Http\Controllers;

use App\Models\KelompokTahfidz;
use App\Models\KelulusanTahfidz;
use App\Services\Tahfidz\AksesTahfidz;
use App\Services\Tahfidz\CapaianTahfidzService;
use App\Services\Tahfidz\RekapTahfidzService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapTahfidzController extends Controller
{
    public function __construct(private readonly AksesTahfidz $akses, private readonly RekapTahfidzService $layanan,
        private readonly CapaianTahfidzService $capaian) {}

    public function kelompok(Request $request, int $kelompok)
    {
        $kelompok = $this->kelompokTerbaca($request, $kelompok);
        $filter = $request->validate(['cari' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['belum', 'berjalan', 'lengkap'])]]);
        $cari = $filter['cari'] ?? '';
        $status = $filter['status'] ?? '';
        $rekap = $this->layanan->kelompok($kelompok);
        $jumlahSiswa = $rekap['baris']->count();
        $baris = $rekap['baris']->filter(fn ($item) => ($status === '' || $item['status'] === $status)
            && ($cari === '' || mb_stripos($item['anggota']->siswa->nama_lengkap, $cari) !== false));

        return view('tahfidz.rekap-kelompok', [...$rekap, 'baris' => $baris,
            'kelompok' => $kelompok, 'jumlahSiswa' => $jumlahSiswa, 'cari' => $cari, 'status' => $status]);
    }

    public function siswa(Request $request, int $kelompok, int $anggota)
    {
        $kelompok = $this->kelompokTerbaca($request, $kelompok);
        $anggota = $kelompok->anggota()->with('siswa:id,nama_lengkap')->findOrFail($anggota);
        $status = $request->validate(['status' => ['nullable', Rule::in(['sudah', 'belum'])]])['status'] ?? '';
        $rekap = $this->layanan->siswa($kelompok, $anggota);
        $baris = $rekap['baris']->filter(fn ($item) => $status === '' || ($item['terbaru'] !== null) === ($status === 'sudah'));
        $bolehDokumen = $this->akses->dokumen($request->user(), $kelompok, $anggota);
        $bolehWisuda = $this->akses->lihatWisuda($request->user(), $kelompok, $anggota);

        return view('tahfidz.rekap-siswa', [...$rekap, 'baris' => $baris,
            'kelompok' => $kelompok, 'anggota' => $anggota, 'status' => $status, 'bolehDokumen' => $bolehDokumen,
            'bolehWisuda' => $bolehWisuda,
            'capaianJuz' => $bolehWisuda ? $this->capaian->jumlahSurah($anggota->siswa_id) : null,
            'kelulusan' => $bolehWisuda ? KelulusanTahfidz::where('siswa_id', $anggota->siswa_id)->first() : null,
            'bolehVerifikasi' => $this->akses->verifikasiWisuda($request->user(), $kelompok, $anggota),
        ]);
    }

    private function kelompokTerbaca(Request $request, int $id): KelompokTahfidz
    {
        return $this->akses->cakupan($request->user())->with('tahunPelajaran', 'pembimbing')->findOrFail($id);
    }
}
