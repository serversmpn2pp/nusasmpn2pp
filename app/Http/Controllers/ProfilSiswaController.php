<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;

class ProfilSiswaController extends Controller
{
    public function show(Request $request)
    {
        $pengguna = $request->user();

        abort_unless($this->akunSiswa($pengguna), 403);

        $siswa = $pengguna->siswa()->firstOrFail();
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();
        $anggotaKelas = $this->anggotaKelasAktif($siswa->id, $tahunPelajaran?->id);

        return view('profil-siswa.show', compact(
            'pengguna',
            'siswa',
            'tahunPelajaran',
            'anggotaKelas',
        ));
    }

    private function akunSiswa(?Pengguna $pengguna): bool
    {
        return (bool) ($pengguna?->akunSiswa() || $pengguna?->memilikiPeran('siswa'));
    }

    private function anggotaKelasAktif(int $siswaId, ?int $tahunPelajaranId): ?AnggotaKelas
    {
        $query = AnggotaKelas::query()
            ->with(['kelas:id,nama,tingkat,wali_kelas_id', 'kelas.waliKelas:id,nama_lengkap', 'tahunPelajaran:id,nama'])
            ->where('siswa_id', $siswaId)
            ->where('status_keanggotaan', 'aktif');

        if ($tahunPelajaranId) {
            $anggotaKelas = (clone $query)
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->latest('id')
                ->first();

            if ($anggotaKelas) {
                return $anggotaKelas;
            }
        }

        return $query->latest('tahun_pelajaran_id')->latest('id')->first();
    }
}
