<?php

namespace App\Http\Controllers;

use App\Models\LaporanPembinaanSiswa;
use App\Services\Pembinaan\AntreanVerifikasiPelanggaranService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PusatVerifikasiPelanggaranController extends Controller
{
    public function __construct(private AntreanVerifikasiPelanggaranService $antreanService)
    {
    }

    public function index(Request $request)
    {
        $pengguna = $request->user();
        $antrean = (string) $request->input('antrean', 'semua');
        $daftarAntrean = [
            'semua' => 'Semua tugas aktif',
            'bk' => 'Pemeriksaan BK',
            'terlambat' => 'Terlambat diproses',
            'selesai' => 'Riwayat selesai',
        ];
        if (! array_key_exists($antrean, $daftarAntrean)) {
            $antrean = 'semua';
        }

        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $query = $this->antreanService->queryUntuk($pengguna)
            ->with([
                'siswa:id,nama_lengkap,nisn', 'kelas:id,nama',
                'pelaporPegawai:id,nama_lengkap',
                'butirPelanggaranLaporan' => fn ($query) => $query->orderByDesc('poin'),
                'verifikasiBkPelanggaran' => fn ($query) => $query->with('bkPegawai:id,nama_lengkap')->latest('diverifikasi_pada'),
            ])
            ->withCount([
                'butirPelanggaranLaporan', 'buktiLaporanPembinaanSiswa',
                'saksiLaporanPembinaanSiswa', 'klarifikasiSiswaPembinaan',
            ])
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $query->where(function (Builder $query) use ($kataKunci) {
                    $query->where('nomor_laporan', 'ilike', '%' . $kataKunci . '%')
                        ->orWhereHas('siswa', fn (Builder $query) => $query
                            ->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                            ->orWhere('nisn', 'ilike', '%' . $kataKunci . '%'))
                        ->orWhereHas('kelas', fn (Builder $query) => $query->where('nama', 'ilike', '%' . $kataKunci . '%'));
                });
            });

        $this->antreanService->terapkanJenisAntrean($query, $antrean);
        $laporan = $query
            ->orderByRaw("case when status_verifikasi = 'perlu_klarifikasi' then 0 else 1 end")
            ->orderBy('updated_at')
            ->paginate(10)
            ->withQueryString();
        $laporan->getCollection()->transform(
            fn (LaporanPembinaanSiswa $item) => $this->antreanService->lengkapiUntukTampilan($item, $pengguna),
        );

        $ringkasan = $this->antreanService->ringkasan($pengguna);
        $hakAksi = [
            'bk' => $pengguna->memilikiIzin('poin_siswa.verifikasi_bk'),
            'monitor_semua' => $this->antreanService->dapatMemantauSemua($pengguna),
        ];

        return view('pusat-verifikasi-pelanggaran.index', compact(
            'laporan', 'ringkasan', 'antrean', 'daftarAntrean', 'kataKunci', 'hakAksi',
        ));
    }
}
