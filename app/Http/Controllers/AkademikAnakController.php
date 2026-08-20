<?php

namespace App\Http\Controllers;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Pengguna;
use App\Services\Nilai\RingkasanNilaiSiswaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AkademikAnakController extends Controller
{
    public function __construct(private RingkasanNilaiSiswaService $ringkasanNilai) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(['jadwal', 'nilai'])],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
        ]);
        [$orangTua, $siswa] = $this->orangTuaDanSiswa($request->user());
        $dataNilai = $this->ringkasanNilai->siapkan(
            $siswa,
            null,
            $data['semester'] ?? null,
        );
        $anggotaKelas = $dataNilai['anggotaKelas'];
        $tahunPelajaran = $dataNilai['tahunPelajaranDipilih'];
        $daftarHari = collect(JamPelajaran::DAFTAR_HARI)->except('minggu')->all();
        $jamPelajaran = JamPelajaran::query()
            ->where('aktif', true)
            ->whereIn('hari', array_keys($daftarHari))
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy('nomor_jam')
            ->get();
        $jamPerHari = $jamPelajaran
            ->groupBy('hari')
            ->map(fn ($items) => $items->keyBy('nomor_jam'));
        $nomorJam = $jamPelajaran->pluck('nomor_jam')->unique()->sort()->values();
        $jadwalKelas = JadwalPelajaran::query()
            ->with([
                'mataPelajaran',
                'guruMataPelajaran.mataPelajaran',
                'guruMataPelajaran.pegawai:id,nama_lengkap',
            ])
            ->when(
                $tahunPelajaran && $anggotaKelas,
                fn ($query) => $query
                    ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                    ->where('kelas_id', $anggotaKelas->kelas_id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('aktif', true)
            ->get()
            ->keyBy('jam_pelajaran_id');
        $hariHariIni = $this->kodeHari(now()->dayOfWeekIso);
        $jamAktifId = $jamPelajaran
            ->where('hari', $hariHariIni)
            ->first(fn (JamPelajaran $jam) => $jam->jam_mulai <= now()->format('H:i:s')
                && $jam->jam_selesai >= now()->format('H:i:s'))
            ?->id;

        return view('akademik-anak.index', array_merge($dataNilai, [
            'orangTua' => $orangTua,
            'tab' => $data['tab'] ?? 'jadwal',
            'tahunPelajaran' => $tahunPelajaran,
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
        ]));
    }

    private function orangTuaDanSiswa(?Pengguna $pengguna): array
    {
        abort_unless($pengguna?->akunOrangTua() || $pengguna?->memilikiPeran('orang_tua'), 403);

        $orangTua = $pengguna->orangTuaWali()
            ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
            ->first();
        $siswa = $orangTua?->siswa
            ->firstWhere('id', $orangTua->siswa_acuan_username_id)
            ?: $orangTua?->siswa->first();

        return [$orangTua, $siswa];
    }

    private function kodeHari(int $nomorHari): string
    {
        return [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$nomorHari];
    }
}
