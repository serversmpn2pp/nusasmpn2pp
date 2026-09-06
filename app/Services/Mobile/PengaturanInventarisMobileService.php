<?php

namespace App\Services\Mobile;

use App\Models\PengaturanInventaris;

class PengaturanInventarisMobileService
{
    public function ambil(): array
    {
        return $this->ringkas(
            PengaturanInventaris::utama()->load('diperbaruiOleh:id,nama'),
        );
    }

    public function simpan(array $data, int $penggunaId): array
    {
        $pengaturan = PengaturanInventaris::utama();
        $pengaturan->update([
            'awalan_nomor_aset' => trim($data['awalan_nomor_aset'], '.'),
            'akhiran_nomor_aset' => trim($data['akhiran_nomor_aset'], '.'),
            'nama_pemilik' => trim($data['nama_pemilik']),
            'jumlah_digit_id_internal' => (int) $data['jumlah_digit_id_internal'],
            'diperbarui_oleh_pengguna_id' => $penggunaId,
        ]);

        return $this->ringkas($pengaturan->refresh()->load('diperbaruiOleh:id,nama'));
    }

    public function ringkas(PengaturanInventaris $pengaturan): array
    {
        $tahun = (int) now()->format('Y');
        $urutan = str_pad('1', $pengaturan->jumlah_digit_id_internal, '0', STR_PAD_LEFT);

        return [
            'id' => (int) $pengaturan->id,
            'kode' => $pengaturan->kode,
            'awalan_nomor_aset' => $pengaturan->awalan_nomor_aset,
            'akhiran_nomor_aset' => $pengaturan->akhiran_nomor_aset,
            'nama_pemilik' => $pengaturan->nama_pemilik,
            'jumlah_digit_id_internal' => (int) $pengaturan->jumlah_digit_id_internal,
            'tahun_contoh' => $tahun,
            'contoh_nomor_aset' => $pengaturan->contohNomorAset($tahun),
            'contoh_kode_barang_habis_pakai' => 'BHP-'.$urutan,
            'contoh_kode_unit_aset' => 'AST-'.$tahun.'-'.$urutan,
            'diperbarui_oleh' => $pengaturan->diperbaruiOleh?->nama,
            'diperbarui_pada' => $pengaturan->updated_at?->toIso8601String(),
            'hak_akses' => [
                'dapat_kelola' => true,
            ],
        ];
    }
}
