<?php

namespace App\Services\Mobile;

use App\Models\Barang;
use App\Models\DetailPeminjamanBarang;
use App\Models\Pegawai;
use App\Models\PeminjamanBarang;
use App\Models\SaldoStokBarang;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UnitBarang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PeminjamanBarangMobileService
{
    public const STATUS_PEMANTAUAN = [
        'aktif' => 'Masih dipinjam',
        'terlambat' => 'Terlambat dikembalikan',
        'jatuh_tempo' => 'Jatuh tempo 7 hari',
        'tanpa_rencana' => 'Belum ada rencana kembali',
        'selesai' => 'Sudah selesai',
        'semua' => 'Semua transaksi',
    ];

    public function daftar(array $filter, bool $dapatKelola): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $jenisPeminjam = $filter['jenis_peminjam'] ?? 'semua';
        $status = $filter['status'] ?? 'semua';
        $tanggalMulai = $filter['tanggal_mulai'] ?? null;
        $tanggalSelesai = $filter['tanggal_selesai'] ?? null;
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);

        $paginator = PeminjamanBarang::query()
            ->with(['siswa', 'pegawai', 'dibuatOleh'])
            ->withCount('detailPeminjamanBarang')
            ->when($jenisPeminjam !== 'semua', fn (Builder $query) => $query->where('jenis_peminjam', $jenisPeminjam))
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->when($tanggalMulai, fn (Builder $query) => $query->whereDate('tanggal_peminjaman', '>=', $tanggalMulai))
            ->when($tanggalSelesai, fn (Builder $query) => $query->whereDate('tanggal_peminjaman', '<=', $tanggalSelesai))
            ->when($cari !== '', fn (Builder $query) => $this->filterPencarian($query, $cari))
            ->orderByDesc('tanggal_peminjaman')
            ->orderByDesc('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        $hariIni = now()->toDateString();

        return [
            'ringkasan' => [
                'total' => PeminjamanBarang::query()->count(),
                'aktif' => $this->queryAktif()->count(),
                'selesai' => PeminjamanBarang::query()->where('status', 'selesai')->count(),
                'hari_ini' => PeminjamanBarang::query()->whereDate('tanggal_peminjaman', $hariIni)->count(),
            ],
            'filter' => [
                'cari' => $cari,
                'jenis_peminjam' => $jenisPeminjam,
                'status' => $status,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
            ],
            'pilihan' => $this->pilihanPeminjaman(),
            'hak_akses' => ['dapat_kelola' => $dapatKelola],
            'items' => collect($paginator->items())
                ->map(fn (PeminjamanBarang $item) => $this->ringkas($item))
                ->values(),
            'paginasi' => $this->paginasi($paginator),
        ];
    }

    public function detail(PeminjamanBarang $peminjaman, bool $dapatKelola): array
    {
        $peminjaman->load([
            'siswa',
            'pegawai',
            'dibuatOleh',
            'detailPeminjamanBarang.barang.satuanBarang',
            'detailPeminjamanBarang.unitBarang',
            'detailPeminjamanBarang.lokasiBarang',
            'pengembalianBarang.dibuatOleh',
            'pengembalianBarang.detailPengembalianBarang.detailPeminjamanBarang.barang.satuanBarang',
        ])->loadCount('detailPeminjamanBarang');

        return [
            'peminjaman' => $this->ringkas($peminjaman) + [
                'catatan' => $peminjaman->catatan,
                'dibuat_oleh' => $peminjaman->dibuatOleh?->nama ?? 'Sistem',
                'items' => $peminjaman->detailPeminjamanBarang
                    ->map(fn (DetailPeminjamanBarang $detail) => $this->detailItem($detail))
                    ->values(),
                'pengembalian' => $peminjaman->pengembalianBarang
                    ->sortByDesc('tanggal_pengembalian')
                    ->map(fn ($pengembalian) => [
                        'id' => (int) $pengembalian->id,
                        'nomor' => $pengembalian->nomor_pengembalian,
                        'tanggal' => $pengembalian->tanggal_pengembalian?->toDateString(),
                        'tanggal_label' => $pengembalian->tanggal_pengembalian?->locale('id')->translatedFormat('d M Y'),
                        'catatan' => $pengembalian->catatan,
                        'dibuat_oleh' => $pengembalian->dibuatOleh?->nama ?? 'Sistem',
                        'items' => $pengembalian->detailPengembalianBarang
                            ->map(fn ($detail) => [
                                'id' => (int) $detail->id,
                                'nama_barang' => $detail->detailPeminjamanBarang?->barang?->nama ?? '-',
                                'jumlah' => (float) $detail->jumlah,
                                'satuan' => $detail->detailPeminjamanBarang?->tipe_pengelolaan === 'aset_individual'
                                    ? 'unit'
                                    : ($detail->detailPeminjamanBarang?->barang?->satuanBarang?->nama ?? 'unit'),
                                'kondisi' => $detail->kondisi_pengembalian,
                                'kondisi_label' => $detail->kondisi_pengembalian
                                    ? (UnitBarang::DAFTAR_KONDISI[$detail->kondisi_pengembalian] ?? str($detail->kondisi_pengembalian)->headline()->toString())
                                    : null,
                                'cara_input' => $detail->cara_input_barang,
                                'catatan' => $detail->catatan,
                            ])->values(),
                    ])->values(),
            ],
            'pilihan' => [
                'kondisi' => $this->pilihanLabel(UnitBarang::DAFTAR_KONDISI),
            ],
            'hak_akses' => [
                'dapat_kelola' => $dapatKelola,
                'dapat_mengembalikan' => $dapatKelola && $peminjaman->masihAktif()
                    && $peminjaman->detailPeminjamanBarang
                        ->contains(fn (DetailPeminjamanBarang $item) => $item->wajib_dikembalikan && $item->jumlahBelumDikembalikan() > 0),
            ],
        ];
    }

    public function daftarPengembalian(array $filter): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);
        $hariIni = now()->startOfDay();

        $paginator = $this->queryAktif()
            ->with(['siswa', 'pegawai'])
            ->withCount('detailPeminjamanBarang')
            ->withCount(['detailPeminjamanBarang as items_belum_kembali_count' => fn (Builder $query) => $query
                ->where('wajib_dikembalikan', true)
                ->whereColumn('jumlah_dikembalikan', '<', 'jumlah')])
            ->whereHas('detailPeminjamanBarang', fn (Builder $query) => $query
                ->where('wajib_dikembalikan', true)
                ->whereColumn('jumlah_dikembalikan', '<', 'jumlah'))
            ->when($cari !== '', fn (Builder $query) => $this->filterPencarian($query, $cari, true))
            ->orderByRaw('CASE WHEN rencana_kembali IS NULL THEN 1 ELSE 0 END')
            ->orderBy('rencana_kembali')
            ->orderByDesc('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'aktif' => $this->queryAktif()->count(),
                'terlambat' => $this->queryAktif()->whereNotNull('rencana_kembali')
                    ->whereDate('rencana_kembali', '<', $hariIni->toDateString())->count(),
                'sebagian' => PeminjamanBarang::query()->where('status', 'sebagian_dikembalikan')->count(),
                'jatuh_tempo' => $this->queryAktif()
                    ->whereBetween('rencana_kembali', [$hariIni->toDateString(), $hariIni->copy()->addDays(7)->toDateString()])
                    ->count(),
            ],
            'filter' => ['cari' => $cari],
            'items' => collect($paginator->items())->map(fn (PeminjamanBarang $item) => $this->ringkas($item) + [
                'items_belum_kembali' => (int) ($item->items_belum_kembali_count ?? 0),
            ])->values(),
            'paginasi' => $this->paginasi($paginator),
        ];
    }

    public function rekap(array $filter, bool $dapatKelola, bool $semua = false): array
    {
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = max(5, min(50, (int) ($filter['per_halaman'] ?? 15)));
        $filter = [
            'kata_kunci' => trim((string) ($filter['kata_kunci'] ?? '')),
            'status_pemantauan' => $filter['status_pemantauan'] ?? 'aktif',
            'jenis_peminjam' => $filter['jenis_peminjam'] ?? 'semua',
            'peminjam' => $filter['peminjam'] ?? '',
            'barang_id' => isset($filter['barang_id']) ? (int) $filter['barang_id'] : null,
            'tanggal_mulai' => $filter['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $filter['tanggal_selesai'] ?? null,
        ];
        $query = $this->queryRekap($filter);

        if ($semua) {
            $model = $query->get();
            $items = $model->map(fn (PeminjamanBarang $item) => $this->itemRekap($item))->values();
            $paginasi = [
                'halaman' => 1,
                'halaman_terakhir' => 1,
                'per_halaman' => $items->count(),
                'total' => $items->count(),
                'ada_halaman_berikutnya' => false,
            ];
        } else {
            $paginator = $query->paginate($perHalaman, ['*'], 'halaman', $halaman);
            $items = collect($paginator->items())
                ->map(fn (PeminjamanBarang $item) => $this->itemRekap($item))
                ->values();
            $paginasi = $this->paginasi($paginator);
        }

        $hariIni = now()->startOfDay();
        $daftarTerlambat = $this->queryRekap([
            ...$filter,
            'status_pemantauan' => 'terlambat',
        ])->get();

        return [
            'ringkasan' => [
                'aktif' => $this->queryAktif()->count(),
                'terlambat' => $this->queryAktif()
                    ->whereNotNull('rencana_kembali')
                    ->whereDate('rencana_kembali', '<', $hariIni->toDateString())
                    ->count(),
                'jatuh_tempo' => $this->queryAktif()
                    ->whereBetween('rencana_kembali', [
                        $hariIni->toDateString(),
                        $hariIni->copy()->addDays(7)->toDateString(),
                    ])->count(),
                'tanpa_rencana' => $this->queryAktif()->whereNull('rencana_kembali')->count(),
            ],
            'filter' => $filter,
            'pilihan' => $this->pilihanRekap(),
            'hak_akses' => ['dapat_mengembalikan' => $dapatKelola],
            'items' => $items,
            'paginasi' => $paginasi,
            'daftar_terlambat' => [
                'jumlah' => $daftarTerlambat->count(),
                'teks' => $this->teksDaftarTerlambat($daftarTerlambat),
            ],
            'dicetak_pada' => $semua ? now()->locale('id')->translatedFormat('d F Y H:i') : null,
        ];
    }

    public function identifikasiPeminjam(string $kode, string $jenis = 'otomatis'): array
    {
        $kode = preg_replace('/\s+/', '', trim($kode));
        $siswa = $jenis !== 'pegawai'
            ? Siswa::query()->where('aktif', true)->where(fn (Builder $query) => $query
                ->where('nisn', $kode)->orWhere('nis', $kode))->first()
            : null;
        $pegawai = $jenis !== 'siswa'
            ? Pegawai::query()->where('aktif', true)->where('nip', $kode)->first()
            : null;

        if ($siswa && $pegawai) {
            throw ValidationException::withMessages([
                'kode' => 'Nomor kartu ditemukan pada siswa dan pegawai. Pilih jenis peminjam lalu gunakan pilihan manual.',
            ]);
        }
        if ($siswa) {
            return [
                'jenis_peminjam' => 'siswa',
                'id' => (int) $siswa->id,
                'nama' => $siswa->nama_lengkap,
                'identitas' => 'NISN '.($siswa->nisn ?: '-'),
                'informasi' => $this->kelasAktif($siswa),
            ];
        }
        if ($pegawai) {
            return [
                'jenis_peminjam' => 'pegawai',
                'id' => (int) $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'identitas' => 'NIP '.($pegawai->nip ?: '-'),
                'informasi' => $pegawai->jenis_pegawai ?: 'Pegawai',
            ];
        }

        throw ValidationException::withMessages([
            'kode' => match ($jenis) {
                'siswa' => 'Siswa dengan NISN atau NIS tersebut tidak ditemukan.',
                'pegawai' => 'Pegawai dengan NIP tersebut tidak ditemukan.',
                default => 'Kartu tidak ditemukan sebagai siswa maupun pegawai aktif.',
            },
        ]);
    }

    public function identifikasiBarang(string $kode, ?int $lokasiId = null): array
    {
        $kode = strtoupper(trim($kode));
        $unit = UnitBarang::query()->with(['barang.satuanBarang', 'lokasiBarang'])
            ->whereRaw('LOWER(kode_inventaris) = ?', [mb_strtolower($kode)])->first();

        if ($unit) {
            if (! $unit->aktif || $unit->status_unit !== 'tersedia' || $unit->barang?->tipe_pengelolaan !== 'aset_individual') {
                throw ValidationException::withMessages(['kode' => 'Unit aset tersebut sedang tidak tersedia untuk dipinjam.']);
            }

            return ['item' => $this->formatUnit($unit)];
        }

        $barang = Barang::query()->with('satuanBarang')->where('aktif', true)
            ->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai'])
            ->whereRaw('LOWER(kode) = ?', [mb_strtolower($kode)])->first();
        if (! $barang) {
            throw ValidationException::withMessages(['kode' => str_starts_with($kode, 'AST-')
                ? 'Barcode aset tidak ditemukan. Pastikan label AST sudah terdaftar dan terbaca lengkap.'
                : (str_starts_with($kode, 'BHP-') ? 'Barcode barang habis pakai tidak ditemukan.' : 'Barcode atau kode barang tidak ditemukan.')]);
        }

        $saldo = SaldoStokBarang::query()->with(['barang.satuanBarang', 'lokasiBarang'])
            ->where('barang_id', $barang->id)->where('jumlah', '>', 0)
            ->when($lokasiId, fn (Builder $query) => $query->where('lokasi_barang_id', $lokasiId))
            ->orderBy('lokasi_barang_id')->get();
        if ($saldo->isEmpty()) {
            throw ValidationException::withMessages(['kode' => 'Stok barang tersebut sedang habis pada lokasi yang dipilih.']);
        }
        if ($saldo->count() > 1 && ! $lokasiId) {
            return [
                'perlu_pilih_lokasi' => true,
                'pesan' => 'Barang tersimpan di beberapa lokasi. Pilih lokasi asal, lalu lanjutkan scan.',
                'pilihan_lokasi' => $saldo->map(fn (SaldoStokBarang $item) => [
                    'id' => (int) $item->lokasi_barang_id,
                    'nama' => $item->lokasiBarang->nama,
                    'saldo' => (float) $item->jumlah,
                    'satuan' => $barang->satuanBarang->nama,
                ])->values(),
            ];
        }

        return ['item' => $this->formatStok($saldo->first())];
    }

    public function identifikasiPengembalian(string $kode): array
    {
        $kode = strtoupper(trim($kode));
        $unit = UnitBarang::query()->whereRaw('LOWER(kode_inventaris) = ?', [mb_strtolower($kode)])->first();
        if (! $unit) {
            throw ValidationException::withMessages(['kode' => str_starts_with($kode, 'BHP-')
                ? 'Barang habis pakai tidak memiliki proses pengembalian.'
                : 'Barcode aset tidak ditemukan. Gunakan barcode internal yang diawali AST.']);
        }
        $detail = DetailPeminjamanBarang::query()
            ->with(['barang.satuanBarang', 'unitBarang', 'lokasiBarang', 'peminjamanBarang.siswa', 'peminjamanBarang.pegawai'])
            ->where('unit_barang_id', $unit->id)->where('wajib_dikembalikan', true)
            ->whereColumn('jumlah_dikembalikan', '<', 'jumlah')
            ->whereHas('peminjamanBarang', fn (Builder $query) => $query->whereIn('status', ['dipinjam', 'sebagian_dikembalikan']))
            ->latest('id')->first();
        if (! $detail) {
            throw ValidationException::withMessages(['kode' => $unit->status_unit === 'tersedia'
                ? 'Aset ini tidak sedang dipinjam atau sudah dikembalikan.'
                : 'Tidak ditemukan transaksi peminjaman aktif untuk aset ini. Periksa riwayat unit secara manual.']);
        }

        return [
            'peminjaman_id' => (int) $detail->peminjaman_barang_id,
            'detail_id' => (int) $detail->id,
            'kode' => $unit->kode_inventaris,
            'nama_barang' => $detail->barang->nama,
            'nomor_aset_resmi' => $unit->nomor_aset_resmi ?: '-',
            'lokasi_asal' => $detail->lokasiBarang?->nama ?: 'Tanpa lokasi',
            'kondisi_tercatat' => $unit->labelKondisi(),
            'nomor_peminjaman' => $detail->peminjamanBarang->nomor_peminjaman,
            'nama_peminjam' => $detail->peminjamanBarang->namaPeminjam(),
            'identitas_peminjam' => $detail->peminjamanBarang->identitasPeminjam(),
            'tanggal_peminjaman' => $detail->peminjamanBarang->tanggal_peminjaman?->locale('id')->translatedFormat('d M Y'),
            'rencana_kembali' => $detail->peminjamanBarang->rencana_kembali?->locale('id')->translatedFormat('d M Y') ?: '-',
        ];
    }

    private function pilihanPeminjaman(): array
    {
        return [
            'jenis_peminjam' => $this->pilihanLabel(PeminjamanBarang::DAFTAR_JENIS_PEMINJAM),
            'status' => $this->pilihanLabel(PeminjamanBarang::DAFTAR_STATUS),
            'siswa' => $this->daftarSiswa(),
            'pegawai' => Pegawai::query()->where('aktif', true)->orderBy('nama_lengkap')->get()
                ->map(fn (Pegawai $item) => ['id' => (int) $item->id, 'label' => $item->nama_lengkap.' · NIP '.($item->nip ?: '-')])->values(),
            'barang' => $this->daftarBarang(),
        ];
    }

    private function pilihanRekap(): array
    {
        $siswa = Siswa::query()->whereHas('peminjamanBarang')->orderBy('nama_lengkap')->get()
            ->map(fn (Siswa $item) => [
                'nilai' => 'siswa:'.$item->id,
                'label' => $item->nama_lengkap.' · NISN '.($item->nisn ?: '-'),
                'jenis' => 'siswa',
            ]);
        $pegawai = Pegawai::query()->whereHas('peminjamanBarang')->orderBy('nama_lengkap')->get()
            ->map(fn (Pegawai $item) => [
                'nilai' => 'pegawai:'.$item->id,
                'label' => $item->nama_lengkap.' · NIP '.($item->nip ?: '-'),
                'jenis' => 'pegawai',
            ]);

        return [
            'status_pemantauan' => $this->pilihanLabel(self::STATUS_PEMANTAUAN),
            'jenis_peminjam' => [
                ['nilai' => 'semua', 'label' => 'Semua'],
                ...$this->pilihanLabel(PeminjamanBarang::DAFTAR_JENIS_PEMINJAM),
            ],
            'peminjam' => $siswa->concat($pegawai)->values(),
            'barang' => Barang::query()->whereHas('detailPeminjamanBarang')->orderBy('nama')->get()
                ->map(fn (Barang $item) => [
                    'id' => (int) $item->id,
                    'kode' => $item->kode,
                    'nama' => $item->nama,
                    'label' => $item->nama.' · '.$item->kode,
                ])->values(),
        ];
    }

    private function daftarSiswa()
    {
        $tahunId = TahunPelajaran::query()->where('aktif', true)->value('id');

        return Siswa::query()->where('aktif', true)
            ->with(['anggotaKelas' => fn ($query) => $query
                ->when($tahunId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunId))
                ->where('status_keanggotaan', 'aktif')->with('kelas')])
            ->orderBy('nama_lengkap')->get()
            ->map(fn (Siswa $item) => [
                'id' => (int) $item->id,
                'label' => $item->nama_lengkap.' · NISN '.($item->nisn ?: '-').' · '.($item->anggotaKelas->first()?->kelas?->nama ?: 'Belum ditempatkan'),
            ])->values();
    }

    private function daftarBarang()
    {
        $unit = UnitBarang::query()->with(['barang.satuanBarang', 'lokasiBarang'])
            ->where('aktif', true)->where('status_unit', 'tersedia')
            ->whereHas('barang', fn (Builder $query) => $query->where('aktif', true)->where('tipe_pengelolaan', 'aset_individual'))
            ->orderBy('kode_inventaris')->get()->map(fn (UnitBarang $item) => $this->formatUnit($item));
        $stok = SaldoStokBarang::query()->with(['barang.satuanBarang', 'lokasiBarang'])
            ->where('jumlah', '>', 0)
            ->whereHas('barang', fn (Builder $query) => $query->where('aktif', true)->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai']))
            ->orderBy('barang_id')->orderBy('lokasi_barang_id')->get()
            ->map(fn (SaldoStokBarang $item) => $this->formatStok($item));

        return $unit->concat($stok)->values();
    }

    private function formatUnit(UnitBarang $unit): array
    {
        return [
            'kunci' => 'unit:'.$unit->id,
            'tipe_item' => 'unit',
            'unit_barang_id' => (int) $unit->id,
            'barang_id' => (int) $unit->barang_id,
            'lokasi_barang_id' => $unit->lokasi_barang_id ? (int) $unit->lokasi_barang_id : null,
            'kode' => $unit->kode_inventaris,
            'label' => $unit->barang->nama,
            'keterangan' => $unit->kode_inventaris.($unit->nomor_aset_resmi ? ' · Aset '.$unit->nomor_aset_resmi : '').' · '.($unit->lokasiBarang?->nama ?: 'Tanpa lokasi'),
            'jenis_tampilan' => 'Aset individual',
            'kelompok' => 'Aset individual (wajib kembali)',
            'wajib_dikembalikan' => true,
            'satuan' => 'unit',
            'saldo' => 1.0,
        ];
    }

    private function formatStok(SaldoStokBarang $saldo): array
    {
        $habisPakai = $saldo->barang->tipe_pengelolaan === 'habis_pakai';

        return [
            'kunci' => 'stok:'.$saldo->barang_id.':'.$saldo->lokasi_barang_id,
            'tipe_item' => 'stok',
            'unit_barang_id' => null,
            'barang_id' => (int) $saldo->barang_id,
            'lokasi_barang_id' => (int) $saldo->lokasi_barang_id,
            'kode' => $saldo->barang->kode,
            'label' => $saldo->barang->nama,
            'keterangan' => $saldo->barang->kode.' · '.$saldo->lokasiBarang->nama.' · tersedia '.number_format((float) $saldo->jumlah, 2, ',', '.').' '.$saldo->barang->satuanBarang->nama,
            'jenis_tampilan' => $habisPakai ? 'Barang habis pakai' : 'Stok yang dikembalikan',
            'kelompok' => $habisPakai ? 'Barang habis pakai' : 'Stok yang wajib dikembalikan',
            'wajib_dikembalikan' => ! $habisPakai,
            'satuan' => $saldo->barang->satuanBarang->nama,
            'saldo' => (float) $saldo->jumlah,
        ];
    }

    private function ringkas(PeminjamanBarang $item): array
    {
        return [
            'id' => (int) $item->id,
            'nomor' => $item->nomor_peminjaman,
            'jenis_peminjam' => $item->jenis_peminjam,
            'jenis_peminjam_label' => PeminjamanBarang::DAFTAR_JENIS_PEMINJAM[$item->jenis_peminjam] ?? '-',
            'nama_peminjam' => $item->namaPeminjam(),
            'identitas_peminjam' => $item->identitasPeminjam(),
            'tanggal' => $item->tanggal_peminjaman?->toDateString(),
            'tanggal_label' => $item->tanggal_peminjaman?->locale('id')->translatedFormat('d M Y'),
            'rencana_kembali' => $item->rencana_kembali?->toDateString(),
            'rencana_kembali_label' => $item->rencana_kembali?->locale('id')->translatedFormat('d M Y'),
            'status' => $item->status,
            'status_label' => $item->labelStatus(),
            'pemantauan_label' => $item->labelPemantauan(),
            'terlambat' => $item->terlambat(),
            'hari_terlambat' => $item->jumlahHariTerlambat(),
            'jumlah_item' => (int) ($item->detail_peminjaman_barang_count ?? 0),
        ];
    }

    private function detailItem(DetailPeminjamanBarang $item): array
    {
        return [
            'id' => (int) $item->id,
            'barang_id' => (int) $item->barang_id,
            'nama_barang' => $item->barang->nama,
            'kode' => $item->unitBarang?->kode_inventaris ?: $item->barang->kode,
            'unit_barang_id' => $item->unit_barang_id ? (int) $item->unit_barang_id : null,
            'lokasi' => $item->lokasiBarang?->nama ?: 'Tanpa lokasi',
            'tipe_pengelolaan' => $item->tipe_pengelolaan,
            'jumlah' => (float) $item->jumlah,
            'jumlah_dikembalikan' => (float) $item->jumlah_dikembalikan,
            'jumlah_belum_dikembalikan' => $item->jumlahBelumDikembalikan(),
            'wajib_dikembalikan' => (bool) $item->wajib_dikembalikan,
            'satuan' => $item->tipe_pengelolaan === 'aset_individual' ? 'unit' : ($item->barang->satuanBarang?->nama ?? 'unit'),
            'cara_input' => $item->cara_input_barang,
            'catatan' => $item->catatan,
        ];
    }

    private function filterPencarian(Builder $query, string $cari, bool $denganBarang = false): Builder
    {
        $pola = '%'.mb_strtolower($cari).'%';

        return $query->where(function (Builder $query) use ($pola, $denganBarang) {
            $query->whereRaw('LOWER(nomor_peminjaman) LIKE ?', [$pola])
                ->orWhereHas('siswa', fn (Builder $query) => $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]))
                ->orWhereHas('pegawai', fn (Builder $query) => $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])->orWhereRaw("LOWER(COALESCE(nip, '')) LIKE ?", [$pola]));
            if ($denganBarang) {
                $query->orWhereHas('detailPeminjamanBarang.barang', fn (Builder $query) => $query
                    ->whereRaw('LOWER(nama) LIKE ?', [$pola])->orWhereRaw("LOWER(COALESCE(kode, '')) LIKE ?", [$pola]));
            }
        });
    }

    private function queryRekap(array $filter): Builder
    {
        $hariIni = now()->startOfDay();

        return PeminjamanBarang::query()
            ->with([
                'siswa',
                'pegawai',
                'detailPeminjamanBarang.barang.satuanBarang',
                'detailPeminjamanBarang.unitBarang',
                'detailPeminjamanBarang.lokasiBarang',
            ])
            ->withCount('detailPeminjamanBarang')
            ->when($filter['status_pemantauan'] === 'aktif', fn (Builder $query) => $query
                ->whereIn('status', ['dipinjam', 'sebagian_dikembalikan']))
            ->when($filter['status_pemantauan'] === 'terlambat', fn (Builder $query) => $query
                ->whereIn('status', ['dipinjam', 'sebagian_dikembalikan'])
                ->whereNotNull('rencana_kembali')
                ->whereDate('rencana_kembali', '<', $hariIni->toDateString()))
            ->when($filter['status_pemantauan'] === 'jatuh_tempo', fn (Builder $query) => $query
                ->whereIn('status', ['dipinjam', 'sebagian_dikembalikan'])
                ->whereBetween('rencana_kembali', [
                    $hariIni->toDateString(),
                    $hariIni->copy()->addDays(7)->toDateString(),
                ]))
            ->when($filter['status_pemantauan'] === 'tanpa_rencana', fn (Builder $query) => $query
                ->whereIn('status', ['dipinjam', 'sebagian_dikembalikan'])
                ->whereNull('rencana_kembali'))
            ->when($filter['status_pemantauan'] === 'selesai', fn (Builder $query) => $query->where('status', 'selesai'))
            ->when($filter['jenis_peminjam'] !== 'semua', fn (Builder $query) => $query
                ->where('jenis_peminjam', $filter['jenis_peminjam']))
            ->when($filter['peminjam'] !== '', function (Builder $query) use ($filter) {
                [$jenis, $id] = explode(':', $filter['peminjam'], 2);

                $query->where('jenis_peminjam', $jenis)
                    ->where($jenis === 'siswa' ? 'siswa_id' : 'pegawai_id', (int) $id);
            })
            ->when($filter['barang_id'], fn (Builder $query) => $query
                ->whereHas('detailPeminjamanBarang', fn (Builder $query) => $query
                    ->where('barang_id', $filter['barang_id'])))
            ->when($filter['tanggal_mulai'], fn (Builder $query) => $query
                ->whereDate('tanggal_peminjaman', '>=', $filter['tanggal_mulai']))
            ->when($filter['tanggal_selesai'], fn (Builder $query) => $query
                ->whereDate('tanggal_peminjaman', '<=', $filter['tanggal_selesai']))
            ->when($filter['kata_kunci'] !== '', fn (Builder $query) => $this
                ->filterPencarian($query, $filter['kata_kunci'], true))
            ->orderByRaw('CASE WHEN rencana_kembali IS NULL THEN 1 ELSE 0 END')
            ->orderBy('rencana_kembali')
            ->orderByDesc('tanggal_peminjaman')
            ->orderByDesc('id');
    }

    private function itemRekap(PeminjamanBarang $item): array
    {
        return $this->ringkas($item) + [
            'items' => $item->detailPeminjamanBarang
                ->map(fn (DetailPeminjamanBarang $detail) => $this->detailItem($detail))
                ->values(),
        ];
    }

    private function teksDaftarTerlambat(Collection $peminjaman): string
    {
        $baris = [
            'DAFTAR BARANG TERLAMBAT DIKEMBALIKAN',
            'SMP Negeri 2 Padang Panjang',
            'Tanggal pantau: '.now()->locale('id')->translatedFormat('d F Y'),
            '',
        ];
        if ($peminjaman->isEmpty()) {
            $baris[] = 'Tidak ada barang terlambat dikembalikan pada pilihan ini.';

            return implode(PHP_EOL, $baris);
        }

        foreach ($peminjaman as $index => $item) {
            $barang = $item->detailPeminjamanBarang
                ->filter(fn (DetailPeminjamanBarang $detail) => $detail->wajib_dikembalikan
                    && $detail->jumlahBelumDikembalikan() > 0)
                ->map(fn (DetailPeminjamanBarang $detail) => $detail->barang->nama.' ('
                    .number_format($detail->jumlahBelumDikembalikan(), 2, ',', '.').' '
                    .($detail->tipe_pengelolaan === 'aset_individual'
                        ? 'unit'
                        : ($detail->barang->satuanBarang?->nama ?? 'unit')).')')
                ->implode(', ');
            $baris[] = ($index + 1).'. '.$item->namaPeminjam().' - '.$item->identitasPeminjam();
            $baris[] = '   Transaksi: '.$item->nomor_peminjaman;
            $baris[] = '   Rencana kembali: '.$item->rencana_kembali?->locale('id')->translatedFormat('d F Y');
            $baris[] = '   Keterlambatan: '.$item->jumlahHariTerlambat().' hari';
            $baris[] = '   Barang: '.($barang ?: '-');
            $baris[] = '';
        }

        return rtrim(implode(PHP_EOL, $baris));
    }

    private function kelasAktif(Siswa $siswa): string
    {
        $tahunId = TahunPelajaran::query()->where('aktif', true)->value('id');
        if (! $tahunId) {
            return 'Belum ditempatkan';
        }

        return $siswa->anggotaKelas()->with('kelas')->where('tahun_pelajaran_id', $tahunId)
            ->where('status_keanggotaan', 'aktif')->first()?->kelas?->nama ?: 'Belum ditempatkan';
    }

    private function queryAktif(): Builder
    {
        return PeminjamanBarang::query()->whereIn('status', ['dipinjam', 'sebagian_dikembalikan']);
    }

    private function pilihanLabel(array $items): array
    {
        return collect($items)->map(fn (string $label, string $nilai) => ['nilai' => $nilai, 'label' => $label])->values()->all();
    }

    private function paginasi(object $paginator): array
    {
        return [
            'halaman' => $paginator->currentPage(),
            'halaman_terakhir' => $paginator->lastPage(),
            'per_halaman' => $paginator->perPage(),
            'total' => $paginator->total(),
            'ada_halaman_berikutnya' => $paginator->hasMorePages(),
        ];
    }
}
