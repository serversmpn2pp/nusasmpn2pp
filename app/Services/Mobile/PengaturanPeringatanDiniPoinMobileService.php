<?php

namespace App\Services\Mobile;

use App\Models\PengaturanPeringatanDiniPoin;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PengaturanPeringatanDiniPoinService;

class PengaturanPeringatanDiniPoinMobileService
{
    public function __construct(private PengaturanPeringatanDiniPoinService $pengaturan) {}

    public function daftar(array $filter): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';

        $items = TahunPelajaran::query()
            ->with(['pengaturanPeringatanDiniPoin.diperbaruiOlehPengguna:id,nama'])
            ->when($cari !== '', function ($query) use ($cari) {
                $query->whereRaw('LOWER(nama) LIKE ?', ['%'.mb_strtolower($cari).'%']);
            })
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->map(fn (TahunPelajaran $tahun) => $this->ringkasTahun($tahun))
            ->filter(fn (array $item) => match ($status) {
                'aktif' => $item['deteksi_aktif'],
                'nonaktif' => ! $item['deteksi_aktif'],
                default => true,
            })
            ->values();

        return [
            'ringkasan' => [
                'jumlah_tahun' => TahunPelajaran::query()->count(),
                'tahun_aktif_id' => TahunPelajaran::query()->where('aktif', true)->value('id'),
                'sudah_diatur' => PengaturanPeringatanDiniPoin::query()->count(),
                'deteksi_aktif' => PengaturanPeringatanDiniPoin::query()->where('aktif', true)->count()
                    + TahunPelajaran::query()->whereDoesntHave('pengaturanPeringatanDiniPoin')->count(),
                'notifikasi_aktif' => PengaturanPeringatanDiniPoin::query()->where('notifikasi_aktif', true)->count()
                    + TahunPelajaran::query()->whereDoesntHave('pengaturanPeringatanDiniPoin')->count(),
            ],
            'filter' => [
                'cari' => $cari,
                'status' => $status,
            ],
            'hak_akses' => ['dapat_kelola' => true],
            'items' => $items,
        ];
    }

    public function simpan(TahunPelajaran $tahun, array $data, ?int $penggunaId): void
    {
        PengaturanPeringatanDiniPoin::updateOrCreate(
            ['tahun_pelajaran_id' => $tahun->id],
            [
                'aktif' => (bool) $data['aktif'],
                'persentase_mendekati_ambang' => (int) $data['persentase_mendekati_ambang'],
                'jumlah_pelanggaran_berulang' => (int) $data['jumlah_pelanggaran_berulang'],
                'periode_pelanggaran_hari' => (int) $data['periode_pelanggaran_hari'],
                'jumlah_keterlambatan_berulang' => (int) $data['jumlah_keterlambatan_berulang'],
                'periode_keterlambatan_hari' => (int) $data['periode_keterlambatan_hari'],
                'notifikasi_aktif' => (bool) $data['notifikasi_aktif'],
                'diperbarui_oleh_pengguna_id' => $penggunaId,
            ],
        );
    }

    public function ringkasTahun(TahunPelajaran $tahun): array
    {
        $tersimpan = $tahun->pengaturanPeringatanDiniPoin;
        $nilai = $tersimpan ?? $this->pengaturan->nilaiUntukTahun((int) $tahun->id);

        return [
            'tahun_pelajaran' => [
                'id' => (int) $tahun->id,
                'nama' => $tahun->nama,
                'tanggal_mulai' => $tahun->tanggal_mulai?->toDateString(),
                'tanggal_selesai' => $tahun->tanggal_selesai?->toDateString(),
                'aktif' => (bool) $tahun->aktif,
            ],
            'tersimpan' => $tersimpan !== null,
            'deteksi_aktif' => (bool) $nilai->aktif,
            'notifikasi_aktif' => (bool) $nilai->notifikasi_aktif,
            'persentase_mendekati_ambang' => (int) $nilai->persentase_mendekati_ambang,
            'jumlah_pelanggaran_berulang' => (int) $nilai->jumlah_pelanggaran_berulang,
            'periode_pelanggaran_hari' => (int) $nilai->periode_pelanggaran_hari,
            'jumlah_keterlambatan_berulang' => (int) $nilai->jumlah_keterlambatan_berulang,
            'periode_keterlambatan_hari' => (int) $nilai->periode_keterlambatan_hari,
            'diperbarui_oleh' => $tersimpan?->diperbaruiOlehPengguna?->nama,
            'diperbarui_pada' => $tersimpan?->updated_at?->toIso8601String(),
        ];
    }
}
