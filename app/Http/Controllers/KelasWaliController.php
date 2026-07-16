<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;

class KelasWaliController extends Controller
{
    public function index(Request $request)
    {
        $pengguna = $request->user();

        abort_unless(
            $pengguna?->pegawai_id && $pengguna->memilikiPeran('wali_kelas'),
            403,
        );

        $kelasWaliIds = $pengguna->kelasWaliIds();

        $kelasWali = Kelas::query()
            ->with([
                'tahunPelajaran',
                'waliKelas',
                'anggotaKelas' => function ($query) {
                    $query->with('siswa')
                        ->where('status_keanggotaan', 'aktif')
                        ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
                        ->orderByRaw('nomor_absen IS NULL')
                        ->orderBy('nomor_absen')
                        ->orderBy('id');
                },
            ])
            ->withCount([
                'anggotaKelas as jumlah_siswa' => function ($query) {
                    $query->where('status_keanggotaan', 'aktif')
                        ->whereHas('siswa', fn ($query) => $query->where('aktif', true));
                },
            ])
            ->whereIn('id', $kelasWaliIds)
            ->orderByDesc('aktif')
            ->orderByDesc(
                TahunPelajaran::select('aktif')
                    ->whereColumn('tahun_pelajaran.id', 'kelas.tahun_pelajaran_id')
                    ->limit(1)
            )
            ->orderByDesc(
                TahunPelajaran::select('nama')
                    ->whereColumn('tahun_pelajaran.id', 'kelas.tahun_pelajaran_id')
                    ->limit(1)
            )
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();

        $anggotaKelas = $kelasWali->flatMap->anggotaKelas;
        $ringkasan = [
            'kelas' => $kelasWali->count(),
            'siswa' => $anggotaKelas->count(),
            'laki_laki' => $anggotaKelas->filter(fn ($anggota) => $anggota->siswa?->jenis_kelamin === 'L')->count(),
            'perempuan' => $anggotaKelas->filter(fn ($anggota) => $anggota->siswa?->jenis_kelamin === 'P')->count(),
        ];

        return view('kelas-wali.index', compact('kelasWali', 'ringkasan'));
    }
}
