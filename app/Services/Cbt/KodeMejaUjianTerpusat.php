<?php

namespace App\Services\Cbt;

use App\Models\KegiatanUjianCbt;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\SesiKegiatanUjianCbt;

class KodeMejaUjianTerpusat
{
    public function buat(
        KegiatanUjianCbt $kegiatan,
        SesiKegiatanUjianCbt $sesi,
        RuangKegiatanUjianCbt $ruang,
        int $nomorMeja,
    ): string {
        $kegiatan->loadMissing(['jenisUjianCbt', 'tahunPelajaran']);

        return sprintf(
            '%s-%s-%s-S%02d-R%02d-M%03d',
            $this->kodeJenis($kegiatan),
            $this->kodeTahun((string) $kegiatan->tahunPelajaran?->nama),
            $kegiatan->semester === 'ganjil' ? '01' : ($kegiatan->semester === 'genap' ? '02' : '00'),
            max(1, (int) $sesi->urutan),
            max(1, (int) $ruang->urutan),
            max(1, $nomorMeja),
        );
    }

    public function sinkronkanKelompok(KelompokPesertaKegiatanUjianCbt $kelompok): bool
    {
        $kelompok->loadMissing([
            'kegiatanUjianCbt.jenisUjianCbt',
            'kegiatanUjianCbt.tahunPelajaran',
            'sesiKegiatanUjianCbt',
            'penempatanPesertaUjianCbt.ruangKegiatanUjianCbt',
        ]);

        $kegiatan = $kelompok->kegiatanUjianCbt;
        $sesi = $kelompok->sesiKegiatanUjianCbt;
        if (! $kegiatan || ! $sesi) {
            return false;
        }

        $berubah = false;
        foreach ($kelompok->penempatanPesertaUjianCbt as $penempatan) {
            if (! $penempatan->ruangKegiatanUjianCbt) {
                continue;
            }

            $kode = $this->buat(
                $kegiatan,
                $sesi,
                $penempatan->ruangKegiatanUjianCbt,
                (int) $penempatan->nomor_meja,
            );

            if ($penempatan->kode_meja !== $kode) {
                $penempatan->update(['kode_meja' => $kode]);
                $berubah = true;
            }
        }

        return $berubah;
    }

    private function kodeJenis(KegiatanUjianCbt $kegiatan): string
    {
        $kode = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($kegiatan->jenisUjianCbt?->kode ?: $kegiatan->kode)));

        return $kode ?: 'UJI';
    }

    private function kodeTahun(string $namaTahun): string
    {
        preg_match_all('/\d{4}/', $namaTahun, $tahun);
        if (count($tahun[0]) >= 2) {
            return substr($tahun[0][0], -2).substr($tahun[0][1], -2);
        }

        $angka = preg_replace('/\D/', '', $namaTahun);

        return strlen($angka) >= 4 ? substr($angka, 0, 4) : str_pad($angka, 4, '0');
    }
}
