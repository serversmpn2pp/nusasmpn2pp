<?php

namespace App\Http\Controllers;

use App\Models\LaporanPembinaanSiswa;
use App\Services\Pembinaan\AksesLaporanPembinaanService;
use App\Services\Pembinaan\PoinPresensiOtomatisService;
use Illuminate\Http\Request;

class KoreksiPoinPresensiController extends Controller
{
    public function store(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa, AksesLaporanPembinaanService $akses, PoinPresensiOtomatisService $service)
    {
        abort_unless($request->user()?->aktif && $akses->bolehKoreksiPoinPresensi($request->user(), $laporanPembinaanSiswa), 403);
        $data = $request->validate(['alasan' => ['required', 'string', 'max:2000']]);
        $service->terimaAlasan($laporanPembinaanSiswa, $request->user()->id, $data['alasan']);
        $pesan = 'Alasan diterima. Poin kejadian ini dibatalkan; catatan presensi tetap tersimpan.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $pesan, 'data' => ['id' => $laporanPembinaanSiswa->id, 'total_poin' => 0]])
                ->header('Cache-Control', 'no-store');
        }

        return back()->with('berhasil', $pesan);
    }
}
