<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\KomponenNilai;
use App\Models\NilaiSiswa;
use App\Services\Nilai\PublikasiNilaiService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublikasiNilaiController extends Controller
{
    public function __construct(private PublikasiNilaiService $publikasiNilai) {}

    public function publikasikan(Request $request, GuruMataPelajaran $guruMataPelajaran, string $semester)
    {
        $semester = $this->semesterValid($semester);
        $this->pastikanBolehAkses($request, $guruMataPelajaran);

        if (! $guruMataPelajaran->aktif) {
            throw ValidationException::withMessages([
                'publikasi' => 'Nilai dari penugasan guru mapel yang tidak aktif tidak dapat dipublikasikan.',
            ]);
        }

        $komponenIds = KomponenNilai::query()
            ->where('guru_mata_pelajaran_id', $guruMataPelajaran->id)
            ->where('semester', $semester)
            ->where('aktif', true)
            ->pluck('id');

        if ($komponenIds->isEmpty()) {
            throw ValidationException::withMessages([
                'publikasi' => 'Belum ada komponen nilai aktif untuk semester ini.',
            ]);
        }

        $memilikiNilai = NilaiSiswa::query()
            ->whereIn('komponen_nilai_id', $komponenIds)
            ->where(function ($query) {
                $query->whereNotNull('nilai')->orWhereNotNull('predikat');
            })
            ->exists();

        if (! $memilikiNilai) {
            throw ValidationException::withMessages([
                'publikasi' => 'Belum ada nilai siswa yang dapat dipublikasikan.',
            ]);
        }

        $this->publikasiNilai->publikasikan(
            (int) $guruMataPelajaran->id,
            $semester,
            $request->user()?->id,
        );

        return redirect()
            ->route('input-nilai.index', array_filter([
                'komponen_nilai_id' => $request->input('komponen_nilai_id'),
            ]))
            ->with('berhasil', 'Nilai berhasil dipublikasikan dan sekarang dapat dilihat oleh siswa.');
    }

    public function jadikanDraf(Request $request, GuruMataPelajaran $guruMataPelajaran, string $semester)
    {
        $semester = $this->semesterValid($semester);
        $this->pastikanBolehAkses($request, $guruMataPelajaran);
        $this->publikasiNilai->tandaiDraf((int) $guruMataPelajaran->id, $semester);

        return redirect()
            ->route('input-nilai.index', array_filter([
                'komponen_nilai_id' => $request->input('komponen_nilai_id'),
            ]))
            ->with('berhasil', 'Publikasi dibatalkan. Nilai kembali menjadi draf dan tidak terlihat oleh siswa.');
    }

    private function semesterValid(string $semester): string
    {
        abort_unless(in_array($semester, ['ganjil', 'genap'], true), 404);

        return $semester;
    }

    private function pastikanBolehAkses(Request $request, GuruMataPelajaran $guruMataPelajaran): void
    {
        $pengguna = $request->user();

        if (! $pengguna || $pengguna->administrator()) {
            return;
        }

        if (
            ! $pengguna->memilikiPeran('guru_mapel')
            || $pengguna->memilikiPeran(['pimpinan', 'wakil_pimpinan_kurikulum'])
        ) {
            return;
        }

        abort_unless(
            (int) $guruMataPelajaran->pegawai_id === (int) $pengguna->pegawai_id,
            403,
        );
    }
}
