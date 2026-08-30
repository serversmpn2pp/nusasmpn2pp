<?php

namespace App\Services\Mobile;

use App\Models\PengaturanBatasProsesPelanggaran;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PengaturanBatasProsesPelanggaranService;

class PengaturanBatasProsesPelanggaranMobileService
{
    public function __construct(private PengaturanBatasProsesPelanggaranService $pengaturan) {}

    public function daftar(array $filter): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';

        $items = TahunPelajaran::query()
            ->with(['pengaturanBatasProsesPelanggaran.diperbaruiOlehPengguna:id,nama'])
            ->when($cari !== '', function ($query) use ($cari) {
                $query->whereRaw('LOWER(nama) LIKE ?', ['%'.mb_strtolower($cari).'%']);
            })
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->map(fn (TahunPelajaran $tahun) => $this->ringkasTahun($tahun))
            ->filter(fn (array $item) => match ($status) {
                'diatur' => $item['tersimpan'],
                'bawaan' => ! $item['tersimpan'],
                default => true,
            })
            ->values();

        $jumlahTahun = TahunPelajaran::query()->count();
        $sudahDiatur = PengaturanBatasProsesPelanggaran::query()->count();

        return [
            'ringkasan' => [
                'jumlah_tahun' => $jumlahTahun,
                'tahun_aktif_id' => TahunPelajaran::query()->where('aktif', true)->value('id'),
                'sudah_diatur' => $sudahDiatur,
                'memakai_bawaan' => max(0, $jumlahTahun - $sudahDiatur),
                'pengingat_aktif' => PengaturanBatasProsesPelanggaran::query()
                    ->where('notifikasi_pengingat_aktif', true)->count()
                    + TahunPelajaran::query()->whereDoesntHave('pengaturanBatasProsesPelanggaran')->count(),
                'terlambat_aktif' => PengaturanBatasProsesPelanggaran::query()
                    ->where('notifikasi_terlambat_aktif', true)->count()
                    + TahunPelajaran::query()->whereDoesntHave('pengaturanBatasProsesPelanggaran')->count(),
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
        PengaturanBatasProsesPelanggaran::updateOrCreate(
            ['tahun_pelajaran_id' => $tahun->id],
            [
                'batas_hari_pemeriksaan_bk' => (int) $data['batas_hari_pemeriksaan_bk'],
                'batas_hari_persetujuan' => (int) $data['batas_hari_persetujuan'],
                'pengingat_hari_sebelum_batas' => (int) $data['pengingat_hari_sebelum_batas'],
                'notifikasi_pengingat_aktif' => (bool) $data['notifikasi_pengingat_aktif'],
                'notifikasi_terlambat_aktif' => (bool) $data['notifikasi_terlambat_aktif'],
                'diperbarui_oleh_pengguna_id' => $penggunaId,
            ],
        );
    }

    public function ringkasTahun(TahunPelajaran $tahun): array
    {
        $tersimpan = $tahun->pengaturanBatasProsesPelanggaran;
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
            'batas_hari_pemeriksaan_bk' => (int) $nilai->batas_hari_pemeriksaan_bk,
            'batas_hari_persetujuan' => (int) $nilai->batas_hari_persetujuan,
            'pengingat_hari_sebelum_batas' => (int) $nilai->pengingat_hari_sebelum_batas,
            'notifikasi_pengingat_aktif' => (bool) $nilai->notifikasi_pengingat_aktif,
            'notifikasi_terlambat_aktif' => (bool) $nilai->notifikasi_terlambat_aktif,
            'diperbarui_oleh' => $tersimpan?->diperbaruiOlehPengguna?->nama,
            'diperbarui_pada' => $tersimpan?->updated_at?->toIso8601String(),
        ];
    }
}
