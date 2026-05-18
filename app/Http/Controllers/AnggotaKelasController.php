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
        $data = $request->validate([
            'siswa_id' => [
                'required',
                Rule::exists('siswa', 'id')->where('aktif', true),
                Rule::unique('anggota_kelas', 'siswa_id')
                    ->where('tahun_pelajaran_id', $kelas->tahun_pelajaran_id),
            ],
            'nomor_absen' => [
                'nullable',
                'integer',
                'min:1',
                'max:500',
                Rule::unique('anggota_kelas', 'nomor_absen')
                    ->where('kelas_id', $kelas->id),
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
            'nomor_absen' => $data['nomor_absen'] ?? null,
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
        $data = $request->validate([
            'nomor_absen' => [
                'nullable',
                'integer',
                'min:1',
                'max:500',
                Rule::unique('anggota_kelas', 'nomor_absen')
                    ->where('kelas_id', $anggotaKelas->kelas_id)
                    ->ignore($anggotaKelas),
            ],
            'tanggal_masuk' => 'nullable|date',
            'keterangan' => 'nullable|string',
        ]);

        $anggotaKelas->update($data);

        return redirect()
            ->route('kelas.show', $anggotaKelas->kelas)
            ->with('berhasil', 'Data anggota kelas berhasil diperbarui.');
    }

    public function destroy(AnggotaKelas $anggotaKelas)
    {
        $kelas = $anggotaKelas->kelas;
        $anggotaKelas->delete();

        return redirect()
            ->route('kelas.show', $kelas)
            ->with('berhasil', 'Siswa berhasil dikeluarkan dari kelas.');
    }
}
