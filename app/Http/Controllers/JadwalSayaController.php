<?php

namespace App\Http\Controllers;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;

class JadwalSayaController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
        ]);

        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $tahunPelajaran);
        $pegawai = $request->user()?->pegawai;

        $daftarHari = collect(JamPelajaran::DAFTAR_HARI)
            ->except('minggu')
            ->all();
        $jamPelajaran = JamPelajaran::query()
            ->where('aktif', true)
            ->whereIn('hari', array_keys($daftarHari))
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy('nomor_jam')
            ->get();
        $jamPerHari = $jamPelajaran
            ->groupBy('hari')
            ->map(fn ($items) => $items->keyBy('nomor_jam'));
        $nomorJam = $jamPelajaran
            ->pluck('nomor_jam')
            ->unique()
            ->sort()
            ->values();

        $jadwalSaya = JadwalPelajaran::query()
            ->with([
                'kelas',
                'jamPelajaran',
                'guruMataPelajaran.mataPelajaran',
            ])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when(
                $pegawai,
                fn ($query) => $query->whereHas(
                    'guruMataPelajaran',
                    fn ($guruMapel) => $guruMapel->where('pegawai_id', $pegawai->id)
                ),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->where('aktif', true)
            ->get()
            ->keyBy('jam_pelajaran_id');

        $hariHariIni = [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][now()->dayOfWeekIso];
        $jamAktifId = $jamPelajaran
            ->where('hari', $hariHariIni)
            ->first(fn (JamPelajaran $jam) => $jam->jam_mulai <= now()->format('H:i:s')
                && $jam->jam_selesai >= now()->format('H:i:s'))
            ?->id;

        return view('jadwal-saya.index', [
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'pegawai' => $pegawai,
            'daftarHari' => $daftarHari,
            'jamPerHari' => $jamPerHari,
            'nomorJam' => $nomorJam,
            'jadwalSaya' => $jadwalSaya,
            'hariHariIni' => $hariHariIni,
            'jamAktifId' => $jamAktifId,
            'jumlahJamMengajar' => $jadwalSaya->count(),
            'jumlahKelas' => $jadwalSaya->pluck('kelas_id')->unique()->count(),
            'jumlahMataPelajaran' => $jadwalSaya->pluck('guruMataPelajaran.mata_pelajaran_id')->unique()->count(),
        ]);
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, $tahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $tahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        return $tahunPelajaran->firstWhere('aktif', true)?->id ?? $tahunPelajaran->first()?->id;
    }
}
