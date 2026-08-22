<?php

namespace App\Services\Cbt;

use App\Models\AnggotaKelas;
use App\Models\UjianCbt;

class SinkronkanPesertaAsesmenKelas
{
    public function jalankan(UjianCbt $ujianCbt, ?int $penggunaId = null): int
    {
        $kelasUjian = $ujianCbt->kelasUjianCbt()->get();
        $anggotaValid = collect();

        foreach ($kelasUjian as $kelas) {
            $anggota = AnggotaKelas::query()
                ->where('tahun_pelajaran_id', $ujianCbt->tahun_pelajaran_id)
                ->where('kelas_id', $kelas->kelas_id)
                ->where('status_keanggotaan', 'aktif')
                ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
                ->get();

            foreach ($anggota as $item) {
                $anggotaValid->push($item->id);

                $peserta = $ujianCbt->pesertaUjianCbt()->firstOrCreate(
                    ['anggota_kelas_id' => $item->id],
                    [
                        'kelas_ujian_cbt_id' => $kelas->id,
                        'sesi_ujian_cbt_id' => null,
                        'nomor_peserta' => sprintf('AK-%d-%d', $ujianCbt->id, $item->id),
                        'status' => 'aktif',
                        'menit_tersisa' => $ujianCbt->durasi_menit,
                        'dibuat_oleh_pengguna_id' => $penggunaId,
                    ],
                );

                if (! $peserta->wasRecentlyCreated && (int) $peserta->kelas_ujian_cbt_id !== (int) $kelas->id) {
                    $peserta->update(['kelas_ujian_cbt_id' => $kelas->id]);
                }
            }
        }

        $ujianCbt->pesertaUjianCbt()
            ->when(
                $anggotaValid->isNotEmpty(),
                fn ($query) => $query->whereNotIn('anggota_kelas_id', $anggotaValid->unique()),
                fn ($query) => $query,
            )
            ->delete();

        return $ujianCbt->pesertaUjianCbt()->count();
    }
}
