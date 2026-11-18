<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Services\Cbt\KelolaJadwalUjianTerpusat;
use App\Services\Cbt\SiapkanFolderSoalUjianTerpusat;
use Illuminate\Http\RedirectResponse;
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
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i', 'after:waktu_mulai'],
            'tingkat' => ['required', 'array', 'min:1'],
            'tingkat.*' => ['integer', Rule::in([7, 8, 9])],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);
        $jadwal = $pengelola->tambah($kegiatanUjianCbt, $data);

        return $this->kembaliKeJadwal($request, $kegiatanUjianCbt)
            ->with('jadwal_baru', $jadwal->pluck('id')->all())
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
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i', 'after:waktu_mulai'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);
        $pengelola->ubah($kegiatanUjianCbt, $jadwalUjianCbt, $data, $request->user());

        return $this->kembaliKeJadwal($request, $kegiatanUjianCbt)
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

        return $this->kembaliKeJadwal($request, $kegiatanUjianCbt)
            ->with('berhasil', 'Jadwal ujian berhasil dihapus.');
    }

    public function siapkanFolderSoal(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        SiapkanFolderSoalUjianTerpusat $folderSoal,
    ) {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        abort_unless($folderSoal->mendukung($kegiatanUjianCbt), 422);
        $jumlahBaru = $folderSoal->lengkapi($kegiatanUjianCbt);

        return $this->kembaliKeJadwal($request, $kegiatanUjianCbt)
            ->with('berhasil', $jumlahBaru > 0
                ? "{$jumlahBaru} folder Bank Soal baru disiapkan."
                : 'Tidak ada folder Bank Soal baru yang perlu disiapkan.');
    }

    private function kembaliKeJadwal(Request $request, KegiatanUjianCbt $kegiatan): RedirectResponse
    {
        $parameter = [$kegiatan, 'tahap' => 7];
        if ($request->query('urutan_jadwal') === 'pelaksanaan') {
            $parameter['urutan_jadwal'] = 'pelaksanaan';
        }

        return redirect()->route('ujian-terpusat.pelaksanaan.index', $parameter);
    }

    private function pastikanAkses(Request $request, KegiatanUjianCbt $kegiatan): void
    {
        abort_unless($kegiatan->dapatDiaksesOleh($request->user()), 403);
    }
}
