<?php

namespace App\Http\Controllers;

use App\Models\PengawasRuangUjianTerpusat;
use App\Services\Cbt\RingkasanPusatCbtService;
use Illuminate\Http\Request;

class PusatCbtController extends Controller
{
    public function index(Request $request, RingkasanPusatCbtService $ringkasanPusatCbt)
    {
        $pengguna = $request->user();
        $ringkasan = $ringkasanPusatCbt->untuk($pengguna);

        return view('pusat-cbt.index', [
            'jumlahSoalSiap' => $ringkasan['soal_siap'],
            'jumlahKegiatanTerpusat' => $ringkasan['kegiatan_terpusat'],
            'jumlahAsesmenKelas' => $ringkasan['asesmen_kelas'],
            'jumlahPaketTerpusatSiap' => $ringkasan['paket_terjadwal'],
            'ringkasanCbtTerbatas' => ! $pengguna->memilikiIzin('cbt.kelola'),
            'dapatMengawasiUjian' => $pengguna?->pegawai_id
                ? PengawasRuangUjianTerpusat::query()
                    ->where(function ($query) use ($pengguna) {
                        $query->where('pengawas_utama_pegawai_id', $pengguna->pegawai_id)
                            ->orWhere('pengawas_pendamping_pegawai_id', $pengguna->pegawai_id);
                    })
                    ->exists()
                : false,
        ]);
    }
}
