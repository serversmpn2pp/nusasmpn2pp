<?php

namespace App\Http\Controllers;

use App\Models\BuktiLaporanPembinaanSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Services\Pembinaan\AksesLaporanPembinaanService;
use App\Services\Pembinaan\SimpanBuktiLaporanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BuktiLaporanPembinaanController extends Controller
{
    public function __construct(
        private AksesLaporanPembinaanService $akses,
        private SimpanBuktiLaporanService $penyimpanan,
    ) {
    }

    public function store(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        abort_unless($this->akses->bolehKelolaFakta($request->user(), $laporanPembinaanSiswa), 403);
        $data = $request->validate([
            'bukti_laporan' => ['required', 'array', 'min:1', 'max:5'],
            'bukti_laporan.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'keterangan_bukti' => ['nullable', 'string', 'max:500'],
        ]);

        $jumlah = $this->penyimpanan->simpanBanyak(
            $laporanPembinaanSiswa,
            $request->file('bukti_laporan', []),
            $data['keterangan_bukti'] ?? null,
            $request->user()?->id,
        );

        return back()->with('berhasil', $jumlah . ' bukti pendukung berhasil ditambahkan.');
    }

    public function download(Request $request, BuktiLaporanPembinaanSiswa $buktiLaporanPembinaanSiswa)
    {
        $this->akses->pastikanBolehLihat($request->user(), $buktiLaporanPembinaanSiswa->laporanPembinaanSiswa);
        abort_unless(Storage::disk('local')->exists($buktiLaporanPembinaanSiswa->lokasi_file), 404);

        return Storage::disk('local')->download(
            $buktiLaporanPembinaanSiswa->lokasi_file,
            $buktiLaporanPembinaanSiswa->nama_file_asli,
        );
    }

    public function destroy(Request $request, BuktiLaporanPembinaanSiswa $buktiLaporanPembinaanSiswa)
    {
        $laporan = $buktiLaporanPembinaanSiswa->laporanPembinaanSiswa;
        abort_unless($this->akses->bolehKelolaFakta($request->user(), $laporan), 403);
        abort_unless($this->akses->bolehMenghapusCatatan($request->user(), $buktiLaporanPembinaanSiswa->diunggah_oleh_pengguna_id), 403);

        $this->penyimpanan->hapus($buktiLaporanPembinaanSiswa, $request->user()?->id);

        return back()->with('berhasil', 'Bukti pendukung berhasil dihapus.');
    }
}
