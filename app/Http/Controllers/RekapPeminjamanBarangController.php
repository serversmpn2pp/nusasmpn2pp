<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Pegawai;
use App\Models\PeminjamanBarang;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class RekapPeminjamanBarangController extends Controller
{
    public const DAFTAR_STATUS_PEMANTAUAN = [
        'aktif' => 'Masih dipinjam',
        'terlambat' => 'Terlambat dikembalikan',
        'jatuh_tempo' => 'Jatuh tempo 7 hari',
        'tanpa_rencana' => 'Belum ada rencana kembali',
        'selesai' => 'Sudah selesai',
        'semua' => 'Semua transaksi',
    ];

    public function index(Request $request)
    {
        $filter = $this->ambilFilter($request);
        $peminjamanBarang = $this->queryRekap($filter)
            ->paginate(15)
            ->withQueryString();

        return view('rekap-peminjaman-barang.index', $this->dataHalaman($filter) + [
            'peminjamanBarang' => $peminjamanBarang,
        ]);
    }

    public function cetak(Request $request)
    {
        $filter = $this->ambilFilter($request);

        return view('rekap-peminjaman-barang.cetak', $this->dataHalaman($filter) + [
            'peminjamanBarang' => $this->queryRekap($filter)->get(),
            'tanggalCetak' => now()->locale('id')->translatedFormat('d F Y H:i'),
        ]);
    }

    private function dataHalaman(array $filter): array
    {
        $hariIni = now()->startOfDay();
        $daftarTerlambat = $this->queryRekap([...$filter, 'status_pemantauan' => 'terlambat'])->get();

        return $filter + [
            'daftarStatusPemantauan' => self::DAFTAR_STATUS_PEMANTAUAN,
            'daftarBarang' => Barang::query()
                ->whereHas('detailPeminjamanBarang')
                ->orderBy('nama')
                ->get(['id', 'kode', 'nama']),
            'daftarSiswa' => Siswa::query()
                ->whereHas('peminjamanBarang')
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nisn']),
            'daftarPegawai' => Pegawai::query()
                ->whereHas('peminjamanBarang')
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nip']),
            'jumlahAktif' => $this->queryStatusAktif()->count(),
            'jumlahTerlambat' => $this->queryStatusAktif()
                ->whereNotNull('rencana_kembali')
                ->whereDate('rencana_kembali', '<', $hariIni->toDateString())
                ->count(),
            'jumlahJatuhTempo' => $this->queryStatusAktif()
                ->whereBetween('rencana_kembali', [$hariIni->toDateString(), $hariIni->copy()->addDays(7)->toDateString()])
                ->count(),
            'jumlahTanpaRencana' => $this->queryStatusAktif()
                ->whereNull('rencana_kembali')
                ->count(),
            'jumlahTerlambatTersaring' => $daftarTerlambat->count(),
            'teksDaftarTerlambat' => $this->teksDaftarTerlambat($daftarTerlambat),
        ];
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
            ->when($filter['status_pemantauan'] === 'aktif', fn (Builder $query) => $query->whereIn('status', ['dipinjam', 'sebagian_dikembalikan']))
            ->when($filter['status_pemantauan'] === 'terlambat', fn (Builder $query) => $query
                ->whereIn('status', ['dipinjam', 'sebagian_dikembalikan'])
                ->whereNotNull('rencana_kembali')
                ->whereDate('rencana_kembali', '<', $hariIni->toDateString()))
            ->when($filter['status_pemantauan'] === 'jatuh_tempo', fn (Builder $query) => $query
                ->whereIn('status', ['dipinjam', 'sebagian_dikembalikan'])
                ->whereBetween('rencana_kembali', [$hariIni->toDateString(), $hariIni->copy()->addDays(7)->toDateString()]))
            ->when($filter['status_pemantauan'] === 'tanpa_rencana', fn (Builder $query) => $query
                ->whereIn('status', ['dipinjam', 'sebagian_dikembalikan'])
                ->whereNull('rencana_kembali'))
            ->when($filter['status_pemantauan'] === 'selesai', fn (Builder $query) => $query->where('status', 'selesai'))
            ->when($filter['jenis_peminjam'] !== 'semua', fn (Builder $query) => $query->where('jenis_peminjam', $filter['jenis_peminjam']))
            ->when($filter['peminjam'] !== '', fn (Builder $query) => $this->filterPeminjam($query, $filter['peminjam']))
            ->when($filter['barang_id'], fn (Builder $query) => $query->whereHas('detailPeminjamanBarang', fn (Builder $query) => $query->where('barang_id', $filter['barang_id'])))
            ->when($filter['tanggal_mulai'], fn (Builder $query) => $query->whereDate('tanggal_peminjaman', '>=', $filter['tanggal_mulai']))
            ->when($filter['tanggal_selesai'], fn (Builder $query) => $query->whereDate('tanggal_peminjaman', '<=', $filter['tanggal_selesai']))
            ->when($filter['kata_kunci'] !== '', function (Builder $query) use ($filter) {
                $kataKunci = $filter['kata_kunci'];

                $query->where(function (Builder $query) use ($kataKunci) {
                    $query->where('nomor_peminjaman', 'ilike', '%' . $kataKunci . '%')
                        ->orWhereHas('siswa', function (Builder $query) use ($kataKunci) {
                            $query->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                                ->orWhere('nisn', 'ilike', '%' . $kataKunci . '%');
                        })
                        ->orWhereHas('pegawai', function (Builder $query) use ($kataKunci) {
                            $query->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                                ->orWhere('nip', 'ilike', '%' . $kataKunci . '%');
                        })
                        ->orWhereHas('detailPeminjamanBarang.barang', function (Builder $query) use ($kataKunci) {
                            $query->where('nama', 'ilike', '%' . $kataKunci . '%')
                                ->orWhere('kode', 'ilike', '%' . $kataKunci . '%');
                        });
                });
            })
            ->orderByRaw('CASE WHEN rencana_kembali IS NULL THEN 1 ELSE 0 END')
            ->orderBy('rencana_kembali')
            ->orderByDesc('tanggal_peminjaman')
            ->orderByDesc('id');
    }

    private function queryStatusAktif(): Builder
    {
        return PeminjamanBarang::query()->whereIn('status', ['dipinjam', 'sebagian_dikembalikan']);
    }

    private function filterPeminjam(Builder $query, string $peminjam): Builder
    {
        [$jenis, $id] = explode(':', $peminjam, 2);

        return $query
            ->where('jenis_peminjam', $jenis)
            ->where($jenis === 'siswa' ? 'siswa_id' : 'pegawai_id', (int) $id);
    }

    private function ambilFilter(Request $request): array
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'status_pemantauan' => ['nullable', Rule::in(array_keys(self::DAFTAR_STATUS_PEMANTAUAN))],
            'jenis_peminjam' => ['nullable', Rule::in(['semua', ...array_keys(PeminjamanBarang::DAFTAR_JENIS_PEMINJAM)])],
            'peminjam' => ['nullable', 'regex:/^(siswa|pegawai):[1-9][0-9]*$/'],
            'barang_id' => ['nullable', 'integer', 'exists:barang,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        return [
            'kata_kunci' => trim((string) ($data['kata_kunci'] ?? '')),
            'status_pemantauan' => $data['status_pemantauan'] ?? 'aktif',
            'jenis_peminjam' => $data['jenis_peminjam'] ?? 'semua',
            'peminjam' => $data['peminjam'] ?? '',
            'barang_id' => $data['barang_id'] ?? null,
            'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
        ];
    }

    private function teksDaftarTerlambat(Collection $peminjamanBarang): string
    {
        $baris = [
            'DAFTAR BARANG TERLAMBAT DIKEMBALIKAN',
            'SMP Negeri 2 Padang Panjang',
            'Tanggal pantau: ' . now()->locale('id')->translatedFormat('d F Y'),
            '',
        ];

        if ($peminjamanBarang->isEmpty()) {
            $baris[] = 'Tidak ada barang terlambat dikembalikan pada pilihan ini.';

            return implode(PHP_EOL, $baris);
        }

        foreach ($peminjamanBarang as $index => $peminjaman) {
            $barangBelumKembali = $peminjaman->detailPeminjamanBarang
                ->filter(fn ($detail) => $detail->wajib_dikembalikan && $detail->jumlahBelumDikembalikan() > 0)
                ->map(function ($detail) {
                    $satuan = $detail->tipe_pengelolaan === 'aset_individual'
                        ? 'unit'
                        : $detail->barang->satuanBarang->nama;

                    return $detail->barang->nama . ' (' . number_format($detail->jumlahBelumDikembalikan(), 2, ',', '.') . ' ' . $satuan . ')';
                })
                ->implode(', ');

            $baris[] = ($index + 1) . '. ' . $peminjaman->namaPeminjam() . ' - ' . $peminjaman->identitasPeminjam();
            $baris[] = '   Transaksi: ' . $peminjaman->nomor_peminjaman;
            $baris[] = '   Rencana kembali: ' . $peminjaman->rencana_kembali->locale('id')->translatedFormat('d F Y');
            $baris[] = '   Keterlambatan: ' . $peminjaman->jumlahHariTerlambat() . ' hari';
            $baris[] = '   Barang: ' . ($barangBelumKembali ?: '-');
            $baris[] = '';
        }

        return rtrim(implode(PHP_EOL, $baris));
    }
}
