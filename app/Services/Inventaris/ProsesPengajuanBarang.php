<?php

namespace App\Services\Inventaris;

use App\Models\Barang;
use App\Models\PengajuanBarang;
use App\Models\SaldoStokBarang;
use App\Models\UnitBarang;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProsesPengajuanBarang
{
    public function __construct(
        private readonly ProsesPeminjamanBarang $prosesPeminjamanBarang,
        private readonly NotifikasiPenggunaService $notifikasi,
    ) {}

    public function ajukan(int $pegawaiId, array $data, ?int $penggunaId = null): PengajuanBarang
    {
        $pengajuan = DB::transaction(function () use ($pegawaiId, $data) {
            $barang = Barang::query()
                ->where('aktif', true)
                ->findOrFail($data['barang_id']);

            $jenisPengajuan = $barang->jenis_barang === 'habis_pakai'
                ? 'permintaan'
                : 'peminjaman';
            $jumlah = $this->normalisasiJumlah($data['jumlah'], $jenisPengajuan === 'peminjaman');

            $this->pastikanTersedia($barang, $jumlah);

            if ($jenisPengajuan === 'peminjaman' && blank($data['rencana_kembali'] ?? null)) {
                throw ValidationException::withMessages([
                    'rencana_kembali' => 'Tanggal rencana kembali wajib diisi untuk peminjaman aset.',
                ]);
            }

            $pengajuan = PengajuanBarang::create([
                'nomor_pengajuan' => 'TMP-'.Str::uuid(),
                'pegawai_id' => $pegawaiId,
                'barang_id' => $barang->id,
                'jenis_pengajuan' => $jenisPengajuan,
                'jumlah' => $jumlah,
                'tanggal_pengajuan' => now()->toDateString(),
                'tanggal_dibutuhkan' => $data['tanggal_dibutuhkan'],
                'rencana_kembali' => $jenisPengajuan === 'peminjaman'
                    ? $data['rencana_kembali']
                    : null,
                'tujuan' => trim($data['tujuan']),
                'status' => 'menunggu',
            ]);

            $pengajuan->update([
                'nomor_pengajuan' => 'PGJ-'.now()->format('Ymd').'-'.str_pad((string) $pengajuan->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $pengajuan->fresh(['pegawai', 'barang.satuanBarang']);
        });

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaDenganIzin('barang.peminjaman_kelola', $penggunaId),
            'penting',
            'Pengajuan barang baru',
            $pengajuan->pegawai->nama_lengkap.' mengajukan '.$pengajuan->barang->nama.'.',
            route('pengajuan-barang.show', $pengajuan),
            'pengajuan-barang-baru-'.$pengajuan->id,
        );

        return $pengajuan;
    }

    public function batalkan(PengajuanBarang $pengajuan, int $pegawaiId, ?int $penggunaId = null): PengajuanBarang
    {
        $pengajuan = DB::transaction(function () use ($pengajuan, $pegawaiId) {
            $terkunci = PengajuanBarang::query()->lockForUpdate()->findOrFail($pengajuan->id);

            if ($terkunci->pegawai_id !== $pegawaiId) {
                abort(403);
            }

            $this->pastikanMasihMenunggu($terkunci);
            $terkunci->update(['status' => 'dibatalkan']);

            return $terkunci->fresh(['pegawai', 'barang']);
        });

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaDenganIzin('barang.peminjaman_kelola', $penggunaId),
            'informasi',
            'Pengajuan dibatalkan',
            $pengajuan->pegawai->nama_lengkap.' membatalkan pengajuan '.$pengajuan->barang->nama.'.',
            route('pengajuan-barang.show', $pengajuan),
            'pengajuan-barang-batal-'.$pengajuan->id,
        );

        return $pengajuan;
    }

    public function tolak(PengajuanBarang $pengajuan, string $catatan, int $penggunaId): PengajuanBarang
    {
        $pengajuan = DB::transaction(function () use ($pengajuan, $catatan, $penggunaId) {
            $terkunci = PengajuanBarang::query()->lockForUpdate()->findOrFail($pengajuan->id);
            $this->pastikanMasihMenunggu($terkunci);

            $terkunci->update([
                'status' => 'ditolak',
                'catatan_petugas' => trim($catatan),
                'diproses_oleh_pengguna_id' => $penggunaId,
                'diproses_pada' => now(),
            ]);

            return $terkunci->fresh(['pegawai', 'barang']);
        });

        $this->beriTahuPemohon($pengajuan, 'peringatan', 'Pengajuan barang ditolak');

        return $pengajuan;
    }

    public function penuhi(PengajuanBarang $pengajuan, array $data, int $penggunaId): PengajuanBarang
    {
        $pengajuan = DB::transaction(function () use ($pengajuan, $data, $penggunaId) {
            $terkunci = PengajuanBarang::query()
                ->with('barang')
                ->lockForUpdate()
                ->findOrFail($pengajuan->id);
            $this->pastikanMasihMenunggu($terkunci);

            if ($terkunci->rencana_kembali?->lt(now()->startOfDay())) {
                throw ValidationException::withMessages([
                    'rencana_kembali' => 'Rencana kembali sudah lewat. Tolak pengajuan ini dan minta pegawai membuat pengajuan baru.',
                ]);
            }

            $items = $this->susunItemTransaksi($terkunci, $data);
            $catatanPetugas = filled($data['catatan_petugas'] ?? null)
                ? trim($data['catatan_petugas'])
                : null;
            $catatanTransaksi = 'Dipenuhi dari '.$terkunci->nomor_pengajuan.'. Tujuan: '.$terkunci->tujuan;

            if ($catatanPetugas) {
                $catatanTransaksi .= ' Catatan petugas: '.$catatanPetugas;
            }

            $peminjaman = $this->prosesPeminjamanBarang->catat([
                'jenis_peminjam' => 'pegawai',
                'pegawai_id' => $terkunci->pegawai_id,
                'cara_input_peminjam' => 'manual',
                'tanggal_peminjaman' => now()->toDateString(),
                'rencana_kembali' => $terkunci->rencana_kembali?->toDateString(),
                'catatan' => $catatanTransaksi,
                'items' => $items,
            ], $penggunaId);

            $terkunci->update([
                'status' => 'dipenuhi',
                'catatan_petugas' => $catatanPetugas,
                'diproses_oleh_pengguna_id' => $penggunaId,
                'diproses_pada' => now(),
                'peminjaman_barang_id' => $peminjaman->id,
            ]);

            return $terkunci->fresh(['pegawai', 'barang', 'peminjamanBarang']);
        });

        $this->beriTahuPemohon($pengajuan, 'berhasil', 'Pengajuan barang dipenuhi');

        return $pengajuan;
    }

    private function susunItemTransaksi(PengajuanBarang $pengajuan, array $data): array
    {
        $barang = $pengajuan->barang;

        if ($barang->tipe_pengelolaan === 'aset_individual') {
            $unitIds = collect($data['unit_barang_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($unitIds->count() !== (int) $pengajuan->jumlah) {
                throw ValidationException::withMessages([
                    'unit_barang_ids' => 'Pilih tepat '.number_format((float) $pengajuan->jumlah, 0, ',', '.').' unit aset.',
                ]);
            }

            $jumlahValid = UnitBarang::query()
                ->whereIn('id', $unitIds)
                ->where('barang_id', $barang->id)
                ->where('aktif', true)
                ->where('status_unit', 'tersedia')
                ->count();

            if ($jumlahValid !== $unitIds->count()) {
                throw ValidationException::withMessages([
                    'unit_barang_ids' => 'Sebagian unit yang dipilih sudah tidak tersedia.',
                ]);
            }

            return $unitIds->map(fn (int $unitId) => [
                'tipe_item' => 'unit',
                'unit_barang_id' => $unitId,
                'cara_input_barang' => 'manual',
            ])->all();
        }

        $lokasiId = (int) ($data['lokasi_barang_id'] ?? 0);

        if (! $lokasiId) {
            throw ValidationException::withMessages([
                'lokasi_barang_id' => 'Pilih lokasi asal barang.',
            ]);
        }

        $saldo = SaldoStokBarang::query()
            ->where('barang_id', $barang->id)
            ->where('lokasi_barang_id', $lokasiId)
            ->value('jumlah');

        if ((float) $saldo < (float) $pengajuan->jumlah) {
            throw ValidationException::withMessages([
                'lokasi_barang_id' => 'Stok pada lokasi yang dipilih tidak mencukupi.',
            ]);
        }

        return [[
            'tipe_item' => 'stok',
            'barang_id' => $barang->id,
            'lokasi_barang_id' => $lokasiId,
            'jumlah' => (float) $pengajuan->jumlah,
            'cara_input_barang' => 'manual',
        ]];
    }

    private function normalisasiJumlah(mixed $jumlah, bool $harusBulat): float|int
    {
        $jumlah = (float) $jumlah;

        if ($jumlah <= 0 || ($harusBulat && floor($jumlah) !== $jumlah)) {
            throw ValidationException::withMessages([
                'jumlah' => $harusBulat
                    ? 'Jumlah aset harus berupa bilangan bulat minimal 1.'
                    : 'Jumlah barang harus lebih dari 0.',
            ]);
        }

        return $harusBulat ? (int) $jumlah : round($jumlah, 2);
    }

    private function pastikanTersedia(Barang $barang, float|int $jumlah): void
    {
        $tersedia = $barang->tipe_pengelolaan === 'aset_individual'
            ? UnitBarang::query()
                ->where('barang_id', $barang->id)
                ->where('aktif', true)
                ->where('status_unit', 'tersedia')
                ->count()
            : (float) SaldoStokBarang::query()
                ->where('barang_id', $barang->id)
                ->sum('jumlah');

        if ($tersedia < $jumlah) {
            throw ValidationException::withMessages([
                'jumlah' => 'Jumlah yang diajukan melebihi ketersediaan saat ini.',
            ]);
        }
    }

    private function pastikanMasihMenunggu(PengajuanBarang $pengajuan): void
    {
        if (! $pengajuan->masihMenunggu()) {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan ini sudah diproses dan tidak dapat diubah lagi.',
            ]);
        }
    }

    private function beriTahuPemohon(PengajuanBarang $pengajuan, string $jenis, string $judul): void
    {
        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaUntukPegawai($pengajuan->pegawai_id),
            $jenis,
            $judul,
            $pengajuan->nomor_pengajuan.' untuk '.$pengajuan->barang->nama.' berstatus '.$pengajuan->labelStatus().'.',
            route('pengajuan-barang-saya.show', $pengajuan),
            'pengajuan-barang-'.$pengajuan->status.'-'.$pengajuan->id,
        );
    }
}
