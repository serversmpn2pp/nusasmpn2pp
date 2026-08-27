<?php

namespace App\Services\Mobile;

use App\Models\GuruMataPelajaran;
use App\Models\PertanyaanSurveiPembelajaran;

class SurveiPembelajaranMobileService
{
    public function susun(array $konteks): array
    {
        /** @var GuruMataPelajaran $penugasan */
        $penugasan = $konteks['guruMataPelajaran'];

        return [
            'guru_mata_pelajaran_id' => (int) $penugasan->id,
            'semester' => $konteks['semester'],
            'sudah_diisi' => (bool) $konteks['sudahDiisi'],
            'pembelajaran' => [
                'mata_pelajaran' => [
                    'id' => (int) $penugasan->mataPelajaran?->id,
                    'kode' => $penugasan->mataPelajaran?->kode,
                    'nama' => $penugasan->mataPelajaran?->nama ?? '-',
                ],
                'guru' => [
                    'id' => (int) $penugasan->pegawai?->id,
                    'nama' => $penugasan->pegawai?->nama_lengkap ?? '-',
                ],
                'kelas' => [
                    'id' => (int) $penugasan->kelas?->id,
                    'nama' => $penugasan->kelas?->nama ?? '-',
                    'tingkat' => (int) $penugasan->kelas?->tingkat,
                ],
                'tahun_pelajaran' => [
                    'id' => (int) $penugasan->tahunPelajaran?->id,
                    'nama' => $penugasan->tahunPelajaran?->nama ?? '-',
                ],
            ],
            'pertanyaan' => $konteks['daftarPertanyaan']
                ->map(fn (PertanyaanSurveiPembelajaran $item) => [
                    'id' => (int) $item->id,
                    'kode' => $item->kode,
                    'pernyataan' => $item->pernyataan,
                    'urutan' => (int) $item->urutan,
                ])
                ->values(),
            'pilihan' => collect($konteks['daftarPilihan'])
                ->map(fn (string $label, int $nilai) => [
                    'nilai' => $nilai,
                    'label' => $label,
                ])
                ->values(),
            'keterangan' => 'Jawaban survei digunakan sebagai umpan balik pembelajaran dan tidak memengaruhi nilai Anda.',
        ];
    }
}
