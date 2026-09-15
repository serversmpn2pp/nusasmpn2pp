<?php

namespace App\Services\Cbt;

use App\Models\KomponenNilai;

class SiapkanKomponenNilaiAsesmen
{
    public function jalankan(
        int $guruMataPelajaranId,
        string $semester,
        string $namaAsesmen,
        string $tanggalMulai,
    ): KomponenNilai {
        $atributUnik = [
            'guru_mata_pelajaran_id' => $guruMataPelajaranId,
            'semester' => $semester,
            'jenis_komponen' => 'sumatif',
            'nama' => $namaAsesmen,
        ];
        $urutan = KomponenNilai::query()
            ->where('guru_mata_pelajaran_id', $guruMataPelajaranId)
            ->where('semester', $semester)
            ->max('urutan') ?? 0;

        $komponen = KomponenNilai::query()->firstOrCreate($atributUnik, [
            'tanggal_penilaian' => substr($tanggalMulai, 0, 10),
            'urutan' => $urutan + 1,
            'aktif' => true,
            'keterangan' => 'Dibuat otomatis dari Asesmen Kelas CBT.',
        ]);

        if (! $komponen->aktif) {
            $komponen->update(['aktif' => true]);
        }

        return $komponen;
    }
}
