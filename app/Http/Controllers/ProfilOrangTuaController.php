<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfilOrangTuaController extends Controller
{
    public function edit(Request $request)
    {
        [$orangTua, $siswa] = $this->orangTuaDanSiswa($request->user());
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();
        $anggotaKelas = $this->anggotaKelasAktif($siswa, $tahunPelajaran);

        return view('profil-orang-tua.edit', [
            'orangTua' => $orangTua,
            'siswa' => $siswa,
            'tahunPelajaran' => $tahunPelajaran,
            'anggotaKelas' => $anggotaKelas,
        ]);
    }

    public function update(Request $request)
    {
        [$orangTua] = $this->orangTuaDanSiswa($request->user());
        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nomor_wa' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]+$/'],
        ], [
            'nama_lengkap.required' => 'Nama orang tua atau wali wajib diisi.',
            'nomor_wa.regex' => 'Nomor WhatsApp hanya boleh berisi angka dan tanda telepon yang umum.',
        ]);
        $data['nama_lengkap'] = trim($data['nama_lengkap']);
        $data['nomor_wa'] = filled($data['nomor_wa'] ?? null)
            ? trim($data['nomor_wa'])
            : null;

        DB::transaction(function () use ($request, $orangTua, $data) {
            $orangTua->update($data);
            $request->user()->forceFill([
                'nama' => $data['nama_lengkap'],
            ])->save();
        });

        return redirect()
            ->route('profil-orang-tua.edit')
            ->with('berhasil', 'Profil orang tua berhasil diperbarui.');
    }

    private function orangTuaDanSiswa(?Pengguna $pengguna): array
    {
        abort_unless($pengguna?->akunOrangTua() || $pengguna?->memilikiPeran('orang_tua'), 403);

        $orangTua = $pengguna->orangTuaWali()
            ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
            ->firstOrFail();
        $siswa = $orangTua->siswa
            ->firstWhere('id', $orangTua->siswa_acuan_username_id)
            ?: $orangTua->siswa->first();

        return [$orangTua, $siswa];
    }

    private function anggotaKelasAktif(?Siswa $siswa, ?TahunPelajaran $tahunPelajaran): ?AnggotaKelas
    {
        if (! $siswa) {
            return null;
        }

        $query = AnggotaKelas::query()
            ->with(['kelas:id,nama,tingkat,wali_kelas_id', 'kelas.waliKelas:id,nama_lengkap'])
            ->where('siswa_id', $siswa->id)
            ->where('status_keanggotaan', 'aktif');

        if ($tahunPelajaran) {
            $anggotaKelas = (clone $query)
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->latest('id')
                ->first();

            if ($anggotaKelas) {
                return $anggotaKelas;
            }
        }

        return $query->latest('tahun_pelajaran_id')->latest('id')->first();
    }
}
