<?php

namespace App\Services\Inventaris;

use App\Models\Barang;
use App\Models\DetailPeminjamanBarang;
use App\Models\Pegawai;
use App\Models\PeminjamanBarang;
use App\Models\Siswa;
use App\Models\UnitBarang;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProsesPeminjamanBarang
{
    public function __construct(
        private readonly ProsesMutasiStokBarang $prosesMutasiStokBarang,
    ) {
    }

    public function catat(array $data, ?int $penggunaId = null): PeminjamanBarang
    {
        return DB::transaction(function () use ($data, $penggunaId) {
            $dataPeminjam = $this->dataPeminjam($data);
            $daftarItem = $this->rapikanDaftarItem($data['items'] ?? []);

            if ($daftarItem === []) {
                throw ValidationException::withMessages([
                    'items' => 'Pilih minimal satu barang untuk dipinjam atau dikeluarkan.',
                ]);
            }

            $peminjamanBarang = PeminjamanBarang::create([
                'nomor_peminjaman' => 'TMP-' . Str::uuid(),
                ...$dataPeminjam,
                'cara_input_peminjam' => $data['cara_input_peminjam'],
                'tanggal_peminjaman' => $data['tanggal_peminjaman'],
                'rencana_kembali' => $data['rencana_kembali'] ?? null,
                'status' => 'dipinjam',
                'catatan' => $data['catatan'] ?? null,
                'dibuat_oleh_pengguna_id' => $penggunaId,
            ]);

            $peminjamanBarang->update([
                'nomor_peminjaman' => $this->buatNomor('PJM', $peminjamanBarang->id, $data['tanggal_peminjaman']),
            ]);

            $adaBarangYangHarusKembali = false;

            foreach ($daftarItem as $item) {
                $detail = $item['tipe_item'] === 'unit'
                    ? $this->catatUnitBarang($peminjamanBarang, $item)
                    : $this->catatStokBarang($peminjamanBarang, $item, $penggunaId);

                $adaBarangYangHarusKembali = $adaBarangYangHarusKembali || $detail->wajib_dikembalikan;
            }

            if (! $adaBarangYangHarusKembali) {
                $peminjamanBarang->update(['status' => 'selesai']);
            }

            return $peminjamanBarang->fresh();
        });
    }

    private function dataPeminjam(array $data): array
    {
        if ($data['jenis_peminjam'] === 'siswa') {
            $siswa = Siswa::query()
                ->where('aktif', true)
                ->find($data['siswa_id'] ?? null);

            if (! $siswa) {
                throw ValidationException::withMessages([
                    'siswa_id' => 'Siswa peminjam tidak ditemukan atau sudah tidak aktif.',
                ]);
            }

            return [
                'jenis_peminjam' => 'siswa',
                'siswa_id' => $siswa->id,
                'pegawai_id' => null,
            ];
        }

        $pegawai = Pegawai::query()
            ->where('aktif', true)
            ->find($data['pegawai_id'] ?? null);

        if (! $pegawai) {
            throw ValidationException::withMessages([
                'pegawai_id' => 'Pegawai peminjam tidak ditemukan atau sudah tidak aktif.',
            ]);
        }

        return [
            'jenis_peminjam' => 'pegawai',
            'siswa_id' => null,
            'pegawai_id' => $pegawai->id,
        ];
    }

    private function rapikanDaftarItem(array $daftarItem): array
    {
        $hasil = [];

        foreach ($daftarItem as $item) {
            if (($item['tipe_item'] ?? null) === 'unit') {
                $unitBarangId = (int) ($item['unit_barang_id'] ?? 0);

                if (! $unitBarangId) {
                    throw ValidationException::withMessages([
                        'items' => 'Unit aset yang dipilih tidak valid.',
                    ]);
                }

                $kunci = 'unit:' . $unitBarangId;

                if (isset($hasil[$kunci])) {
                    throw ValidationException::withMessages([
                        'items' => 'Satu unit aset tidak dapat dipinjam dua kali dalam transaksi yang sama.',
                    ]);
                }

                $hasil[$kunci] = [
                    'tipe_item' => 'unit',
                    'unit_barang_id' => $unitBarangId,
                    'cara_input_barang' => $item['cara_input_barang'] ?? 'manual',
                    'catatan' => $item['catatan'] ?? null,
                ];

                continue;
            }

            if (($item['tipe_item'] ?? null) !== 'stok') {
                throw ValidationException::withMessages([
                    'items' => 'Jenis barang yang dipilih tidak dikenali.',
                ]);
            }

            $barangId = (int) ($item['barang_id'] ?? 0);
            $lokasiBarangId = (int) ($item['lokasi_barang_id'] ?? 0);
            $jumlah = $this->keSatuanTerkecil($item['jumlah'] ?? 0);

            if (! $barangId || ! $lokasiBarangId || $jumlah <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Barang stok, lokasi, dan jumlah harus diisi dengan benar.',
                ]);
            }

            $kunci = 'stok:' . $barangId . ':' . $lokasiBarangId;

            if (! isset($hasil[$kunci])) {
                $hasil[$kunci] = [
                    'tipe_item' => 'stok',
                    'barang_id' => $barangId,
                    'lokasi_barang_id' => $lokasiBarangId,
                    'jumlah' => 0,
                    'cara_input_barang' => $item['cara_input_barang'] ?? 'manual',
                    'catatan' => $item['catatan'] ?? null,
                ];
            }

            $hasil[$kunci]['jumlah'] += $jumlah;

            if ($hasil[$kunci]['cara_input_barang'] !== ($item['cara_input_barang'] ?? 'manual')) {
                $hasil[$kunci]['cara_input_barang'] = 'campuran';
            }
        }

        ksort($hasil);

        return array_values($hasil);
    }

    private function catatUnitBarang(PeminjamanBarang $peminjamanBarang, array $item): DetailPeminjamanBarang
    {
        $unitBarang = UnitBarang::query()
            ->with('barang')
            ->lockForUpdate()
            ->findOrFail($item['unit_barang_id']);

        if (
            ! $unitBarang->aktif
            || $unitBarang->status_unit !== 'tersedia'
            || $unitBarang->barang?->tipe_pengelolaan !== 'aset_individual'
        ) {
            throw ValidationException::withMessages([
                'items' => 'Unit aset ' . ($unitBarang->kode_inventaris ?: '-') . ' sedang tidak tersedia untuk dipinjam.',
            ]);
        }

        $unitBarang->update(['status_unit' => 'dipinjam']);

        return $peminjamanBarang->detailPeminjamanBarang()->create([
            'barang_id' => $unitBarang->barang_id,
            'unit_barang_id' => $unitBarang->id,
            'lokasi_barang_id' => $unitBarang->lokasi_barang_id,
            'tipe_pengelolaan' => 'aset_individual',
            'jumlah' => '1.00',
            'jumlah_dikembalikan' => '0.00',
            'wajib_dikembalikan' => true,
            'cara_input_barang' => $item['cara_input_barang'],
            'catatan' => $item['catatan'],
        ]);
    }

    private function catatStokBarang(PeminjamanBarang $peminjamanBarang, array $item, ?int $penggunaId): DetailPeminjamanBarang
    {
        $barang = Barang::query()->findOrFail($item['barang_id']);

        if (! $barang->aktif || ! in_array($barang->tipe_pengelolaan, ['stok_dikembalikan', 'habis_pakai'], true)) {
            throw ValidationException::withMessages([
                'items' => 'Barang stok ' . ($barang->nama ?: '-') . ' tidak tersedia untuk transaksi ini.',
            ]);
        }

        $jumlah = $this->formatJumlah($item['jumlah']);
        $wajibDikembalikan = $barang->tipe_pengelolaan === 'stok_dikembalikan';

        $this->prosesMutasiStokBarang->catat([
            'barang_id' => $barang->id,
            'lokasi_barang_id' => $item['lokasi_barang_id'],
            'jenis_mutasi' => 'keluar',
            'kategori_mutasi' => $wajibDikembalikan ? 'peminjaman' : 'pengeluaran_pemakaian',
            'tanggal_mutasi' => $peminjamanBarang->tanggal_peminjaman->toDateString(),
            'jumlah' => $jumlah,
            'referensi' => $peminjamanBarang->nomor_peminjaman,
            'keterangan' => 'Dicatat dari transaksi peminjaman barang.',
        ], $penggunaId);

        return $peminjamanBarang->detailPeminjamanBarang()->create([
            'barang_id' => $barang->id,
            'unit_barang_id' => null,
            'lokasi_barang_id' => $item['lokasi_barang_id'],
            'tipe_pengelolaan' => $barang->tipe_pengelolaan,
            'jumlah' => $jumlah,
            'jumlah_dikembalikan' => '0.00',
            'wajib_dikembalikan' => $wajibDikembalikan,
            'cara_input_barang' => $item['cara_input_barang'],
            'catatan' => $item['catatan'],
        ]);
    }

    private function buatNomor(string $awalan, int $id, mixed $tanggal): string
    {
        return $awalan . '-' . date('Ymd', strtotime((string) $tanggal)) . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    private function keSatuanTerkecil(mixed $jumlah): int
    {
        return (int) round(((float) $jumlah) * 100);
    }

    private function formatJumlah(int $jumlah): string
    {
        return number_format($jumlah / 100, 2, '.', '');
    }
}
