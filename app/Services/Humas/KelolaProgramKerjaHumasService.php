<?php

namespace App\Services\Humas;

use App\Models\LaporanPelaksanaanHumas;
use App\Models\ProgramKerjaHumas;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KelolaProgramKerjaHumasService
{
    public function kunci(ProgramKerjaHumas $program, ?int $versi = null): ProgramKerjaHumas
    {
        $program = ProgramKerjaHumas::lockForUpdate()->findOrFail($program->id);
        if ($versi !== null && $program->versi !== $versi) {
            throw ValidationException::withMessages(['versi' => 'Program telah berubah. Muat ulang sebelum menyimpan.']);
        }

        return $program;
    }

    public function kunciLaporan(ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan, int $versi, bool $draf = true): LaporanPelaksanaanHumas
    {
        $this->pastikanTerbuka($program);
        $laporan = LaporanPelaksanaanHumas::lockForUpdate()->findOrFail($laporan->id);
        abort_unless($laporan->program_kerja_humas_id === $program->id, 404);
        if ($laporan->versi !== $versi) {
            throw ValidationException::withMessages(['versi' => 'Laporan atau buktinya telah berubah. Muat ulang sebelum menyimpan.']);
        }
        if ($draf && $laporan->status !== 'draf') {
            throw ValidationException::withMessages(['status' => 'Laporan bukan draf. Buka revisi dengan alasan sebelum mengubahnya.']);
        }

        return $laporan;
    }

    public function pastikanTerbuka(ProgramKerjaHumas $program): void
    {
        if (! $program->terbuka()) {
            throw ValidationException::withMessages(['status' => 'Program sudah ditutup. Ubah status menjadi Sedang berjalan sebelum mengelola laporan.']);
        }
    }

    public function tanggal(ProgramKerjaHumas $program, $mulai, $selesai): void
    {
        $tahun = $program->tahunPelajaran()->firstOrFail();
        if (($tahun->tanggal_mulai && $mulai->lt($tahun->tanggal_mulai)) || ($tahun->tanggal_selesai && $selesai->gt($tahun->tanggal_selesai))) {
            throw ValidationException::withMessages(['tanggal_mulai' => 'Tanggal harus berada dalam tahun pelajaran yang dipilih.']);
        }
    }

    public function validasiProgram(ProgramKerjaHumas $program): void
    {
        if ($program->exists && $program->isDirty('tahun_pelajaran_id') && $program->laporan()->exists()) {
            throw ValidationException::withMessages(['tahun_pelajaran_id' => 'Tahun pelajaran tidak dapat diganti setelah laporan ditambahkan.']);
        }
        $this->tanggal($program, $program->tanggal_mulai, $program->tanggal_selesai);
        if ($program->status === 'selesai' && (! $program->exists || ! $program->laporanFinal()->exists() || $program->laporan()->where('status', 'draf')->exists())) {
            throw ValidationException::withMessages(['status' => 'Untuk menyelesaikan program, finalisasi minimal satu laporan dan selesaikan atau batalkan semua draf.']);
        }
    }

    public function catat(ProgramKerjaHumas $program, Request $request, string $aksi, ?string $catatan = null, ?LaporanPelaksanaanHumas $laporan = null): void
    {
        $program->riwayat()->create(['laporan_pelaksanaan_humas_id' => $laporan?->id, 'aksi' => $aksi,
            'versi' => $laporan?->versi ?? $program->versi, 'snapshot' => $laporan ? $laporan->snapshot() : $program->snapshot(),
            'catatan_perubahan' => $catatan, 'pengguna_id' => $request->user()->id, 'created_at' => now()]);
    }

    public function ubahLaporan(LaporanPelaksanaanHumas $laporan, Request $request): void
    {
        $laporan->forceFill(['versi' => $laporan->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
    }
}
