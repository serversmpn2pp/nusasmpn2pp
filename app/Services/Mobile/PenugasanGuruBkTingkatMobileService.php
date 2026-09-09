<?php

namespace App\Services\Mobile;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruBkTingkat;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PenugasanGuruBkTingkatService;
use Illuminate\Support\Facades\DB;

class PenugasanGuruBkTingkatMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $daftarTahun = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'nama', 'aktif']);
        $tahunDipilih = $daftarTahun
            ->firstWhere('id', (int) ($filter['tahun_pelajaran_id'] ?? 0))
            ?: $daftarTahun->firstWhere('aktif', true)
            ?: $daftarTahun->first();

        $penugasan = PenugasanGuruBkTingkat::query()
            ->with('pegawai:id,nama_lengkap,nip,jabatan_utama')
            ->when(
                $tahunDipilih,
                fn ($query) => $query->where('tahun_pelajaran_id', $tahunDipilih->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('aktif', true)
            ->orderBy('tingkat')
            ->orderBy('pegawai_id')
            ->get();
        $guruBk = Pegawai::query()
            ->where('aktif', true)
            ->whereHas('pengguna', fn ($query) => $query
                ->where('aktif', true)
                ->where(function ($query) {
                    $query->where('peran', 'bk')
                        ->orWhereHas('daftarPeran', fn ($query) => $query
                            ->where('kode', 'bk')
                            ->where('aktif', true));
                }))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip', 'jabatan_utama']);

        return [
            'tahun_pelajaran' => $daftarTahun->map(fn (TahunPelajaran $tahun) => [
                'id' => (int) $tahun->id,
                'nama' => $tahun->nama,
                'aktif' => (bool) $tahun->aktif,
            ])->values(),
            'tahun_pelajaran_dipilih' => $tahunDipilih ? [
                'id' => (int) $tahunDipilih->id,
                'nama' => $tahunDipilih->nama,
                'aktif' => (bool) $tahunDipilih->aktif,
            ] : null,
            'tingkat' => collect(PenugasanGuruBkTingkatService::DAFTAR_TINGKAT)
                ->map(fn (string $label, int $tingkat) => [
                    'tingkat' => $tingkat,
                    'label' => $label,
                    'penugasan' => $penugasan
                        ->where('tingkat', $tingkat)
                        ->map(fn (PenugasanGuruBkTingkat $item) => $this->ringkas($item))
                        ->values(),
                ])->values(),
            'guru_bk' => $guruBk->map(fn (Pegawai $pegawai) => [
                'id' => (int) $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nip' => $pegawai->nip,
                'jabatan' => $pegawai->jabatan_utama,
                'tingkat_aktif' => $penugasan
                    ->where('pegawai_id', $pegawai->id)
                    ->pluck('tingkat')
                    ->map(fn ($tingkat) => (int) $tingkat)
                    ->values(),
            ])->values(),
            'ringkasan' => [
                'jumlah_penugasan' => $penugasan->count(),
                'jumlah_guru_bk' => $penugasan->pluck('pegawai_id')->unique()->count(),
                'tingkat_terisi' => $penugasan->pluck('tingkat')->unique()->count(),
                'pembagian_aktif' => $penugasan->isNotEmpty(),
            ],
            'hak_akses' => [
                'dapat_kelola' => $pengguna->memilikiIzin('bk.penugasan_tingkat_kelola'),
            ],
        ];
    }

    public function simpan(Pengguna $pengguna, array $data): array
    {
        $this->pastikanGuruBk((int) $data['pegawai_id']);

        $hasil = DB::transaction(function () use ($pengguna, $data) {
            return collect($data['tingkat'])->map(function ($tingkat) use ($pengguna, $data) {
                return PenugasanGuruBkTingkat::query()->updateOrCreate(
                    [
                        'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                        'pegawai_id' => $data['pegawai_id'],
                        'tingkat' => $tingkat,
                    ],
                    [
                        'tanggal_mulai' => now()->toDateString(),
                        'tanggal_selesai' => null,
                        'aktif' => true,
                        'dibuat_oleh_pengguna_id' => $pengguna->id,
                    ],
                );
            });
        });

        return [
            'jumlah' => $hasil->count(),
            'penugasan' => $hasil->map(fn (PenugasanGuruBkTingkat $item) => $this->ringkas(
                $item->loadMissing('pegawai:id,nama_lengkap,nip,jabatan_utama'),
            ))->values(),
        ];
    }

    public function akhiri(PenugasanGuruBkTingkat $penugasan): array
    {
        $penugasan->update([
            'aktif' => false,
            'tanggal_selesai' => now()->toDateString(),
        ]);

        return $this->ringkas(
            $penugasan->refresh()->loadMissing('pegawai:id,nama_lengkap,nip,jabatan_utama'),
        );
    }

    private function pastikanGuruBk(int $pegawaiId): void
    {
        $valid = Pegawai::query()
            ->whereKey($pegawaiId)
            ->where('aktif', true)
            ->whereHas('pengguna', fn ($query) => $query
                ->where('aktif', true)
                ->where(function ($query) {
                    $query->where('peran', 'bk')
                        ->orWhereHas('daftarPeran', fn ($query) => $query
                            ->where('kode', 'bk')
                            ->where('aktif', true));
                }))
            ->exists();

        abort_unless($valid, 422, 'Pegawai yang dipilih harus memiliki akun aktif dengan role Guru BK.');
    }

    private function ringkas(PenugasanGuruBkTingkat $item): array
    {
        return [
            'id' => (int) $item->id,
            'tingkat' => (int) $item->tingkat,
            'guru_bk' => [
                'id' => $item->pegawai ? (int) $item->pegawai->id : null,
                'nama' => $item->pegawai?->nama_lengkap ?? '-',
                'nip' => $item->pegawai?->nip,
                'jabatan' => $item->pegawai?->jabatan_utama,
            ],
            'tanggal_mulai' => $item->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $item->tanggal_selesai?->toDateString(),
            'aktif' => (bool) $item->aktif,
        ];
    }
}
