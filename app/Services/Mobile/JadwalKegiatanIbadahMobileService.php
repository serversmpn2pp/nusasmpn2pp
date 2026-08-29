<?php

namespace App\Services\Mobile;

use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\TahunPelajaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JadwalKegiatanIbadahMobileService
{
    public function daftar(array $filter): array
    {
        $tahunPelajaran = $this->daftarTahunPelajaran();
        $kegiatanIbadah = KegiatanIbadah::query()
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId(
            isset($filter['tahun_pelajaran_id']) ? (int) $filter['tahun_pelajaran_id'] : null,
            $tahunPelajaran,
        );
        $kegiatanIbadahId = $this->ambilKegiatanIbadahId(
            isset($filter['kegiatan_ibadah_id']) ? (int) $filter['kegiatan_ibadah_id'] : null,
            $kegiatanIbadah,
        );

        $jadwal = JadwalKegiatanIbadah::query()
            ->with(['kegiatanIbadah:id,nama,kode,aktif', 'tahunPelajaran:id,nama,aktif'])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when($kegiatanIbadahId, fn ($query) => $query->where('kegiatan_ibadah_id', $kegiatanIbadahId))
            ->orderBy('urutan_hari')
            ->get();

        return [
            'items' => $jadwal->map(fn (JadwalKegiatanIbadah $item) => $this->ringkas($item))->values(),
            'ringkasan' => [
                'jumlah_hari' => count(JadwalKegiatanIbadah::DAFTAR_HARI),
                'sudah_diatur' => $jadwal->count(),
                'aktif' => $jadwal->where('aktif', true)->count(),
            ],
            'filter' => [
                'tahun_pelajaran_id' => $tahunPelajaranId,
                'kegiatan_ibadah_id' => $kegiatanIbadahId,
            ],
            'referensi' => [
                'tahun_pelajaran' => $tahunPelajaran->map(fn (TahunPelajaran $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama,
                    'aktif' => (bool) $item->aktif,
                ])->values(),
                'kegiatan_ibadah' => $kegiatanIbadah->map(fn (KegiatanIbadah $item) => [
                    'id' => (int) $item->id,
                    'kode' => $item->kode,
                    'nama' => $item->nama,
                    'aktif' => (bool) $item->aktif,
                ])->values(),
                'hari' => collect(JadwalKegiatanIbadah::DAFTAR_HARI)
                    ->map(fn (array $item, string $kode) => [
                        'kode' => $kode,
                        'label' => $item['label'],
                        'urutan' => $item['urutan'],
                    ])
                    ->values(),
            ],
        ];
    }

    public function terapkan(array $data): int
    {
        $this->pastikanKegiatanAktif((int) $data['kegiatan_ibadah_id']);
        $this->pastikanUrutanWaktuBenar($data);

        DB::transaction(function () use ($data) {
            foreach ($data['hari'] as $hari) {
                JadwalKegiatanIbadah::query()->updateOrCreate(
                    [
                        'kegiatan_ibadah_id' => $data['kegiatan_ibadah_id'],
                        'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                        'hari' => $hari,
                    ],
                    [
                        'urutan_hari' => JadwalKegiatanIbadah::DAFTAR_HARI[$hari]['urutan'],
                        'jam_scan_mulai' => $data['jam_scan_mulai'],
                        'jam_pelaksanaan' => $data['jam_pelaksanaan'],
                        'jam_scan_selesai' => $data['jam_scan_selesai'],
                        'aktif' => (bool) $data['aktif'],
                        'keterangan' => $this->teks($data['keterangan'] ?? null),
                    ],
                );
            }
        });

        return count($data['hari']);
    }

    public function ubah(JadwalKegiatanIbadah $jadwal, array $data): void
    {
        $this->pastikanUrutanWaktuBenar($data);
        $jadwal->update([
            'jam_scan_mulai' => $data['jam_scan_mulai'],
            'jam_pelaksanaan' => $data['jam_pelaksanaan'],
            'jam_scan_selesai' => $data['jam_scan_selesai'],
            'aktif' => (bool) $data['aktif'],
            'keterangan' => $this->teks($data['keterangan'] ?? null),
        ]);
    }

    public function nonaktifkan(JadwalKegiatanIbadah $jadwal): void
    {
        $jadwal->update(['aktif' => false]);
    }

    public function ringkas(JadwalKegiatanIbadah $item): array
    {
        return [
            'id' => (int) $item->id,
            'kegiatan_ibadah_id' => (int) $item->kegiatan_ibadah_id,
            'tahun_pelajaran_id' => (int) $item->tahun_pelajaran_id,
            'hari' => $item->hari,
            'label_hari' => $item->labelHari(),
            'urutan_hari' => (int) $item->urutan_hari,
            'jam_scan_mulai' => $item->formatJam($item->jam_scan_mulai),
            'jam_pelaksanaan' => $item->formatJam($item->jam_pelaksanaan),
            'jam_scan_selesai' => $item->formatJam($item->jam_scan_selesai),
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
            'kegiatan' => $item->relationLoaded('kegiatanIbadah') ? [
                'nama' => $item->kegiatanIbadah?->nama,
                'kode' => $item->kegiatanIbadah?->kode,
                'aktif' => (bool) $item->kegiatanIbadah?->aktif,
            ] : null,
            'tahun_pelajaran' => $item->relationLoaded('tahunPelajaran') ? [
                'nama' => $item->tahunPelajaran?->nama,
                'aktif' => (bool) $item->tahunPelajaran?->aktif,
            ] : null,
        ];
    }

    private function daftarTahunPelajaran(): Collection
    {
        return TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
    }

    private function ambilTahunPelajaranId(?int $id, Collection $daftar): ?int
    {
        if ($id && $daftar->contains('id', $id)) {
            return $id;
        }

        return $daftar->firstWhere('aktif', true)?->id ?? $daftar->first()?->id;
    }

    private function ambilKegiatanIbadahId(?int $id, Collection $daftar): ?int
    {
        if ($id && $daftar->contains('id', $id)) {
            return $id;
        }

        return $daftar->firstWhere('aktif', true)?->id ?? $daftar->first()?->id;
    }

    private function pastikanKegiatanAktif(int $id): void
    {
        if (! KegiatanIbadah::query()->whereKey($id)->where('aktif', true)->exists()) {
            throw ValidationException::withMessages([
                'kegiatan_ibadah_id' => 'Kegiatan ibadah yang dipilih sudah tidak aktif.',
            ]);
        }
    }

    private function pastikanUrutanWaktuBenar(array $data): void
    {
        $mulai = $this->menit($data['jam_scan_mulai']);
        $pelaksanaan = $this->menit($data['jam_pelaksanaan']);
        $selesai = $this->menit($data['jam_scan_selesai']);

        if ($mulai > $pelaksanaan || $pelaksanaan > $selesai) {
            throw ValidationException::withMessages([
                'jam_pelaksanaan' => 'Waktu pelaksanaan harus berada di antara mulai dan batas akhir scan.',
            ]);
        }
    }

    private function menit(string $jam): int
    {
        [$jamAngka, $menit] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($jamAngka * 60) + $menit;
    }

    private function teks(mixed $nilai): ?string
    {
        return filled($nilai) ? trim((string) $nilai) : null;
    }
}
