<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\KomponenNilai;
use App\Models\NilaiSiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InputNilaiController extends Controller
{
    public function index(Request $request)
    {
        $komponenNilaiId = old('komponen_nilai_id', $request->input('komponen_nilai_id'));
        $daftarKomponenNilai = $this->ambilDaftarKomponenNilai();
        $komponenDipilih = null;
        $anggotaKelas = collect();
        $nilaiTersimpan = collect();

        if ($komponenNilaiId) {
            $komponenDipilih = $this->ambilKomponenDipilih($komponenNilaiId);
            $kelasId = $komponenDipilih->guruMataPelajaran?->kelas_id;

            if ($kelasId) {
                $anggotaKelas = $this->ambilAnggotaKelas($kelasId);
                $siswaIds = $anggotaKelas->pluck('siswa_id');
                $nilaiTersimpan = NilaiSiswa::query()
                    ->where('komponen_nilai_id', $komponenDipilih->id)
                    ->whereIn('siswa_id', $siswaIds)
                    ->get()
                    ->keyBy('siswa_id');
            }
        }

        $jumlahSiswa = $anggotaKelas->count();
        $jumlahTerisi = $nilaiTersimpan
            ->filter(fn (NilaiSiswa $nilaiSiswa) => $nilaiSiswa->nilai !== null)
            ->count();
        $rataRata = $nilaiTersimpan
            ->filter(fn (NilaiSiswa $nilaiSiswa) => $nilaiSiswa->nilai !== null)
            ->avg('nilai');

        return view('input-nilai.index', compact(
            'daftarKomponenNilai',
            'komponenNilaiId',
            'komponenDipilih',
            'anggotaKelas',
            'nilaiTersimpan',
            'jumlahSiswa',
            'jumlahTerisi',
            'rataRata',
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'komponen_nilai_id' => ['required', 'exists:komponen_nilai,id'],
            'nilai' => ['nullable', 'array'],
            'nilai.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'array'],
            'catatan.*' => ['nullable', 'string', 'max:255'],
        ], [
            'nilai.*.numeric' => 'Nilai harus berupa angka.',
            'nilai.*.min' => 'Nilai minimal 0.',
            'nilai.*.max' => 'Nilai maksimal 100.',
        ]);

        $komponenDipilih = $this->ambilKomponenDipilih($data['komponen_nilai_id']);
        $kelasId = $komponenDipilih->guruMataPelajaran?->kelas_id;
        $anggotaKelas = $kelasId ? $this->ambilAnggotaKelas($kelasId) : collect();
        $siswaIds = $anggotaKelas->pluck('siswa_id')->map(fn ($id) => (int) $id);
        $idsDikirim = collect(array_keys($data['nilai'] ?? []))
            ->merge(array_keys($data['catatan'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($idsDikirim->diff($siswaIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'komponen_nilai_id' => 'Ada data siswa yang tidak sesuai dengan kelas komponen nilai ini.',
            ]);
        }

        DB::transaction(function () use ($komponenDipilih, $siswaIds, $data) {
            foreach ($siswaIds as $siswaId) {
                $nilaiMentah = $data['nilai'][$siswaId] ?? null;
                $catatan = trim((string) ($data['catatan'][$siswaId] ?? ''));
                $nilai = $nilaiMentah === null || $nilaiMentah === ''
                    ? null
                    : round((float) $nilaiMentah, 2);

                if ($nilai === null && $catatan === '') {
                    NilaiSiswa::query()
                        ->where('komponen_nilai_id', $komponenDipilih->id)
                        ->where('siswa_id', $siswaId)
                        ->delete();

                    continue;
                }

                NilaiSiswa::updateOrCreate(
                    [
                        'komponen_nilai_id' => $komponenDipilih->id,
                        'siswa_id' => $siswaId,
                    ],
                    [
                        'nilai' => $nilai,
                        'catatan' => $catatan ?: null,
                    ]
                );
            }
        });

        return redirect()
            ->route('input-nilai.index', ['komponen_nilai_id' => $komponenDipilih->id])
            ->with('berhasil', 'Nilai siswa berhasil disimpan.');
    }

    private function ambilDaftarKomponenNilai()
    {
        return KomponenNilai::query()
            ->with([
                'guruMataPelajaran.tahunPelajaran',
                'guruMataPelajaran.kelas',
                'guruMataPelajaran.mataPelajaran',
                'guruMataPelajaran.pegawai',
            ])
            ->where('aktif', true)
            ->whereHas('guruMataPelajaran', function ($query) {
                $query->where('aktif', true);
            })
            ->orderBy('semester')
            ->orderBy('jenis_komponen')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    private function ambilKomponenDipilih(int|string $komponenNilaiId): KomponenNilai
    {
        return KomponenNilai::query()
            ->with([
                'guruMataPelajaran.tahunPelajaran',
                'guruMataPelajaran.kelas',
                'guruMataPelajaran.mataPelajaran',
                'guruMataPelajaran.pegawai',
            ])
            ->where('aktif', true)
            ->whereKey($komponenNilaiId)
            ->firstOrFail();
    }

    private function ambilAnggotaKelas(int $kelasId)
    {
        return AnggotaKelas::query()
            ->with('siswa')
            ->where('kelas_id', $kelasId)
            ->where('status_keanggotaan', 'aktif')
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->get();
    }
}
