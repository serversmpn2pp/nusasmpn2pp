<?php

namespace App\Http\Controllers;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;

class JadwalKelasSayaController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);

        $pengguna = $request->user();
        $pegawai = $pengguna?->pegawai;
        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId(
            $data['tahun_pelajaran_id'] ?? null,
            $tahunPelajaran,
        );

        $daftarKelas = Kelas::query()
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when(
                $pegawai,
                fn ($query) => $query->where('wali_kelas_id', $pegawai->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('aktif', true)
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();
        $kelasId = $this->ambilKelasId($data['kelas_id'] ?? null, $daftarKelas);
        $kelasDipilih = $daftarKelas->firstWhere('id', $kelasId);

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

        $jadwalKelas = JadwalPelajaran::query()
            ->with([
                'mataPelajaran',
                'guruMataPelajaran.mataPelajaran',
                'guruMataPelajaran.pegawai',
            ])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when(
                $kelasId,
                fn ($query) => $query->where('kelas_id', $kelasId),
                fn ($query) => $query->whereRaw('1 = 0'),
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

        return view('jadwal-kelas-saya.index', [
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'daftarKelas' => $daftarKelas,
            'kelasId' => $kelasId,
            'kelasDipilih' => $kelasDipilih,
            'pegawai' => $pegawai,
            'daftarHari' => $daftarHari,
            'jamPerHari' => $jamPerHari,
            'nomorJam' => $nomorJam,
            'jadwalKelas' => $jadwalKelas,
            'hariHariIni' => $hariHariIni,
            'jamAktifId' => $jamAktifId,
            'jumlahJadwal' => $jadwalKelas->count(),
            'jumlahMataPelajaran' => $jadwalKelas
                ->map(fn (JadwalPelajaran $jadwal) => $jadwal->mataPelajaranTerjadwal()?->id)
                ->filter()
                ->unique()
                ->count(),
        ]);
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, $tahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $tahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        return $tahunPelajaran->firstWhere('aktif', true)?->id ?? $tahunPelajaran->first()?->id;
    }

    private function ambilKelasId(?int $kelasId, $daftarKelas): ?int
    {
        if ($kelasId && $daftarKelas->contains('id', $kelasId)) {
            return $kelasId;
        }

        return $daftarKelas->first()?->id;
    }
}
