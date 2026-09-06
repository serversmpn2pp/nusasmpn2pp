<?php

namespace App\Services\Mobile;

use App\Models\Barang;
use App\Models\LokasiBarang;
use App\Models\PengaturanInventaris;
use App\Models\SumberPerolehanBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\GeneratorIdentitasInventaris;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitBarangMobileService
{
    public function __construct(private GeneratorIdentitasInventaris $generatorIdentitas) {}

    public function daftar(array $filter, bool $dapatKelola): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $kondisi = $filter['kondisi'] ?? 'semua';
        $statusUnit = $filter['status_unit'] ?? 'semua';
        $barangId = isset($filter['barang_id']) ? (int) $filter['barang_id'] : null;
        $lokasiId = isset($filter['lokasi_barang_id']) ? (int) $filter['lokasi_barang_id'] : null;
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $bagianKode = preg_match('/^(\d{2}(?:\.\d{2}){4})\.(\d+)$/', $cari, $bagian)
            ? ['kode' => $bagian[1], 'urutan' => (int) $bagian[2]]
            : null;

        $paginator = UnitBarang::query()
            ->with(['barang.kategoriBarang', 'barang.satuanBarang', 'lokasiBarang', 'sumberPerolehanBarang'])
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($kondisi !== 'semua', fn (Builder $query) => $query->where('kondisi', $kondisi))
            ->when($statusUnit !== 'semua', fn (Builder $query) => $query->where('status_unit', $statusUnit))
            ->when($barangId, fn (Builder $query) => $query->where('barang_id', $barangId))
            ->when($lokasiId, fn (Builder $query) => $query->where('lokasi_barang_id', $lokasiId))
            ->when($cari !== '', function (Builder $query) use ($cari, $bagianKode) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function (Builder $query) use ($pola, $bagianKode) {
                    $query->whereRaw('LOWER(kode_inventaris) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nomor_aset_resmi, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nomor_seri, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(merek, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(tipe, '')) LIKE ?", [$pola])
                        ->orWhereHas('barang', function (Builder $query) use ($pola) {
                            $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                                ->orWhereRaw("LOWER(COALESCE(kode, '')) LIKE ?", [$pola]);
                        });
                    if ($bagianKode) {
                        $query->orWhere(function (Builder $query) use ($bagianKode) {
                            $query->where('urutan_dalam_penerimaan', $bagianKode['urutan'])
                                ->whereHas('barang', fn (Builder $query) => $query
                                    ->where('kode', $bagianKode['kode'])
                                    ->orWhere('kode', 'like', $bagianKode['kode'].'.__'));
                        });
                    }
                });
            })
            ->orderByDesc('aktif')
            ->orderByDesc('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'total' => UnitBarang::query()->count(),
                'aktif' => UnitBarang::query()->where('aktif', true)->count(),
                'tersedia' => UnitBarang::query()->where('aktif', true)->where('status_unit', 'tersedia')->count(),
                'perlu_perhatian' => UnitBarang::query()->where('aktif', true)
                    ->whereIn('status_unit', ['dalam_perbaikan', 'hilang'])->count(),
            ],
            'filter' => [
                'cari' => $cari,
                'status' => $status,
                'kondisi' => $kondisi,
                'status_unit' => $statusUnit,
                'barang_id' => $barangId,
                'lokasi_barang_id' => $lokasiId,
            ],
            'pilihan' => $this->pilihanForm(),
            'hak_akses' => ['dapat_kelola' => $dapatKelola],
            'items' => collect($paginator->items())
                ->map(fn (UnitBarang $unit) => $this->ringkas($unit))
                ->values(),
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function tambah(array $data): UnitBarang
    {
        $data = $this->rapikanData($data, (bool) $data['aktif']);
        $jumlah = (int) $data['jumlah_unit'];
        unset($data['jumlah_unit']);
        if ($jumlah > 1 && filled($data['nomor_seri'])) {
            throw ValidationException::withMessages([
                'nomor_seri' => 'Nomor seri hanya dapat langsung diisi jika menambahkan satu unit.',
            ]);
        }

        return DB::transaction(function () use ($data, $jumlah) {
            $barang = Barang::query()->lockForUpdate()->findOrFail($data['barang_id']);
            $this->pastikanBarangAsetIndividual($barang);
            $nomorTerakhir = (int) UnitBarang::where('barang_id', $barang->id)->max('nomor_unit');
            $unitPertama = null;
            $data['lokasi_barang_id'] ??= $barang->lokasi_penyimpanan_id;
            for ($urutan = 1; $urutan <= $jumlah; $urutan++) {
                $tahun = (int) $data['tahun_perolehan'];
                $unit = UnitBarang::create($data + [
                    'nomor_unit' => $nomorTerakhir + $urutan,
                    'urutan_dalam_penerimaan' => $urutan,
                    'kode_inventaris' => $this->generatorIdentitas->buatKodeUnitAset($tahun),
                    'nomor_aset_resmi' => $this->generatorIdentitas->buatNomorAsetResmi($tahun),
                ]);
                $unitPertama ??= $unit;
            }

            return $unitPertama;
        });
    }

    public function ubah(UnitBarang $unit, array $data): void
    {
        $data = $this->rapikanData($data, (bool) $data['aktif']);
        unset($data['jumlah_unit'], $data['barang_id']);
        if (filled($data['tahun_perolehan'] ?? null)) {
            $data['nomor_aset_resmi'] = $this->generatorIdentitas
                ->buatNomorAsetResmi((int) $data['tahun_perolehan']);
        }
        $unit->update($data);
    }

    public function nonaktifkan(UnitBarang $unit): void
    {
        $unit->update(['aktif' => false]);
    }

    public function detail(UnitBarang $unit): array
    {
        $unit->load([
            'barang.kategoriBarang',
            'barang.satuanBarang',
            'lokasiBarang',
            'sumberPerolehanBarang',
            'detailPenerimaanBarang.penerimaanBarang.sumberPerolehanBarang',
            'detailPenerimaanBarang.penerimaanBarang.dibuatOleh',
            'detailPenerimaanBarang.lokasiBarang',
            'detailPeminjamanBarang.peminjamanBarang.siswa',
            'detailPeminjamanBarang.peminjamanBarang.pegawai',
            'detailPeminjamanBarang.peminjamanBarang.dibuatOleh',
            'detailPeminjamanBarang.detailPengembalianBarang.pengembalianBarang.dibuatOleh',
        ]);
        $pinjamanAktif = $unit->detailPeminjamanBarang
            ->sortByDesc('id')
            ->first(fn ($detail) => $detail->peminjamanBarang?->masihAktif()
                && $detail->jumlahBelumDikembalikan() > 0);

        return $this->ringkas($unit) + [
            'peminjaman_aktif' => $pinjamanAktif ? [
                'nomor' => $pinjamanAktif->peminjamanBarang->nomor_peminjaman,
                'peminjam' => $pinjamanAktif->peminjamanBarang->namaPeminjam(),
                'identitas' => $pinjamanAktif->peminjamanBarang->identitasPeminjam(),
                'rencana_kembali' => $pinjamanAktif->peminjamanBarang->rencana_kembali?->toDateString(),
                'pemantauan' => $pinjamanAktif->peminjamanBarang->labelPemantauan(),
            ] : null,
            'riwayat' => $this->susunRiwayat($unit),
            'dibuat_pada' => $unit->created_at?->toIso8601String(),
            'diperbarui_pada' => $unit->updated_at?->toIso8601String(),
        ];
    }

    public function pilihanForm(?UnitBarang $unit = null): array
    {
        $pengaturan = PengaturanInventaris::utama();

        return [
            'barang' => Barang::query()->where('tipe_pengelolaan', 'aset_individual')
                ->orderByDesc('aktif')->orderBy('nama')->get()
                ->map(fn (Barang $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama,
                    'kode' => $item->kodeKlasifikasi(),
                    'aktif' => (bool) $item->aktif,
                ])->values(),
            'lokasi' => LokasiBarang::query()->orderByDesc('aktif')->orderBy('nama')->get()
                ->map(fn (LokasiBarang $item) => $this->pilihan($item))->values(),
            'sumber_perolehan' => SumberPerolehanBarang::query()
                ->where(function (Builder $query) use ($unit) {
                    $query->where('aktif', true)
                        ->when($unit?->sumber_perolehan_barang_id, fn (Builder $query, $id) => $query->orWhere('id', $id));
                })->orderByDesc('aktif')->orderBy('nama')->get()
                ->map(fn (SumberPerolehanBarang $item) => $this->pilihan($item))->values(),
            'kondisi' => $this->pilihanLabel(UnitBarang::DAFTAR_KONDISI),
            'status_unit' => $this->pilihanLabel(UnitBarang::DAFTAR_STATUS),
            'nomor_aset' => [
                'awalan' => $pengaturan->awalan_nomor_aset,
                'akhiran' => $pengaturan->akhiran_nomor_aset,
                'contoh' => $pengaturan->contohNomorAset(),
            ],
        ];
    }

    private function ringkas(UnitBarang $unit): array
    {
        return [
            'id' => (int) $unit->id,
            'barang' => [
                'id' => (int) $unit->barang->id,
                'nama' => $unit->barang->nama,
                'kode' => $unit->barang->kodeKlasifikasi(),
                'kategori' => $unit->barang->kategoriBarang->nama,
                'satuan' => $unit->barang->satuanBarang->nama,
            ],
            'nomor_unit' => (int) $unit->nomor_unit,
            'kode_barang_unit' => $unit->kodeBarangUnit(),
            'kode_inventaris' => $unit->kode_inventaris,
            'nomor_aset_resmi' => $unit->nomor_aset_resmi,
            'lokasi' => $unit->lokasiBarang ? $this->pilihan($unit->lokasiBarang) : null,
            'nomor_seri' => $unit->nomor_seri,
            'merek' => $unit->merek,
            'tipe' => $unit->tipe,
            'kondisi' => $unit->kondisi,
            'label_kondisi' => $unit->labelKondisi(),
            'status_unit' => $unit->status_unit,
            'label_status_unit' => $unit->labelStatus(),
            'tanggal_perolehan' => $unit->tanggal_perolehan?->toDateString(),
            'tahun_perolehan' => $unit->tahun_perolehan,
            'sumber_perolehan' => $unit->sumberPerolehanBarang
                ? $this->pilihan($unit->sumberPerolehanBarang)
                : null,
            'sumber_perolehan_lama' => $unit->sumber_perolehan,
            'harga_perolehan' => $unit->harga_perolehan !== null ? (float) $unit->harga_perolehan : null,
            'keterangan' => $unit->keterangan,
            'aktif' => (bool) $unit->aktif,
        ];
    }

    private function rapikanData(array $data, bool $aktif): array
    {
        foreach (['nomor_seri', 'merek', 'tipe', 'keterangan'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim($data[$field]) : null;
            }
        }
        if (array_key_exists('lokasi_barang_id', $data)) {
            $data['lokasi_barang_id'] = filled($data['lokasi_barang_id']) ? (int) $data['lokasi_barang_id'] : null;
        }
        if (array_key_exists('harga_perolehan', $data)) {
            $data['harga_perolehan'] = filled($data['harga_perolehan']) ? $data['harga_perolehan'] : null;
        }
        $data['aktif'] = $aktif;

        if (filled($data['tanggal_perolehan'] ?? null)
            && filled($data['tahun_perolehan'] ?? null)
            && (int) substr($data['tanggal_perolehan'], 0, 4) !== (int) $data['tahun_perolehan']) {
            throw ValidationException::withMessages([
                'tahun_perolehan' => 'Tahun perolehan harus sama dengan tahun pada tanggal perolehan.',
            ]);
        }
        if (filled($data['sumber_perolehan_barang_id'] ?? null)) {
            $sumber = SumberPerolehanBarang::findOrFail($data['sumber_perolehan_barang_id']);
            $data['sumber_perolehan'] = $sumber->nama;
        } else {
            unset($data['sumber_perolehan_barang_id'], $data['tahun_perolehan']);
        }

        return $data;
    }

    private function pastikanBarangAsetIndividual(Barang $barang): void
    {
        if ($barang->tipe_pengelolaan !== 'aset_individual') {
            throw ValidationException::withMessages([
                'barang_id' => 'Unit inventaris hanya dapat dibuat untuk barang dengan tipe aset individual.',
            ]);
        }
    }

    private function susunRiwayat(UnitBarang $unit): array
    {
        $riwayat = collect();
        $detailPenerimaan = $unit->detailPenerimaanBarang;
        $penerimaan = $detailPenerimaan?->penerimaanBarang;
        $riwayat->push([
            'jenis' => $penerimaan ? 'penerimaan' : 'pencatatan',
            'label' => $penerimaan ? 'Penerimaan' : 'Pencatatan',
            'judul' => $penerimaan ? 'Aset diterima dan dicatat' : 'Unit aset dicatat di NUSA',
            'keterangan' => $penerimaan
                ? collect([$penerimaan->nomor_penerimaan, $penerimaan->asal_barang ? 'Dari '.$penerimaan->asal_barang : null])->filter()->join(' - ')
                : 'Riwayat penerimaan terperinci belum tersedia untuk unit ini.',
            'tanggal' => ($penerimaan?->tanggal_penerimaan ?? $unit->tanggal_perolehan ?? $unit->created_at)?->toDateString(),
            'waktu_urut' => ($penerimaan?->created_at ?? $unit->created_at)?->toIso8601String(),
        ]);
        foreach ($unit->detailPeminjamanBarang as $detail) {
            $pinjam = $detail->peminjamanBarang;
            if (! $pinjam) {
                continue;
            }
            $riwayat->push([
                'jenis' => 'peminjaman',
                'label' => 'Peminjaman',
                'judul' => 'Dipinjam oleh '.$pinjam->namaPeminjam(),
                'keterangan' => $detail->catatan ?: $pinjam->nomor_peminjaman.' - '.$pinjam->identitasPeminjam(),
                'tanggal' => $pinjam->tanggal_peminjaman?->toDateString(),
                'waktu_urut' => $pinjam->created_at?->toIso8601String(),
            ]);
            foreach ($detail->detailPengembalianBarang as $detailKembali) {
                $kembali = $detailKembali->pengembalianBarang;
                if (! $kembali) {
                    continue;
                }
                $riwayat->push([
                    'jenis' => 'pengembalian',
                    'label' => 'Pengembalian',
                    'judul' => 'Aset dikembalikan oleh '.$pinjam->namaPeminjam(),
                    'keterangan' => $detailKembali->catatan ?: ($kembali->catatan ?: $kembali->nomor_pengembalian),
                    'tanggal' => $kembali->tanggal_pengembalian?->toDateString(),
                    'waktu_urut' => $kembali->created_at?->toIso8601String(),
                ]);
            }
        }

        return $riwayat->sortByDesc('waktu_urut')->values()->all();
    }

    private function pilihan(object $item): array
    {
        return [
            'id' => (int) $item->id,
            'nama' => $item->nama,
            'kode' => $item->kode,
            'aktif' => (bool) $item->aktif,
        ];
    }

    private function pilihanLabel(array $items): array
    {
        return collect($items)
            ->map(fn (string $label, string $nilai) => ['nilai' => $nilai, 'label' => $label])
            ->values()->all();
    }
}
