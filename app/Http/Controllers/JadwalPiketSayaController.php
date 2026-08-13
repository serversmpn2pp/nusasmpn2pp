<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;

class JadwalPiketSayaController extends Controller
{
    public function index(Request $request)
    {
        $pengguna = $request->user();
        abort_unless($pengguna?->pegawai_id, 403);

        $tahunPelajaranAktif = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();
        $kodeHariIni = array_keys(JadwalPiketGuru::DAFTAR_HARI)[now()->dayOfWeekIso - 1] ?? null;
        $jadwalSaya = JadwalPiketGuru::query()
            ->with('tahunPelajaran:id,nama')
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->when(
                $tahunPelajaranAktif,
                fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('aktif', true)
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->get();
        $guruMapelAktif = $tahunPelajaranAktif && GuruMataPelajaran::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranAktif->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('jenis_penugasan', 'pengampu')
            ->where('aktif', true)
            ->exists();
        $jadwalHariIni = $jadwalSaya->firstWhere('hari', $kodeHariIni);

        return view('jadwal-piket-saya.index', [
            'tahunPelajaranAktif' => $tahunPelajaranAktif,
            'jadwalSaya' => $jadwalSaya,
            'jadwalHariIni' => $jadwalHariIni,
            'kodeHariIni' => $kodeHariIni,
            'guruMapelAktif' => $guruMapelAktif,
            'dapatMencatatHariIni' => (bool) ($guruMapelAktif && $jadwalHariIni),
            'daftarHari' => JadwalPiketGuru::DAFTAR_HARI,
        ]);
    }
}
