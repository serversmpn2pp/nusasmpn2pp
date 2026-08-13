<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnggotaKelasController extends Controller
{
    public function store(Request $request, Kelas $kelas)
    {
        abort_unless($request->user()?->dapatMengaksesKelasSebagaiWali($kelas->id) ?? false, 403);

        $data = $request->validate([
            'siswa_id' => [
                'required',
                Rule::exists('siswa', 'id')->where('aktif', true),
                Rule::unique('anggota_kelas', 'siswa_id')
                    ->where('tahun_pelajaran_id', $kelas->tahun_pelajaran_id),
            ],
            'tanggal_masuk' => 'nullable|date',
            'keterangan' => 'nullable|string',
        ]);

        if ($kelas->kapasitas && $kelas->anggotaKelas()->count() >= $kelas->kapasitas) {
            return back()
                ->withErrors(['siswa_id' => 'Kapasitas kelas sudah penuh.'])
                ->withInput();
        }

        AnggotaKelas::create([
            'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $data['siswa_id'],
            'nomor_absen' => null,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $data['tanggal_masuk'] ?? $kelas->tahunPelajaran?->tanggal_mulai,
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        return redirect()
            ->route('kelas.show', $kelas)
            ->with('berhasil', 'Siswa berhasil ditambahkan ke kelas.');
    }

    public function update(Request $request, AnggotaKelas $anggotaKelas)
    {
        abort_unless($request->user()?->dapatMengaksesKelasSebagaiWali($anggotaKelas->kelas_id) ?? false, 403);

        $data = $request->validate([
            'tanggal_masuk' => 'nullable|date',
            'keterangan' => 'nullable|string',
        ]);

        $anggotaKelas->update($data);

        return $this->redirectSetelahUbah($anggotaKelas, $request)
            ->with('berhasil', 'Data anggota kelas berhasil diperbarui.');
    }

    public function destroy(Request $request, AnggotaKelas $anggotaKelas)
    {
        abort_unless($request->user()?->dapatMengaksesKelasSebagaiWali($anggotaKelas->kelas_id) ?? false, 403);

        $kelas = $anggotaKelas->kelas;
        $tahunPelajaranId = $anggotaKelas->tahun_pelajaran_id;
        $kelasId = $anggotaKelas->kelas_id;
        $anggotaKelas->delete();

        if ($request->input('kembali') === 'penempatan') {
            return redirect()
                ->route('penempatan-siswa.index', [
                    'tahun_pelajaran_id' => $tahunPelajaranId,
                    'kelas_id' => $kelasId,
                ])
                ->with('berhasil', 'Siswa berhasil dikeluarkan dari kelas.');
        }

        return redirect()
            ->route('kelas.show', $kelas)
            ->with('berhasil', 'Siswa berhasil dikeluarkan dari kelas.');
    }

    private function redirectSetelahUbah(AnggotaKelas $anggotaKelas, Request $request)
    {
        if ($request->input('kembali') === 'penempatan') {
            return redirect()->route('penempatan-siswa.index', [
                'tahun_pelajaran_id' => $anggotaKelas->tahun_pelajaran_id,
                'kelas_id' => $anggotaKelas->kelas_id,
            ]);
        }

        return redirect()->route('kelas.show', $anggotaKelas->kelas);
    }
}
