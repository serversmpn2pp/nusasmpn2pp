<?php

namespace App\Services\Mobile;

use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PengaturanPresensiSiswaMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $hari = $filter['hari'] ?? 'semua';
        $status = $filter['status'] ?? 'semua';
        $hariTeratur = PengaturanAbsensi::query()->pluck('id', 'hari');
        $items = PengaturanAbsensi::query()
            ->when($hari !== 'semua', fn (Builder $query) => $query->where('hari', $hari))
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->orderBy('urutan_hari')
            ->orderBy('hari')
            ->get();

        return [
            'items' => $items->map(fn (PengaturanAbsensi $item) => $this->ringkas($item))->values(),
            'ringkasan' => [
                'total' => PengaturanAbsensi::count(),
                'aktif' => PengaturanAbsensi::where('aktif', true)->count(),
                'nonaktif' => PengaturanAbsensi::where('aktif', false)->count(),
                'belum_diatur' => max(count(PengaturanAbsensi::DAFTAR_HARI) - PengaturanAbsensi::count(), 0),
            ],
            'hari' => collect(PengaturanAbsensi::DAFTAR_HARI)
                ->map(fn (array $item, string $kode) => [
                    'kode' => $kode,
                    'label' => $item['label'],
                    'urutan' => $item['urutan'],
                    'sudah_diatur' => $hariTeratur->has($kode),
                ])
                ->values(),
            'filter' => [
                'hari' => $hari,
                'status' => $status,
            ],
            'hak_akses' => [
                'dapat_kelola' => $pengguna->memilikiIzin('absensi.pengaturan_kelola'),
            ],
        ];
    }

    public function tambah(array $data): PengaturanAbsensi
    {
        $this->pastikanUrutanWaktuBenar($data);

        return PengaturanAbsensi::create($this->dataSimpan($data));
    }

    public function ubah(PengaturanAbsensi $pengaturanAbsensi, array $data): void
    {
        $this->pastikanUrutanWaktuBenar($data);
        $pengaturanAbsensi->update($this->dataSimpan($data));
    }

    private function ringkas(PengaturanAbsensi $item): array
    {
        return [
            'id' => (int) $item->id,
            'hari' => $item->hari,
            'hari_label' => $item->labelHari(),
            'urutan_hari' => (int) $item->urutan_hari,
            'jam_scan_masuk_mulai' => $item->formatJam($item->jam_scan_masuk_mulai),
            'jam_masuk' => $item->formatJam($item->jam_masuk),
            'jam_scan_masuk_selesai' => $item->formatJam($item->jam_scan_masuk_selesai),
            'jam_scan_pulang_mulai' => $item->formatJam($item->jam_scan_pulang_mulai),
            'jam_pulang' => $item->formatJam($item->jam_pulang),
            'jam_scan_pulang_selesai' => $item->formatJam($item->jam_scan_pulang_selesai),
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
        ];
    }

    private function dataSimpan(array $data): array
    {
        return [
            'hari' => $data['hari'],
            'urutan_hari' => PengaturanAbsensi::DAFTAR_HARI[$data['hari']]['urutan'],
            'jam_scan_masuk_mulai' => $data['jam_scan_masuk_mulai'],
            'jam_masuk' => $data['jam_masuk'],
            'jam_scan_masuk_selesai' => $data['jam_scan_masuk_selesai'],
            'jam_scan_pulang_mulai' => $data['jam_scan_pulang_mulai'],
            'jam_pulang' => $data['jam_pulang'],
            'jam_scan_pulang_selesai' => $data['jam_scan_pulang_selesai'],
            'aktif' => (bool) $data['aktif'],
            'keterangan' => filled($data['keterangan'] ?? null)
                ? trim($data['keterangan'])
                : null,
        ];
    }

    private function pastikanUrutanWaktuBenar(array $data): void
    {
        if (! $this->berurutan(
            $data['jam_scan_masuk_mulai'],
            $data['jam_masuk'],
            $data['jam_scan_masuk_selesai'],
        )) {
            throw ValidationException::withMessages([
                'jam_masuk' => 'Jam masuk resmi harus berada di antara waktu mulai dan tutup scan masuk.',
            ]);
        }

        if (! $this->berurutan(
            $data['jam_scan_pulang_mulai'],
            $data['jam_pulang'],
            $data['jam_scan_pulang_selesai'],
        )) {
            throw ValidationException::withMessages([
                'jam_pulang' => 'Jam pulang resmi harus berada di antara waktu mulai dan tutup scan pulang.',
            ]);
        }
    }

    private function berurutan(string $mulai, string $resmi, string $selesai): bool
    {
        return $this->menit($mulai) <= $this->menit($resmi)
            && $this->menit($resmi) <= $this->menit($selesai);
    }

    private function menit(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
    }
}
