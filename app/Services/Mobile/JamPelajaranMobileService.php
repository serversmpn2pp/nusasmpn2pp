<?php

namespace App\Services\Mobile;

use App\Models\JamPelajaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JamPelajaranMobileService
{
    public function daftar(array $filter): array
    {
        $hari = $filter['hari'] ?? 'semua';
        $status = $filter['status'] ?? 'semua';
        $query = JamPelajaran::query()
            ->withCount(['jadwalPelajaran' => fn ($query) => $query->where('aktif', true)]);
        $items = $query
            ->when($hari !== 'semua', fn ($query) => $query->where('hari', $hari))
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy('nomor_jam')
            ->get();

        return [
            'items' => $items->map(fn (JamPelajaran $item) => $this->ringkas($item))->values(),
            'ringkasan' => [
                'total' => JamPelajaran::count(),
                'aktif' => JamPelajaran::where('aktif', true)->count(),
                'nonaktif' => JamPelajaran::where('aktif', false)->count(),
            ],
            'hari' => collect(JamPelajaran::DAFTAR_HARI)
                ->map(fn (string $label, string $kode) => ['kode' => $kode, 'label' => $label])
                ->values(),
            'jenis' => collect(JamPelajaran::DAFTAR_JENIS)
                ->map(fn (string $label, string $kode) => ['kode' => $kode, 'label' => $label])
                ->values(),
            'filter' => ['hari' => $hari, 'status' => $status],
        ];
    }

    public function tambah(array $data): array
    {
        $this->pastikanJamValid($data);
        $daftarHari = collect($data['hari'])->unique()->values();
        $posisi = $data['posisi_sisip'] ?? 'akhir';
        $dataJam = collect($data)
            ->except(['hari', 'posisi_sisip'])
            ->all();

        return DB::transaction(function () use ($daftarHari, $posisi, $dataJam) {
            $ids = [];
            $jumlahDigeser = 0;

            foreach ($daftarHari as $hari) {
                $slotHari = JamPelajaran::query()
                    ->where('hari', $hari)
                    ->orderByDesc('nomor_jam')
                    ->lockForUpdate()
                    ->get();
                $nomorMaksimal = (int) ($slotHari->max('nomor_jam') ?? 0);
                $nomorSisip = $this->nomorSisip($posisi, $nomorMaksimal);
                $slotDigeser = $slotHari
                    ->where('nomor_jam', '>=', $nomorSisip)
                    ->sortByDesc('nomor_jam');

                if ($slotDigeser->isNotEmpty() && $nomorMaksimal >= 20) {
                    throw ValidationException::withMessages([
                        'posisi_sisip' => "Urutan {$hari} sudah mencapai batas 20 slot.",
                    ]);
                }

                foreach ($slotDigeser as $slot) {
                    $nomorBaru = $slot->nomor_jam + 1;
                    $slot->update([
                        'nomor_jam' => $nomorBaru,
                        'label' => $this->labelSetelahPergeseran($slot->label, $nomorBaru),
                    ]);
                    $jumlahDigeser++;
                }

                $baru = JamPelajaran::create([
                    ...$dataJam,
                    'hari' => $hari,
                    'nomor_jam' => $nomorSisip,
                ]);
                $ids[] = (int) $baru->id;
            }

            return [
                'ids' => $ids,
                'jumlah_baru' => count($ids),
                'jumlah_digeser' => $jumlahDigeser,
            ];
        });
    }

    public function ubah(JamPelajaran $jamPelajaran, array $data): void
    {
        $this->pastikanJamValid($data);
        $jamPelajaran->update($data);
    }

    private function ringkas(JamPelajaran $item): array
    {
        return [
            'id' => (int) $item->id,
            'hari' => $item->hari,
            'hari_label' => $item->labelHari(),
            'nomor_jam' => (int) $item->nomor_jam,
            'label' => $item->label,
            'jam_mulai' => $item->formatJam($item->jam_mulai),
            'jam_selesai' => $item->formatJam($item->jam_selesai),
            'jenis' => $item->jenis,
            'jenis_label' => $item->labelJenis(),
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
            'jumlah_jadwal_aktif' => (int) ($item->jadwal_pelajaran_count ?? 0),
        ];
    }

    private function nomorSisip(string $posisi, int $nomorMaksimal): int
    {
        if ($nomorMaksimal >= 20) {
            throw ValidationException::withMessages([
                'posisi_sisip' => 'Urutan jam sudah mencapai batas 20 slot.',
            ]);
        }

        if ($posisi === 'awal') {
            return 1;
        }

        if ($posisi === 'akhir') {
            return $nomorMaksimal + 1;
        }

        $nomorSetelah = (int) str($posisi)->after('setelah:')->toString();

        return min(max(1, $nomorSetelah + 1), $nomorMaksimal + 1);
    }

    private function labelSetelahPergeseran(?string $label, int $nomorBaru): ?string
    {
        if (! $label) {
            return $label;
        }

        if (preg_match('/^Jam ke-\d+$/i', $label)) {
            return "Jam ke-{$nomorBaru}";
        }

        if (preg_match('/^Jam \d+$/i', $label)) {
            return "Jam {$nomorBaru}";
        }

        return $label;
    }

    private function pastikanJamValid(array $data): void
    {
        if ($this->menit($data['jam_selesai']) <= $this->menit($data['jam_mulai'])) {
            throw ValidationException::withMessages([
                'jam_selesai' => 'Jam selesai harus lebih besar dari jam mulai.',
            ]);
        }
    }

    private function menit(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
    }
}
