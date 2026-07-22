<?php

namespace App\Http\Controllers;

use App\Models\KlarifikasiSiswaPembinaan;
use App\Models\LaporanPembinaanSiswa;
use App\Services\Pembinaan\AksesLaporanPembinaanService;
use App\Services\Pembinaan\CatatRiwayatPembinaanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KlarifikasiSiswaPembinaanController extends Controller
{
    public function __construct(
        private AksesLaporanPembinaanService $akses,
        private CatatRiwayatPembinaanService $riwayat,
    ) {
    }

    public function store(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        abort_unless($this->akses->bolehMencatatKlarifikasi($request->user(), $laporanPembinaanSiswa), 403);
        $data = $request->validate([
            'isi_klarifikasi' => ['required', 'string', 'max:10000'],
            'metode' => ['required', Rule::in(array_keys(KlarifikasiSiswaPembinaan::DAFTAR_METODE))],
            'pendamping' => ['nullable', 'string', 'max:160'],
            'disampaikan_pada' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($request, $laporanPembinaanSiswa, $data) {
            $laporanPembinaanSiswa->klarifikasiSiswaPembinaan()->create([
                'isi_klarifikasi' => trim($data['isi_klarifikasi']),
                'metode' => $data['metode'],
                'pendamping' => filled($data['pendamping'] ?? null) ? trim($data['pendamping']) : null,
                'disampaikan_pada' => $data['disampaikan_pada'],
                'dicatat_oleh_pengguna_id' => $request->user()?->id,
            ]);
            $this->riwayat->catat(
                $laporanPembinaanSiswa,
                'klarifikasi_siswa',
                'Klarifikasi siswa dicatat',
                'Keterangan siswa ditambahkan ke pemeriksaan fakta.',
                $laporanPembinaanSiswa->status_verifikasi,
                $laporanPembinaanSiswa->status_verifikasi,
                $request->user()?->id,
            );
        });

        return back()->with('berhasil', 'Klarifikasi siswa berhasil dicatat.');
    }
}
