<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Services\Cbt\KelolaJadwalUjianTerpusat;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JadwalUjianTerpusatController extends Controller
{
    public function store(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        KelolaJadwalUjianTerpusat $pengelola,
    ) {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'tingkat' => ['required', 'array', 'min:1'],
            'tingkat.*' => ['integer', Rule::in([7, 8, 9])],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);
        $jadwal = $pengelola->tambah($kegiatanUjianCbt, $data);

        return redirect()
            ->route('ujian-terpusat.pelaksanaan.index', [$kegiatanUjianCbt, 'tahap' => 7])
            ->with('berhasil', "Jadwal {$jadwal->first()->mataPelajaran?->nama} berhasil ditambahkan untuk {$jadwal->count()} tingkat.");
    }

    public function update(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        KelolaJadwalUjianTerpusat $pengelola,
    ) {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);
        $pengelola->ubah($kegiatanUjianCbt, $jadwalUjianCbt, $data, $request->user());

        return redirect()
            ->route('ujian-terpusat.pelaksanaan.index', [$kegiatanUjianCbt, 'tahap' => 7])
            ->with('berhasil', 'Jadwal ujian berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        KelolaJadwalUjianTerpusat $pengelola,
    ) {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $pengelola->hapus($kegiatanUjianCbt, $jadwalUjianCbt);

        return redirect()
            ->route('ujian-terpusat.pelaksanaan.index', [$kegiatanUjianCbt, 'tahap' => 7])
            ->with('berhasil', 'Jadwal ujian berhasil dihapus.');
    }

    private function pastikanAkses(Request $request, KegiatanUjianCbt $kegiatan): void
    {
        abort_unless($kegiatan->dapatDiaksesOleh($request->user()), 403);
    }
}
