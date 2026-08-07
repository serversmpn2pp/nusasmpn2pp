<?php

namespace App\Services\Notifikasi;

use App\Models\NotifikasiPengguna;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class NotifikasiPenggunaService
{
    public function kirim(
        Pengguna|int $pengguna,
        string $jenis,
        string $judul,
        string $pesan,
        ?string $tautan = null,
        ?string $kunciUnik = null,
        array $dataTambahan = [],
    ): ?NotifikasiPengguna {
        $pengguna = $pengguna instanceof Pengguna
            ? $pengguna
            : Pengguna::query()->find($pengguna);

        if (! $pengguna?->aktif) {
            return null;
        }

        $data = [
            'jenis' => array_key_exists($jenis, NotifikasiPengguna::DAFTAR_JENIS) ? $jenis : 'informasi',
            'judul' => $judul,
            'pesan' => $pesan,
            'tautan' => $this->rapikanTautan($tautan),
            'data_tambahan' => $dataTambahan ?: null,
        ];

        if ($kunciUnik) {
            return NotifikasiPengguna::query()->firstOrCreate(
                ['pengguna_id' => $pengguna->id, 'kunci_unik' => $kunciUnik],
                $data,
            );
        }

        return $pengguna->notifikasiPengguna()->create($data);
    }

    public function kirimKeBanyak(
        iterable $daftarPengguna,
        string $jenis,
        string $judul,
        string $pesan,
        ?string $tautan = null,
        ?string $kunciUnik = null,
        array $dataTambahan = [],
    ): Collection {
        return collect($daftarPengguna)
            ->map(fn (Pengguna|int $pengguna) => $this->kirim(
                $pengguna,
                $jenis,
                $judul,
                $pesan,
                $tautan,
                $kunciUnik,
                $dataTambahan,
            ))
            ->filter()
            ->values();
    }

    public function penggunaDenganPeran(string|array $kodePeran, ?int $kecualiPenggunaId = null): EloquentCollection
    {
        $kodePeran = (array) $kodePeran;

        return Pengguna::query()
            ->where('aktif', true)
            ->when($kecualiPenggunaId, fn ($query) => $query->where('id', '<>', $kecualiPenggunaId))
            ->where(function ($query) use ($kodePeran) {
                $query->whereIn('peran', $kodePeran)
                    ->orWhereHas('daftarPeran', fn ($query) => $query
                        ->whereIn('kode', $kodePeran)
                        ->where('aktif', true));
            })
            ->distinct()
            ->get();
    }

    public function penggunaDenganIzin(string|array $kodeIzin, ?int $kecualiPenggunaId = null): EloquentCollection
    {
        $kodeIzin = (array) $kodeIzin;

        return Pengguna::query()
            ->where('aktif', true)
            ->when($kecualiPenggunaId, fn ($query) => $query->where('id', '<>', $kecualiPenggunaId))
            ->where(function ($query) use ($kodeIzin) {
                $query->where('akun_sistem', true)
                    ->orWhere('peran', 'administrator')
                    ->orWhereHas('daftarPeran', fn ($query) => $query
                        ->where('peran.aktif', true)
                        ->whereHas('izin', fn ($query) => $query
                            ->whereIn('izin.kode', $kodeIzin)
                            ->where('izin.aktif', true)));
            })
            ->distinct()
            ->get();
    }

    public function penggunaUntukPegawai(int $pegawaiId): EloquentCollection
    {
        return Pengguna::query()
            ->where('pegawai_id', $pegawaiId)
            ->where('aktif', true)
            ->get();
    }

    public function penggunaUntukSiswa(int $siswaId): EloquentCollection
    {
        return Pengguna::query()
            ->where('siswa_id', $siswaId)
            ->where('aktif', true)
            ->get();
    }

    public function penggunaUntukDaftarSiswa(iterable $siswaIds): EloquentCollection
    {
        $siswaIds = collect($siswaIds)
            ->filter(fn ($siswaId) => is_numeric($siswaId) && (int) $siswaId > 0)
            ->map(fn ($siswaId) => (int) $siswaId)
            ->unique()
            ->values()
            ->all();

        return Pengguna::query()
            ->whereIn('siswa_id', $siswaIds)
            ->where('aktif', true)
            ->get();
    }

    private function rapikanTautan(?string $tautan): ?string
    {
        if (blank($tautan)) {
            return null;
        }

        $bagian = parse_url($tautan);
        $path = $bagian['path'] ?? '/';
        $query = isset($bagian['query']) ? '?'.$bagian['query'] : '';
        $fragment = isset($bagian['fragment']) ? '#'.$bagian['fragment'] : '';

        return str_starts_with($path, '/') ? $path.$query.$fragment : '/'.$path.$query.$fragment;
    }
}
