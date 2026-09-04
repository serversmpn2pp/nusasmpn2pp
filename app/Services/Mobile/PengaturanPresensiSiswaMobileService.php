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
        $dataSimpan = $this->dataSimpan($data);
        $this->pastikanUrutanWaktuBenar($dataSimpan);

        return PengaturanAbsensi::create($dataSimpan);
    }

    public function ubah(PengaturanAbsensi $pengaturanAbsensi, array $data): void
    {
        $dataSimpan = $this->dataSimpan($data, $pengaturanAbsensi);
        $this->pastikanUrutanWaktuBenar($dataSimpan);
        $pengaturanAbsensi->update($dataSimpan);
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
            'pulang_jumat_dibedakan' => $item->pulangJumatDibedakan(),
            'jam_scan_pulang_perempuan_mulai' => $item->pulangJumatDibedakan()
                ? $item->formatJam($item->jam_scan_pulang_perempuan_mulai) : null,
            'jam_pulang_perempuan' => $item->pulangJumatDibedakan()
                ? $item->formatJam($item->jam_pulang_perempuan) : null,
            'jam_scan_pulang_perempuan_selesai' => $item->pulangJumatDibedakan()
                ? $item->formatJam($item->jam_scan_pulang_perempuan_selesai) : null,
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
        ];
    }

    private function dataSimpan(array $data, ?PengaturanAbsensi $pengaturanAbsensi = null): array
    {
        $dibedakan = $data['hari'] === 'jumat'
            && (array_key_exists('pulang_jumat_dibedakan', $data)
                ? (bool) $data['pulang_jumat_dibedakan']
                : (bool) $pengaturanAbsensi?->pulang_jumat_dibedakan);

        return [
            'hari' => $data['hari'],
            'urutan_hari' => PengaturanAbsensi::DAFTAR_HARI[$data['hari']]['urutan'],
            'jam_scan_masuk_mulai' => $data['jam_scan_masuk_mulai'],
            'jam_masuk' => $data['jam_masuk'],
            'jam_scan_masuk_selesai' => $data['jam_scan_masuk_selesai'],
            'jam_scan_pulang_mulai' => $data['jam_scan_pulang_mulai'],
            'jam_pulang' => $data['jam_pulang'],
            'jam_scan_pulang_selesai' => $data['jam_scan_pulang_selesai'],
            'pulang_jumat_dibedakan' => $dibedakan,
            'jam_scan_pulang_perempuan_mulai' => $dibedakan
                ? ($data['jam_scan_pulang_perempuan_mulai'] ?? $pengaturanAbsensi?->jam_scan_pulang_perempuan_mulai) : null,
            'jam_pulang_perempuan' => $dibedakan
                ? ($data['jam_pulang_perempuan'] ?? $pengaturanAbsensi?->jam_pulang_perempuan) : null,
            'jam_scan_pulang_perempuan_selesai' => $dibedakan
                ? ($data['jam_scan_pulang_perempuan_selesai'] ?? $pengaturanAbsensi?->jam_scan_pulang_perempuan_selesai) : null,
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

        if (! $data['pulang_jumat_dibedakan']) {
            return;
        }

        $kolomPerempuan = [
            'jam_scan_pulang_perempuan_mulai' => 'Waktu mulai scan pulang siswi wajib diisi.',
            'jam_pulang_perempuan' => 'Jam pulang resmi siswi wajib diisi.',
            'jam_scan_pulang_perempuan_selesai' => 'Waktu tutup scan pulang siswi wajib diisi.',
        ];

        foreach ($kolomPerempuan as $kolom => $pesan) {
            if (blank($data[$kolom] ?? null)) {
                throw ValidationException::withMessages([$kolom => $pesan]);
            }
        }

        if (! $this->berurutan(
            $data['jam_scan_pulang_perempuan_mulai'],
            $data['jam_pulang_perempuan'],
            $data['jam_scan_pulang_perempuan_selesai'],
        )) {
            throw ValidationException::withMessages([
                'jam_pulang_perempuan' => 'Jam pulang resmi siswi harus berada di antara waktu mulai dan tutup scan.',
            ]);
        }

        if ($this->menit($data['jam_scan_pulang_perempuan_mulai']) > $this->menit($data['jam_scan_pulang_mulai'])
            || $this->menit($data['jam_pulang_perempuan']) > $this->menit($data['jam_pulang'])) {
            throw ValidationException::withMessages([
                'jam_pulang_perempuan' => 'Jadwal pulang siswi harus sama atau lebih awal daripada jadwal siswa laki-laki.',
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
