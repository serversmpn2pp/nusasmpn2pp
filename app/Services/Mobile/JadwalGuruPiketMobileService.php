<?php

namespace App\Services\Mobile;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JadwalGuruPiketMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tahun = $this->daftarTahunPelajaran();
        $tahunId = filled($filter['tahun_pelajaran_id'] ?? null)
            ? (int) $filter['tahun_pelajaran_id']
            : (int) ($tahun->firstWhere('aktif', true)?->id ?? $tahun->first()?->id ?? 0);
        $hari = $filter['hari'] ?? 'semua';
        $status = $filter['status'] ?? 'semua';
        $cari = trim((string) ($filter['cari'] ?? ''));
        $dasar = JadwalPiketGuru::query()->where('tahun_pelajaran_id', $tahunId);
        $jadwal = (clone $dasar)
            ->with(['pegawai:id,nama_lengkap,nip', 'tahunPelajaran:id,nama,aktif'])
            ->when($hari !== 'semua', fn (Builder $query) => $query->where('hari', $hari))
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->whereHas('pegawai', fn (Builder $query) => $query
                    ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                    ->orWhereRaw("LOWER(COALESCE(nip, '')) LIKE ?", [$pola]));
            })
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy(
                Pegawai::select('nama_lengkap')
                    ->whereColumn('pegawai.id', 'jadwal_piket_guru.pegawai_id')
                    ->limit(1),
            )
            ->get();

        return [
            'items' => $jadwal->map(fn (JadwalPiketGuru $item) => $this->ringkas($item))->values(),
            'ringkasan' => [
                'jadwal_aktif' => (clone $dasar)->where('aktif', true)->count(),
                'jumlah_guru' => (clone $dasar)->where('aktif', true)->distinct('pegawai_id')->count('pegawai_id'),
                'hari_terisi' => (clone $dasar)->where('aktif', true)->distinct('hari')->count('hari'),
            ],
            'tahun_pelajaran' => $tahun->map(fn (TahunPelajaran $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'aktif' => (bool) $item->aktif,
            ])->values(),
            'hari' => collect(JadwalPiketGuru::DAFTAR_HARI)->map(fn ($label, $kode) => [
                'kode' => $kode,
                'label' => $label,
            ])->values(),
            'filter' => [
                'tahun_pelajaran_id' => $tahunId ?: null,
                'hari' => $hari,
                'status' => $status,
                'cari' => $cari,
            ],
            'hak_akses' => ['dapat_kelola' => $pengguna->memilikiIzin('piket_guru.kelola')],
        ];
    }

    public function referensi(?int $tahunPelajaranId): array
    {
        $tahun = $this->daftarTahunPelajaran();
        $tahunPelajaranId ??= $tahun->firstWhere('aktif', true)?->id ?? $tahun->first()?->id;

        return [
            'tahun_pelajaran' => $tahun->map(fn (TahunPelajaran $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'aktif' => (bool) $item->aktif,
            ])->values(),
            'guru' => $this->daftarGuruMapel($tahunPelajaranId)->map(fn (Pegawai $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama_lengkap,
                'nip' => $item->nip,
            ])->values(),
            'hari' => collect(JadwalPiketGuru::DAFTAR_HARI)->map(fn ($label, $kode) => [
                'kode' => $kode,
                'label' => $label,
            ])->values(),
            'tahun_pelajaran_id' => $tahunPelajaranId ? (int) $tahunPelajaranId : null,
        ];
    }

    public function tambah(array $data): int
    {
        $pegawaiIds = collect($data['pegawai_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $this->pastikanGuruMapel($pegawaiIds, (int) $data['tahun_pelajaran_id']);

        DB::transaction(function () use ($data, $pegawaiIds) {
            foreach ($pegawaiIds as $pegawaiId) {
                JadwalPiketGuru::query()->updateOrCreate([
                    'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                    'hari' => $data['hari'],
                    'pegawai_id' => $pegawaiId,
                ], [
                    'aktif' => $data['aktif'],
                    'keterangan' => $data['keterangan'] ?? null,
                ]);
            }
        });

        return $pegawaiIds->count();
    }

    public function ubah(JadwalPiketGuru $jadwal, array $data): void
    {
        $this->pastikanGuruMapel(collect([(int) $data['pegawai_id']]), (int) $data['tahun_pelajaran_id']);
        $duplikat = JadwalPiketGuru::query()
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->where('hari', $data['hari'])
            ->where('pegawai_id', $data['pegawai_id'])
            ->whereKeyNot($jadwal->id)
            ->exists();

        if ($duplikat) {
            throw ValidationException::withMessages([
                'pegawai_id' => 'Guru tersebut sudah terdaftar pada hari yang dipilih.',
            ]);
        }

        $jadwal->update($data);
    }

    private function ringkas(JadwalPiketGuru $item): array
    {
        return [
            'id' => (int) $item->id,
            'tahun_pelajaran' => $item->tahunPelajaran ? [
                'id' => (int) $item->tahunPelajaran->id,
                'nama' => $item->tahunPelajaran->nama,
                'aktif' => (bool) $item->tahunPelajaran->aktif,
            ] : null,
            'pegawai' => $item->pegawai ? [
                'id' => (int) $item->pegawai->id,
                'nama' => $item->pegawai->nama_lengkap,
                'nip' => $item->pegawai->nip,
            ] : null,
            'hari' => $item->hari,
            'hari_label' => $item->labelHari(),
            'aktif' => (bool) $item->aktif,
            'keterangan' => $item->keterangan,
        ];
    }

    private function daftarTahunPelajaran()
    {
        return TahunPelajaran::query()->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();
    }

    private function daftarGuruMapel(?int $tahunPelajaranId)
    {
        if (! $tahunPelajaranId) {
            return collect();
        }

        return Pegawai::query()
            ->where('aktif', true)
            ->whereHas('guruMataPelajaran', fn (Builder $query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('jenis_penugasan', 'pengampu')
                ->where('aktif', true))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip']);
    }

    private function pastikanGuruMapel($pegawaiIds, int $tahunPelajaranId): void
    {
        $valid = GuruMataPelajaran::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('jenis_penugasan', 'pengampu')
            ->where('aktif', true)
            ->whereIn('pegawai_id', $pegawaiIds)
            ->pluck('pegawai_id')->map(fn ($id) => (int) $id)->unique();

        if ($pegawaiIds->diff($valid)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'pegawai_ids' => 'Guru piket harus merupakan guru mata pelajaran aktif pada tahun pelajaran tersebut.',
            ]);
        }
    }
}
