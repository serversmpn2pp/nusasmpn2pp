<?php

namespace App\Services\Pembinaan;

use App\Models\AbsensiSiswa;
use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\PengaturanPoinKeterlambatan;
use Illuminate\Validation\ValidationException;

class AturanPresensiManualService
{
    public function pilihan()
    {
        return JenisPelanggaranSiswa::where('aktif', true)
            ->where(fn ($q) => $q->whereNull('kategori_pembinaan_siswa_id')->orWhereHas('kategoriPembinaanSiswa', fn ($q) => $q->where('aktif', true)))
            ->orderBy('urutan')->orderBy('nama')->get(['id', 'kode', 'nama', 'poin', 'tingkat', 'kategori_pembinaan_siswa_id']);
    }

    public function aturan(int $tahunId): array
    {
        $id = PengaturanPoinKeterlambatan::where('tahun_pelajaran_id', $tahunId)->value('pelanggaran_hadir_manual_id');
        $jenis = $id ? JenisPelanggaranSiswa::find($id) : JenisPelanggaranSiswa::where('kode', 'PRESENSI_MANUAL')->first();
        if (! $jenis) {
            $kategori = KategoriPembinaanSiswa::firstOrCreate(['kode' => 'KEHADIRAN'], ['nama' => 'Kehadiran', 'aktif' => true]);
            $jenis = JenisPelanggaranSiswa::firstOrCreate(['kode' => 'PRESENSI_MANUAL'], [
                'kategori_pembinaan_siswa_id' => $kategori->id, 'nama' => 'Tidak melakukan scan kartu pelajar (presensi manual)',
                'tingkat' => 'ringan', 'poin' => 15, 'urutan' => 999, 'aktif' => true,
            ]);
        }

        return $jenis ? $jenis->only(['id', 'kode', 'nama', 'tingkat', 'poin', 'kategori_pembinaan_siswa_id']) : [
            'id' => null, 'kode' => 'PRESENSI_MANUAL', 'nama' => 'Presensi manual tanpa kartu',
            'tingkat' => 'ringan', 'poin' => 15, 'kategori_pembinaan_siswa_id' => null,
        ];
    }

    public function simpan(PengaturanPoinKeterlambatan $pengaturan, array $data): void
    {
        if (! array_key_exists('pelanggaran_hadir_manual_id', $data)) {
            return;
        }
        $jenis = $this->pilihan()->firstWhere('id', (int) $data['pelanggaran_hadir_manual_id']);
        if (! $jenis) {
            throw ValidationException::withMessages(['pelanggaran_hadir_manual_id' => 'Pilih jenis pelanggaran aktif untuk presensi manual.']);
        }
        $pengaturan->update(['pelanggaran_hadir_manual_id' => $jenis->id]);
    }

    public function bekukan(AbsensiSiswa $absensi): array
    {
        if (is_array($absensi->aturan_poin_hadir_manual)) {
            return $absensi->aturan_poin_hadir_manual;
        }
        $snapshot = $this->aturan((int) $absensi->tahun_pelajaran_id) + ['diambil_pada' => now()->toISOString()];
        $absensi->aturan_poin_hadir_manual = $snapshot;

        return $snapshot;
    }
}
