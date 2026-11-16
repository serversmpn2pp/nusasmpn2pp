<?php

namespace App\Services\Cbt;

use App\Models\FolderSoalCbt;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SiapkanFolderSoalUjianTerpusat
{
    public function mendukung(KegiatanUjianCbt $kegiatan): bool
    {
        return $kegiatan->status !== 'nonaktif' && $kegiatan->jenisUjianCbt?->kode !== PaketSimulasiCbt::KODE;
    }

    public function untukJadwal(KegiatanUjianCbt $kegiatan, JadwalUjianCbt $jadwal): void
    {
        if ($jadwal->status !== 'dibatalkan' && $this->mendukung($kegiatan)) {
            $this->buatJikaBelumAda($kegiatan, $jadwal->mata_pelajaran_id, $jadwal->tingkat);
        }
    }

    public function lengkapi(KegiatanUjianCbt $kegiatan): int
    {
        if (! $this->mendukung($kegiatan)) {
            return 0;
        }

        return DB::transaction(fn () => $kegiatan->jadwalUjianCbt()
            ->where('status', '!=', 'dibatalkan')
            ->whereNotNull('mata_pelajaran_id')
            ->whereIn('tingkat', [7, 8, 9])
            ->distinct()
            ->orderBy('mata_pelajaran_id')
            ->orderBy('tingkat')
            ->get(['mata_pelajaran_id', 'tingkat'])
            ->filter(fn (JadwalUjianCbt $jadwal) => $this->buatJikaBelumAda($kegiatan, $jadwal->mata_pelajaran_id, $jadwal->tingkat)->wasRecentlyCreated)
            ->count());
    }

    private function buatJikaBelumAda(KegiatanUjianCbt $kegiatan, int $mapelId, int $tingkat): FolderSoalCbt
    {
        $konteks = ['mata_pelajaran_id' => $mapelId, 'tingkat' => $tingkat];
        $identitas = ['kegiatan_ujian_cbt_id' => $kegiatan->id, ...$konteks];
        $judul = trim($kegiatan->nama) ?: 'Ujian terpusat';

        for ($urutan = 0; ; $urutan++) {
            $penanda = match ($urutan) {
                0 => '',
                1 => " ({$kegiatan->kode})",
                default => " ({$kegiatan->kode}-{$urutan})",
            };
            $nama = rtrim(mb_substr($judul, 0, 120 - mb_strlen($penanda))).$penanda;

            try {
                return FolderSoalCbt::query()->firstOrCreate($identitas, [
                    'nama' => $nama,
                    'keterangan' => "Ujian terpusat: {$kegiatan->nama} ({$kegiatan->kode}).",
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // Folder lain dengan nama yang sama tidak boleh diambil alih.
                if (! FolderSoalCbt::query()->where([...$konteks, 'nama' => $nama])->exists()) {
                    throw $e;
                }
            }
        }
    }
}
