<?php

namespace App\Http\Controllers;

use App\Models\BuktiPelaksanaanSanksi;
use App\Models\SanksiPoinSiswa;
use App\Services\Pembinaan\AksesSanksiPoinService;
use App\Services\Pembinaan\SimpanBuktiPelaksanaanSanksiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BuktiPelaksanaanSanksiController extends Controller
{
    public function __construct(
        private AksesSanksiPoinService $akses,
        private SimpanBuktiPelaksanaanSanksiService $penyimpanan,
    ) {}

    public function store(Request $request, SanksiPoinSiswa $sanksiPoinSiswa)
    {
        abort_unless($this->akses->bolehKelola($request->user(), $sanksiPoinSiswa), 403);
        $data = $request->validate([
            'bukti_sanksi' => ['required', 'array', 'min:1', 'max:5'],
            'bukti_sanksi.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'keterangan_bukti' => ['nullable', 'string', 'max:500'],
        ]);

        $jumlah = $this->penyimpanan->simpanBanyak(
            $sanksiPoinSiswa,
            $request->file('bukti_sanksi', []),
            $data['keterangan_bukti'] ?? null,
            $request->user()?->id,
        );

        return back()->with('berhasil', $jumlah.' bukti pelaksanaan berhasil ditambahkan.');
    }

    public function download(Request $request, BuktiPelaksanaanSanksi $buktiPelaksanaanSanksi)
    {
        abort_unless($this->akses->bolehLihat($request->user(), $buktiPelaksanaanSanksi->sanksiPoinSiswa), 403);
        abort_unless(Storage::disk('local')->exists($buktiPelaksanaanSanksi->lokasi_file), 404);

        return Storage::disk('local')->download(
            $buktiPelaksanaanSanksi->lokasi_file,
            $buktiPelaksanaanSanksi->nama_file_asli,
        );
    }

    public function destroy(Request $request, BuktiPelaksanaanSanksi $buktiPelaksanaanSanksi)
    {
        abort_unless($this->akses->bolehKelola($request->user(), $buktiPelaksanaanSanksi->sanksiPoinSiswa), 403);
        $this->penyimpanan->hapus($buktiPelaksanaanSanksi, $request->user()?->id);

        return back()->with('berhasil', 'Bukti pelaksanaan berhasil dihapus.');
    }
}
